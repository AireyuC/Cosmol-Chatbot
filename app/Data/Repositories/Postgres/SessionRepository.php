<?php

declare(strict_types=1);

namespace App\Data\Repositories\Postgres;

use App\Data\Interfaces\SessionRepositoryInterface;
use App\Core\Database;
use PDO;

class SessionRepository implements SessionRepositoryInterface
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
        $stmtCheck = $this->db->prepare("SELECT telefono_whatsapp FROM chat_session WHERE telefono_whatsapp = :telefono");
        $stmtCheck->execute(['telefono' => $telefonoWhatsapp]);
        
        if ($stmtCheck->fetch()) {
            // Existe, actualizamos y renovamos el timestamp
            $sql = "UPDATE chat_session 
                    SET codigo_socio = :codigo, 
                        estado_actual = :estado, 
                        intentos_fallidos = :intentos,
                        ultima_interaccion = CURRENT_TIMESTAMP
                    WHERE telefono_whatsapp = :telefono";
        } else {
            $sql = "INSERT INTO chat_session (telefono_whatsapp, codigo_socio, estado_actual, intentos_fallidos) 
                    VALUES (:telefono, :codigo, :estado, :intentos)";
        }

        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            'telefono' => $telefonoWhatsapp,
            'codigo' => $codigoSocio,
            'estado' => $estadoActual,
            'intentos' => $intentosFallidos
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
