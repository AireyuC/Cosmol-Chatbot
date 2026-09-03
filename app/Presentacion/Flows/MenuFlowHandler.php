<?php

declare(strict_types=1);

namespace App\Presentacion\Flows;

use App\Modules\Session\SessionService;
use App\Modules\Socio\SocioService;
use App\Modules\Reconexion\ReconexionService;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaFactura;
use App\Presentacion\PlantillasWhatsApp\PlantillaReclamo;
use App\Presentacion\PlantillasWhatsApp\PlantillaReconexion;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Manejador de las opciones y botones del menú principal (MAIN_MENU).
 */
class MenuFlowHandler
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
     * @var ReconexionService
     */
    private $reconexionService;

    public function __construct(
        SessionService $sessionService,
        SocioService $socioService,
        ReconexionService $reconexionService
    ) {
        $this->sessionService = $sessionService;
        $this->socioService = $socioService;
        $this->reconexionService = $reconexionService;
    }

    /**
     * Procesa la opción seleccionada por el usuario en el menú principal.
     *
     * @param string $telefono
     * @param string $tipoMensaje
     * @param mixed $contenido
     * @param string|int|null $codigoSocio
     * @param array $contextData
     * @return array Payload de WhatsApp a enviar
     */
    public function handle(string $telefono, string $tipoMensaje, $contenido, $codigoSocio, array $contextData): array
    {
        $codigoSocioStr = (string)$codigoSocio;

        if ($tipoMensaje !== 'interactive') {
            // Si el socio escribe texto plano en lugar de tocar una opción del menú
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
        }

        $accion = (string)$contenido;

        // 1. Pago y Deudas pendientes
        if (strpos($accion, 'MENU_PAGAR_') === 0) {
            $partes = explode('_', $accion);
            $cod = $partes[2] ?? $codigoSocioStr;

            $deudasResult = $this->socioService->obtenerDeudas($cod);
            if (isset($deudasResult['status']) && $deudasResult['status'] === 'success') {
                return PlantillaFactura::listaDeudas(
                    $cod,
                    $deudasResult['cantidad_facturas'],
                    $deudasResult['total_deuda'],
                    $deudasResult['facturas_pendientes']
                );
            }
            return PlantillaSistema::textoSimple("Ocurrió un error al obtener las deudas.");
        }

        // 2. Redirección con Agente Humano
        if ($accion === 'MENU_AGENTE') {
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

        // 6. Submenú de Reclamos
        if ($accion === 'MENU_RECLAMOS') {
            return PlantillaReclamo::menuReclamos();
        }

        // 7. Historial de facturas pagadas
        if ($accion === 'MENU_HISTORIAL') {
            $historialResult = $this->socioService->obtenerHistorial($codigoSocioStr);
            if (isset($historialResult['status']) && $historialResult['status'] === 'success') {
                return PlantillaFactura::historialFacturas(
                    $codigoSocioStr,
                    $historialResult['facturas'],
                    $historialResult['cantidad']
                );
            }
            return PlantillaSistema::textoSimple("Ocurrió un error al obtener el historial de facturas.");
        }

        // 8. Solicitud de Reconexión
        if ($accion === 'MENU_RECONEXION') {
            // Validar si ya tiene reconexión pendiente
            if ($this->reconexionService->tieneReconexionPendiente($codigoSocioStr)) {
                $mensaje = PlantillaReconexion::reconexionPendiente();
                return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
            }

            // Validar si supera el límite de mora (más de 2 facturas)
            $deudasResult = $this->socioService->obtenerDeudas($codigoSocioStr);
            $cantidadDeudas = ($deudasResult['status'] ?? '') === 'success' ? $deudasResult['cantidad_facturas'] : 0;

            if ($cantidadDeudas > 2) {
                $mensaje = PlantillaReconexion::deudaExcedida($cantidadDeudas);
                return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
            }

            // Iniciar flujo de reconexión
            $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_GPS', 0, []);
            return PlantillaReconexion::solicitarGps();
        }

        // 9. Información de Oficinas y Horarios
        if ($accion === 'MENU_OFICINAS') {
            $infoOficinas = "📍 *Oficina Central COSMOL R.L. Montero*\n\n" .
                            "🕒 *Horarios de Atención:*\n" .
                            "Lunes a Viernes:\n" .
                            "Mañanas: 08:00 AM a 12:00 PM\n" .
                            "Tardes: 14:00 PM a 18:00 PM\n\n" .
                            "🗺️ *Ubicación:*\n" .
                            "Calle Isaias Parada, entre calle Santa Cruz y calle Ballivian.\n\n" .
                            "📍 *Ver en Google Maps:*\n" .
                            "https://maps.app.goo.gl/eGbuK1Sh5XfbfTr27";

            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $infoOficinas);
        }

        // 10. Consulta de estado de reclamos
        if ($accion === 'RECLAMO_ESTADO') {
            return PlantillaReclamo::menuReclamos("🏗️ La consulta de estado está en construcción. Seleccione otra opción:");
        }

        // 11. Selección de tipo de reclamo específico
        if (strpos($accion, 'RECLAMO_') === 0) {
            $mapaReclamos = [
                'RECLAMO_AGUA_TURBIA' => ['id_tipo' => 2, 'desc' => 'Agua turbia'],
                'RECLAMO_FUGA'        => ['id_tipo' => 2, 'desc' => 'Fuga de agua'],
                'RECLAMO_REBALSE'     => ['id_tipo' => 3, 'desc' => 'Rebalse alcantarillado'],
                'RECLAMO_TRANCADO'    => ['id_tipo' => 3, 'desc' => 'Alcantarilla trancada']
            ];

            if (isset($mapaReclamos[$accion])) {
                $contextData['id_tipo_reclamo'] = $mapaReclamos[$accion]['id_tipo'];
                $contextData['descripcion_reclamo'] = $mapaReclamos[$accion]['desc'];

                $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECLAMO_GPS', 0, $contextData);
                return PlantillaReclamo::solicitarGpsReclamo();
            }

            return PlantillaReclamo::menuReclamos();
        }

        // Opción desconocida
        return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
    }
}
