<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

interface SessionRepositoryInterface
{
    public function getSession(string $telefonoWhatsapp): ?array;


    public function saveSession(string $telefonoWhatsapp, ?int $codigoSocio, string $estadoActual, int $intentosFallidos): bool;


    public function resetSession(string $telefonoWhatsapp): bool;
}
