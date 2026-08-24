<?php

declare(strict_types=1);

namespace App\Data\Repositories\Postgres;

use App\Data\Interfaces\ReclamoRepositoryInterface;
use PDO;

class ReclamoRepository implements ReclamoRepositoryInterface {
    private $pdo;

    /**
     * El repositorio recibe la conexión PDO al momento de ser instanciado.
     */
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Inserta un nuevo reclamo en la base de datos de PostgreSQL.
     * Implementa la promesa del contrato (interfaz).
     */
    public function createReclamo(array $data): int {
        // Preparamos la consulta SQL para evitar inyecciones SQL
        $stmt = $this->pdo->prepare(
            "INSERT INTO reclamo
                (codigo_socio, tipo_reclamo, descripcion, direccion, estado, fecha_creacion)
             VALUES
                (:codigo_socio, :tipo, :descripcion, :direccion, 'PENDIENTE', CURRENT_TIMESTAMP)
             RETURNING id"
        );

        // Ejecutamos la consulta reemplazando las variables con los datos reales
        $stmt->execute([
            ':codigo_socio' => $data['codigo_socio'],
            ':tipo'         => $data['tipo_reclamo'],
            ':descripcion'  => $data['descripcion'],
            ':direccion'    => $data['direccion'], // Esta dirección se extrae de la BD, no del usuario
        ]);

        // PostgreSQL devuelve el ID autoincremental generado a través de RETURNING id
        return (int) $stmt->fetchColumn();
    }

    /**
     * Busca todos los reclamos asociados a un código fijo de socio.
     */
    public function findByCodigoSocio(string $codigo): array {
        $stmt = $this->pdo->prepare(
            "SELECT id, codigo_socio, tipo_reclamo, descripcion, direccion, estado, fecha_creacion 
             FROM reclamo 
             WHERE codigo_socio = :codigo 
             ORDER BY fecha_creacion DESC"
        );
        $stmt->execute([':codigo' => $codigo]);
        
        // fetchAll devuelve todas las filas encontradas como un arreglo
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
