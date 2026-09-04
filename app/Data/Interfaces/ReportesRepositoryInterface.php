<?php

declare(strict_types=1);

namespace App\Data\Interfaces;

/**
 * Contrato para el almacenamiento y recuperación de consultas en el buffer local (cola de reportes).
 */
interface ReportesRepositoryInterface
{
    /**
     * Inserta una consulta en la cola local con estado PENDIENTE.
     *
     * @param array $datos
     * @return bool
     */
    public function guardarEnCola(array $datos): bool;

    /**
     * Obtiene los registros pendientes de envío.
     *
     * @param int $limite
     * @return array
     */
    public function obtenerPendientes(int $limite = 20): array;

    /**
     * Marca un registro como ENVIADO exitosamente.
     *
     * @param int $id
     * @return bool
     */
    public function marcarComoEnviado(int $id): bool;

    /**
     * Registra un intento fallido y actualiza el último error.
     *
     * @param int $id
     * @param string $error
     * @return bool
     */
    public function incrementarIntento(int $id, string $error): bool;
}
