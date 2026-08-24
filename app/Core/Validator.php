<?php

declare(strict_types=1);

namespace App\Core;

class Validator {

    // codigo_socio: solo dígitos, 1-10 caracteres
    public static function codigoSocio(?string $value): bool {
        if ($value === null) return false;
        return (bool) preg_match('/^\d{1,10}$/', $value);
    }

    // tipo_reclamo: solo valores permitidos (lista blanca)
    public static function tipoReclamo(?string $value): bool {
        if ($value === null) return false;
        $allowed = ['agua_turbia', 'fuga', 'sin_servicio', 'presion_baja', 'corte_injustificado', 'baja_presion', 'otro'];
        return in_array(strtolower(trim($value)), $allowed, true);
    }

    // descripcion: texto libre, max 500 caracteres, sin HTML
    public static function descripcion(?string $value): bool {
        if ($value === null || strlen($value) > 500) return false;
        return strip_tags($value) === $value;
    }
}
