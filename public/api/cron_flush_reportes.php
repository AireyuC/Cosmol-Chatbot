<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\AppContainer;
use App\Core\Logger;

/**
 * Endpoint de Cron/Worker para vaciar la cola de resiliencia hacia COSMOL-Reportes.
 * Puede ser invocado periódicamente por n8n (Schedule Trigger cada 2-5 min) o por HTTP interno.
 * Protegido por X-Internal-Token en bootstrap.php.
 */
try {
    $container = AppContainer::getInstance();
    $auditService = $container->getAuditService();
    $bufferRepo = $container->getReportesBufferRepository();

    // 0. Verificar si el pusheo está habilitado globalmente
    if (!\App\Core\FeatureFlags::isReportesSyncEnabled()) {
        http_response_code(200);
        echo json_encode([
            'status' => 'paused',
            'message' => 'El pusheo a COSMOL-Reportes está pausado por configuración (REPORTES_SYNC_ENABLED=false)',
            'sincronizados' => 0,
            'restantes' => count($bufferRepo->obtenerPendientes(100))
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 1. Verificar si hay registros pendientes
    $pendientesAntes = $bufferRepo->obtenerPendientes(1);
    if (empty($pendientesAntes)) {
        http_response_code(200);
        echo json_encode([
            'status' => 'idle',
            'message' => 'No hay consultas pendientes en la cola',
            'sincronizados' => 0,
            'restantes' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Intentar vaciar hasta 50 consultas pendientes acumuladas
    $sincronizados = $auditService->vaciarColaPendiente(50);
    $pendientesDespues = count($bufferRepo->obtenerPendientes(100));

    if ($sincronizados > 0) {
        Logger::info("CronFlushReportes: Sincronización exitosa", [
            'sincronizados' => $sincronizados,
            'restantes' => $pendientesDespues
        ]);
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => "Se sincronizaron {$sincronizados} consultas con COSMOL-Reportes",
        'sincronizados' => $sincronizados,
        'restantes' => $pendientesDespues
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    Logger::error("CronFlushReportes: Error interno", [
        'error' => $e->getMessage()
    ]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno al vaciar cola: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
