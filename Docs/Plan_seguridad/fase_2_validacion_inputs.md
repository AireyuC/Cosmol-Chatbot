# Fase 2 — Validación y Sanitización de Inputs (Prioridad ALTA)

**Problema actual:** `codigo_socio`, `tipo_reclamo` y `descripcion` solo se verifican
como `!= null`, pero no se valida su formato. Un input malicioso podría explorar
el sistema con valores inesperados.

**Solución:** Crear una clase `Validator` que aplique reglas de formato estrictas
(regex, listas blancas, longitud máxima) antes de que cualquier dato llegue al
servicio de negocio.

### Archivos afectados

#### [NEW] `app/Core/Validator.php`
Clase con métodos estáticos de validación reutilizables.

```php
namespace App\Core;

class Validator {

    // codigo_socio: solo dígitos, 1-10 caracteres
    public static function codigoSocio(?string $value): bool {
        if ($value === null) return false;
        return (bool) preg_match('/^\d{1,10}$/', $value);
    }

    // tipo_reclamo: solo valores permitidos (lista blanca)
    public static function tipoReclamo(?string $value): bool {
        $allowed = ['agua_turbia', 'fuga', 'sin_servicio', 'presion_baja', 'otro'];
        return in_array($value, $allowed, true);
    }

    // descripcion: texto libre, max 500 caracteres, sin HTML
    public static function descripcion(?string $value): bool {
        if ($value === null || strlen($value) > 500) return false;
        return strip_tags($value) === $value; // No permite HTML
    }
}
```

#### [MODIFY] [socio.php](file:///c:/Proyectos/Cosmol-Chatbot/public/api/socio.php)
Reemplazar la validación `if ($codigo_socio === null)` por `Validator::codigoSocio()`.

#### [MODIFY] [reclamos.php](file:///c:/Proyectos/Cosmol-Chatbot/public/api/reclamos.php)
Aplicar los tres validadores antes del bloque `try`.

### Verificación
- Enviar `codigo_socio=abc!@#` → respuesta `400` con mensaje de validación
- Enviar `tipo_reclamo=hacking` → respuesta `400` con mensaje de validación
