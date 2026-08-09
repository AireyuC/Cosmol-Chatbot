<?php

declare(strict_types=1);

namespace App\Core;

class Controller
{
    /**
     * Devuelve una respuesta JSON estandarizada y termina la ejecución.
     *
     * @param array $data Los datos a devolver.
     * @param int $status El código de estado HTTP (por defecto 200).
     * @return void
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
  
