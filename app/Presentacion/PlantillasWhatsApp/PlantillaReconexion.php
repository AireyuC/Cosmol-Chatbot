<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados al módulo de Reconexiones.
 */
class PlantillaReconexion
{
    /**
     * Pregunta si la reconexión es en el domicilio registrado del medidor o mediante GPS.
     */
    public static function preguntaTipoUbicacion(string $direccion = ''): array
    {
        $detalleDireccion = !empty($direccion) ? "\n🏠 *Domicilio Registrado:* {$direccion}\n" : '';

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => 'Ubicación de Reconexión'
                ],
                'body' => [
                    'text' => "📍 *Solicitud de Reconexión*\n\n¿La reconexión debe realizarse en la dirección registrada de su medidor o en otra ubicación?\n{$detalleDireccion}\nSi el suministro está en otra dirección o no tiene numeración, seleccione 'Otra Ubic. (GPS)'."
                ],
                'footer' => [
                    'text' => 'Paso 1 de 3'
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECONEXION_UBICACION_DOMICILIO',
                                'title' => '🏠 Mi Domicilio'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECONEXION_UBICACION_GPS',
                                'title' => '📍 Otra Ubic. (GPS)'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Solicita al usuario que envíe su ubicación GPS.
     */
    public static function solicitarGps(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "📍 *Solicitud de Reconexión*\n\nPor favor, adjunte su ubicación actual mediante la función de *Enviar Ubicación* de WhatsApp para que nuestros técnicos puedan llegar.\n(No escriba texto, use el icono de adjuntar 📎 o ➕)."
            ]
        ];
    }

    /**
     * Muestra la lista interactiva de tipos/motivos de reconexión.
     */
    public static function menuTipo(): array
    {
        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'Tipo de Reconexión'
                ],
                'body' => [
                    'text' => "Ubicación registrada correctamente. ✅\n\nPor favor, seleccione el motivo o tipo de reconexión:"
                ],
                'footer' => [
                    'text' => 'Paso 2 de 3'
                ],
                'action' => [
                    'button' => 'Seleccionar Tipo',
                    'sections' => [
                        [
                            'title' => 'Opciones',
                            'rows' => [
                                [
                                    'id' => 'RECONEXION_TIPO_1',
                                    'title' => 'Corte normal',
                                    'description' => 'Sin retiro de medidor'
                                ],
                                [
                                    'id' => 'RECONEXION_TIPO_2',
                                    'title' => 'Con medidor',
                                    'description' => 'Retiro de medidor'
                                ],
                                [
                                    'id' => 'RECONEXION_TIPO_3',
                                    'title' => 'Con material',
                                    'description' => 'Falta de accesorios'
                                ],
                                [
                                    'id' => 'RECONEXION_TIPO_4',
                                    'title' => 'Otros',
                                    'description' => 'Otro motivo'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Solicita la fotografía de evidencia para el trámite de reconexión (conservado por compatibilidad).
     */
    public static function solicitarFoto(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "📸 *Fotografía requerida*\n\nPor favor, envíe una foto del medidor o del lugar donde se requiere la reconexión usando el icono de la cámara o galería de WhatsApp."
            ]
        ];
    }

    /**
     * Solicita la observación o glosa descriptiva.
     */
    public static function solicitarGlosa(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "📝 *Paso final (3 de 3) — Referencia*\n\nPor favor, escribe una breve indicación o punto de referencia para ayudar al equipo técnico a ubicar rápidamente tu medidor (ej: _'Casa con rejas negras frente a la plaza'_ o escribe _'Ninguna'_)."
            ]
        ];
    }

    /**
     * Mensaje de éxito al registrar la solicitud de reconexión.
     */
    public static function confirmacionExitosa(string $ticket): string
    {
        return "✅ *¡Solicitud de reconexión programada!*\n🎫 Ticket de trámite: *#{$ticket}*\n\nLa cuadrilla técnica ha recibido tu solicitud para realizar la reconexión de tu servicio de agua potable. ¡Estaremos atendiéndote a la brevedad posible! 💧";
    }

    /**
     * Mensaje informativo cuando ya existe una solicitud previa pendiente.
     */
    public static function reconexionPendiente(): string
    {
        return "⏳ *Solicitud en curso*\n\nEstimado asociado, tu solicitud de reconexión ya se encuentra registrada y asignada a nuestras cuadrillas técnicas. Nuestro personal acudirá a la brevedad posible para restablecer tu servicio.";
    }

    /**
     * Mensaje de rechazo cuando el socio tiene más facturas de las permitidas (máximo 1 factura).
     */
    public static function deudaExcedida(int $cantidadDeudas): string
    {
        return "⚠️ *Regularización de cuenta requerida*\n\nEstimado asociado, registramos *{$cantidadDeudas} facturas pendientes* en tu cuenta. Para procesar una orden de reconexión, es necesario tener como máximo 1 factura pendiente.\n\nPor favor, realiza tu pago mediante la opción *Consultar Deuda* del menú principal para que podamos programar tu reconexión a la brevedad. ¡Estamos para servirte! 🤝";
    }

    /**
     * Mensaje de advertencia cuando se excede el límite de 1 reconexión por día.
     */
    public static function advertenciaLimiteReconexiones(): string
    {
        return "⚠️ *Límite diario de reconexiones alcanzado*\n\nEstimado asociado, esta cuenta ya cuenta con *1 solicitud de reconexión registrada hoy* (límite máximo diario permitido).\n\nSu trámite ya ha sido ingresado a nuestro sistema operativo y la cuadrilla técnica lo tiene en agenda. Por favor, espere la atención en el transcurso del día.";
    }

    /**
     * Mensaje de advertencia cuando se intenta solicitar reconexiones consecutivas antes de que expire el cooldown en segundos.
     */
    public static function advertenciaCooldownReconexion(int $segundosRestantes): string
    {
        return "⏳ *Por favor, aguarde unos momentos*\n\nSe acaba de registrar una interacción técnica reciente. Para evitar duplicidades en el sistema de cuadrillas, por favor espere *{$segundosRestantes} segundos* antes de volver a solicitar una reconexión.";
    }
}
