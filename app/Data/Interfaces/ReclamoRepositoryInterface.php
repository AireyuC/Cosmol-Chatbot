<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

interface ReclamoRepositoryInterface {
    /** Crea un nuevo registro de reclamo. */
    public function createReclamo(array $data): int;

    public function findByCodigoSocio(string $codigo): array;
}
