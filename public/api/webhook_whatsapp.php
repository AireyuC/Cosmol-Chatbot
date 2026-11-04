<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

use App\Core\WebhookKernel;

/**
 * Front Controller para el Webhook de WhatsApp consumido por n8n.
 * Punto de entrada HTTP que delega el ciclo de vida de la petición al WebhookKernel.
 */
$kernel = new WebhookKernel();
$kernel->handle();
