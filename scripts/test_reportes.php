<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Integrations\CosmolReportes\ClienteApiReportes;
use App\Data\Repositories\Postgres\ReportesBufferRepository;
use App\Modules\Audit\ConsultaAuditService;

echo "========================================\n";
echo " DIAGNÓSTICO INTEGRACIÓN COSMOL-REPORTES \n";
echo "========================================\n";

$urlConfigurada = defined('REPORTES_API_URL') ? REPORTES_API_URL : '';
$tokenConfigurado = defined('REPORTES_API_TOKEN') ? REPORTES_API_TOKEN : '';

echo "1. Variables de Entorno en el Contenedor:\n";
echo "   - REPORTES_API_URL:   " . ($urlConfigurada ?: '(VACÍA - No configurada)') . "\n";
echo "   - REPORTES_API_TOKEN: " . ($tokenConfigurado ? substr($tokenConfigurado, 0, 8) . '...' : '(VACÍA)') . "\n\n";

if (empty($urlConfigurada)) {
    echo "❌ ERROR: REPORTES_API_URL está vacía en este contenedor.\n";
    echo "   Recuerda correr: sudo docker compose up -d (o recrear el contenedor) tras editar .env\n";
    exit(1);
}

$cliente = new ClienteApiReportes();
$repo = new ReportesBufferRepository();
$service = new ConsultaAuditService($cliente, $repo);

echo "2. Estado de la cola local (cola_reportes):\n";
$pendientes = $repo->obtenerPendientes(50);
echo "   - Registros pendientes en cola: " . count($pendientes) . "\n\n";


$inicio = microtime(true);

$duracion = round((microtime(true) - $inicio) * 1000, 2);

if ($exito) {
    echo "✅ ÉXITO: COSMOL-Reportes respondió correctamente (200/201) en {$duracion} ms.\n";
    echo "   Tu compañero debe ver la consulta en su ngrok y en su base de datos.\n\n";

    echo "4. Vaciando registros acumulados en cola...\n";
    $vaciados = $service->vaciarColaPendiente(20);
    echo "   - Se sincronizaron {$vaciados} registros pendientes exitosamente.\n";
} else {
    echo "❌ FALLÓ: No se pudo conectar con COSMOL-Reportes (Duración: {$duracion} ms).\n";
    echo "   Revisa si tu compañero tiene ngrok activo, si la URL coincide y si el puerto 8080 está corriendo.\n";
}

echo "========================================\n";
