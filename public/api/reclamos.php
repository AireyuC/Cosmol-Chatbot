<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Data\Repositories\Postgres\SocioRepository;
use App\Data\Repositories\Postgres\ReclamoRepository;
use App\Modules\Reclamo\ReclamoService;
use App\Core\Validator;

class ReclamoEndpoint extends Controller
{
    public function handleRequest()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->handleError('Método HTTP no soportado. Debe ser POST.', 405);
        }
        $input = $this->getBody() ?? [];

        $codigo_socio = $input['codigo_socio'] ?? null;
        $tipo_reclamo = $input['tipo_reclamo'] ?? null;
        $descripcion  = $input['descripcion'] ?? null;

        if (!Validator::codigoSocio((string)$codigo_socio)) {
            $this->handleError('El campo codigo_socio es inválido.', 400);
        }
        if (!Validator::tipoReclamo((string)$tipo_reclamo)) {
            $this->handleError('El campo tipo_reclamo es inválido o no permitido.', 400);
        }
        if (!Validator::descripcion((string)$descripcion)) {
            $this->handleError('El campo descripcion es inválido o supera los 500 caracteres sin HTML permitidos.', 400);
        }

        try {
            
            $db = Database::getInstance();
            
            $socioRepo = new SocioRepository($db);
            $reclamoRepo = new ReclamoRepository($db);
            
            $service = new ReclamoService($reclamoRepo, $socioRepo);

            $resultado = $service->registrarReclamo(
                (string) $codigo_socio,
                (string) $tipo_reclamo,
                (string) $descripcion
            );

            $httpStatus = $resultado['success'] ? 200 : 400;
            $this->json($resultado, $httpStatus);

        } catch (Exception $e) {
            Logger::error('Error crítico en ReclamoEndpoint', [
                'exception'    => $e->getMessage(),
                'codigo_socio' => $codigo_socio ?? null,
                'tipo_reclamo' => $tipo_reclamo ?? null,
            ]);
            $this->handleError('Ocurrió un error interno en el servidor.', 500);
        }
    }
}

$endpoint = new ReclamoEndpoint();
$endpoint->handleRequest();