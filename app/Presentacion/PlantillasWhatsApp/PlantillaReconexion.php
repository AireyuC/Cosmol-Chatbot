<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados al módulo de Reconexiones.
 */
class PlantillaReconexion
{
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
                    'text' => "Ubicación recibida correctamente. ✅\n\nPor favor, seleccione el motivo o tipo de reconexión:"
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
     * Solicita la fotografía de evidencia para el trámite de reconexión.
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
                'body' => "📝 *Último paso*\n\nPor favor escriba una breve observación o referencia para ayudar a los técnicos a encontrar el lugar (Ej: 'Casa de rejas negras' o 'Ninguna')."
            ]
        ];
    }

    /**
     * Mensaje de éxito al registrar la solicitud de reconexión.
     */
    public static function confirmacionExitosa(string $ticket): string
    {
        return "✅ *Solicitud de Reconexión registrada exitosamente.*\nSu número de ticket es: *#{$ticket}*.\n\nNuestros técnicos se pondrán en contacto pronto.";
    }

    /**
     * Mensaje informativo cuando ya existe una solicitud previa pendiente.
     */
    public static function reconexionPendiente(): string
    {
        return "❌ Actualmente ya tiene una solicitud de reconexión *PENDIENTE*.\nNuestros técnicos atenderán su solicitud pronto. Por favor, espere.";
    }

    /**
     * Mensaje de rechazo cuando el socio tiene más de 2 facturas pendientes.
     */
    public static function deudaExcedida(int $cantidadDeudas): string
    {
        return "❌ Lo sentimos, no puede solicitar una reconexión porque tiene {$cantidadDeudas} facturas pendientes.\nPor favor, regularice su situación antes de realizar esta solicitud. \nPuede efectuar su deuda en *Consultar Deuda* en el siguiente menú.";
    }
}
