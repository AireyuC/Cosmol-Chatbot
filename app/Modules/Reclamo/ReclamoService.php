<?php

declare(strict_types=1);

namespace App\Modules\Reclamo;

use App\Data\Interfaces\ReclamoRepositoryInterface;
use App\Data\Interfaces\SocioRepositoryInterface;
use Exception;

class ReclamoService {
    private $reclamoRepo;
    private $socioRepo;

    // Constante con los tipos de reclamos permitidos para evitar basura en la BD
    const TIPOS_VALIDOS = ['AGUA_TURBIA', 'FUGA', 'SIN_SERVICIO', 'PRESION_BAJA', 'CORTE_INJUSTIFICADO', 'BAJA_PRESION', 'OTRO'];

    /**
     * Inyección de ambos repositorios.
     * Recibe las Interfaces, independientemente del motor (PostgreSQL / API SAI).
     */
    public function __construct(
        ReclamoRepositoryInterface $reclamoRepo,
        SocioRepositoryInterface $socioRepo
    ) {
        $this->reclamoRepo = $reclamoRepo;
        $this->socioRepo = $socioRepo;
    }

    /**
     * Registra un nuevo reclamo validando todo previamente.
     * 
     * @param string $codigoSocio
     * @param string $tipoReclamo
     * @param string $descripcion
     * @return array Respuesta con formato {success, message, data}
     */
    public function registrarReclamo(string $codigoSocio, string $tipoReclamo, string $descripcion): array {
        
        // 1. Validar si el tipo de reclamo existe en nuestra lista
        if (!in_array(strtoupper($tipoReclamo), self::TIPOS_VALIDOS)) {
            return [
                'success' => false,
                'message' => "El tipo de reclamo '{$tipoReclamo}' no es válido.",
                'data' => null
            ];
        }

        // 2. Buscar al socio en la base de datos (Usando el repo de la Fase 2)
        $socio = $this->socioRepo->findByCodigo($codigoSocio);

        if (!$socio) {
            return [
                'success' => false,
                'message' => "No se encontró ningún socio con el código fijo proporcionado.",
                'data' => null
            ];
        }

        // 3. La dirección se extrae de la BD (sin GPS). La devuelve SocioRepository::findByCodigo().
        $direccionSocio = $socio['direccion'] ?? 'Sin dirección registrada';

        // 4. Armamos el array de datos para enviarlo al repositorio de reclamos
        $datosReclamo = [
            'codigo_socio' => $codigoSocio,
            'tipo_reclamo' => strtoupper($tipoReclamo),
            'descripcion'  => $descripcion,
            'direccion'    => $direccionSocio
        ];

        try {
            // 5. Guardamos en la BD y obtenemos el ticket ID
            $ticketId = $this->reclamoRepo->createReclamo($datosReclamo);

            return [
                'success' => true,
                'message' => "Reclamo registrado correctamente con el ticket #{$ticketId}.",
                'data' => ['ticket_id' => $ticketId]
            ];

        } catch (Exception $e) {
            // Si algo falla a nivel de base de datos (ej. se cayó el servidor)
            error_log("Error en ReclamoService::registrarReclamo: " . $e->getMessage());
            return [
                'success' => false,
                'message' => "Error interno al registrar el reclamo.",
                'data' => null
            ];
        }
    }
}
