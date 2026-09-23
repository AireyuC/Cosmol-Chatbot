<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Session\SessionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Data\Interfaces\SocioRepositoryInterface;
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

    /**
     * @var ConsultaAuditService|null
     */
    private $auditService;

    /**
     * @var SocioRepositoryInterface|null
     */
    private $socioRepository;

    // --- CONTROLADORES DE NEGOCIO Y TIEMPO (ADMINISTRACIÓN DESDE CÓDIGO) ---
    public const MAX_RECLAMOS_POR_SOCIO    = 3;  // Máximo de reclamos diarios por código de socio
    public const MAX_RECLAMOS_POR_TELEFONO = 6;  // Máximo diario global por número de WhatsApp
    public const COOLDOWN_SECONDS          = 30; // Segundos mínimos entre reclamos consecutivos (anti-spam)

    private static $mapaReclamos = [
        'RECLAMO_AGUA_TURBIA' => ['id_tipo' => 2, 'desc' => 'Agua turbia'],
        'RECLAMO_FUGA'        => ['id_tipo' => 2, 'desc' => 'Fuga de agua'],
        'RECLAMO_REBALSE'     => ['id_tipo' => 3, 'desc' => 'Rebalse alcantarillado'],
        'RECLAMO_TRANCADO'    => ['id_tipo' => 3, 'desc' => 'Alcantarilla trancada']
    ];

    public function __construct(
        SessionService $sessionService,
        ?ConsultaAuditService $auditService = null,
        ?SocioRepositoryInterface $socioRepository = null
    ) {
        $this->sessionService = $sessionService;
        $this->auditService = $auditService;
        $this->socioRepository = $socioRepository;
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

        // 1. Control de límite diario por código de socio (máximo 3 reclamos al día)
        if ($this->auditService !== null && !$this->auditService->puedeRegistrarReclamoSocio((int)$codigoSocio)) {
            return PlantillaReclamo::advertenciaLimiteReclamos(self::MAX_RECLAMOS_POR_SOCIO);
        }

        // 2. Control de límite global por número de WhatsApp (máximo 6 reclamos en total hoy)
        if ($this->auditService !== null && !$this->auditService->puedeRegistrarReclamoTelefono($telefono)) {
            return PlantillaReclamo::advertenciaLimiteGlobalTelefono(self::MAX_RECLAMOS_POR_TELEFONO);
        }

        // 3. Control de Cooldown en segundos (anti-spam / doble clic accidental)
        if ($this->auditService !== null) {
            $segundosRestantes = $this->auditService->obtenerSegundosRestantesCooldownReclamo($telefono);
            if ($segundosRestantes > 0) {
                return PlantillaReclamo::advertenciaCooldownReclamo($segundosRestantes);
            }
        }

        if ($accion === 'MENU_RECLAMOS') {
            return PlantillaReclamo::menuReclamos();
        }

        if (isset(self::$mapaReclamos[$accion])) {
            $direccion = '';
            if ($this->socioRepository !== null) {
                $socio = $this->socioRepository->findByCodigo($codigoSocioStr);
                if ($socio) {
                    $direccion = $socio['direccion'] ?? ($socio['DIRECCION'] ?? '');
                    if (empty($direccion) && isset($socio['ZONA'])) {
                        $direccion = "Zona {$socio['ZONA']}, Ruta {$socio['RUTA']}";
                    }
                }
            }

            $contextData['id_tipo_reclamo'] = self::$mapaReclamos[$accion]['id_tipo'];
            $contextData['descripcion_reclamo'] = self::$mapaReclamos[$accion]['desc'];
            $contextData['nombre_socio'] = $nombreSocio;
            $contextData['direccion_registrada'] = $direccion;

            $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECLAMO_TIPO_UBICACION', 0, $contextData);
            return PlantillaReclamo::preguntaTipoUbicacion($direccion);
        }

        return PlantillaReclamo::menuReclamos();
    }
}
