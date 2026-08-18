<?php

declare(strict_types=1);

namespace App\Data\Repositories\MySQL;

use App\Data\Interfaces\SessionRepositoryInterface;
use App\Core\Database;
use PDO;

class RepositorioSessionMySQL implements SessionRepositoryInterface
{
    /**
     * @var PDO
     */
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getSession(string $telefonoWhatsapp): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM chat_session WHERE telefono_whatsapp = :telefono");
        $stmt->execute(['telefono' => $telefonoWhatsapp]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function saveSession(string $telefonoWhatsapp, ?int $codigoSocio, string $estadoActual, int $intentosFallidos): bool
    {
        // Upsert (Insert or Update)
        $sql = "INSERT INTO chat_session (telefono_whatsapp, codigo_socio, estado_actual, intentos_fallidos) 
                VALUES (:telefono, :codigo, :estado, :intentos)
                ON DUPLICATE KEY UPDATE 
                codigo_socio = :codigo_upd, 
                estado_actual = :estado_upd, 
                intentos_fallidos = :intentos_upd";

        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'telefono' => $telefonoWhatsapp,
            'codigo' => $codigoSocio,
            'estado' => $estadoActual,
            'intentos' => $intentosFallidos,
            'codigo_upd' => $codigoSocio,
            'estado_upd' => $estadoActual,
            'intentos_upd' => $intentosFallidos
        ]);
    }

    public function resetSession(string $telefonoWhatsapp): bool
    {
        $sql = "UPDATE chat_session 
                SET codigo_socio = NULL, estado_actual = 'AWAITING_CODE', intentos_fallidos = 0 
                WHERE telefono_whatsapp = :telefono";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['telefono' => $telefonoWhatsapp]);
    }
}
