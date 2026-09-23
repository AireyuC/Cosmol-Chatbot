<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Integrations\CosmolReportes\ClienteApiReportes;
use App\Data\Interfaces\ReportesRepositoryInterface;

/**
 * Servicio de auditoría y métricas para registrar consultas del chatbot hacia COSMOL-Reportes.
 * Implementa el patrón Buffer/Cola de resiliencia ante caídas del servidor de reportes.
 */
class ConsultaAuditService
{
    // ids de tipo de consultas
    // --- TIPOS DE CONSULTA ---
    const TIPO_AUTENTICACION        = 1;
    const TIPO_CONSULTA_DEUDA       = 2;
    const TIPO_HISTORIAL_FACTURAS   = 3;
    const TIPO_REGISTRO_RECLAMO     = 4;
    const TIPO_SOLICITUD_RECONEXION = 5;
    const TIPO_INFO_OFICINAS        = 6;
    const TIPO_DERIVACION_AGENTE    = 7;
    const TIPO_ESTADO_SOLICITUDES   = 8;

    // --- LÍMITES DE REGISTROS DIARIOS ---
    const MAX_RECLAMOS_POR_SOCIO       = 3;   // Máximo de reclamos diarios por código de socio
    const MAX_RECLAMOS_POR_TELEFONO    = 6;   // Máximo global de reclamos diarios por número de WhatsApp
    const MAX_RECONEXIONES_POR_SOCIO   = 1;   // Máximo de reconexiones diarias por código de socio
    const MAX_SOCIOS_POR_TELEFONO      = 5;   // Máximo de cuentas de socio distintas consultadas por teléfono por día
    const MAX_FACTURAS_MORA_RECONEXION = 1;   // Facturas en mora permitidas (<= 1; si adeuda > 1 se rechaza)

    // --- CONTROLADORES DE TIEMPO EN SEGUNDOS ---
    const COOLDOWN_RECLAMO_SECONDS    = 30;  // Segundos mínimos entre reclamos consecutivos (anti-spam / doble toque)
    const COOLDOWN_RECONEXION_SECONDS = 30;  // Segundos mínimos entre solicitudes de reconexión

    /**
     * @var ClienteApiReportes
     */
    private $clienteApi;

    /**
     * @var ReportesRepositoryInterface
     */
    private $bufferRepository;

    public function __construct(
        ClienteApiReportes $clienteApi,
        ReportesRepositoryInterface $bufferRepository
    ) {
        $this->clienteApi = $clienteApi;
        $this->bufferRepository = $bufferRepository;
    }

    /**
     * Registra un evento de consulta. Intenta enviarlo a COSMOL-Reportes; si falla, lo guarda en el buffer local.
     */
    public function registrar(
        int $codigoSocio,
        string $nombres,
        int $idTipo,
        string $tipoConsulta,
        ?string $telefono = null,
        ?string $tipoUbicacion = null
    ): void {
        $payload = [
            'codigo_socio'   => $codigoSocio,
            'nombres'        => !empty($nombres) ? $nombres : 'Socio #' . $codigoSocio,
            'telefono'       => $telefono,
            'id_tipo'        => $idTipo,
            'tipo_consulta'  => $tipoConsulta,
            'tipo_ubicacion' => $tipoUbicacion,
            'fecha_consulta' => date('Y-m-d'),
            'hora_consulta'  => date('H:i:s')
        ];

        // 1. Intentar envío directo a la API de reportes
        $enviado = $this->clienteApi->enviarConsulta($payload);

        if ($enviado) {
            // Si el servidor de reportes está disponible, aprovechamos para enviar registros pendientes acumulados
            $this->vaciarColaPendiente(5);
        } else {
            // 2. Si falló la conexión o la API está caída, almacenar en buffer local seguro
            $this->bufferRepository->guardarEnCola($payload);
        }
    }

