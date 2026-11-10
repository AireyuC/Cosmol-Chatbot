<?php

declare(strict_types=1);

namespace App\Integrations\WhatsApp;

use App\Core\Logger;

/**
 * Servicio para despacho directo de mensajes hacia Meta WhatsApp Cloud API.
 * Diseñado para notificaciones proactivas del sistema (ej. Sesión Expirada).
 */
class WhatsAppSenderService
{
    /**
     * @var string|null
     */
    private $token;

    /**
     * @var string|null
     */
    private $phoneNumberId;

    public function __construct(?string $token = null, ?string $phoneNumberId = null)
    {
        $this->token = $token ?: (getenv('WHATSAPP_TOKEN') ?: ($_ENV['WHATSAPP_TOKEN'] ?? $_SERVER['WHATSAPP_TOKEN'] ?? null));
        $this->phoneNumberId = $phoneNumberId ?: (getenv('WHATSAPP_PHONE_NUMBER_ID') ?: ($_ENV['WHATSAPP_PHONE_NUMBER_ID'] ?? $_SERVER['WHATSAPP_PHONE_NUMBER_ID'] ?? '1257228694141168'));
    }

    /**
     * Envía un payload de WhatsApp compatible con Graph API a un número de teléfono.
     *
     * @param string $to Número de teléfono destino en formato internacional (ej. 59177312345)
     * @param array $whatsappPayload Payload estructurado generado por PlantillasWhatsApp
     * @return bool True si Meta aceptó el mensaje (HTTP 200/201), false en caso contrario
     */
    public function enviarMensaje(string $to, array $whatsappPayload): bool
    {
        if (empty($this->token)) {
            Logger::error("WhatsAppSenderService: WHATSAPP_TOKEN no está configurado.");
            return false;
        }

        if (empty($this->phoneNumberId)) {
            Logger::error("WhatsAppSenderService: WHATSAPP_PHONE_NUMBER_ID no está configurado.");
            return false;
        }

        $url = "https://graph.facebook.com/v25.0/{$this->phoneNumberId}/messages";

        // Estructura oficial requerida por Meta Cloud API
        $body = array_merge([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
        ], $whatsappPayload);

        $jsonData = json_encode($body);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->token}",
            "Content-Type: application/json",
            "Content-Length: " . strlen($jsonData)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            Logger::info("WhatsAppSenderService: Mensaje enviado exitosamente a {$to}");
            return true;
        }

        Logger::error("WhatsAppSenderService: Error enviando a {$to} (HTTP {$httpCode})", [
            'error' => $error,
            'response' => $response
        ]);

        return false;
    }
}

