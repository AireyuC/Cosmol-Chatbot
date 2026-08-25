<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

class PlantillaSocio
{
    public static function saludo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "Bienvenido a COSMOL R.L.\nGracias por comunicarse con nosotros.\nDigite su Código de asociado:"
            ]
        ];
    }

    public static function menuPrincipal(string $codSocio, string $nombreSocio = '', bool $esError = false, ?string $mensajePersonalizado = null, bool $ocultarPagar = false): array
    {
        if ($mensajePersonalizado !== null) {
            $mensaje = $mensajePersonalizado;
        } elseif ($esError) {
            $mensaje = "Opción inválida. Por favor usa los botones del menú 👇";
        } else {
            $mensaje = "Su Código Fijo $codSocio ($nombreSocio) ha sido validado.\n\n¿En qué puedo ayudarle? Por favor, haga clic en Mostrar Menú.";
        }

        $rows = [];
        
        if (!$ocultarPagar) {
            $rows[] = [
                'id' => 'MENU_PAGAR_' . $codSocio,
                'title' => 'Consultar Deuda',
                'description' => 'Ver y pagar facturas pendientes'
            ];
        }

        $rows[] = [
            'id' => 'MENU_RECONEXION',
            'title' => 'Solicitar Reconexión',
            'description' => 'Solicita reconexión de servicio'
        ];

        $rows[] = [
            'id' => 'MENU_HISTORIAL',
            'title' => 'Historial',
            'description' => 'Historial de pagos y consumos'
        ];

        $rows[] = [
            'id' => 'MENU_RECLAMOS',
            'title' => 'Reclamos',
            'description' => 'Reporta emergencias y reclamos'
        ];

        $rows[] = [
            'id' => 'MENU_OFICINAS',
            'title' => 'Oficinas y horarios',
            'description' => 'Información de atención'
        ];

        $rows[] = [
            'id' => 'MENU_AGENTE',
            'title' => 'Hablar con un asesor',
            'description' => 'Soporte personalizado'
        ];

        $rows[] = [
            'id' => 'MENU_CAMBIAR_CODIGO',
            'title' => 'Consultar otro Socio',
            'description' => 'Ingresar un código diferente'
        ];

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'Menú Principal'
                ],
                'body' => [
                    'text' => $mensaje
                ],
                'footer' => [
                    'text' => 'COSMOL - Tu cooperativa'
                ],
                'action' => [
                    'button' => 'Mostrar Menú',
                    'sections' => [
                        [
                            'title' => 'Opciones',
                            'rows' => $rows
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function redireccionAgente(): array
    {
        $numeroAgente = "59170003204";
        $mensaje = "Para contactarte con nuestro equipo de Atención al Cliente, registrar un reclamo o solicitar un servicio, por favor haz clic en el siguiente enlace:\n\nhttps://wa.me/" . $numeroAgente . "\n\nSerás atendido por un agente de COSMOL a la brevedad posible.";

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $mensaje
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'MENU_PRINCIPAL_VOLVER',
                                'title' => 'Volver al Menú'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
