<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\Manejadores;

use App\Presentacion\Flows\MenuActions\PagarAction;
use App\Presentacion\Flows\MenuActions\HistorialAction;
use App\Presentacion\Flows\MenuActions\ReconexionAction;
use App\Presentacion\Flows\MenuActions\ReclamoAction;
use App\Presentacion\Flows\MenuActions\EstadoTramitesAction;
use App\Presentacion\Flows\MenuActions\InfoAction;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;

/**
 * Manejador del menú principal (MAIN_MENU).
 * Actúa como un despachador ágil y desacoplado hacia las acciones modulares correspondientes.
 */
class MenuFlowHandler
{
    /**
     * @var PagarAction
     * @var HistorialAction
     * @var ReconexionAction
     * @var ReclamoAction
     * @var EstadoTramitesAction
     * @var InfoAction
     */

    private $pagarAction;
    private $historialAction;
    private $reconexionAction;
    private $reclamoAction;
    private $estadoTramitesAction;
    private $infoAction;

    public function __construct(
        PagarAction $pagarAction,
        HistorialAction $historialAction,
        ReconexionAction $reconexionAction,
        ReclamoAction $reclamoAction,
        EstadoTramitesAction $estadoTramitesAction,
        InfoAction $infoAction
    ) {
        $this->pagarAction = $pagarAction;
        $this->historialAction = $historialAction;
        $this->reconexionAction = $reconexionAction;
        $this->reclamoAction = $reclamoAction;
        $this->estadoTramitesAction = $estadoTramitesAction;
        $this->infoAction = $infoAction;
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
        $nombreSocio = $contextData['nombre_socio'] ?? 'Socio';

        if ($tipoMensaje !== 'interactive') {
            // Si el socio escribe texto plano en lugar de tocar una opción del menú
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
        }

        $accion = (string)$contenido;

        // 1. Deuda y Pago
        if (strpos($accion, 'MENU_PAGAR_') === 0) {
            return $this->pagarAction->execute($accion, $codigoSocioStr, $nombreSocio);
        }

        // 2. Historial de facturas pagadas
        if ($accion === 'MENU_HISTORIAL') {
            return $this->historialAction->execute($codigoSocio, $codigoSocioStr, $nombreSocio);
        }

        // 3. Solicitud de Reconexión
        if ($accion === 'MENU_RECONEXION') {
            return $this->reconexionAction->execute($telefono, $codigoSocio, $codigoSocioStr, $nombreSocio);
        }

        // 4. Consulta de estado de trámites (reclamos y reconexiones)
        if ($accion === 'MENU_ESTADO_TRAMITES' || $accion === 'RECLAMO_ESTADO') {
            return $this->estadoTramitesAction->execute($codigoSocio, $codigoSocioStr, $nombreSocio);
        }

        // 5. Reclamos (submenú y tipos específicos)
        if ($accion === 'MENU_RECLAMOS' || strpos($accion, 'RECLAMO_') === 0) {
            return $this->reclamoAction->execute($accion, $telefono, $codigoSocio, $codigoSocioStr, $nombreSocio, $contextData);
        }

        // 6. Información, asesor y control de sesión
        if (in_array($accion, ['MENU_OFICINAS', 'MENU_AGENTE', 'MENU_CAMBIAR_CODIGO', 'MENU_CERRAR_SESION', 'MENU_PRINCIPAL_VOLVER'], true)) {
            return $this->infoAction->execute($accion, $telefono, $codigoSocio, $codigoSocioStr, $nombreSocio);
        }

        // Opción desconocida
        return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
    }
}
