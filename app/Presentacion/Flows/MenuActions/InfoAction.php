<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Session\SessionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Acción del menú principal: Maneja información institucional, derivación a asesor y control de sesión.
 */
class InfoAction
{
    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var ConsultaAuditService|null
     */
    private $auditService;

    public function __construct(
        SessionService $sessionService,
        ?ConsultaAuditService $auditService = null
    ) {
        $this->sessionService = $sessionService;
        $this->auditService = $auditService;
    }

    /**
     * Procesa las opciones de información, soporte y navegación de sesión.
     *
     * @param string $accion
     * @param string $telefono
     * @param string|int|null $codigoSocio
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @return array Payload de WhatsApp
     */
    public function execute(
        string $accion,
        string $telefono,
        $codigoSocio,
        string $codigoSocioStr,
        string $nombreSocio
    ): array {
        // 1. Información de Oficinas y Horarios
        if ($accion === 'MENU_OFICINAS') {
            if (!FeatureFlags::isEnabled('oficinas')) {
                return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Información de Oficinas"));
            }

            if ($this->auditService !== null) {
                $this->auditService->registrarConsultaOficinas((int)$codigoSocio, $nombreSocio);
            }

            $infoOficinas = "📍 *Oficina Central COSMOL R.L. Montero*\n\n" .
                            "🕒 *Horarios de Atención:*\n" .
                            "Lunes a Viernes:\n" .
                            "Mañanas: 08:00 AM a 12:00 PM\n" .
                            "Tardes: 14:00 PM a 18:00 PM\n\n" .
                            "🗺️ *Ubicación:*\n" .
                            "Calle Isaias Parada, entre calle Santa Cruz y calle Ballivian.\n\n" .
                            "📍 *Ver en Google Maps:*\n" .
                            "https://www.google.com/maps?q=-17.338790,-63.256831";

            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $infoOficinas);
        }

        // 2. Redirección con Asesor Humano
        if ($accion === 'MENU_AGENTE') {
            if (!FeatureFlags::isEnabled('agente')) {
                return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Atención con un Asesor"));
            }

            if ($this->auditService !== null) {
                $this->auditService->registrarDerivacionAgente((int)$codigoSocio, $nombreSocio);
            }

            return PlantillaSocio::redireccionAgente();
        }

        // 3. Volver al menú principal
        if ($accion === 'MENU_PRINCIPAL_VOLVER') {
            return PlantillaSocio::menuPrincipal($codigoSocioStr);
        }

        // 4. Cambiar código de socio
        if ($accion === 'MENU_CAMBIAR_CODIGO') {
            $this->sessionService->resetSession($telefono);
            return PlantillaSistema::textoSimple("Sesión cerrada. Por favor, ingresa tu nuevo código de socio.");
        }

        // 5. Cerrar sesión
        if ($accion === 'MENU_CERRAR_SESION') {
            $this->sessionService->resetSession($telefono);
            return PlantillaSistema::textoSimple("¡Gracias por utilizar nuestro servicio! 👋\n\nTu sesión ha sido cerrada correctamente. Si necesitas algo más en el futuro, simplemente escríbenos 'Hola'.\n\n¡Que tengas un excelente día!");
        }

        return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
    }
}
