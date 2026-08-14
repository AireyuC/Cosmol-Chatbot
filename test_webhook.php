<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$input = json_encode(['telefono' => '59163448562', 'tipo_mensaje' => 'text', 'contenido' => 'hola']);
file_put_contents('php://input', $input);
require 'public/api/webhook_whatsapp.php';
