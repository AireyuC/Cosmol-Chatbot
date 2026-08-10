<?php

namespace App\Data\Interfaces;

interface ReclamoRepositoryInterface {
    /**
     * Crea un nuevo registro de reclamo.
     * 
     * @param array $data Los datos del reclamo (codigo_socio, tipo_reclamo, descripcion, direccion)
     * @return int Devuelve el ID generado para el ticket del reclamo.
     */
    public function createReclamo(array $data): int;

    /**
     * Obtiene el historial de reclamos de un socio.
     * 
     * @param string $codigo El código fijo del socio.
     * @return array Lista de reclamos.
     */
    public function findByCodigoSocio(string $codigo): array;
}
