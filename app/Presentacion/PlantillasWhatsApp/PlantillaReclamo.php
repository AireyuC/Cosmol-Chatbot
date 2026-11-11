<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados al módulo de Reclamos.
 * Formato unificado por pasos consecutivos con redacción institucional limpia.
 */
class PlantillaReclamo
{
    /**
     * Paso 1 de 4: Menú interactivo con lista de tipos de reclamos y anticipación de pasos.
     */
    public static function menuReclamos(?string $mensajePersonalizado = null): array
    {
        $mensaje = $mensajePersonalizado ?? "*Atención de Reclamos (Paso 1 de 4)*\n\n" .
                                            "Para registrar y derivar tu reporte a la cuadrilla técnica completaremos 4 pasos sencillos:\n" .
                                            "1. Tipo de reclamo\n" .
                                            "2. Confirmación de ubicación\n" .
                                            "3. Fotografía de evidencia\n" .
                                            "4. Detalle y referencias\n\n" .
                                            "Por favor, selecciona en la lista de opciones el tipo de situación que deseas reportar:";

        $rows = [
            [
                'id' => 'RECLAMO_AGUA_TURBIA',
                'title' => 'Agua Turbia',
                'description' => 'Sedimentos o color en el suministro'
            ],
            [
                'id' => 'RECLAMO_FUGA',
                'title' => 'Fuga de Agua',
                'description' => 'Fuga en calle, acera o nicho'
            ],
            [
                'id' => 'RECLAMO_REBALSE',
                'title' => 'Rebalse Alcantarillado',
                'description' => 'Aguas servidas en la calzada'
            ],
            [
                'id' => 'RECLAMO_TRANCADO',
                'title' => 'Alcantarilla Trancada',
                'description' => 'Obstrucción en red de desagüe'
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
                    'text' => 'Atención de Reclamos'
                ],
                'body' => [
                    'text' => $mensaje
                ],
                'footer' => [
                    'text' => 'Paso 1 de 4 — Tipo de Reclamo'
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
     * Paso 2 de 4: Pregunta si el reclamo es en su domicilio registrado o mediante GPS.
     */
    public static function preguntaTipoUbicacion(string $direccion = ''): array
    {
        $detalleDireccion = !empty($direccion) ? "\n• Dirección registrada: {$direccion}\n" : '';

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => 'Atención de Reclamos'
                ],
                'body' => [
                    'text' => "*Atención de Reclamos (Paso 2 de 4)*\n\n" .
                              "¿El problema se encuentra en la dirección registrada de tu medidor o en otra ubicación?\n" .
                              "{$detalleDireccion}\n" .
                              "Selecciona una opción:"
                ],
                'footer' => [
                    'text' => 'Paso 2 de 4 — Ubicación'
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECLAMO_UBICACION_DOMICILIO',
                                'title' => 'Mi Domicilio'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECLAMO_UBICACION_GPS',
                                'title' => 'Otra Ubic. (GPS)'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Paso 2 de 4 (Alternativo): Solicita la ubicación GPS para enviar al técnico.
     */
    public static function solicitarGpsReclamo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "*Atención de Reclamos (Paso 2 de 4)*\n\n" .
                          "Ubicación por GPS:\n\n" .
                          "Por favor, envía tu ubicación actual mediante la función \"Ubicación\" de WhatsApp (icono de adjuntar 📎 o ➕)."
            ]
        ];
    }

    /**
     * Paso 3 de 4: Solicita la fotografía obligatoria del problema.
     */
    public static function solicitarFotoReclamo(string $mensajeExtra = ''): array
    {
        $extra = !empty($mensajeExtra) ? "\n{$mensajeExtra}\n" : '';

        return [
            'type' => 'text',
            'text' => [
                'body' => "*Atención de Reclamos (Paso 3 de 4)*\n\n" .
                          "Ubicación registrada correctamente.\n" .
                          "{$extra}\n" .
                          "Por favor, envía una fotografía clara del problema (fuga, medidor o alcantarilla).\n\n" .
                          "La fotografía es obligatoria para que el personal técnico acuda con los repuestos y herramientas adecuadas."
            ]
        ];
    }

    /**
     * Paso 3 de 4 (Recordatorio): Insistencia cuando el usuario envía texto en vez de imagen.
     */
    public static function fotoRequerida(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "*Fotografía requerida (Paso 3 de 4)*\n\n" .
                          "Para continuar con el registro del reclamo es obligatorio adjuntar una fotografía clara del problema.\n\n" .
                          "Por favor, toma una fotografía o selecciónala desde la galería de WhatsApp para continuar."
            ]
        ];
    }

    /**
     * Paso 4 de 4: Solicita la glosa descriptiva del reclamo.
     */
    public static function solicitarGlosaReclamo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "*Atención de Reclamos (Paso 4 de 4)*\n\n" .
                          "Fotografía recibida correctamente.\n\n" .
                          "Como último paso, escribe una breve descripción de lo sucedido y puntos de referencia de la vivienda para facilitar la llegada de la cuadrilla técnica.\n\n" .
                          "(Ejemplo: \"Fuga de agua potable sobre la acera, casa con portón blanco frente a la plaza\")."
            ]
        ];
    }

    /**
     * Mensaje de confirmación al registrar el ticket de reclamo.
     */
    public static function confirmacionExitosa(string $ticket): string
    {
        return "*Reporte de Reclamo Registrado*\n\n" .
               "Ticket de seguimiento: *#{$ticket}*\n\n" .
               "Tu reporte ha sido registrado exitosamente. Nuestras cuadrillas técnicas lo tienen en agenda para su atención oportuna. Muchas gracias por tu colaboración.";
    }

    /**
     * Mensaje de advertencia cuando se excede el límite diario de reclamos por código de socio.
     */
    public static function advertenciaLimiteReclamos(int $limite = 3): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "*Límite diario por asociado alcanzado*\n\n" .
                          "Estimado asociado, esta cuenta ha alcanzado el límite máximo de *{$limite} reclamos por día*.\n\n" .
                          "Tus solicitudes previas ya se encuentran en agenda técnica de COSMOL R.L. Si se trata de una emergencia mayor en la red pública, por favor comunícate directamente con nuestras oficinas centrales."
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
                'body' => "*Límite diario alcanzado*\n\n" .
                          "Estimado usuario, desde este número de WhatsApp se ha alcanzado el tope máximo de *{$limiteGlobal} reclamos diarios*.\n\n" .
                          "Nuestras cuadrillas técnicas ya cuentan con las solicitudes previas en agenda para su atención."
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
                'body' => "*Por favor, aguarda unos instantes*\n\n" .
                          "Acabamos de registrar una solicitud técnica. Para evitar reportes duplicados, por favor espera *{$segundosRestantes} segundos* antes de enviar un nuevo reclamo."
            ]
        ];
    }
}

