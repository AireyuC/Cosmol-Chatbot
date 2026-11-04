<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\AppContainer;
use App\Core\Logger;

/**
 * Worker CLI para vaciar automáticamente la cola de resiliencia hacia COSMOL-Reportes.
 * Uso: php /app/scripts/cron_flush_reportes.php
 * Apto para ejecutarse en Crontab de Linux o tareas programadas.
 */
try {
    $container = AppContainer::getInstance();
    $auditService = $container->getAuditService();
    $bufferRepo = $container->getReportesBufferRepository();

    if (!\App\Core\FeatureFlags::isReportesSyncEnabled()) {
        echo "[" . date('Y-m-d H:i:s') . "] Pusheo pausado por configuración: REPORTES_SYNC_ENABLED=false.\n";
        exit(0);
    }

    $pendientesAntes = $bufferRepo->obtenerPendientes(1);
    if (empty($pendientesAntes)) {
        echo "[" . date('Y-m-d H:i:s') . "] Cola limpia: No hay consultas pendientes.\n";
        exit(0);
    }

    $sincronizados = $auditService->vaciarColaPendiente(50);
    $pendientesDespues = count($bufferRepo->obtenerPendientes(100));

    echo "[" . date('Y-m-d H:i:s') . "] Sincronización finalizada: {$sincronizados} enviadas, {$pendientesDespues} restantes.\n";
    exit(0);

} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
