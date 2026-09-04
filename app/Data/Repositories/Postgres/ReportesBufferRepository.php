<?php

declare(strict_types=1);

namespace App\Data\Repositories\Postgres;

use App\Data\Interfaces\ReportesRepositoryInterface;
use App\Core\Database;
use App\Core\Logger;
use PDO;
use Exception;

/**
 * Repositorio de buffer local para almacenar temporalmente las consultas no sincronizadas.
 */
class ReportesBufferRepository implements ReportesRepositoryInterface
{
    /**
     * @var PDO
     */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->asegurarTabla();
    }

    /**
     * Crea la tabla cola_reportes de forma defensiva si no existe en el contenedor de BD.
     */
    private function asegurarTabla(): void
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS cola_reportes (
                id SERIAL PRIMARY KEY,
                codigo_socio INT NOT NULL,
                nombres VARCHAR(200) NOT NULL,
                id_tipo INT NOT NULL,
                tipo_consulta VARCHAR(100) NOT NULL,
                fecha_consulta DATE NOT NULL DEFAULT CURRENT_DATE,
                hora_consulta TIME NOT NULL DEFAULT CURRENT_TIME,
                estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
                intentos INT NOT NULL DEFAULT 0,
                ultimo_error TEXT NULL,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->db->exec($sql);
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository: No se pudo asegurar la tabla cola_reportes", [
                'error' => $e->getMessage()
            ]);
        }
    }

    public function guardarEnCola(array $datos): bool
    {
        try {
            $sql = "INSERT INTO cola_reportes 
                    (codigo_socio, nombres, id_tipo, tipo_consulta, fecha_consulta, hora_consulta, estado)
                    VALUES (:codigo_socio, :nombres, :id_tipo, :tipo_consulta, :fecha_consulta, :hora_consulta, 'PENDIENTE')";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':codigo_socio'   => $datos['codigo_socio'],
                ':nombres'        => $datos['nombres'],
                ':id_tipo'        => $datos['id_tipo'],
                ':tipo_consulta'  => $datos['tipo_consulta'],
                ':fecha_consulta' => $datos['fecha_consulta'] ?? date('Y-m-d'),
                ':hora_consulta'  => $datos['hora_consulta'] ?? date('H:i:s')
            ]);
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::guardarEnCola error", [
                'error' => $e->getMessage(),
                'datos' => $datos
            ]);
            return false;
        }
    }

    public function obtenerPendientes(int $limite = 20): array
    {
        try {
            $sql = "SELECT id, codigo_socio, nombres, id_tipo, tipo_consulta, fecha_consulta, hora_consulta, intentos
                    FROM cola_reportes
                    WHERE estado = 'PENDIENTE'
                    ORDER BY id ASC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::obtenerPendientes error", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function marcarComoEnviado(int $id): bool
    {
        try {
            $sql = "UPDATE cola_reportes 
                    SET estado = 'ENVIADO', actualizado_en = CURRENT_TIMESTAMP 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':id' => $id]);
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::marcarComoEnviado error", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function incrementarIntento(int $id, string $error): bool
    {
        try {
            $sql = "UPDATE cola_reportes 
                    SET intentos = intentos + 1, 
                        ultimo_error = :error, 
                        actualizado_en = CURRENT_TIMESTAMP 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':id'    => $id,
                ':error' => mb_substr($error, 0, 500)
            ]);
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::incrementarIntento error", [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
