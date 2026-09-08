<?php

declare(strict_types=1);

namespace App\Core;

use App\Data\Interfaces\SessionRepositoryInterface;
use App\Data\Interfaces\SocioRepositoryInterface;
use App\Data\Interfaces\ReconexionRepositoryInterface;
use App\Data\Interfaces\ReclamoRepositoryInterface;
use App\Data\Interfaces\ReportesRepositoryInterface;

use App\Data\Repositories\Postgres\SessionRepository;
use App\Data\Repositories\Api\SocioRepository;
use App\Data\Repositories\Api\ReconexionRepository;
use App\Data\Repositories\Api\ReclamoRepository;
use App\Data\Repositories\Postgres\ReportesBufferRepository;

use App\Integrations\CosmolApi\ClienteApiCosmol;
use App\Integrations\CosmolReportes\ClienteApiReportes;
use App\Integrations\WhatsApp\WhatsAppMediaService;

use App\Modules\Session\SessionService;
use App\Modules\Socio\SocioService;
use App\Modules\Facturacion\FacturacionService;
use App\Modules\Reconexion\ReconexionService;
use App\Modules\Reclamo\ReclamoService;
use App\Modules\Audit\ConsultaAuditService;

use App\Presentacion\Flows\Manejadores\AuthFlowHandler;
use App\Presentacion\Flows\Manejadores\MenuFlowHandler;
use App\Presentacion\Flows\Manejadores\ReconexionFlowHandler;
use App\Presentacion\Flows\Manejadores\ReclamoFlowHandler;
use App\Presentacion\Flows\FlowRouter;
use App\Presentacion\Flows\MenuActions\PagarAction;
use App\Presentacion\Flows\MenuActions\HistorialAction;
use App\Presentacion\Flows\MenuActions\ReconexionAction;
use App\Presentacion\Flows\MenuActions\ReclamoAction;
use App\Presentacion\Flows\MenuActions\EstadoTramitesAction;
use App\Presentacion\Flows\MenuActions\InfoAction;

/**
 * Contenedor Central de Dependencias (Service Container / App Base).
 * Provee instancias compartidas con Lazy Loading de todos los repositorios,
 * clientes de integración, servicios de dominio y Flow Handlers.
 */
class AppContainer
{
    /**
     * @var AppContainer|null
     */
    private static $instance = null;

    // Integraciones
    private $clienteApiCosmol = null;
    private $clienteApiReportes = null;
    private $whatsAppMediaService = null;

    // Repositorios
    private $sessionRepository = null;
    private $socioRepository = null;
    private $reconexionRepository = null;
    private $reclamoRepository = null;
    private $reportesBufferRepository = null;

    // Servicios
    private $sessionService = null;
    private $socioService = null;
    private $facturacionService = null;
    private $reconexionService = null;
    private $reclamoService = null;
    private $auditService = null;
    private $maintenanceGuard = null;

    // Flow Handlers y Router
    private $authFlowHandler = null;
    private $menuFlowHandler = null;
    private $reconexionFlowHandler = null;
    private $reclamoFlowHandler = null;
    private $flowRouter = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // =========================================================================
    // Integraciones Externas
    // =========================================================================

    public function getClienteApiCosmol(): ClienteApiCosmol
    {
        if ($this->clienteApiCosmol === null) {
            $this->clienteApiCosmol = new ClienteApiCosmol();
        }
        return $this->clienteApiCosmol;
    }

    public function getClienteApiReportes(): ClienteApiReportes
    {
        if ($this->clienteApiReportes === null) {
            $this->clienteApiReportes = new ClienteApiReportes();
        }
        return $this->clienteApiReportes;
    }

    public function getWhatsAppMediaService(): WhatsAppMediaService
    {
        if ($this->whatsAppMediaService === null) {
            $this->whatsAppMediaService = new WhatsAppMediaService();
        }
        return $this->whatsAppMediaService;
    }

    // =========================================================================
    // Repositorios
    // =========================================================================

    public function getSessionRepository(): SessionRepositoryInterface
    {
        if ($this->sessionRepository === null) {
            $this->sessionRepository = new SessionRepository();
        }
        return $this->sessionRepository;
    }

    public function getSocioRepository(): SocioRepositoryInterface
    {
        if ($this->socioRepository === null) {
            $this->socioRepository = new SocioRepository($this->getClienteApiCosmol());
        }
        return $this->socioRepository;
    }

    public function getReconexionRepository(): ReconexionRepositoryInterface
    {
        if ($this->reconexionRepository === null) {
            $this->reconexionRepository = new ReconexionRepository($this->getClienteApiCosmol());
        }
        return $this->reconexionRepository;
    }

