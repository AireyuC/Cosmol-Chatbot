<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados al módulo de Reconexiones.
 * Formato unificado por pasos consecutivos con redacción institucional limpia.
 */
class PlantillaReconexion
{
    /**
     * Paso 1 de 4: Pregunta de ubicación inicial y anticipación de los pasos del trámite.
     */
    public static function preguntaTipoUbicacion(string $direccion = ''): array
    {
        $detalleDireccion = !empty($direccion) ? "\n• Dirección registrada: {$direccion}\n" : '';

        $bodyText = "*Solicitud de Reconexión (Paso 1 de 4)*\n\n" .
                    "Para programar la reconexión de tu servicio completaremos 4 pasos sencillos:\n" .
                    "1. Confirmación de ubicación\n" .
                    "2. Tipo de reconexión\n" .
                    "3. Fotografía del medidor\n" .
                    "4. Referencias de la vivienda\n\n" .
                    "¿El servicio se encuentra en la dirección registrada de tu medidor o en otra ubicación?\n" .
                    "{$detalleDireccion}\n" .
                    "Selecciona una opción:";

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'text',
                    'text' => 'Reconexión de Servicio'
                ],
                'body' => [
                    'text' => $bodyText
                ],
                'footer' => [
                    'text' => 'Paso 1 de 4 — Ubicación'
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECONEXION_UBICACION_DOMICILIO',
                                'title' => 'Mi Domicilio'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'RECONEXION_UBICACION_GPS',
                                'title' => 'Otra Ubic. (GPS)'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Paso 1 de 4 (Alternativo): Solicita al usuario que envíe su ubicación GPS.
     */
    public static function solicitarGps(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "*Solicitud de Reconexión (Paso 1 de 4)*\n\n" .
                          "Ubicación por GPS:\n\n" .
                          "Por favor, envía tu ubicación actual mediante la función de \"Ubicación\" de WhatsApp (icono de adjuntar 📎 o ➕)."
            ]
        ];
    }

    /**
     * Paso 2 de 4: Muestra la lista interactiva de tipos/motivos de reconexión.
     */
    public static function menuTipo(): array
    {
        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'Reconexión de Servicio'
                ],
                'body' => [
                    'text' => "*Solicitud de Reconexión (Paso 2 de 4)*\n\n" .
                              "Ubicación registrada correctamente.\n\n" .
                              "Por favor, selecciona el tipo o motivo de corte de tu servicio:"
                ],
                'footer' => [
                    'text' => 'Paso 2 de 4 — Tipo de Corte'
                ],
                'action' => [
                    'button' => 'Seleccionar Tipo',
                    'sections' => [
                        [
                            'title' => 'Opciones de Reconexión',
                            'rows' => [
                                [
                                    'id' => 'RECONEXION_TIPO_1',
                                    'title' => 'Corte normal',
                                    'description' => 'Sin retiro de medidor'
                                ],
                                [
                                    'id' => 'RECONEXION_TIPO_2',
                                    'title' => 'Con medidor',
                                    'description' => 'Con retiro de medidor'
                                ],
                                [
                                    'id' => 'RECONEXION_TIPO_3',
                                    'title' => 'Con material',
                                    'description' => 'Falta de accesorios o llave'
                                ],
                                [
                                    'id' => 'RECONEXION_TIPO_4',
                                    'title' => 'Otros',
                                    'description' => 'Otro motivo de corte'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Paso 3 de 4: Solicita la fotografía obligatoria de evidencia.
     */
    public static function solicitarFoto(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "*Solicitud de Reconexión (Paso 3 de 4)*\n\n" .
                          "Tipo de reconexión registrado correctamente.\n\n" .
                          "Por favor, envía una fotografía clara del medidor o de la caja de corte.\n\n" .
                          "La fotografía es obligatoria para verificar las condiciones técnicas del suministro antes de la visita de la cuadrilla."
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
                          "Para continuar con la reconexión es obligatorio adjuntar una fotografía del medidor o de la caja de corte.\n\n" .
                          "Por favor, toma una fotografía o selecciónala desde la galería de WhatsApp para continuar."
            ]
        ];
    }

    /**
     * Paso 4 de 4: Solicita la descripción y referencias de la vivienda.
     */
    public static function solicitarGlosa(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "*Solicitud de Reconexión (Paso 4 de 4)*\n\n" .
                          "Fotografía recibida correctamente.\n\n" .
                          "Como último paso, escribe una breve descripción de tu vivienda y puntos de referencia para facilitar la llegada del personal técnico.\n\n" .
                          "(Ejemplo: \"Casa de dos pisos color crema, portón negro, frente a la tienda Doña María\")."
            ]
        ];
    }

    /**
     * Mensaje de éxito al registrar la solicitud de reconexión.
     */
    public static function confirmacionExitosa(string $ticket): string
    {
        return "*Solicitud de Reconexión Programada*\n\n" .
               "Ticket de trámite: *#{$ticket}*\n\n" .
               "Tu solicitud ha sido registrada exitosamente. La cuadrilla técnica de COSMOL R.L. programará la visita a la brevedad posible para restablecer tu servicio de agua potable.";
    }

    /**
     * Mensaje informativo cuando ya existe una solicitud de reconexión activa en curso.
     */
    public static function reconexionPendiente(): string
    {
        return "*Solicitud de Reconexión Activa*\n\n" .
               "Estimado asociado, tu cuenta ya cuenta con una solicitud de reconexión en curso asignada a nuestras cuadrillas técnicas.\n\n" .
               "Por normativa operativa, no es posible generar una nueva orden de reconexión hasta que la cuadrilla complete la atención de la solicitud pendiente.\n\n" .
               "Puedes consultar el avance de tu caso en la opción \"Estado de Solicitudes\" del menú principal o acudir a la Oficina de Reconexiones en nuestras oficinas centrales (Calle Isaías Parada).";
    }

    /**
     * Mensaje de rechazo cuando el socio tiene más facturas de las permitidas (máximo 1 factura).
     */
    public static function deudaExcedida(int $cantidadDeudas): string
    {
        return "*Regularización de cuenta requerida*\n\n" .
               "Estimado asociado, registramos *{$cantidadDeudas} facturas pendientes* en tu cuenta. Para procesar una orden de reconexión, es necesario tener como máximo 1 factura pendiente.\n\n" .
               "Puedes realizar el pago de tus facturas mediante la opción *Consultar Deuda* del menú principal.\n\n" .
               "Si requieres atención personalizada, te invitamos a visitar la Oficina de Reconexiones / Comercial en COSMOL R.L. (Calle Isaías Parada).";
    }

    /**
     * Mensaje de error cuando ocurre un inconveniente técnico al procesar el trámite.
     */
    public static function errorProcesamiento(): string
    {
        return "*Inconveniente al procesar tu solicitud*\n\n" .
               "Ocurrió un inconveniente al procesar tu solicitud de reconexión de forma automática por este canal.\n\n" .
               "Por favor, te invitamos a visitar la Oficina de Reconexiones en COSMOL R.L. (Calle Isaías Parada, entre Santa Cruz y Ballivián) para que nuestro personal registre tu trámite de forma manual e inmediata.\n\n" .
               "Horarios de Atención:\n" .
               "Lunes a Viernes: 08:00 - 12:00 | 14:00 - 18:00";
    }

    /**
     * Mensaje de advertencia cuando se excede el límite de 1 reconexión por día.
     */
    public static function advertenciaLimiteReconexiones(): string
    {
        return "*Límite diario de reconexiones alcanzado*\n\n" .
               "Estimado asociado, esta cuenta ya cuenta con *1 solicitud de reconexión registrada hoy* (límite máximo diario permitido).\n\n" .
               "Tu trámite ya ha sido ingresado al sistema operativo y la cuadrilla técnica lo tiene en agenda. Por favor, espera la atención en el transcurso del día.";
    }

    /**
     * Mensaje de advertencia cuando se intenta solicitar reconexiones consecutivas antes de que expire el cooldown en segundos.
     */
    public static function advertenciaCooldownReconexion(int $segundosRestantes): string
    {
        return "*Por favor, aguarda unos momentos*\n\n" .
               "Se acaba de registrar una interacción técnica reciente. Para evitar duplicidades en el sistema de cuadrillas, por favor espera *{$segundosRestantes} segundos* antes de volver a solicitar una reconexión.";
    }
}
