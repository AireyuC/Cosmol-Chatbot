<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

/**
 * Genera los payloads JSON de WhatsApp relacionados a Facturas y Pagos.
 */
class PlantillaFactura
{
    /**
     * Retorna el mensaje con el listado de deudas y el link de pago.
     */
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
            $mensajeTexto .= "\n\n💳 *Link de pago seguro:*\nhttps://multipago.com/service/cosmol_payment/first\n\n¡Gracias por preferir nuestros canales digitales!";
        } else {
            $mensajeTexto = "El Código Fijo ($codSocio) no tiene deudas pendientes en este momento.";
        }

        return [
            'type' => 'text',
            'text' => [
                'body' => $mensajeTexto
            ]
        ];
    }
}
