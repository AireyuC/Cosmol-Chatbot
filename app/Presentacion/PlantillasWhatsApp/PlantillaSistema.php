<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/** Genera los payloads JSON de WhatsApp relacionados a Mensajes de Sistema (Errores, Validaciones, Bloqueos) */
class PlantillaSistema
{
    public static function codigoInvalido(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'No se encontró un asociado con el código proporcionado. Por favor, verifica el código e inténtalo de nuevo.'
            ]
        ];
    }

    public static function bloqueado(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Has excedido el número máximo de intentos. Tu cuenta está temporalmente bloqueada por 5 minutos. Por favor, intenta más tarde.'
            ]
        ];
    }

    public static function desbloqueado(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Tu bloqueo ha expirado. Por favor, envía tu código de socio.'
            ]
        ];
    }

    public static function sesionExpirada(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Sesión expirada por inactividad. Por favor, envía tu código de socio.'
            ]
        ];
    }

    public static function opcionInvalida(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => 'Opción inválida. Por favor usa los botones del menú.'
            ]
        ];
    }

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
