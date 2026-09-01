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

        $rows[] = [
            'id' => 'MENU_CERRAR_SESION',
            'title' => 'Cerrar Sesión',
            'description' => 'Finalizar la atención y salir'
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
        $numeroAgente = "59161555507";
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

    public static function solicitarGpsReconexion(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "📍 *Solicitud de Reconexión*\n\nPor favor, adjunte su ubicación actual mediante la función de *Enviar Ubicación* de WhatsApp para que nuestros técnicos puedan llegar.\n(No escriba texto, use el icono de adjuntar 📎 o ➕)."
            ]
        ];
    }

    public static function menuTipoReconexion(): array
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
                    'text' => 'Ubicación recibida correctamente. ✅\n\nPor favor, seleccione el motivo o tipo de reconexión:'
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

    public static function solicitarGlosaReconexion(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "📝 *Último paso*\n\nPor favor escriba una breve observación o referencia para ayudar a los técnicos a encontrar el lugar (Ej: 'Casa de rejas negras' o 'Ninguna')."
            ]
        ];
    }

    public static function mensajeTextoSimple(string $mensaje): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => $mensaje
            ]
        ];
    }
}
