<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Plantillas interactivas de WhatsApp para el módulo de Pago de Facturas (Mock Alpha).
 * Cumple estrictamente con la especificación de Meta WhatsApp Cloud API (Graph API).
 */
class PlantillaPago
{
    /**
     * Construye un mensaje interactivo de tipo lista para seleccionar las facturas a cancelar.
     * Implementa la regla estricta: NO se puede pagar saltado (las opciones son acumulativas
     * desde la factura más antigua hasta la elegida).
     *
     * @param string $codSocio Código fijo del socio
     * @param array $facturasPendientes Lista de facturas pendientes/vencidas
     * @param float $totalRedondeado Monto total adeudado
     * @return array Payload interactivo para Meta
     */
    public static function selectorFacturas(string $codSocio, array $facturasPendientes, float $totalRedondeado): array
    {
        $cantidad = count($facturasPendientes);
        $totalFormateado = number_format($totalRedondeado, 2, ',', '.');

        $bodyText = "💳 *Pago de Facturas — COSMOL R.L.*\n" .
                    "📌 Código Fijo: *{$codSocio}*\n" .
                    "⚠️ Facturas pendientes: *{$cantidad}*\n" .
                    "💰 Deuda total: *{$totalFormateado} Bs.*\n\n" .
                    "📲 *Canales de pago disponibles:*\n" .
                    "1. *Directo en WhatsApp (QR Simple):* Toca el botón abajo para elegir cuántos periodos pagar (se aplican cronológicamente desde el más antiguo).\n" .
                    "2. *Enlace Web (Multipago):* Si prefieres pagar con tarjeta o banca externa en línea, ingresa aquí:\n" .
                    "👉 https://multipago.com/service/cosmol_payment/first\n\n" .
                    "Toca el botón abajo para elegir tus periodos a cancelar 👇";

        $rows = [];
        $montoAcumulado = 0.0;
        $periodosAcumulados = [];

        for ($i = 0; $i < $cantidad; $i++) {
            $f = $facturasPendientes[$i];
            $montoAcumulado += (float)($f['monto'] ?? 0.0);
            $periodosAcumulados[] = $f['periodo'] ?? '';
            $numFacturas = $i + 1;

            $montoF = number_format($montoAcumulado, 2, ',', '.');
            $periodosStr = implode(', ', $periodosAcumulados);

            $tituloFila = ($numFacturas === 1) 
                ? "Pagar 1 Factura" 
                : (($numFacturas === $cantidad) ? "Pagar Todo ({$numFacturas} fac)" : "Pagar {$numFacturas} Facturas");

            $descFila = "Total: {$montoF} Bs. ({$periodosStr})";
            if (mb_strlen($descFila) > 70) {
                $descFila = "Total: {$montoF} Bs. (Hasta {$f['periodo']})";
            }

            $rows[] = [
                'id' => "PAGO_SELEC_{$numFacturas}_{$codSocio}",
                'title' => mb_substr($tituloFila, 0, 24),
                'description' => mb_substr($descFila, 0, 72)
            ];
        }

        // Fila de retorno / cancelación
        $rowsNavegacion = [
            [
                'id' => 'MENU_PRINCIPAL_VOLVER',
                'title' => '❌ Cancelar y Volver',
                'description' => 'Terminar la acción y regresar al menú'
            ]
        ];

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => [
                    'type' => 'text',
                    'text' => 'Opciones de Pago'
                ],
                'body' => [
                    'text' => $bodyText
                ],
                'footer' => [
                    'text' => 'COSMOL R.L. — Pagos Digitales'
                ],
                'action' => [
                    'button' => 'Elegir Facturas 👇',
                    'sections' => [
                        [
                            'title' => 'Periodos Acumulados',
                            'rows' => $rows
                        ],
                        [
                            'title' => 'Navegación',
                            'rows' => $rowsNavegacion
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Muestra el Código QR generado dinámicamente con cabecera de imagen en un mensaje interactivo
     * con botones de confirmación y cancelación.
     *
     * @param string $codSocio
     * @param int $cantidad
     * @param float $montoTotal
     * @param string $periodosTexto
     * @return array
     */
    public static function mostrarQrPago(string $codSocio, int $cantidad, float $montoTotal, string $periodosTexto): array
    {
        $montoFormateado = number_format($montoTotal, 2, ',', '.');
        $plural = $cantidad > 1 ? 's' : '';

        // Generación dinámica de QR con los datos de cobro simulados
        $qrData = urlencode("COSMOL_PAGO_QR|SOCIO:{$codSocio}|MONTO:{$montoTotal}|PERIODOS:{$periodosTexto}");
        $qrImageUrl = "https://api.qrserver.com/v1/create-qr-code/?size=400x400&data={$qrData}&margin=10";

        $bodyText = "💳 *Pago por QR Simple — COSMOL R.L.*\n" .
                    "📌 Código Fijo: *{$codSocio}*\n" .
                    "🧾 Factura{$plural} a cancelar: *{$cantidad}* ({$periodosTexto})\n" .
                    "💰 Monto total: *{$montoFormateado} Bs.*\n" .
                    "⏱️ Validez del QR: *15 minutos*\n\n" .
                    "📲 *Instrucciones para pagar:*\n" .
                    "1. Realiza una captura de pantalla a este código QR o guarda la imagen.\n" .
                    "2. Abre tu app bancaria móvil preferida.\n" .
                    "3. Selecciona la opción *Pago Simple / QR* y escanea la captura.\n\n" .
                    "_Al ser un módulo Alpha de prueba, presiona el botón abajo para simular la confirmación del pago:_";

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'image',
                    'image' => [
                        'link' => $qrImageUrl
                    ]
                ],
                'body' => [
                    'text' => $bodyText
                ],
                'footer' => [
                    'text' => 'Simulación de Cobro QR'
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => "PAGO_CONFIRMAR_{$cantidad}_{$codSocio}",
                                'title' => '✅ Simular Pago'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'MENU_PRINCIPAL_VOLVER',
                                'title' => '❌ Cancelar y Volver'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Genera el comprobante digital de pago exitoso (Mock).
     *
     * @param string $codSocio
     * @param string $nombreSocio
     * @param int $cantidad
     * @param float $montoTotal
     * @param string $periodosTexto
     * @return array
     */
    public static function pagoExitoso(string $codSocio, string $nombreSocio, int $cantidad, float $montoTotal, string $periodosTexto): array
    {
        $montoFormateado = number_format($montoTotal, 2, ',', '.');
        $nroRecibo = 'REC-MOCK-' . strtoupper(substr(md5($codSocio . time()), 0, 8));
        $fechaHora = date('d/m/Y H:i');

        $bodyText = "🎉 *¡Pago Realizado con Éxito!* (Simulación)\n\n" .
                    "🏢 *COOPERATIVA DE SERVICIOS PÚBLICOS MONTERO R.L.*\n" .
                    "🧾 *Nº de Comprobante:* #{$nroRecibo}\n" .
                    "📌 Código Fijo: *{$codSocio}*\n" .
                    "👤 Titular: *" . (!empty($nombreSocio) ? $nombreSocio : 'Socio') . "*\n" .
                    "📅 Fecha y hora: *{$fechaHora}*\n" .
                    "💰 Monto pagado: *{$montoFormateado} Bs.*\n" .
                    "📋 Periodos cancelados: *{$periodosTexto}* ({$cantidad} fac.)\n" .
                    "💳 Medio: *QR Simple Interbancario*\n" .
                    "✅ Estado: *CONCILIADO*\n\n" .
                    "_Este es un comprobante digital de prueba (Alpha). Sus facturas se encuentran al día._";

        return [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => [
                    'text' => $bodyText
                ],
                'footer' => [
                    'text' => 'COSMOL R.L. — Transacción Exitosa'
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'MENU_PRINCIPAL_VOLVER',
                                'title' => '📋 Mostrar Menú'
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}

