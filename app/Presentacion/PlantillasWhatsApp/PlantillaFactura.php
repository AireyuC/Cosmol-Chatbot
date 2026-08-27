<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

class PlantillaFactura
{
    public static function listaDeudas(string $codSocio, int $cantidadFacturas, float $totalRedondeado, array $listaDeudas): array
    {
        if ($cantidadFacturas > 0) {
            $totalFormateado = number_format($totalRedondeado, 2, ',', '.');
            $mensajeTexto = "El Código Fijo ($codSocio) tiene $cantidadFacturas facturas impagas, cuyo monto total es $totalFormateado Bs.\nEl detalle es el siguiente:\n\n";
            $contador = 1;
            foreach ($listaDeudas as $d) {
                $montoF = number_format($d['monto'], 2, ',', '.');
                $mensajeTexto .= "$contador. {$d['periodo']}, $montoF Bs. (Pendiente)\n";
                $contador++;
            }
            $mensajeTexto = trim($mensajeTexto);
            $mensajeTexto .= "\n\n💳 *Link de pago seguro:*\nhttps://multipago.com/service/cosmol_payment/first\n\n¿Necesitas algún otro servicio? Por favor, usa el menú 👇";
        } else {
            $mensajeTexto = "El Código Fijo ($codSocio) no tiene deudas pendientes en este momento.\n\n¿Necesitas algún otro servicio? Por favor, usa el menú 👇";
        }
        return PlantillaSocio::menuPrincipal($codSocio, '', false, $mensajeTexto, true);
    }

    public static function historialFacturas(string $codSocio, array $facturas, int $cantidad): array
    {
        if ($cantidad > 0) {
            $mensajeTexto = "🧾 *Historial de Facturas (Últimos meses)*\n\n";
            $contador = 1;
            foreach ($facturas as $f) {
                $montoF = number_format($f['monto'], 2, ',', '.');
                $fechaF = date('d/m/Y', strtotime($f['fecha']));
                $mensajeTexto .= "$contador. Periodo: {$f['periodo']} - Monto: $montoF Bs. (Pagado el $fechaF)\n";
                $contador++;
            }
            $mensajeTexto = trim($mensajeTexto);
            $mensajeTexto .= "\n\n¿Necesitas consultar algo más? Por favor, usa el menú 👇";
        } else {
            $mensajeTexto = "El Código Fijo ($codSocio) no tiene un historial de facturas pagadas disponible en este momento.\n\n¿Necesitas consultar algo más? Por favor, usa el menú 👇";
        }

        return PlantillaSocio::menuPrincipal($codSocio, '', false, $mensajeTexto, false);
    }
}