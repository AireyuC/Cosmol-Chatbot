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
    const TIPO_AUTENTICACION       = 1;
    const TIPO_CONSULTA_DEUDA      = 2;
    const TIPO_HISTORIAL_FACTURAS  = 3;
    const TIPO_REGISTRO_RECLAMO    = 4;
    const TIPO_SOLICITUD_RECONEXION = 5;
    const TIPO_INFO_OFICINAS       = 6;
    const TIPO_DERIVACION_AGENTE   = 7;

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
    public function registrar(int $codigoSocio, string $nombres, int $idTipo, string $tipoConsulta): void
    {
        $payload = [
            'codigo_socio'   => $codigoSocio,
            'nombres'        => !empty($nombres) ? $nombres : 'Socio #' . $codigoSocio,
            'id_tipo'        => $idTipo,
            'tipo_consulta'  => $tipoConsulta,
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

    public function registrarAcceso(int $codigoSocio, string $nombres): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_AUTENTICACION, 'Autenticación / Acceso');
    }

    public function registrarConsultaDeuda(int $codigoSocio, string $nombres): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_CONSULTA_DEUDA, 'Consulta de Deuda');
    }

    public function registrarConsultaHistorial(int $codigoSocio, string $nombres): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_HISTORIAL_FACTURAS, 'Historial de Facturas');
    }

    public function registrarReclamo(int $codigoSocio, string $nombres): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_REGISTRO_RECLAMO, 'Registro de Reclamo');
    }

    public function registrarReconexion(int $codigoSocio, string $nombres): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_SOLICITUD_RECONEXION, 'Solicitud de Reconexión');
    }

    public function registrarConsultaOficinas(int $codigoSocio, string $nombres): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_INFO_OFICINAS, 'Información de Oficinas');
    }

    public function registrarDerivacionAgente(int $codigoSocio, string $nombres): void
    {
        $this->registrar($codigoSocio, $nombres, self::TIPO_DERIVACION_AGENTE, 'Derivación a Agente');
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
                'id_tipo'        => (int)$item['id_tipo'],
                'tipo_consulta'  => $item['tipo_consulta'],
                'fecha_consulta' => $item['fecha_consulta'],
                'hora_consulta'  => $item['hora_consulta']
            ];

            if ($this->clienteApi->enviarConsulta($payload)) {
                $this->bufferRepository->marcarComoEnviado((int)$item['id']);
                $sincronizados++;
            } else {
                $this->bufferRepository->incrementarIntento((int)$item['id'], 'Servidor no disponible al reintentar');
                break; // Si vuelve a fallar, detenemos la iteración para no generar latencia
            }
        }

        return $sincronizados;
    }
}
