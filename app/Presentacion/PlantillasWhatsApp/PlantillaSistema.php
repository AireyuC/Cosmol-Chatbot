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

    /**
     * Genera el mensaje para el modo de mantenimiento global, avisando el límite de mensajes si alcanza el tope.
     */
    public static function mantenimientoGlobal(int $intento, int $maxIntentos, int $minutosEspera): array
    {
        $mensaje = "🛠️ *Mantenimiento Programado - COSMOL R.L.*\n\n" .
                   "Estimado asociado, en este momento nuestros canales de atención automática por WhatsApp se encuentran en mantenimiento para mejorar la calidad de nuestros servicios.\n\n" .
                   "🕒 Por favor, intente comunicarse más tarde.\n" .
                   "📍 Si se trata de una emergencia, puede acudir a nuestras oficinas centrales en Calle Isaias Parada.\n\n" .
                   "Disculpe las molestias ocasionadas.";

        if ($intento >= $maxIntentos) {
            $mensaje .= "\n\n⚠️ *Aviso de límite:* Ha alcanzado el límite de mensajes ({$maxIntentos}). Para evitar saturación, el sistema no responderá más mensajes durante los próximos {$minutosEspera} minutos.";
        }

        return self::textoSimple($mensaje);
    }

    /**
     * Mensaje de texto amigable para un módulo específico en mantenimiento.
     */
    public static function moduloEnMantenimiento(string $nombreModulo): string
    {
        return "🔧 El servicio de *{$nombreModulo}* se encuentra temporalmente en mantenimiento por mejoras en el sistema.\n\nPor favor, seleccione otra opción del menú 👇";
    }
}

