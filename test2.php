<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['telefono' => '59163448562', 'tipo_mensaje' => 'text', 'contenido' => 'hola'];
require 'public/api/webhook_whatsapp.php';
