<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\AppContainer;
use App\Core\Logger;

/**
 * Worker / Cron Endpoint para Expiración Automática de Sesiones Inactivas (10 minutos).
 * Invocado periódicamente por n8n (Schedule Trigger cada 1 minuto) o por llamada interna.
 * Protegido por X-Internal-Token en bootstrap.php.
 */
try {
    $container = AppContainer::getInstance();
    $sessionService = $container->getSessionService();

    // Tiempo de inactividad: 600 segundos = 10 minutos
    $resultado = $sessionService->expirarSesionesInactivas(600);

    $total = $resultado['total'] ?? 0;
    $notificadas = $resultado['notificadas'] ?? 0;
    $subflujosCancelados = $resultado['subflujos_cancelados'] ?? 0;

    if ($total > 0 || $subflujosCancelados > 0) {
        Logger::info("CronExpireSessions: Se procesaron expiraciones", [
            'subflujos_cancelados' => $subflujosCancelados,
            'total_expiradas' => $total,
            'notificadas' => $notificadas
        ]);
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => ($total > 0 || $subflujosCancelados > 0)
            ? "Ciclo completado: {$subflujosCancelados} sub-flujos cancelados (3 min) y {$total} sesiones expiradas (10 min)"
            : 'No hay sesiones ni sub-flujos inactivos en este ciclo',
        'subflujos_cancelados' => $subflujosCancelados,
        'total' => $total,
        'notificadas' => $notificadas,
        'expiradas' => $resultado['expiradas'] ?? []
    ], JSON_UNESCAPED_UNICODE);

} catch (\Throwable $e) {
    Logger::error("CronExpireSessions: Error interno al procesar expiraciones", [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Ocurrió un error al procesar las sesiones expiradas',
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

