<?php

declare(strict_types=1);

// Carga manual de dependencias debido a que aún no hay Autoloader (Fase 4)
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Core/Controller.php';
require_once __DIR__ . '/../../app/Core/Database.php';

// Dependencias de Socios (Requeridas por el servicio de Reclamos)
require_once __DIR__ . '/../../app/Data/Interfaces/SocioRepositoryInterface.php';
require_once __DIR__ . '/../../app/Data/Repositories/MySQL/SocioRepository.php';

// Dependencias de Reclamos
require_once __DIR__ . '/../../app/Data/Interfaces/ReclamoRepositoryInterface.php';
require_once __DIR__ . '/../../app/Data/Repositories/MySQL/ReclamoRepository.php';
require_once __DIR__ . '/../../app/Modules/Reclamo/ReclamoService.php';

use App\Core\Controller;
use App\Core\Database;
use App\Data\Repositories\MySQL\SocioRepository;
use App\Data\Repositories\MySQL\ReclamoRepository;
use App\Modules\Reclamo\ReclamoService;

/**
 * Endpoint de la API para Reclamos
 * Recibe peticiones POST desde n8n para crear un nuevo reclamo.
 */
class ReclamoEndpoint extends Controller
{
    public function handleRequest()
    {
        // Este endpoint está diseñado para recibir un JSON vía POST desde n8n
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->handleError('Método HTTP no soportado. Debe ser POST.', 405);
        }

        // Usamos la función getBody() heredada del Controller base
        $input = $this->getBody();

        $codigo_socio = $input['codigo_socio'] ?? null;
        $tipo_reclamo = $input['tipo_reclamo'] ?? null;
        $descripcion  = $input['descripcion'] ?? null;

        // Validar que los campos requeridos existan
        if (!$codigo_socio || !$tipo_reclamo || !$descripcion) {
            $this->handleError('Faltan campos requeridos: codigo_socio, tipo_reclamo, descripcion.', 400);
        }

        try {
            // 1. Instanciar la conexión a la base de datos (Singleton)
            $db = Database::getInstance();

            // 2. Inyectar dependencias manualmente (Wiring)
            $socioRepo = new SocioRepository($db);
            $reclamoRepo = new ReclamoRepository($db);
            
            $service = new ReclamoService($reclamoRepo, $socioRepo);

            // 3. Ejecutar la lógica de negocio del servicio
            $resultado = $service->registrarReclamo(
                (string) $codigo_socio,
                (string) $tipo_reclamo,
                (string) $descripcion
            );

            // 4. Formatear la respuesta a n8n
            $httpStatus = $resultado['exito'] ? 200 : 400;
            $this->json($resultado, $httpStatus);

        } catch (Exception $e) {
            // Manejo de errores a nivel superior (ej. base de datos caída)
            error_log("Error crítico en ReclamoEndpoint: " . $e->getMessage());
            $this->handleError('Ocurrió un error interno en el servidor.', 500);
        }
    }
}

$endpoint = new ReclamoEndpoint();
$endpoint->handleRequest();
