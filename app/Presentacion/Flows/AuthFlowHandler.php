<?php

declare(strict_types=1);

namespace App\Presentacion\Flows;

use App\Modules\Session\SessionService;
use App\Modules\Socio\SocioService;
use App\Modules\Audit\ConsultaAuditService;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Manejador del flujo de autenticación por Código Fijo (AWAITING_CODE).
 */
class AuthFlowHandler
{
    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var SocioService
     */
    private $socioService;

    /**
     * @var ConsultaAuditService|null
     */
    private $auditService;

    public function __construct(
        SessionService $sessionService,
        SocioService $socioService,
        ?ConsultaAuditService $auditService = null
    ) {
        $this->sessionService = $sessionService;
        $this->socioService = $socioService;
        $this->auditService = $auditService;
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
                $nombreSocio = $validacion['datos_socio']['nombre'] ?? 'Socio';
                $contextData = ['nombre_socio' => $nombreSocio];

                // Socio válido -> Actualizar estado a MAIN_MENU y guardar nombre en sesión
                $this->sessionService->updateSession($telefono, (int)$codigoIngresado, 'MAIN_MENU', 0, $contextData);

                // Registrar auditoría hacia COSMOL-Reportes
                if ($this->auditService !== null) {
                    $this->auditService->registrarAcceso((int)$codigoIngresado, $nombreSocio);
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
