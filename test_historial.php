<?php
require_once __DIR__ . '/app/bootstrap.php';
$repo = new \App\Data\Repositories\Api\SocioRepository(new \App\Integrations\CosmolApi\ClienteApiCosmol());
$service = new \App\Modules\Socio\SocioService($repo);
print_r($service->obtenerHistorial('23807'));
