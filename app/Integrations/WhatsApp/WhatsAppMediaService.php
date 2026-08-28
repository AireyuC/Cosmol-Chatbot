<?php

declare(strict_types=1);

namespace App\Integrations\WhatsApp;

use Exception;

class WhatsAppMediaService
{
    private $token;

    public function __construct()
    {
        // El token puede estar en la variable WHATSAPP_TOKEN
        // O si no está, lanzamos una excepción o devolvemos nulo
        $this->token = getenv('WHATSAPP_TOKEN');
        if (!$this->token) {
            $this->token = $_ENV['WHATSAPP_TOKEN'] ?? $_SERVER['WHATSAPP_TOKEN'] ?? null;
        }
    }

    /**
     * Descarga una imagen desde Meta usando el Media ID
     * y la guarda en la carpeta public/uploads/reclamos/.
     * 
     * @param string $mediaId El ID del archivo de Media provisto por WhatsApp
     * @param string $codigoSocio Código del socio para nombrar el archivo
     * @return string|null La URL pública relativa de la imagen, o null si falla.
     */
    public function descargarYGuardar(string $mediaId, string $codigoSocio): ?string
    {
        if (!$this->token) {
            \App\Core\Logger::error("WhatsAppMediaService: WHATSAPP_TOKEN no está configurado.");
            return null;
        }

        try {
            // 1. Obtener la URL de descarga desde Graph API
            $urlGraph = "https://graph.facebook.com/v25.0/{$mediaId}";
            
            $ch = curl_init($urlGraph);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->token}"
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode((string)$response, true);
            if (!isset($data['url'])) {
                \App\Core\Logger::error("WhatsAppMediaService: No se obtuvo URL para el media {$mediaId}", $data ?? []);
                return null;
            }

            $mediaUrl = $data['url'];
            $mimeType = $data['mime_type'] ?? 'image/jpeg';
            $extension = $this->getExtensionFromMime($mimeType);

            // 2. Descargar los bytes de la imagen
            $ch2 = curl_init($mediaUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->token}"
            ]);
            $imageBytes = curl_exec($ch2);
            $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);

            if ($httpCode !== 200 || !$imageBytes) {
                \App\Core\Logger::error("WhatsAppMediaService: Error al descargar imagen de {$mediaUrl} (HTTP {$httpCode})");
                return null;
            }

            // 3. Guardar en disco
            // Evitar carpetas extrañas
            $carpetaDestino = ($tipoTramite === 'reclamos') ? 'reclamos' : 'reconexiones';
            
            // Usar un prefijo de archivo para saber qué es
            $prefijoArchivo = ($tipoTramite === 'reclamos') ? 'reclamo' : 'reconexion';
            
            $fileName = "{$prefijoArchivo}_{$codigoSocio}_" . time() . "_{$mediaId}.{$extension}";
            
            $uploadDir = __DIR__ . '/../../../public/uploads/' . $carpetaDestino;
            
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . '/' . $fileName;
            $saved = @file_put_contents($filePath, $imageBytes);

            if ($saved === false) {
                \App\Core\Logger::error("WhatsAppMediaService: Permiso denegado o error al guardar en {$filePath}");
                return null;
            }

            // Retornamos la ruta pública
            return "/uploads/{$carpetaDestino}/{$fileName}";

        } catch (Exception $e) {
            \App\Core\Logger::error("WhatsAppMediaService::descargarYGuardar error", ['exception' => $e->getMessage()]);
            return null;
        }
    }

    private function getExtensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];
        return $map[$mimeType] ?? 'jpg';
    }
}
