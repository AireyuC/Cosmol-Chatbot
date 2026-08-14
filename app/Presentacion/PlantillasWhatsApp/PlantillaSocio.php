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
    public static function menuPrincipal(string $codSocio, string $nombreSocio): array
    {
        $mensaje = "Su Código Fijo $codSocio ($nombreSocio) ha sido validado.\n\n¿En qué puedo ayudarle? Por favor, haga clic en Mostrar Menú.";

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
                            'rows' => [
                                [
                                    'id' => 'MENU_PAGAR_' . $codSocio,
                                    'title' => 'Pagar Deuda',
                                    'description' => 'Consultar y pagar tus facturas'
                                ],
                                [
                                    'id' => 'MENU_CAMBIAR_CODIGO',
                                    'title' => 'Consultar otro Socio',
                                    'description' => 'Ingresar un código fijo diferente'
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
