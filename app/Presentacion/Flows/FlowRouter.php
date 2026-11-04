<?php

declare(strict_types=1);

namespace App\Presentacion\Flows;

use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;
use App\Presentacion\Flows\Manejadores\AuthFlowHandler;
use App\Presentacion\Flows\Manejadores\MenuFlowHandler;
use App\Presentacion\Flows\Manejadores\ReconexionFlowHandler;
use App\Presentacion\Flows\Manejadores\ReclamoFlowHandler;

/**
 * Enrutador de la Máquina de Estados del Chatbot.
 * Dirige el flujo conversacional hacia el Flow Handler adecuado según el estado actual de la sesión.
 */
class FlowRouter
{
    /**
     * @var AuthFlowHandler
     * @var MenuFlowHandler
     * @var ReconexionFlowHandler
     * @var ReclamoFlowHandler
     */

    private $authFlow;
    private $menuFlow;
    private $reconexionFlow;
    private $reclamoFlow;

    public function __construct(
        AuthFlowHandler $authFlow,
        MenuFlowHandler $menuFlow,
        ReconexionFlowHandler $reconexionFlow,
        ReclamoFlowHandler $reclamoFlow
    ) {
        $this->authFlow = $authFlow;
        $this->menuFlow = $menuFlow;
        $this->reconexionFlow = $reconexionFlow;
        $this->reclamoFlow = $reclamoFlow;
    }

    /**
     * Despacha la solicitud al manejador correspondiente según el estado de la sesión.
     *
     * @param string $estadoActual Estado de la sesión actual
     * @param string $telefono Número de teléfono del usuario
     * @param string $tipoMensaje Tipo de mensaje recibido (text, interactive, image, location)
     * @param mixed $contenido Contenido del mensaje o payload interactivo
     * @param string|null $sysMessage Mensaje de sistema generado por la sesión (ej. bloqueo)
     * @param int $intentos Contador de intentos fallidos
     * @param string|int|null $codigoSocio Código fijo del socio autenticado
     * @param array $contextData Datos adicionales de contexto
     * @return array|null Payload de WhatsApp listo para Meta, o null si corresponde silencio
     */
    public function dispatch(
        string $estadoActual,
        string $telefono,
        string $tipoMensaje,
        $contenido,
        ?string $sysMessage,
        int $intentos,
        $codigoSocio,
        array $contextData
    ): ?array {
        if ($sysMessage) {
            if ($tipoMensaje === 'text' && is_numeric(trim((string)$contenido))) {
                return $this->authFlow->handle($telefono, $tipoMensaje, $contenido, $intentos);
            }
            return PlantillaSistema::textoSimple($sysMessage);
        }

        if ($estadoActual === 'BLOCKED') {
            // Silencio durante el bloqueo temporal por seguridad
            return null;
        }

        if ($estadoActual === 'AWAITING_CODE') {
            return $this->authFlow->handle($telefono, $tipoMensaje, $contenido, $intentos);
        }

        if ($estadoActual === 'MAIN_MENU') {
            return $this->menuFlow->handle($telefono, $tipoMensaje, $contenido, $codigoSocio, $contextData);
        }

        if (strpos($estadoActual, 'AWAITING_RECONEXION_') === 0) {
            return $this->reconexionFlow->handle($estadoActual, $telefono, $tipoMensaje, $contenido, $codigoSocio, $contextData);
        }

        if (strpos($estadoActual, 'AWAITING_RECLAMO_') === 0) {
            return $this->reclamoFlow->handle($estadoActual, $telefono, $tipoMensaje, $contenido, $codigoSocio, $contextData);
        }

        return null;
    }
}
