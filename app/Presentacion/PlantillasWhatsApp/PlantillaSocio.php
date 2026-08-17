<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados a Saludos y Menú Principal del Socio.
 */
class PlantillaSocio
{
    /**
     * Retorna el mensaje de saludo inicial cuando no hay sesión.
     */
    public static function saludo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => '¡Hola! Bienvenido al Chatbot de COSMOL 💧. Por favor, escribe únicamente tu *Código Fijo de Socio* (solo números) para poder consultar tus datos y deudas.'
            ]
        ];
    }

    /**
     * Retorna el menú principal interactivo cuando el socio fue validado.
     */
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
                'title' => 'Pagar Deuda',
                'description' => 'Consultar y pagar tus facturas'
            ];
        }

        $rows[] = [
            'id' => 'MENU_AGENTE',
            'title' => 'Consultar con un agente',
            'description' => 'Soporte y registro de reclamos'
        ];

        $rows[] = [
            'id' => 'MENU_CAMBIAR_CODIGO',
            'title' => 'Consultar otro Socio',
            'description' => 'Ingresar un código fijo diferente'
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

    /**
     * Retorna el mensaje con el enlace al agente humano y los botones de volver/cancelar.
     */
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
