<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\Manejadores;

use App\Modules\Session\SessionService;
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
     */
    private $pagarAction;

    /**
     * @var HistorialAction
     */
    private $historialAction;

    /**
     * @var ReconexionAction
     */
    private $reconexionAction;

    /**
     * @var ReclamoAction
     */
    private $reclamoAction;

    /**
     * @var EstadoTramitesAction
     */
    private $estadoTramitesAction;

    /**
     * @var InfoAction
     */
    private $infoAction;

    /**
     * @var SessionService|null
     */
    private $sessionService;

    public function __construct(
        PagarAction $pagarAction,
        HistorialAction $historialAction,
        ReconexionAction $reconexionAction,
        ReclamoAction $reclamoAction,
        EstadoTramitesAction $estadoTramitesAction,
        InfoAction $infoAction,
        ?SessionService $sessionService = null
    ) {
        $this->pagarAction = $pagarAction;
        $this->historialAction = $historialAction;
        $this->reconexionAction = $reconexionAction;
        $this->reclamoAction = $reclamoAction;
        $this->estadoTramitesAction = $estadoTramitesAction;
        $this->infoAction = $infoAction;
        $this->sessionService = $sessionService;
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

        // 0. Retorno al Menú Principal con control anti-bucles
        if ($accion === 'MENU_PRINCIPAL_VOLVER') {
            $ultimaOpcion = $contextData['ultima_opcion'] ?? null;
            $contadorExclusion = (int)($contextData['contador_exclusion'] ?? 0);

            if (!empty($ultimaOpcion) && $contadorExclusion === 1) {
                // 2ª vez: Omitir la opción recién consultada en el menú desplegable
                $contextData['contador_exclusion'] = 2;
                if ($this->sessionService !== null) {
                    $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'MAIN_MENU', 0, $contextData);
                }
                return PlantillaSocio::menuPrincipal($codigoSocioStr, $nombreSocio, false, null, false, $ultimaOpcion);
            }

            // 3ª vez o subsecuente: Restablecer menú normal completo
            $contextData['ultima_opcion'] = null;
            $contextData['contador_exclusion'] = 0;
            if ($this->sessionService !== null) {
                $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'MAIN_MENU', 0, $contextData);
            }
            return PlantillaSocio::menuPrincipal($codigoSocioStr, $nombreSocio, false, null, false, null);
        }

        // Detección de la acción base para registrarla en el historial de navegación
        $accionBase = null;
        if (strpos($accion, 'MENU_PAGAR_') === 0 || strpos($accion, 'PAGO_') === 0) {
            $accionBase = 'MENU_PAGAR';
        } elseif ($accion === 'MENU_HISTORIAL') {
            $accionBase = 'MENU_HISTORIAL';
        } elseif ($accion === 'MENU_RECONEXION') {
            $accionBase = 'MENU_RECONEXION';
        } elseif ($accion === 'MENU_ESTADO_TRAMITES' || $accion === 'RECLAMO_ESTADO') {
            $accionBase = 'MENU_ESTADO_TRAMITES';
        } elseif ($accion === 'MENU_RECLAMOS' || strpos($accion, 'RECLAMO_') === 0) {
            $accionBase = 'MENU_RECLAMOS';
        } elseif ($accion === 'MENU_OFICINAS') {
            $accionBase = 'MENU_OFICINAS';
        } elseif ($accion === 'MENU_AGENTE') {
            $accionBase = 'MENU_AGENTE';
        }

        if ($accionBase !== null) {
            $contextData['ultima_opcion'] = $accionBase;
            $contextData['contador_exclusion'] = 1;
            if ($this->sessionService !== null) {
                $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'MAIN_MENU', 0, $contextData);
            }
        }

        // 1. Deuda y Pago
        if (strpos($accion, 'MENU_PAGAR_') === 0) {
            return $this->pagarAction->execute($accion, $codigoSocioStr, $nombreSocio, $telefono);
        }

        // 1.1 Selección interactiva de facturas a pagar
        if (strpos($accion, 'PAGO_SELEC_') === 0) {
            return $this->pagarAction->handleSeleccion($accion, $codigoSocioStr, $nombreSocio);
        }

        // 1.2 Confirmación simulada de pago exitoso (Alpha Mock)
        if (strpos($accion, 'PAGO_CONFIRMAR_') === 0) {
            return $this->pagarAction->handleConfirmacion($accion, $codigoSocioStr, $nombreSocio);
        }

        // 2. Historial de facturas pagadas
        if ($accion === 'MENU_HISTORIAL') {
            return $this->historialAction->execute($codigoSocio, $codigoSocioStr, $nombreSocio, $telefono);
        }

        // 3. Solicitud de Reconexión
        if ($accion === 'MENU_RECONEXION') {
            return $this->reconexionAction->execute($telefono, $codigoSocio, $codigoSocioStr, $nombreSocio);
        }

        // 4. Consulta de estado de trámites (reclamos y reconexiones)
        if ($accion === 'MENU_ESTADO_TRAMITES' || $accion === 'RECLAMO_ESTADO') {
            return $this->estadoTramitesAction->execute($codigoSocio, $codigoSocioStr, $nombreSocio, $telefono);
        }

        // 5. Reclamos (submenú y tipos específicos)
        if ($accion === 'MENU_RECLAMOS' || strpos($accion, 'RECLAMO_') === 0) {
            return $this->reclamoAction->execute($accion, $telefono, $codigoSocio, $codigoSocioStr, $nombreSocio, $contextData);
        }

        // 6. Información, asesor y control de sesión
        if (in_array($accion, ['MENU_OFICINAS', 'MENU_AGENTE', 'MENU_CAMBIAR_CODIGO', 'MENU_CERRAR_SESION'], true)) {
            return $this->infoAction->execute($accion, $telefono, $codigoSocio, $codigoSocioStr, $nombreSocio);
        }

        // Opción desconocida
        return PlantillaSocio::menuPrincipal($codigoSocioStr, '', true);
    }
}
