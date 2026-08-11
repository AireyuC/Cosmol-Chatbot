<?php

declare(strict_types=1);

namespace App\Core;

class Controller{
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('content-type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    protected function getBody(): ?array
    {
        $input = file_get_contents('php://input');

        return json_decode($input, true);
    }

    protected function handleError(string $message, int $status = 400): void
    {
        $this->json([
            'success' => false,
            'message' => $message,
            'data' => null
        ], $status);

    }
}
