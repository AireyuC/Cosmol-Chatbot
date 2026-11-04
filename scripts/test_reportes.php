<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Integrations\CosmolReportes\ClienteApiReportes;
use App\Data\Repositories\Postgres\ReportesBufferRepository;
use App\Modules\Audit\ConsultaAuditService;
use App\Core\Database;

echo "====================================================\n";
echo " DIAGNÓSTICO Y COLA DE REPORTES (DATOS REALES)     \n";
echo "====================================================\n";

$urlConfigurada = defined('REPORTES_API_URL') ? REPORTES_API_URL : '';
$tokenConfigurado = defined('REPORTES_API_TOKEN') ? REPORTES_API_TOKEN : '';

echo "1. Variables de Entorno en el Contenedor:\n";
echo "   - REPORTES_API_URL:   " . ($urlConfigurada ?: '(VACÍA - No configurada)') . "\n";
echo "   - REPORTES_API_TOKEN: " . ($tokenConfigurado ? substr($tokenConfigurado, 0, 8) . '...' : '(VACÍA)') . "\n\n";

if (empty($urlConfigurada)) {
    echo "❌ ERROR: REPORTES_API_URL está vacía en este contenedor.\n";
    echo "   Verifica tu archivo .env y reinicia el contenedor con: docker compose up -d backend\n";
    exit(1);
}

$cliente = new ClienteApiReportes();
$repo = new ReportesBufferRepository();
$service = new ConsultaAuditService($cliente, $repo);

// 2. Revisar si el usuario pasó el flag para reactivar registros fallidos
$args = $argv ?? [];
$reactivar = in_array('--reactivar-fallidos', $args) || in_array('--retry', $args);

if ($reactivar) {
    echo "2. Reactivando registros fallidos...\n";
    $reactivados = $repo->reactivarFallidos();
    echo "   🔄 Se reactivaron {$reactivados} registros a estado PENDIENTE (intentos reiniciados a 0).\n\n";
}

// 3. Consultar estadísticas reales en la base de datos
echo "3. Estado actual de la cola (cola_reportes):\n";
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT estado, COUNT(*) as total FROM cola_reportes GROUP BY estado ORDER BY estado ASC");
    $estados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
} catch (\Exception $e) {
    $estados = [];
}

$cantPendientes = isset($estados['PENDIENTE']) ? (int)$estados['PENDIENTE'] : 0;
$cantFallidos   = isset($estados['FALLIDO']) ? (int)$estados['FALLIDO'] : 0;
$cantEnviados   = isset($estados['ENVIADO']) ? (int)$estados['ENVIADO'] : 0;

echo "   - PENDIENTES : {$cantPendientes}\n";
echo "   - FALLIDOS   : {$cantFallidos}\n";
echo "   - ENVIADOS   : {$cantEnviados}\n\n";

// 4. Procesar exclusivamente registros reales existentes
if ($cantPendientes === 0) {
    echo "ℹ️ No hay consultas pendientes en la cola para sincronizar.\n";
    if ($cantFallidos > 0) {
        echo "\n⚠️ Atención: Tienes {$cantFallidos} registros en estado FALLIDO.\n";
        echo "   Para reactivarlos y enviarlos a Reportes, ejecuta este comando:\n";
        echo "   php scripts/test_reportes.php --reactivar-fallidos\n";
    } else {
        echo "   La cola se encuentra completamente al día. No se enviaron datos ficticios.\n";
    }
    echo "====================================================\n";
    exit(0);
}

echo "4. Sincronizando registros reales pendientes hacia COSMOL-Reportes...\n";
$inicio = microtime(true);

// Vaciamos hasta 50 registros reales pendientes
$sincronizados = $service->vaciarColaPendiente(50);
$duracion = round((microtime(true) - $inicio) * 1000, 2);

if ($sincronizados > 0) {
    echo "✅ ÉXITO: Se sincronizaron {$sincronizados} consultas reales en {$duracion} ms.\n";

    // Ver cuántos quedan pendientes tras el vaciado
    $restantes = count($repo->obtenerPendientes(100));
    if ($restantes > 0) {
        echo "   Quedan {$restantes} registros pendientes en la cola.\n";
    } else {
        echo "   ¡Cola de pendientes completamente vaciada y sincronizada!\n";
    }
} else {
    echo "❌ NO SE PUDO SINCRONIZAR (Duración: {$duracion} ms).\n";
    if ($cliente->estaServidorOffline()) {
        echo "   Motivo: El servidor de COSMOL-Reportes no responde o está apagado/inaccesible.\n";
        echo "   (Los registros no fueron penalizados y permanecen en PENDIENTE).\n";
    }
    $errorDetalle = $cliente->obtenerUltimoError();
    if ($errorDetalle) {
        echo "   Detalle del error: {$errorDetalle}\n";
    }
    echo "   Verifica que REPORTES_API_URL apunte al puerto correcto (ej. 8082) y que el contenedor esté corriendo.\n";
}

echo "====================================================\n";