    public function getReclamoRepository(): ReclamoRepositoryInterface
    {
        if ($this->reclamoRepository === null) {
            $this->reclamoRepository = new ReclamoRepository($this->getClienteApiCosmol());
        }
        return $this->reclamoRepository;
    }

    public function getReportesBufferRepository(): ReportesRepositoryInterface
    {
        if ($this->reportesBufferRepository === null) {
            $this->reportesBufferRepository = new ReportesBufferRepository();
        }
        return $this->reportesBufferRepository;
    }

    // =========================================================================
    // Servicios de Dominio
    // =========================================================================

    public function getSessionService(): SessionService
    {
        if ($this->sessionService === null) {
            $this->sessionService = new SessionService($this->getSessionRepository());
        }
        return $this->sessionService;
    }

    public function getSocioService(): SocioService
    {
        if ($this->socioService === null) {
            $this->socioService = new SocioService($this->getSocioRepository());
        }
        return $this->socioService;
    }

    public function getFacturacionService(): FacturacionService
    {
        if ($this->facturacionService === null) {
            $this->facturacionService = new FacturacionService($this->getSocioRepository());
        }
        return $this->facturacionService;
    }

    public function getReconexionService(): ReconexionService
    {
        if ($this->reconexionService === null) {
            $this->reconexionService = new ReconexionService(
                $this->getReconexionRepository(),
                $this->getSocioRepository()
            );
        }
        return $this->reconexionService;
    }

    public function getReclamoService(): ReclamoService
    {
        if ($this->reclamoService === null) {
            $this->reclamoService = new ReclamoService(
                $this->getReclamoRepository(),
                $this->getSocioRepository()
            );
        }
        return $this->reclamoService;
    }

    public function getAuditService(): ConsultaAuditService
    {
        if ($this->auditService === null) {
            $this->auditService = new ConsultaAuditService(
                $this->getClienteApiReportes(),
                $this->getReportesBufferRepository()
            );
        }
        return $this->auditService;
    }

    public function getMaintenanceGuard(): MaintenanceGuard
    {
        if ($this->maintenanceGuard === null) {
            $this->maintenanceGuard = new MaintenanceGuard($this->getSessionRepository());
        }
        return $this->maintenanceGuard;
    }

    // =========================================================================
    // Flow Handlers y Router
    // =========================================================================

    public function getAuthFlowHandler(): AuthFlowHandler
    {
        if ($this->authFlowHandler === null) {
            $this->authFlowHandler = new AuthFlowHandler(
                $this->getSessionService(),
                $this->getSocioService(),
                $this->getAuditService()
            );
        }
        return $this->authFlowHandler;
    }

    public function getMenuFlowHandler(): MenuFlowHandler
    {
        if ($this->menuFlowHandler === null) {
            $this->menuFlowHandler = new MenuFlowHandler(
                new PagarAction($this->getFacturacionService(), $this->getAuditService()),
                new HistorialAction($this->getFacturacionService(), $this->getAuditService()),
                new ReconexionAction($this->getSessionService(), $this->getReconexionService(), $this->getFacturacionService()),
                new ReclamoAction($this->getSessionService()),
                new EstadoTramitesAction($this->getReclamoService(), $this->getReconexionService(), $this->getAuditService()),
                new InfoAction($this->getSessionService(), $this->getAuditService())
            );
        }
        return $this->menuFlowHandler;
    }

    public function getReconexionFlowHandler(): ReconexionFlowHandler
    {
        if ($this->reconexionFlowHandler === null) {
            $this->reconexionFlowHandler = new ReconexionFlowHandler(
                $this->getSessionService(),
                $this->getReconexionService(),
                $this->getWhatsAppMediaService(),
                $this->getAuditService()
            );
        }
        return $this->reconexionFlowHandler;
    }

    public function getReclamoFlowHandler(): ReclamoFlowHandler
    {
        if ($this->reclamoFlowHandler === null) {
            $this->reclamoFlowHandler = new ReclamoFlowHandler(
                $this->getSessionService(),
                $this->getReclamoService(),
                $this->getWhatsAppMediaService(),
                $this->getAuditService()
            );
        }
        return $this->reclamoFlowHandler;
    }

    public function getFlowRouter(): FlowRouter
    {
        if ($this->flowRouter === null) {
            $this->flowRouter = new FlowRouter(
                $this->getAuthFlowHandler(),
                $this->getMenuFlowHandler(),
                $this->getReconexionFlowHandler(),
                $this->getReclamoFlowHandler()
            );
        }
        return $this->flowRouter;
    }
}
