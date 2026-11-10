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

    if ($total > 0) {
        Logger::info("CronExpireSessions: Se procesaron sesiones expiradas", [
            'total' => $total,
            'notificadas' => $notificadas
        ]);
    }

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => $total > 0 
            ? "Se procesaron {$total} sesiones expiradas ({$notificadas} notificadas por WhatsApp)" 
            : 'No hay sesiones expiradas en este ciclo',
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

