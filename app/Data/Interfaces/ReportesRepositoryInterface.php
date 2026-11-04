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

    /**
     * Reactiva registros en estado FALLIDO regresándolos a PENDIENTE con 0 intentos.
     *
     * @return int Cantidad de registros reactivados
     */
    public function reactivarFallidos(): int;

    /**
     * Cuenta cuántas consultas de un tipo específico ha realizado el teléfono o socio en el día actual.
     *
     * @param string $telefono
     * @param int $codigoSocio
     * @param int $idTipo
     * @return int
     */
    public function contarConsultasHoy(string $telefono, int $codigoSocio, int $idTipo): int;

    /**
     * Cuenta cuántas consultas de un tipo específico ha registrado un código de socio hoy.
     *
     * @param int $codigoSocio
     * @param int $idTipo
     * @return int
     */
    public function contarConsultasPorSocioHoy(int $codigoSocio, int $idTipo): int;

    /**
     * Cuenta cuántas consultas de un tipo específico ha registrado un número de teléfono hoy (global).
     *
     * @param string $telefono
     * @param int $idTipo
     * @return int
     */
    public function contarConsultasPorTelefonoHoy(string $telefono, int $idTipo): int;

    /**
     * Cuenta la cantidad de códigos de socio distintos consultados desde un número de teléfono hoy.
     *
     * @param string $telefono
     * @return int
     */
    public function contarSociosDistintosTelefonoHoy(string $telefono): int;

    /**
     * Verifica si un número de teléfono ya ha interactuado con un código de socio específico hoy.
     *
     * @param string $telefono
     * @param int $codigoSocio
     * @return bool
     */
    public function haConsultadoSocioHoy(string $telefono, int $codigoSocio): bool;

    /**
     * Obtiene la cantidad de segundos transcurridos desde la última consulta de un tipo por este teléfono.
     * Retorna null si no existen registros previos.
     *
     * @param string $telefono
     * @param int $idTipo
     * @return int|null
     */
    public function obtenerSegundosDesdeUltimaConsulta(string $telefono, int $idTipo);
}


