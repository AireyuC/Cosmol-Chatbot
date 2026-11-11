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
                'body' => "🔍 No logramos encontrar una cuenta con ese número.\n\nPor favor, revisa tu factura o aviso de cobranza e ingresa nuevamente tu *Código Fijo* (solo números, ej: 10245)."
            ]
        ];
    }

    public static function bloqueado(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "🔒 *Protección de seguridad temporal*\n\nPor seguridad de nuestros asociados, la atención se ha pausado por *5 minutos* tras varios intentos seguidos.\n\nTe pedimos aguardar este breve momento para volver a intentar. ¡Gracias por tu comprensión! 🤝"
            ]
        ];
    }

    public static function desbloqueado(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "✅ *¡Ya puedes ingresar de nuevo!*\n\nEl tiempo de espera ha finalizado. Por favor, escribe tu *Código Fijo* para iniciar tu consulta."
            ]
        ];
    }

    public static function sesionExpirada(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "⏱️ *Sesión cerrada por inactividad*\n\nPor tu seguridad, cerramos la sesión tras unos minutos sin interacción.\n\nSi deseas realizar otra consulta o trámite, solo escribe nuevamente tu *Código Fijo* y con gusto te atenderemos."
            ]
        ];
    }

    public static function opcionInvalida(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "🤖 No logré comprender esa opción.\n\nPor favor, utiliza los botones o el menú interactivo que aparece en pantalla 👇"
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

    /**
     * Mensaje de advertencia cuando se excede el límite de códigos de socio distintos consultados hoy desde un mismo número.
     */
    public static function advertenciaLimiteCuentasPorTelefono(int $limite = 5): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "⚠️ *Límite diario de cuentas alcanzado*\n\nEstimado asociado, desde este número de WhatsApp ya se han consultado *{$limite} códigos de socio distintos hoy* (límite máximo permitido por día).\n\nSi necesita realizar consultas o trámites para más medidores, por favor intente nuevamente mañana o apersónese a nuestras oficinas de atención al cliente."
            ]
        ];
    }

    /**
     * Mensaje informativo que advierte que el sistema está en desarrollo y entorno de pruebas.
     */
    public static function avisoSistemaEnDesarrollo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "⚠️ *AVISO IMPORTANTE — ENTORNO DE PRUEBAS* ⚠️\n\n" .
                          "Estimado socio, este canal de atención por WhatsApp se encuentra en *fase de desarrollo y pruebas piloto*.\n\n" .
                          "Las consultas, solicitudes o trámites simulados realizados en este chat *NO se registrarán ni afectarán el sistema central de COSMOL R.L.*\n\n" .
                          "Agradecemos tu comprensión y colaboración para evaluar este nuevo servicio digital. 🤝"
            ]
        ];
    }
}

