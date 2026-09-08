<?php

declare(strict_types=1);

namespace App\Core;

use App\Data\Interfaces\SessionRepositoryInterface;
use App\Presentacion\PlantillasWhatsApp\PlantillaSistema;

/**
 * Guardia de Mantenimiento Global y Anti-spam desacoplado.
 * Controla si el chatbot se encuentra temporalmente inhabilitado y aplica límites
 * de mensajes para evitar spam sin alterar el estado de sesión del socio.
 */
class MaintenanceGuard
{
    /**
     * @var SessionRepositoryInterface
     */
    private $sessionRepo;

    public function __construct(SessionRepositoryInterface $sessionRepo)
    {
        $this->sessionRepo = $sessionRepo;
    }

    /**
     * Evalúa si el modo mantenimiento está activo y procesa el control de spam para el teléfono.
     *
     * @param string $telefono Número del usuario.
     * @return array|null Retorna la respuesta estandarizada para n8n si está en mantenimiento, o null si está inactivo.
     */
    public function check(string $telefono): ?array
    {
        if (!FeatureFlags::isMaintenanceMode()) {
            return null;
        }

        $session = $this->sessionRepo->getSession($telefono);
        $contextData = [];
        if ($session && !empty($session['context_data'])) {
            $decoded = json_decode((string)$session['context_data'], true);
            if (is_array($decoded)) {
                $contextData = $decoded;
            }
        }

        $mantData = $contextData['mantenimiento'] ?? [
            'intentos' => 0,
            'ultimo_mensaje' => 0
        ];

        $maxIntentos = FeatureFlags::getMaintenanceMaxAttempts();
        $timeoutSegundos = FeatureFlags::getMaintenanceTimeoutSeconds();
        $timeoutMinutos = FeatureFlags::getMaintenanceTimeoutMinutes();

        $ahora = time();
        $ultimoMensaje = (int)($mantData['ultimo_mensaje'] ?? 0);
        $intentos = (int)($mantData['intentos'] ?? 0);

        // Si pasaron más de los minutos configurados de inactividad, se resetean los intentos
        if (($ahora - $ultimoMensaje) > $timeoutSegundos) {
            $intentos = 0;
        }

        $intentos++;
        $mantData['intentos'] = $intentos;
        $mantData['ultimo_mensaje'] = $ahora;
        $contextData['mantenimiento'] = $mantData;

        // Guardar context_data actualizado SIN tocar codigo_socio ni intentos_fallidos
        $codigoSocioActual = ($session && !empty($session['codigo_socio'])) ? (int)$session['codigo_socio'] : null;
        $estadoActual = ($session && !empty($session['estado_actual'])) ? $session['estado_actual'] : 'AWAITING_CODE';
        $intentosFallidosActuales = ($session && isset($session['intentos_fallidos'])) ? (int)$session['intentos_fallidos'] : 0;

        $this->sessionRepo->saveSession(
            $telefono,
            $codigoSocioActual,
            $estadoActual,
            $intentosFallidosActuales,
            json_encode($contextData)
        );

        // Si excede el máximo de intentos, silencio total ("dejado en visto")
        if ($intentos > $maxIntentos) {
            $whatsappPayload = null;
        } else {
            $whatsappPayload = PlantillaSistema::mantenimientoGlobal($intentos, $maxIntentos, $timeoutMinutos);
        }

        return [
            'status' => 'success',
            'modo' => 'mantenimiento',
            'whatsapp_payload' => $whatsappPayload
        ];
    }
}
