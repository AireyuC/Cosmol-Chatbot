<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados a Mensajes de Sistema (Errores, Validaciones, Bloqueos).
 */
class PlantillaSistema
{
    /**
     * Retorna el mensaje cuando el socio no fue encontrado o el código es inválido.
     */
    public static function codigoInvalido(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'No se encontró un asociado con el código proporcionado. Por favor, verifica el código e inténtalo de nuevo.'
            ]
        ];
    }

    /**
     * Retorna el mensaje cuando la sesión del usuario ha sido bloqueada temporalmente por intentos fallidos.
     */
    public static function bloqueado(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Has excedido el número máximo de intentos. Tu cuenta está temporalmente bloqueada por 5 minutos. Por favor, intenta más tarde.'
            ]
        ];
    }

    /**
     * Retorna el mensaje cuando se desbloquea al usuario.
     */
    public static function desbloqueado(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Tu bloqueo ha expirado. Por favor, envía tu código de socio.'
            ]
        ];
    }

    /**
     * Retorna el mensaje de sesión expirada.
     */
    public static function sesionExpirada(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Sesión expirada por inactividad. Por favor, envía tu código de socio.'
            ]
        ];
    }

    /**
     * Retorna el mensaje de opción inválida en el menú principal.
     */
    public static function opcionInvalida(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Opción inválida. Por favor usa los botones del menú.'
            ]
        ];
    }

    /**
     * Retorna un texto simple genérico.
     */
    public static function textoSimple(string $mensaje): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => $mensaje
            ]
        ];
    }
}
