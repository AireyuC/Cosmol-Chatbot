<?php

declare(strict_types=1);

namespace App\Presentacion\PlantillasWhatsApp;

use App\Core\FeatureFlags;

class PlantillaSocio
{
    public static function saludo(): array
    {
        return [
            'type' => 'text',
            'text' => [
                'body' => "👋 ¡Hola! Te damos la bienvenida a *COSMOL R.L.* 💧\n_Tu cooperativa de agua potable y alcantarillado._\n\nPor favor, escribe tu *Código Fijo de Asociado* (ej: 10245) para comenzar a ayudarte:"
            ]
        ];
    }

    public static function menuPrincipal(string $codSocio, string $nombreSocio = '', bool $esError = false, ?string $mensajePersonalizado = null, bool $ocultarPagar = false): array
    {
        if ($mensajePersonalizado !== null) {
            $mensaje = $mensajePersonalizado;
        } elseif ($esError) {
            $mensaje = "🤖 Opción no válida. Por favor, utiliza los botones o el menú interactivo 👇";
        } else {
            $saludoPersonalizado = !empty(trim($nombreSocio)) ? "¡Qué gusto saludarte, *" . trim($nombreSocio) . "*! 👋\n" : "¡Qué gusto saludarte! 👋\n";
            $mensaje = "{$saludoPersonalizado}📌 Código Fijo: *{$codSocio}*\n\n¿En qué podemos colaborar el día de hoy? Toca el botón *Mostrar Menú* 👇 para elegir una opción:";
        }

        $rows = [];
        
        if (!$ocultarPagar && FeatureFlags::isEnabled('deuda')) {
            $rows[] = [
                'id' => 'MENU_PAGAR_' . $codSocio,
                'title' => 'Consultar Deuda',
                'description' => 'Ver y pagar facturas pendientes'
            ];
        }

        if (FeatureFlags::isEnabled('reconexion')) {
            $rows[] = [
                'id' => 'MENU_RECONEXION',
                'title' => 'Solicitar Reconexión',
                'description' => 'Solicita reconexión de servicio'
            ];
        }

        if (FeatureFlags::isEnabled('historial')) {
            $rows[] = [
                'id' => 'MENU_HISTORIAL',
                'title' => 'Historial',
                'description' => 'Historial de pagos y consumos'
            ];
        }

        if (FeatureFlags::isEnabled('reclamos')) {
            $rows[] = [
                'id' => 'MENU_RECLAMOS',
                'title' => 'Reclamos',
                'description' => 'Reporta emergencias y reclamos'
            ];
        }

        if (FeatureFlags::isEnabled('estado_tramites')) {
            $rows[] = [
                'id' => 'MENU_ESTADO_TRAMITES',
                'title' => 'Estado de Solicitudes',
                'description' => 'Reclamos y reconexiones'
            ];
        }

        if (FeatureFlags::isEnabled('oficinas')) {
            $rows[] = [
                'id' => 'MENU_OFICINAS',
                'title' => 'Oficinas y horarios',
                'description' => 'Información de atención'
            ];
        }

        if (FeatureFlags::isEnabled('agente')) {
            $rows[] = [
                'id' => 'MENU_AGENTE',
                'title' => 'Hablar con un asesor',
                'description' => 'Soporte personalizado'
            ];
        }

        // Opciones del sistema (siempre presentes para garantizar navegación y respetar límites de Meta)
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
        $mensaje = "🤝 *Atención Personalizada COSMOL*\n\nNuestro equipo de Atención al Cliente está listo para ayudarte personalmente. Toca el siguiente enlace para iniciar un chat directo con un asesor:\n\n👉 https://wa.me/" . $numeroAgente . "\n\n_Te responderemos a la brevedad posible._";

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

    /**
     * @deprecated Utilizar PlantillaReconexion::solicitarGps()
     */
    public static function solicitarGpsReconexion(): array
    {
        return PlantillaReconexion::solicitarGps();
    }

    /**
     * @deprecated Utilizar PlantillaReconexion::menuTipo()
     */
    public static function menuTipoReconexion(): array
    {
        return PlantillaReconexion::menuTipo();
    }

    /**
     * @deprecated Utilizar PlantillaReconexion::solicitarGlosa()
     */
    public static function solicitarGlosaReconexion(): array
    {
        return PlantillaReconexion::solicitarGlosa();
    }

    /**
     * Muestra el resumen de estado de reclamos y solicitudes de reconexión del socio.
     */
    public static function estadoSolicitudes(string $codSocio, string $nombreSocio, array $reclamos, array $reconexiones): array
    {
        $nombre = trim($nombreSocio);
        $headerNombre = !empty($nombre) ? " - {$nombre}" : "";
        $texto = "📋 *Estado de Solicitudes y Reclamos*\n";
        $texto .= "Socio: *{$codSocio}{$headerNombre}*\n\n";

        // 1. Reclamos Técnicos
        $texto .= "🔧 *Reclamos Técnicos:*\n";
        if (!empty($reclamos)) {
            $ultimosReclamos = array_slice($reclamos, -3);
            $ultimosReclamos = array_reverse($ultimosReclamos);
            foreach ($ultimosReclamos as $rec) {
                $id = $rec['id_reclamo'] ?? '?';
                $desc = trim((string)($rec['descripcion'] ?? 'Reclamo'));
                $estado = strtoupper(trim((string)($rec['estado'] ?? 'PENDIENTE')));
                $emojiEstado = '🟡';
                if (in_array($estado, ['CONCLUIDO', 'ATENDIDO', 'FINALIZADO'])) {
                    $emojiEstado = '🟢';
                } elseif (in_array($estado, ['EN PROCESO', 'ASIGNADO'])) {
                    $emojiEstado = '🔵';
                } elseif (in_array($estado, ['CANCELADO', 'RECHAZADO'])) {
                    $emojiEstado = '🔴';
                }

                $rawFecha = $rec['fecha_registro'] ?? ($rec['fecha_creacion'] ?? '');
                $fechaFormateada = self::formatearFechaLocal($rawFecha);
                $fechaStr = !empty($fechaFormateada) ? " ({$fechaFormateada})" : '';

                $texto .= "• Ticket *#{$id}*: {$desc}\n  Estado: {$emojiEstado} *{$estado}*{$fechaStr}\n";
            }
        } else {
            $texto .= "• No tienes reclamos registrados.\n";
        }

        // 2. Solicitudes de Reconexión
        $texto .= "\n⚡ *Solicitudes de Reconexión:*\n";
        if (!empty($reconexiones)) {
            $mapaTipos = [
                1 => 'Corte normal',
                2 => 'Con medidor',
                3 => 'Con material',
                4 => 'Otros'
            ];
            $ultimasReconexiones = array_slice($reconexiones, -3);
            $ultimasReconexiones = array_reverse($ultimasReconexiones);
            foreach ($ultimasReconexiones as $recx) {
                $id = $recx['id_reconexion'] ?? '?';
                $idTipo = (int)($recx['id_tipo_reconexion'] ?? 1);
                $tipoDesc = $mapaTipos[$idTipo] ?? 'Reconexión';
                $estado = strtoupper(trim((string)($recx['estado'] ?? 'PENDIENTE')));
                $emojiEstado = '🟡';
                if (in_array($estado, ['CONCLUIDO', 'ATENDIDO', 'RECONECTADO', 'FINALIZADO'])) {
                    $emojiEstado = '🟢';
                } elseif (in_array($estado, ['EN PROCESO', 'ASIGNADO'])) {
                    $emojiEstado = '🔵';
                } elseif (in_array($estado, ['CANCELADO', 'RECHAZADO'])) {
                    $emojiEstado = '🔴';
                }

                $rawFecha = $recx['fecha_registro'] ?? ($recx['fecha_creacion'] ?? '');
                $fechaFormateada = self::formatearFechaLocal($rawFecha);
                $fechaStr = !empty($fechaFormateada) ? " ({$fechaFormateada})" : '';

                $texto .= "• Ticket *#{$id}*: {$tipoDesc}\n  Estado: {$emojiEstado} *{$estado}*{$fechaStr}\n";
            }
        } else {
            $texto .= "• No tienes solicitudes de reconexión registradas.\n";
        }

        $texto .= "\n¿Necesitas realizar otra consulta? Por favor, usa el menú 👇";

        return self::menuPrincipal($codSocio, $nombreSocio, false, $texto);
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

    /**
     * Convierte y formatea una marca de tiempo (habitualmente en UTC o devuelta por la API/BD)
     * a la hora local oficial de Bolivia (America/La_Paz, UTC-4).
     *
     * @param string|null $fechaStr
     * @param string $formato
     * @return string
     */
    public static function formatearFechaLocal(?string $fechaStr, string $formato = 'd/m/Y H:i'): string
    {
        if (empty($fechaStr)) {
            return '';
        }

        try {
            // Si la cadena no especifica timezone (ej. "2026-09-10 23:14:59"), asume UTC como origen.
            // Si ya contiene offset explícito (Z, +00:00, -04:00), DateTime respetará automáticamente la zona indicada.
            $dt = new \DateTime($fechaStr, new \DateTimeZone('UTC'));
            $dt->setTimezone(new \DateTimeZone('America/La_Paz'));
            return $dt->format($formato);
        } catch (\Exception $e) {
            return $fechaStr;
        }
    }
}

