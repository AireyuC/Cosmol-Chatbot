<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/** Genera los payloads JSON de WhatsApp relacionados al módulo de Reclamos */
class PlantillaReclamos
{
    public static function menuReclamos(?string $mensajePersonalizado = null): array
    {
        $mensaje = $mensajePersonalizado ?? 'Por favor, selecciona el tipo de reclamo o emergencia que deseas reportar:';
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
                'id' => 'RECLAMO_ESTADO',
                'title' => 'Consultar estado',
                'description' => 'Ver estado de un reclamo previo'
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
}
