<?php

declare(strict_types=1);

namespace App\Modules\Socio;

use App\Data\Interfaces\SocioRepositoryInterface;

/**
 * Class SocioService
 * 
 * Contiene la lógica de negocio relacionada con los Socios.
 */
class SocioService
{
    /**
     * @var SocioRepositoryInterface
     */
    private $socioRepository;

    /**
     * SocioService constructor.
     *
     * @param SocioRepositoryInterface $socioRepository Inyección de la dependencia del repositorio.
     */
    public function __construct(SocioRepositoryInterface $socioRepository)
    {
        $this->socioRepository = $socioRepository;
    }

    /**
     * Valida la existencia de un socio por su código y devuelve sus datos formateados.
     *
     * @param string $cod_socio El código fijo a validar.
     * @return array Arreglo estandarizado con el resultado de la operación.
     */
    public function validarSocio(string $cod_socio): array
    {
        // Limpiamos el código ingresado (ej. quitamos espacios)
        $cod_socio = trim($cod_socio);

        if (empty($cod_socio)) {
            return [
                'status' => 'error',
                'message' => 'El código de socio es requerido.',
                'whatsapp_payload' => [
                    'type' => 'text',
                    'text' => [
                        'body' => '¡Hola! Bienvenido al Chatbot de COSMOL 💧. Por favor, escribe tu *Código Fijo de Socio* (solo números) para poder atenderte.'
                    ]
                ]
            ];
        }

        if (!is_numeric($cod_socio)) {
            return [
                'status' => 'not_found',
                'mensaje' => 'El valor ingresado no es un número.',
                'datos_socio' => null,
                'whatsapp_payload' => [
                    'type' => 'text',
                    'text' => [
                        'body' => '¡Hola! Bienvenido al Chatbot de COSMOL 💧. Por favor, escribe únicamente tu *Código Fijo de Socio* (solo números) para poder consultar tus datos y deudas.'
                    ]
                ]
            ];
        }

        // Delegamos la búsqueda al repositorio
        $socioData = $this->socioRepository->findByCodigo($cod_socio);

        if ($socioData) {
            // Limpiar campos devueltos por la API (quitar espacios en blanco sobrantes)
            if (isset($socioData['NOMBRE'])) {
                $socioData['NOMBRE'] = trim($socioData['NOMBRE']);
            }
            if (isset($socioData['DIRECCION'])) {
                $socioData['DIRECCION'] = trim($socioData['DIRECCION']);
            }

            $nombreSocio = $socioData['NOMBRE'];
            $mensaje = "Su Código Fijo $cod_socio ($nombreSocio) ha sido validado.\n\n¿En qué puedo ayudarle? Por favor, haga clic en Mostrar Menú.";

            return [
                'status' => 'success',
                'mensaje' => 'Socio encontrado exitosamente.',
                'datos_socio' => [
                    'nombre' => $nombreSocio,
                    'direccion' => $socioData['DIRECCION'] ?? ''
                ],
                'whatsapp_payload' => [
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
                                            'id' => 'MENU_PAGAR_' . $cod_socio,
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
                ]
            ];
        } else {
            return [
                'status' => 'not_found',
                'mensaje' => 'No se encontró un asociado con el código proporcionado.',
                'datos_socio' => null,
                'whatsapp_payload' => [
                    'type' => 'text',
                    'text' => [
                        'body' => 'No se encontró un asociado con el código proporcionado. Por favor, verifica el código e inténtalo de nuevo.'
                    ]
                ]
            ];
        }
    }

    /**
     * Obtiene el listado de deudas pendientes y calcula el monto total.
     *
     * @param string $cod_socio El código fijo.
     * @return array Arreglo estandarizado con el resultado y cálculos.
     */
    public function obtenerDeudas(string $cod_socio): array
    {
        $cod_socio = trim($cod_socio);

        if (empty($cod_socio)) {
            return [
                'status' => 'error',
                'message' => 'El código de socio es requerido.'
            ];
        }

        $deudas = $this->socioRepository->findDeudasByCodigo($cod_socio);

        if ($deudas !== null) {
            $totalSuma = 0.0;
            $listaDeudas = [];

            foreach ($deudas as $deuda) {
                $monto = isset($deuda['MONTOTOTAL']) ? (float) $deuda['MONTOTOTAL'] : 0.0;
                $totalSuma += $monto;

                // Limpiar textos de la factura si es necesario
                $razonSocial = isset($deuda['RAZONSOCIAL']) ? trim($deuda['RAZONSOCIAL']) : '';

                $listaDeudas[] = [
                    'factura' => $deuda['NROFACTURA'] ?? '',
                    'periodo' => ($deuda['NMES'] ?? '') . '-' . ($deuda['ANIO'] ?? ''),
                    'monto' => $monto,
                    'razon_social' => $razonSocial
                ];
            }

            $mensajeTexto = "";
            $cantidadFacturas = count($listaDeudas);
            $totalRedondeado = round($totalSuma, 2);
            $totalFormateado = number_format($totalRedondeado, 2, ',', '.');

            if ($cantidadFacturas > 0) {
                $mensajeTexto = "El Código Fijo ($cod_socio) tiene $cantidadFacturas facturas impagas, cuyo monto total es $totalFormateado Bs.\nEl detalle es el siguiente:\n\n";
                $contador = 1;
                foreach ($listaDeudas as $d) {
                    $montoF = number_format($d['monto'], 2, ',', '.');
                    $mensajeTexto .= "$contador. {$d['periodo']}, $montoF Bs. (Pendiente)\n";
                    $contador++;
                }
                $mensajeTexto = trim($mensajeTexto);
                $mensajeTexto .= "\n\n💳 *Link de pago seguro:*\nhttps://multipago.com/service/cosmol_payment/first";
            } else {
                $mensajeTexto = "El Código Fijo ($cod_socio) no tiene deudas pendientes en este momento.";
            }

            return [
                'status' => 'success',
                'codigo_socio' => $cod_socio,
                'mensaje_texto' => $mensajeTexto,
                'facturas_pendientes' => $listaDeudas,
                'total_deuda' => $totalRedondeado,
                'whatsapp_payload' => [
                    'type' => 'text',
                    'text' => [
                        'body' => $mensajeTexto
                    ]
                ]
            ];
        } else {
            return [
                'status' => 'error',
                'mensaje_texto' => 'Ocurrió un error al obtener las deudas o no se encontró el socio.',
                'whatsapp_payload' => [
                    'type' => 'text',
                    'text' => [
                        'body' => 'Ocurrió un error al obtener las deudas o no se encontró el socio. Por favor, intenta de nuevo más tarde.'
                    ]
                ]
            ];
        }
    }
}
