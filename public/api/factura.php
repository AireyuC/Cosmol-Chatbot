<?php

declare(strict_types=1);

// Carga manual de dependencias
require_once __DIR__ . '/../../app/Core/Controller.php';

use App\Core\Controller;

/**
 * Endpoint temporal de MOCK para Facturas
 */
class FacturaEndpoint extends Controller
{
    public function handleRequest()
    {
        $cod_socio = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $cod_socio = $input['cod_socio'] ?? null;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $cod_socio = $_GET['cod_socio'] ?? null;
        } else {
            $this->json(['status' => 'error', 'message' => 'Método HTTP no soportado'], 405);
        }

        if ($cod_socio === null) {
            $this->json(['status' => 'error', 'message' => 'El parámetro cod_socio es requerido'], 400);
        }

        // --- INICIO DEL MOCK TEMPORAL ---
        // Simula la respuesta de la base de datos para el menú interactivo
        $this->json([
            'status' => 'success',
            'codigo_socio' => $cod_socio,
            'mensaje_texto' => "El Código Fijo ($cod_socio) tiene 2 facturas impagas, cuyo monto total es 221,90 Bs.\nEl detalle es el siguiente:\n\n1. Junio-2026, 107,60 Bs. (Pendiente)\n2. Julio-2026, 114,30 Bs. (Pendiente)",
            'facturas_pendientes' => [
                ['periodo' => 'Junio-2026', 'monto' => 107.60],
                ['periodo' => 'Julio-2026', 'monto' => 114.30]
            ],
            'total_deuda' => 221.90
        ], 200);
        // --- FIN DEL MOCK TEMPORAL ---
    }
}

// Ejecutar el endpoint
$endpoint = new FacturaEndpoint();
$endpoint->handleRequest();