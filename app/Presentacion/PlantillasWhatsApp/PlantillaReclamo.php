<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados al módulo de Reclamos.
 */
class PlantillaReclamo
{
    /**
     * Menú interactivo con lista de tipos de reclamos.
     */
    public static function menuReclamos(?string $mensajePersonalizado = null): array
    {
        $mensaje = $mensajePersonalizado ?? "🛠️ *Atención de Reclamos y Emergencias*\n\nPor favor, selecciona en la lista de opciones 👇 el tipo de situación que deseas reportar:";
        $rows = [
            [
                'id' => 'RECLAMO_AGUA_TURBIA',
                'title' => 'Agua Turbia',
                'description' => 'Reportar agua con sedimentos o color'
            ],
            [
                'id' => 'RECLAMO_FUGA',
                'title' => 'Fuga de Agua',
                'description' => 'Reportar fuga en calle o acera'
            ],
            [
                'id' => 'RECLAMO_REBALSE',
                'title' => 'Rebalse Alcantarillado',
                'description' => 'Reportar aguas servidas en la calle'
            ],
            [
                'id' => 'RECLAMO_TRANCADO',
                'title' => 'Alcantarilla Trancada',
                'description' => 'Reportar obstrucción en la red'
            ],
            [
                'id' => 'MENU_PRINCIPAL_VOLVER',
                'title' => 'Volver',
                'description' => 'Regresar al menú principal'
            ]
        ];

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'Módulo de Reclamos'
                ],
                'body' => [
                    'text' => $mensaje
                ],
                'footer' => [
                    'text' => 'COSMOL - Tu cooperativa'
                ],
                'action' => [
                    'button' => 'Opciones de Reclamo',
                    'sections' => [
                        [
                            'title' => 'Tipos de Reclamos',
                            'rows' => $rows
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Solicita la ubicación GPS para enviar al técnico.
     */
    public static function solicitarGpsReclamo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "📍 *Ubicación del problema*\n\nPara enviar a la cuadrilla técnica al punto exacto, por favor comparte tu ubicación tocando el ícono de **Adjuntar** (📎 o ➕) y seleccionando **Ubicación**.",
                'preview_url' => false
            ]
        ];
    }

    /**
     * Solicita una fotografía del daño o problema.
     */
    public static function solicitarFotoReclamo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "📸 *Fotografía de evidencia*\n\n¡Muy bien! Ahora envíanos una **fotografía clara** del daño o fuga para que el equipo técnico acuda con las herramientas y repuestos adecuados.",
                'preview_url' => false
            ]
        ];
    }

    /**
     * Solicita una glosa descriptiva del reclamo.
     */
    public static function solicitarGlosaReclamo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "✍️ *Detalle o referencia del lugar*\n\nPor último, escribe un breve mensaje contándonos qué sucede o danos una referencia (ej: _'Fuga sobre la acera frente al portón blanco'_).",
                'preview_url' => false
            ]
        ];
    }

    /**
     * Pregunta si el reclamo es en su domicilio registrado o mediante GPS.
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
                    'text' => 'Ubicación del Reclamo'
                ],
                'body' => [
                    'text' => "¿El problema se encuentra en la dirección registrada de su medidor (en su casa) o en otra ubicación?\n{$detalleDireccion}\n⚠️ *Aviso:* Si la falla no está en su domicilio, elija 'Otra Ubic. (GPS)' para que la cuadrilla técnica acuda al punto exacto."
                ],
                'footer' => [
                    'text' => 'COSMOL R.L.'
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECLAMO_UBICACION_DOMICILIO',
                                'title' => '🏠 Mi Domicilio'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECLAMO_UBICACION_GPS',
                                'title' => '📍 Otra Ubic. (GPS)'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Mensaje de advertencia cuando se excede el límite diario de reclamos por código de socio.
     */
    public static function advertenciaLimiteReclamos(int $limite = 3): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "⚠️ *Límite diario por asociado alcanzado*\n\nEstimado asociado, esta cuenta ha alcanzado el límite máximo de *{$limite} reclamos por día*.\n\nSus solicitudes previas ya se encuentran en proceso de revisión técnica por las cuadrillas de COSMOL. Si se trata de una emergencia mayor en la red pública, por favor comuníquese directamente con nuestra línea de guardia.",
                'preview_url' => false
            ]
        ];
    }

    /**
     * Mensaje de advertencia cuando se excede el límite diario global de reclamos desde un mismo teléfono.
     */
    public static function advertenciaLimiteGlobalTelefono(int $limiteGlobal = 6): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "⚠️ *Límite diario por número alcanzado*\n\nEstimado usuario, desde este número de WhatsApp se ha alcanzado el tope máximo de *{$limiteGlobal} reclamos diarios* entre todas las cuentas gestionadas hoy.\n\nLas cuadrillas ya cuentan con sus solicitudes en agenda. Por favor, aguarde la atención de los reportes enviados.",
                'preview_url' => false
            ]
        ];
    }

    /**
     * Mensaje de advertencia cuando se intenta enviar reclamos demasiado rápido (cooldown anti-spam en segundos).
     */
    public static function advertenciaCooldownReclamo(int $segundosRestantes): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "⏳ *Por favor, aguarde unos instantes*\n\nAcabamos de registrar una solicitud. Para evitar reportes duplicados en nuestro sistema operativo, por favor espere *{$segundosRestantes} segundos* antes de enviar un nuevo reclamo.",
                'preview_url' => false
            ]
        ];
    }

    /**
     * Mensaje de confirmación al registrar el ticket de reclamo.
     */
    public static function confirmacionExitosa(string $ticket): string
    {
        return "✅ *¡Tu reporte ha sido registrado con éxito!*\n🎫 Ticket de seguimiento: *#{$ticket}*\n\nNuestras cuadrillas técnicas ya lo tienen en agenda para su atención oportuna. ¡Muchas gracias por ayudarnos a cuidar el agua en Montero! 💧";
    }
}

