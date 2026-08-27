<?php

declare(strict_types=1);

namespace App\Core;

class Controller{
    protected function json(array $data, int $status = 200): void
    {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code($status);
        header('content-type: application/json; charset=utf-8');
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded === false) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error encoding JSON: ' . json_last_error_msg()
            ]);
        } else {
            echo $encoded;
        }
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
