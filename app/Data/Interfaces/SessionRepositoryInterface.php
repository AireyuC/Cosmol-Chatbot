<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

interface SessionRepositoryInterface
{
    /**
     * Obtiene la sesión actual por número de teléfono.
     * Si no existe, retorna null.
     */
    public function getSession(string $telefonoWhatsapp): ?array;

    /**
     * Crea o actualiza una sesión.
     */
    public function saveSession(string $telefonoWhatsapp, ?int $codigoSocio, string $estadoActual, int $intentosFallidos): bool;

    /**
     * Resetea una sesión (limpia el código, estado a AWAITING_CODE y cero intentos).
     */
    public function resetSession(string $telefonoWhatsapp): bool;
}
