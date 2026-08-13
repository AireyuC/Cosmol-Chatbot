<?php

declare(strict_types=1);

// Punto de entrada HTTP del módulo de Facturas.
// bootstrap.php carga el autoloader (PSR-4), la configuración global y los headers de la API.
require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;

/**
 * Endpoint temporal de MOCK para Facturas
 */
class FacturaEndpoint extends Controller
{
    public function handleRequest()
    {
        $codigo_socio = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $codigo_socio = $input['codigo_socio'] ?? $input['cod_socio'] ?? null;
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $codigo_socio = $_GET['codigo_socio'] ?? $_GET['cod_socio'] ?? null;
        } else {
            $this->json(['success' => false, 'message' => 'Método HTTP no soportado', 'data' => null], 405);
        }

        if ($codigo_socio === null) {
            $this->json(['success' => false, 'message' => 'El parámetro codigo_socio es requerido', 'data' => null], 400);
        }

        // --- INICIO DEL MOCK TEMPORAL ---
        // Simula la respuesta de la base de datos para el menú interactivo
        $this->json([
            'success' => true,
            'message' => "El Código Fijo ($codigo_socio) tiene 2 facturas impagas, cuyo monto total es 221,90 Bs.",
            'data' => [
                'codigo_socio' => $codigo_socio,
                'total_deuda' => 221.90,
                'facturas_pendientes' => [
                    ['periodo' => 'Junio-2026', 'monto' => 107.60, 'estado' => 'PENDIENTE'],
                    ['periodo' => 'Julio-2026', 'monto' => 114.30, 'estado' => 'PENDIENTE']
                ]
            ]
        ], 200);
        // --- FIN DEL MOCK TEMPORAL ---
    }
}

// Ejecutar el endpoint
$endpoint = new FacturaEndpoint();
$endpoint->handleRequest();