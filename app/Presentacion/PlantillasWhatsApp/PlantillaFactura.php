<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

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

    public static function historialFacturas(string $codSocio, array $facturas, int $cantidad): array
    {
        if ($cantidad > 0) {
            $mensajeTexto = "🧾 *Historial de Facturas (Últimos periodos)*\n" .
                            "📌 Código Fijo: *{$codSocio}*\n\n";
            $contador = 1;
            foreach ($facturas as $f) {
                $montoF = number_format($f['monto'], 2, ',', '.');
                $fechaF = PlantillaSocio::formatearFechaLocal((string)($f['fecha'] ?? ''), 'd/m/Y');
                if (empty($fechaF)) {
                    $fechaF = (string)($f['fecha'] ?? '');
                }
                $mensajeTexto .= "{$contador}. Periodo: *{$f['periodo']}* — {$montoF} Bs. (Pagado el {$fechaF}) ✅\n";
                $contador++;
            }
            $mensajeTexto = trim($mensajeTexto);
            $mensajeTexto .= "\n\n¿Deseas realizar alguna otra consulta? Toca el menú 👇";
        } else {
            $mensajeTexto = "📋 *Historial de Facturación*\n\nEl Código Fijo (*{$codSocio}*) no registra pagos recientes en este momento.\n\n¿Necesitas consultar algo más? Toca el menú 👇";
        }

        return PlantillaSocio::menuPrincipal($codSocio, '', false, $mensajeTexto, false);
    }
}