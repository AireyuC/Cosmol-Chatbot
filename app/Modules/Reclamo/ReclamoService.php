<?php

namespace App\Modules\Reclamo;

use App\Data\Interfaces\ReclamoRepositoryInterface;
use App\Data\Interfaces\SocioRepositoryInterface;
use Exception;

class ReclamoService {
    private $reclamoRepo;
    private $socioRepo;

    // Constante con los tipos de reclamos permitidos para evitar basura en la BD
    const TIPOS_VALIDOS = ['AGUA_TURBIA', 'FUGA', 'CORTE_INJUSTIFICADO', 'BAJA_PRESION'];

    /**
     * Inyección de ambos repositorios.
     * Fíjate que pedimos las "Interfaces", no la clase de MySQL directamente.
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
     * @return array Respuesta con formato {exito, ticket_id, mensaje}
     */
    public function registrarReclamo(string $codigoSocio, string $tipoReclamo, string $descripcion): array {
        
        // 1. Validar si el tipo de reclamo existe en nuestra lista
        if (!in_array(strtoupper($tipoReclamo), self::TIPOS_VALIDOS)) {
            return [
                'exito' => false,
                'mensaje' => "El tipo de reclamo '{$tipoReclamo}' no es válido."
            ];
        }

        // 2. Buscar al socio en la base de datos (Usando el repo de la Fase 2)
        $socio = $this->socioRepo->findByCodigo($codigoSocio);

        if (!$socio) {
            return [
                'exito' => false,
                'mensaje' => "No se encontró ningún socio con el código fijo proporcionado."
            ];
        }

        // 3. Extraemos la dirección que el socio ya tiene registrada.
        // Asumiendo que la tabla socio tiene una columna 'direccion' o usaremos un valor por defecto si no la tiene en Fase 2
        // Revisando tu tabla 'socio' recién creada en SQL, noté que no pusiste 'direccion', 
        // así que por ahora asumiremos que se tomaría de alguna columna de ubicación, 
        // o lo dejaremos como un aviso para tu compañero. Por ahora pondremos algo genérico para que no falle.
        $direccionSocio = $socio['direccion'] ?? 'Dirección registrada en sistema (SAI)';

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
                'exito' => true,
                'ticket_id' => $ticketId,
                'mensaje' => "Reclamo registrado correctamente con el ticket #{$ticketId}."
            ];

        } catch (Exception $e) {
            // Si algo falla a nivel de base de datos (ej. se cayó el servidor)
            return [
                'exito' => false,
                'mensaje' => "Error interno al registrar el reclamo: " . $e->getMessage()
            ];
        }
    }
}
