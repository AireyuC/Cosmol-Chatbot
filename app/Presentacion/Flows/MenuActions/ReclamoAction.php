<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Session\SessionService;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaReclamo;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Acción del menú principal: Muestra submenú de reclamos y configura el tipo seleccionado.
 */
class ReclamoAction
{
    /**
     * @var SessionService
     */
    private $sessionService;

    private static $mapaReclamos = [
        'RECLAMO_AGUA_TURBIA' => ['id_tipo' => 2, 'desc' => 'Agua turbia'],
        'RECLAMO_FUGA'        => ['id_tipo' => 2, 'desc' => 'Fuga de agua'],
        'RECLAMO_REBALSE'     => ['id_tipo' => 3, 'desc' => 'Rebalse alcantarillado'],
        'RECLAMO_TRANCADO'    => ['id_tipo' => 3, 'desc' => 'Alcantarilla trancada']
    ];

    public function __construct(SessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Ejecuta el submenú o la selección del tipo de reclamo.
     *
     * @param string $accion
     * @param string $telefono
     * @param string|int|null $codigoSocio
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @param array $contextData
     * @return array Payload interactivo de WhatsApp
     */
    public function execute(
        string $accion,
        string $telefono,
        $codigoSocio,
        string $codigoSocioStr,
        string $nombreSocio,
        array $contextData
    ): array {
        if (!FeatureFlags::isEnabled('reclamos')) {
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Reclamos"));
        }

        if ($accion === 'MENU_RECLAMOS') {
            return PlantillaReclamo::menuReclamos();
        }

        if (isset(self::$mapaReclamos[$accion])) {
            $contextData['id_tipo_reclamo'] = self::$mapaReclamos[$accion]['id_tipo'];
            $contextData['descripcion_reclamo'] = self::$mapaReclamos[$accion]['desc'];
            $contextData['nombre_socio'] = $nombreSocio;

            $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECLAMO_GPS', 0, $contextData);
            return PlantillaReclamo::solicitarGpsReclamo();
        }

        return PlantillaReclamo::menuReclamos();
    }
}
