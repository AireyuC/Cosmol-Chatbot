<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\Manejadores;

use App\Modules\Session\SessionService;
use App\Modules\Socio\SocioService;
use App\Modules\Audit\ConsultaAuditService;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Manejador del flujo de autenticación por Código Fijo (AWAITING_CODE).
 */
class AuthFlowHandler extends BaseFlowHandler
{
    /**
     * @var SocioService
     */
    private $socioService;

    // --- CONTROLADORES DE NEGOCIO (ADMINISTRACIÓN DESDE CÓDIGO) ---
    public const MAX_SOCIOS_POR_TELEFONO = 5; // Máximo de códigos de socio distintos consultados por teléfono por día

    public function __construct(
        SessionService $sessionService,
        SocioService $socioService,
        ?ConsultaAuditService $auditService = null
    ) {
        parent::__construct($sessionService, $auditService);
        $this->socioService = $socioService;
    }

    /**
     * Procesa la entrada cuando el estado actual es AWAITING_CODE.
     *
     * @param string $telefono
     * @param string $tipoMensaje
     * @param mixed $contenido
     * @param int $intentos
     * @return array|null Payload de WhatsApp a enviar
     */
    public function handle(string $telefono, string $tipoMensaje, $contenido, int $intentos): ?array
    {
        if ($tipoMensaje === 'text') {
            $codigoIngresado = trim((string)$contenido);
            $esCodigoValido = false;
            $validacion = null;

            if (is_numeric($codigoIngresado)) {
                $validacion = $this->socioService->validarSocio($codigoIngresado);
                if (isset($validacion['status']) && $validacion['status'] === 'success') {
                    $esCodigoValido = true;
                }
            }

            if ($esCodigoValido) {
                // Control diario de cuentas por teléfono (máximo 5 códigos de socio distintos por día)
                if ($this->auditService !== null && !$this->auditService->puedeConsultarSocioHoy($telefono, (int)$codigoIngresado)) {
                    return PlantillaSistema::advertenciaLimiteCuentasPorTelefono(self::MAX_SOCIOS_POR_TELEFONO);
                }

                $nombreSocio = $validacion['datos_socio']['nombre'] ?? 'Socio';
                $contextData = ['nombre_socio' => $nombreSocio];

                // Socio válido -> Actualizar estado a MAIN_MENU y guardar nombre en sesión
                $this->sessionService->updateSession($telefono, (int)$codigoIngresado, 'MAIN_MENU', 0, $contextData);

                // Registrar auditoría hacia COSMOL-Reportes con telefono
                if ($this->auditService !== null) {
                    $this->auditService->registrarAcceso((int)$codigoIngresado, $nombreSocio, $telefono);
                }

                return PlantillaSocio::menuPrincipal($codigoIngresado, $nombreSocio);
            }

            // Código inválido o texto no numérico
            $intentos++;

            if (!is_numeric($codigoIngresado) && $intentos === 1) {
                $whatsappPayload = PlantillaSocio::saludo();
            } else {
                $whatsappPayload = PlantillaSistema::codigoInvalido();
            }

            $this->sessionService->updateSession($telefono, null, 'AWAITING_CODE', $intentos);

            $nuevaSesion = $this->sessionService->processSessionState($telefono, '');
            if ($nuevaSesion['estado_actual'] === 'BLOCKED') {
                // Al momento de bloquearse, notificamos al usuario
                return PlantillaSistema::bloqueado();
            }

            return $whatsappPayload;
        }

        // Si el usuario envió un mensaje no texto (ej. interactivo o archivo) esperando código
        $intentos++;
        $this->sessionService->updateSession($telefono, null, 'AWAITING_CODE', $intentos);
        $nuevaSesion = $this->sessionService->processSessionState($telefono, '');

        if ($nuevaSesion['estado_actual'] === 'BLOCKED') {
            return PlantillaSistema::bloqueado();
        }

        return PlantillaSistema::codigoInvalido();
    }
}