    public function registrarAcceso(int $codigoSocio, string $nombres, ?string $telefono = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_AUTENTICACION, 'Autenticación / Acceso', $telefono);
    }

    public function registrarConsultaDeuda(int $codigoSocio, string $nombres, ?string $telefono = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_CONSULTA_DEUDA, 'Consulta de Deuda', $telefono);
    }

    public function registrarConsultaHistorial(int $codigoSocio, string $nombres, ?string $telefono = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_HISTORIAL_FACTURAS, 'Historial de Facturas', $telefono);
    }

    public function registrarReclamo(int $codigoSocio, string $nombres, ?string $telefono = null, ?string $tipoUbicacion = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_REGISTRO_RECLAMO, 'Registro de Reclamo', $telefono, $tipoUbicacion);
    }

    public function registrarReconexion(int $codigoSocio, string $nombres, ?string $telefono = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_SOLICITUD_RECONEXION, 'Solicitud de Reconexión', $telefono);
    }

    public function registrarConsultaOficinas(int $codigoSocio, string $nombres, ?string $telefono = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_INFO_OFICINAS, 'Información de Oficinas', $telefono);
    }

    public function registrarDerivacionAgente(int $codigoSocio, string $nombres, ?string $telefono = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_DERIVACION_AGENTE, 'Derivación a Agente', $telefono);
    }

    public function registrarConsultaEstado(int $codigoSocio, string $nombres, ?string $telefono = null): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_ESTADO_SOLICITUDES, 'Estado de Solicitudes', $telefono);
    }

    /**
     * Valida si el usuario puede registrar un reclamo hoy evaluando tanto el límite por socio como el global por teléfono.
     */
    public function puedeRegistrarReclamo(string $telefono, int $codigoSocio): bool
    {
        return $this->puedeRegistrarReclamoSocio($codigoSocio) && $this->puedeRegistrarReclamoTelefono($telefono);
    }

    /**
     * Valida si el socio puede registrar otro reclamo hoy (límite máximo: 3 por código de socio).
     */
    public function puedeRegistrarReclamoSocio(int $codigoSocio): bool
    {
        if ($codigoSocio <= 0) {
            return true;
        }
        $conteo = $this->bufferRepository->contarConsultasPorSocioHoy($codigoSocio, self::TIPO_REGISTRO_RECLAMO);
        return $conteo < self::MAX_RECLAMOS_POR_SOCIO;
    }

    /**
     * Valida si el teléfono puede registrar otro reclamo hoy a nivel global (máximo 6 entre todas las cuentas).
     */
    public function puedeRegistrarReclamoTelefono(string $telefono): bool
    {
        if (empty($telefono)) {
            return true;
        }
        $conteo = $this->bufferRepository->contarConsultasPorTelefonoHoy($telefono, self::TIPO_REGISTRO_RECLAMO);
        return $conteo < self::MAX_RECLAMOS_POR_TELEFONO;
    }

    /**
     * Calcula los segundos restantes de cooldown antes de permitir un nuevo reclamo desde este teléfono.
     * Retorna 0 si ya transcurrió el tiempo o si no hay solicitudes previas.
     */
    public function obtenerSegundosRestantesCooldownReclamo(string $telefono): int
    {
        if (empty($telefono) || self::COOLDOWN_RECLAMO_SECONDS <= 0) {
            return 0;
        }
        $segundos = $this->bufferRepository->obtenerSegundosDesdeUltimaConsulta($telefono, self::TIPO_REGISTRO_RECLAMO);
        if ($segundos === null) {
            return 0;
        }
        $restantes = self::COOLDOWN_RECLAMO_SECONDS - $segundos;
        return $restantes > 0 ? $restantes : 0;
    }

    /**
     * Valida si el usuario puede solicitar una reconexión hoy (límite máximo: 1 por día por código de socio).
     */
    public function puedeSolicitarReconexion(string $telefono, int $codigoSocio): bool
    {
        return $this->puedeSolicitarReconexionSocio($codigoSocio);
    }

    /**
     * Valida si el socio puede solicitar una reconexión hoy (máximo 1 por código de socio).
     */
    public function puedeSolicitarReconexionSocio(int $codigoSocio): bool
    {
        if ($codigoSocio <= 0) {
            return true;
        }
        $conteo = $this->bufferRepository->contarConsultasPorSocioHoy($codigoSocio, self::TIPO_SOLICITUD_RECONEXION);
        return $conteo < self::MAX_RECONEXIONES_POR_SOCIO;
    }

    /**
     * Calcula los segundos restantes de cooldown antes de permitir otra solicitud de reconexión.
     */
    public function obtenerSegundosRestantesCooldownReconexion(string $telefono): int
    {
        if (empty($telefono) || self::COOLDOWN_RECONEXION_SECONDS <= 0) {
            return 0;
        }
        $segundos = $this->bufferRepository->obtenerSegundosDesdeUltimaConsulta($telefono, self::TIPO_SOLICITUD_RECONEXION);
        if ($segundos === null) {
            return 0;
        }
        $restantes = self::COOLDOWN_RECONEXION_SECONDS - $segundos;
        return $restantes > 0 ? $restantes : 0;
    }

    /**
     * Valida si el teléfono puede consultar un código de socio hoy (máximo 5 socios distintos por día).
     * Si el teléfono ya consultó este socio hoy, se permite el reingreso libremente.
     */
    public function puedeConsultarSocioHoy(string $telefono, int $codigoSocio): bool
    {
        if (empty($telefono) || $codigoSocio <= 0) {
            return true;
        }
        // Si ya interactuó con esta cuenta hoy, se le permite el acceso
        if ($this->bufferRepository->haConsultadoSocioHoy($telefono, $codigoSocio)) {
            return true;
        }
        // Si es una cuenta nueva hoy, verificar si no excedió el límite de socios distintos
        $sociosDistintos = $this->bufferRepository->contarSociosDistintosTelefonoHoy($telefono);
        return $sociosDistintos < self::MAX_SOCIOS_POR_TELEFONO;
    }

    public function obtenerConteoReclamosHoy(string $telefono, int $codigoSocio): int
    {
        return $this->bufferRepository->contarConsultasPorSocioHoy($codigoSocio, self::TIPO_REGISTRO_RECLAMO);
    }

    public function obtenerConteoReconexionesHoy(string $telefono, int $codigoSocio): int
    {
        return $this->bufferRepository->contarConsultasPorSocioHoy($codigoSocio, self::TIPO_SOLICITUD_RECONEXION);
    }

    /**
     * Intenta enviar registros que quedaron pendientes en el buffer local.
     *
     * @param int $limite Cantidad máxima de registros a procesar en esta iteración
     * @return int Cantidad de registros sincronizados con éxito
     */
    public function vaciarColaPendiente(int $limite = 10): int
    {
        $pendientes = $this->bufferRepository->obtenerPendientes($limite);
        if (empty($pendientes)) {
            return 0;
        }

        $sincronizados = 0;
        foreach ($pendientes as $item) {
            $payload = [
                'codigo_socio'   => (int)$item['codigo_socio'],
                'nombres'        => $item['nombres'],
                'telefono'       => $item['telefono'] ?? null,
                'id_tipo'        => (int)$item['id_tipo'],
                'tipo_consulta'  => $item['tipo_consulta'],
                'tipo_ubicacion' => $item['tipo_ubicacion'] ?? null,
                'fecha_consulta' => $item['fecha_consulta'],
                'hora_consulta'  => $item['hora_consulta']
            ];

            if ($this->clienteApi->enviarConsulta($payload)) {
                $this->bufferRepository->marcarComoEnviado((int)$item['id']);
                $sincronizados++;
            } else {
                if ($this->clienteApi->estaServidorOffline()) {
                    // Servidor de reportes caído/apagado:
                    // NO se penaliza el registro ni se aumentan intentos; se mantiene PENDIENTE.
                    // Se detiene la iteración para esperar al siguiente ciclo cuando el servidor reviva.
                    break;
                }

                // El servidor sí está en línea pero rechazó este dato específico:
                $errorMsg = $this->clienteApi->obtenerUltimoError() ?: 'Rechazado por servidor de Reportes';
                $this->bufferRepository->incrementarIntento((int)$item['id'], $errorMsg);
            }
        }

        return $sincronizados;
    }
}
