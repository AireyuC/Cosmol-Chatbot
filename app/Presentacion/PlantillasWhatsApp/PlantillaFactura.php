<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

use App\Core\FeatureFlags;

class PlantillaFactura
{
    public static function listaDeudas(string $codSocio, int $cantidadFacturas, float $totalRedondeado, array $listaDeudas): array
    {
        if ($cantidadFacturas > 0) {
            $totalFormateado = number_format($totalRedondeado, 2, ',', '.');
            $mensajeTexto = "🧾 *Estado de Cuenta - Facturas Pendientes*\n" .
                            "📌 Código Fijo: *{$codSocio}*\n" .
                            "⚠️ Facturas por regularizar: *{$cantidadFacturas}*\n" .
                            "💰 Total a pagar: *{$totalFormateado} Bs.*\n\n" .
                            "📋 *Detalle de periodos:*\n";
            $contador = 1;
            foreach ($listaDeudas as $d) {
                $montoF = number_format($d['monto'], 2, ',', '.');
                $mensajeTexto .= "{$contador}. Periodo: {$d['periodo']} — *{$montoF} Bs.* (Pendiente)\n";
                $contador++;
            }
            $mensajeTexto = trim($mensajeTexto);
            $mensajeTexto .= "\n\n💳 *Paga fácil y seguro desde tu celular aquí:*\n" .
                             "• *Multipago:*\nhttps://multipago.com/service/cosmol_payment/first\n" .
                             "• *Pago al Paso:*\nhttps://red.pagoalpaso247.net/servicio/cosmol\n\n" .
                             "¿Deseas realizar otra consulta? Toca el menú 👇";
        } else {
            $mensajeTexto = "🎉 *¡Excelentes noticias!* Tu cuenta (*{$codSocio}*) se encuentra *al día* y no registra facturas pendientes de pago. 💧\n\n¡Muchas gracias por tu puntualidad! 🤝\n\n¿En qué más podemos ayudarte? Toca el menú 👇";
        }
        return PlantillaSocio::menuPrincipal($codSocio, '', false, $mensajeTexto, true);
    }

    /**
     * Genera el Historial de Facturación con el formato limpio solicitado:
     * 1. 8/2026 - ## Bs. (Estado)
     * Botones interactivos: [💳 Pagar Ahora] (si adeuda) y [🏠 Menú Principal].
     *
     * @param string $codSocio
     * @param array $facturas
     * @param int $cantidad
     * @param int $totalImpagas
     * @return array
     */
    public static function historialFacturas(string $codSocio, array $facturas, int $cantidad, int $totalImpagas = 0): array
    {
        if ($cantidad > 0) {
            $mensajeTexto = "🧾 *Historial de Facturación (Últimos periodos)*\n" .
                            "📌 Código Fijo: *{$codSocio}*\n\n";

            $contador = 1;
            foreach ($facturas as $f) {
                $montoF = number_format((float)($f['monto'] ?? 0.0), 2, ',', '.');
                $periodo = (string)($f['periodo'] ?? '');
                $estado = (string)($f['estado'] ?? 'Pagada');

                // Formato exacto requerido: 1. 8/2026 - ## Bs. (Estado)
                $mensajeTexto .= "{$contador}. {$periodo} - {$montoF} Bs. ({$estado})\n";
                $contador++;
            }

            $mensajeTexto = trim($mensajeTexto);

            $buttons = [];

            // Si tiene facturas impagas o vencidas y la opción de deuda está activa
            if ($totalImpagas > 0 && FeatureFlags::isEnabled('deuda')) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => 'MENU_PAGAR_' . $codSocio,
                        'title' => '💳 Pagar Ahora'
                    ]
                ];
            }

            // Botón de retorno al menú principal
            $buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => 'MENU_PRINCIPAL_VOLVER',
                    'title' => '🏠 Menú Principal'
                ]
            ];

            return [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $mensajeTexto
                    ],
                    'action' => [
                        'buttons' => $buttons
                    ]
                ]
            ];
        }

        $mensajeTexto = "📋 *Historial de Facturación*\n\nEl Código Fijo (*{$codSocio}*) no registra facturas en el sistema.\n\n¿Necesitas consultar algo más? Toca el menú 👇";
        return PlantillaSocio::menuPrincipal($codSocio, '', false, $mensajeTexto, false);
    }
}