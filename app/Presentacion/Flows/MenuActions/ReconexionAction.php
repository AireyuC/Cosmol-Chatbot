<?php

declare(strict_types=1);

namespace App\Presentacion\Flows\MenuActions;

use App\Modules\Session\SessionService;
use App\Modules\Reconexion\ReconexionService;
use App\Modules\Facturacion\FacturacionService;
use App\Modules\Audit\ConsultaAuditService;
use App\Data\Interfaces\SocioRepositoryInterface;
use App\Core\FeatureFlags;
use App\Presentacion\PlantillasWhatsApp\PlantillaSocio;
use App\Presentacion\PlantillasWhatsApp\PlantillaReconexion;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Acción del menú principal: Validación de reglas de mora e inicio de reconexión.
 */
class ReconexionAction
{
    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var ReconexionService
     */
    private $reconexionService;

    /**
     * @var FacturacionService
     */
    private $facturacionService;

    /**
     * @var ConsultaAuditService|null
     */
    private $auditService;

    /**
     * @var SocioRepositoryInterface|null
     */
    private $socioRepository;

    // --- CONTROLADORES DE NEGOCIO Y TIEMPO (ADMINISTRACIÓN DESDE CÓDIGO) ---
    public const MAX_FACTURAS_MORA          = 1;  // Máximo de facturas en mora permitidas (si adeuda > 1 se rechaza)
    public const MAX_RECONEXIONES_POR_SOCIO = 1;  // Límite diario de solicitudes por código de socio
    public const COOLDOWN_SECONDS           = 60; // Segundos mínimos entre solicitudes consecutivas

    public function __construct(
        SessionService $sessionService,
        ReconexionService $reconexionService,
        FacturacionService $facturacionService,
        ?ConsultaAuditService $auditService = null,
        ?SocioRepositoryInterface $socioRepository = null
    ) {
        $this->sessionService = $sessionService;
        $this->reconexionService = $reconexionService;
        $this->facturacionService = $facturacionService;
        $this->auditService = $auditService;
        $this->socioRepository = $socioRepository;
    }

    /**
     * Evalúa las políticas de negocio e inicia el flujo de reconexión si procede.
     *
     * @param string $telefono
     * @param string|int|null $codigoSocio
     * @param string $codigoSocioStr
     * @param string $nombreSocio
     * @return array Payload interactivo de WhatsApp
     */
    public function execute(string $telefono, $codigoSocio, string $codigoSocioStr, string $nombreSocio): array
    {
        if (!FeatureFlags::isEnabled('reconexion')) {
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, PlantillaSistema::moduloEnMantenimiento("Solicitud de Reconexión"));
        }

        // 1. Validar si ya tiene una reconexión activa/pendiente (solo se permite 1 reconexión hasta ser atendida)
        $tienePendiente = $this->reconexionService->tieneReconexionPendiente($codigoSocioStr);
        if (!$tienePendiente && $this->auditService !== null) {
            $tienePendiente = !$this->auditService->puedeSolicitarReconexionSocio((int)$codigoSocio);
        }

        if ($tienePendiente) {
            $mensaje = PlantillaReconexion::reconexionPendiente();
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
        }

        // 2. Validar si supera el límite de mora (máximo 1 factura permitida; > 1 rechaza)
        $deudasResult = $this->facturacionService->obtenerDeudas($codigoSocioStr);
        $cantidadDeudas = ($deudasResult['status'] ?? '') === 'success' ? $deudasResult['cantidad_facturas'] : 0;

        if ($cantidadDeudas > self::MAX_FACTURAS_MORA) {
            $mensaje = PlantillaReconexion::deudaExcedida($cantidadDeudas);
            return PlantillaSocio::menuPrincipal($codigoSocioStr, '', false, $mensaje);
        }

        // 3. Obtener dirección registrada del socio en caso de existir
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

        // 4. Iniciar flujo de reconexión solicitando tipo de ubicación (Domicilio vs GPS)
        $contextReconexion = [
            'nombre_socio' => $nombreSocio,
            'direccion_registrada' => $direccion
        ];
        $this->sessionService->updateSession($telefono, (int)$codigoSocio, 'AWAITING_RECONEXION_TIPO_UBICACION', 0, $contextReconexion);
        return PlantillaReconexion::preguntaTipoUbicacion($direccion);
    }
}
