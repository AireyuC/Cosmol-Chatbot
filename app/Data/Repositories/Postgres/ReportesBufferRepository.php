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
                telefono VARCHAR(30) NULL,
                id_tipo INT NOT NULL,
                tipo_consulta VARCHAR(100) NOT NULL,
                tipo_ubicacion VARCHAR(20) NULL,
                fecha_consulta DATE NOT NULL DEFAULT CURRENT_DATE,
                hora_consulta TIME NOT NULL DEFAULT CURRENT_TIME,
                estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
                intentos INT NOT NULL DEFAULT 0,
                ultimo_error TEXT NULL,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $this->db->exec($sql);

            // Migración defensiva no destructiva para tablas ya creadas
            $this->db->exec("ALTER TABLE cola_reportes ADD COLUMN IF NOT EXISTS telefono VARCHAR(30);");
            $this->db->exec("ALTER TABLE cola_reportes ADD COLUMN IF NOT EXISTS tipo_ubicacion VARCHAR(20);");
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
                    (codigo_socio, nombres, telefono, id_tipo, tipo_consulta, tipo_ubicacion, fecha_consulta, hora_consulta, estado)
                    VALUES (:codigo_socio, :nombres, :telefono, :id_tipo, :tipo_consulta, :tipo_ubicacion, :fecha_consulta, :hora_consulta, 'PENDIENTE')";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':codigo_socio'   => $datos['codigo_socio'],
                ':nombres'        => $datos['nombres'],
                ':telefono'       => !empty($datos['telefono']) ? (string)$datos['telefono'] : null,
                ':id_tipo'        => $datos['id_tipo'],
                ':tipo_consulta'  => $datos['tipo_consulta'],
                ':tipo_ubicacion' => !empty($datos['tipo_ubicacion']) ? (string)$datos['tipo_ubicacion'] : null,
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
            $sql = "SELECT id, codigo_socio, nombres, telefono, id_tipo, tipo_consulta, tipo_ubicacion, fecha_consulta, hora_consulta, intentos
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
                        estado = CASE WHEN intentos + 1 >= 5 THEN 'FALLIDO' ELSE estado END,
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

    public function reactivarFallidos(): int
    {
        try {
            $sql = "UPDATE cola_reportes 
                    SET estado = 'PENDIENTE', intentos = 0, ultimo_error = NULL, actualizado_en = CURRENT_TIMESTAMP 
                    WHERE estado = 'FALLIDO'";
            $filas = $this->db->exec($sql);
            return $filas !== false ? (int)$filas : 0;
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::reactivarFallidos error", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function contarConsultasHoy(string $telefono, int $codigoSocio, int $idTipo): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM cola_reportes 
                    WHERE (telefono = :telefono OR (codigo_socio = :codigo_socio AND :codigo_socio > 0))
                      AND id_tipo = :id_tipo 
                      AND fecha_consulta = CURRENT_DATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':telefono'     => $telefono,
                ':codigo_socio' => $codigoSocio,
                ':id_tipo'      => $idTipo
            ]);

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::contarConsultasHoy error", [
                'telefono'    => $telefono,
                'codigoSocio' => $codigoSocio,
                'idTipo'      => $idTipo,
                'error'       => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function contarConsultasPorSocioHoy(int $codigoSocio, int $idTipo): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM cola_reportes 
                    WHERE codigo_socio = :codigo_socio 
                      AND id_tipo = :id_tipo 
                      AND fecha_consulta = CURRENT_DATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':codigo_socio' => $codigoSocio,
                ':id_tipo'      => $idTipo
            ]);

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::contarConsultasPorSocioHoy error", [
                'codigoSocio' => $codigoSocio,
                'idTipo'      => $idTipo,
                'error'       => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function contarConsultasPorTelefonoHoy(string $telefono, int $idTipo): int
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM cola_reportes 
                    WHERE telefono = :telefono 
                      AND id_tipo = :id_tipo 
                      AND fecha_consulta = CURRENT_DATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':telefono' => $telefono,
                ':id_tipo'  => $idTipo
            ]);

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::contarConsultasPorTelefonoHoy error", [
                'telefono' => $telefono,
                'idTipo'   => $idTipo,
                'error'    => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function contarSociosDistintosTelefonoHoy(string $telefono): int
    {
        try {
            $sql = "SELECT COUNT(DISTINCT codigo_socio) 
                    FROM cola_reportes 
                    WHERE telefono = :telefono 
                      AND codigo_socio > 0 
                      AND fecha_consulta = CURRENT_DATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':telefono' => $telefono]);

            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::contarSociosDistintosTelefonoHoy error", [
                'telefono' => $telefono,
                'error'    => $e->getMessage()
            ]);
            return 0;
        }
    }

    public function haConsultadoSocioHoy(string $telefono, int $codigoSocio): bool
    {
        try {
            $sql = "SELECT COUNT(*) 
                    FROM cola_reportes 
                    WHERE telefono = :telefono 
                      AND codigo_socio = :codigo_socio 
                      AND fecha_consulta = CURRENT_DATE";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':telefono'     => $telefono,
                ':codigo_socio' => $codigoSocio
            ]);

            return ((int)$stmt->fetchColumn()) > 0;
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::haConsultadoSocioHoy error", [
                'telefono'    => $telefono,
                'codigoSocio' => $codigoSocio,
                'error'       => $e->getMessage()
            ]);
            return false;
        }
    }

    public function obtenerSegundosDesdeUltimaConsulta(string $telefono, int $idTipo)
    {
        try {
            $sql = "SELECT EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - creado_en))::int AS segundos 
                    FROM cola_reportes 
                    WHERE telefono = :telefono 
                      AND id_tipo = :id_tipo 
                    ORDER BY creado_en DESC 
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':telefono' => $telefono,
                ':id_tipo'  => $idTipo
            ]);

            $segundos = $stmt->fetchColumn();
            return $segundos !== false && $segundos !== null ? (int)$segundos : null;
        } catch (Exception $e) {
            Logger::error("ReportesBufferRepository::obtenerSegundosDesdeUltimaConsulta error", [
                'telefono' => $telefono,
                'idTipo'   => $idTipo,
                'error'    => $e->getMessage()
            ]);
            return null;
        }
    }
}

