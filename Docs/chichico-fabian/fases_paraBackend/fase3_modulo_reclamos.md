# Fase 3 — Módulo de Reclamos

> [!IMPORTANT]
> Este módulo es fundamental para el negocio: permite que un asociado reporte una falla técnica (agua turbia, fuga de agua, etc.) **sin salir de WhatsApp**. La ubicación del reclamo **nunca se pide al usuario** — se extrae directamente de la base de datos.

---

## ¿Qué se construye en esta fase?

El flujo completo para registrar un reclamo técnico. A diferencia del módulo de Socios que solo *lee* datos, este módulo *escribe* en la base de datos: crea un nuevo registro de reclamo asociado al socio que lo reporta.

Se construyen **4 archivos** que siguen la misma estructura de capas que la Fase 2.

---

## Decisión de Diseño Crítica: Sin GPS

> [!NOTE]
> A diferencia de otros sistemas, **no se captura la ubicación GPS del asociado desde WhatsApp**. En su lugar, `ReclamoService` llama al módulo de Socios para obtener la dirección almacenada en la BD. Esto simplifica el flujo al usuario y garantiza que la dirección sea la registrada oficialmente en el sistema SAI.

---

## Archivos de esta fase

### 1. `app/Data/Interfaces/ReclamoRepositoryInterface.php`
**¿Qué es?** El contrato que define qué operaciones existen sobre los reclamos.

**¿Qué métodos declara?**
```php
interface ReclamoRepositoryInterface {
    public function createReclamo(array $data): int;
    public function findByCodigoSocio(string $codigo): array;
}
```

**`createReclamo(array $data)`:** Recibe un array con todos los datos del reclamo y devuelve el ID del registro creado (útil para confirmar al asociado con un número de ticket).

**`findByCodigoSocio(string $codigo)`:** Opcional en esta fase, pero útil para que el asociado pueda consultar el estado de sus reclamos anteriores.

**¿Por qué importa esta interfaz?**
Igual que en la Fase 2, garantiza que el repositorio MySQL de ahora y el repositorio Informix de la Fase 5 tengan exactamente la misma firma. El `ReclamoService` no sabrá nunca con cuál está hablando.

---

### 2. `app/Data/Repositories/MySQL/ReclamoRepository.php`
**¿Qué es?** La implementación concreta para insertar reclamos en MySQL (desarrollo local).

**¿Qué hace?**
- Recibe la conexión PDO.
- Implementa `createReclamo()`: prepara y ejecuta un `INSERT` en la tabla `reclamos`.
- Devuelve el `lastInsertId()` — el ID del reclamo recién creado.

**Ejemplo conceptual de `createReclamo()`:**
```php
public function createReclamo(array $data): int {
    $stmt = $this->pdo->prepare(
        "INSERT INTO reclamos
            (codigo_socio, tipo_reclamo, descripcion, direccion, estado, fecha_creacion)
         VALUES
            (:codigo_socio, :tipo, :descripcion, :direccion, 'PENDIENTE', NOW())"
    );
    $stmt->execute([
        ':codigo_socio' => $data['codigo_socio'],
        ':tipo'         => $data['tipo_reclamo'],
        ':descripcion'  => $data['descripcion'],
        ':direccion'    => $data['direccion'],   // ← viene de la BD, no del GPS
    ]);
    return (int) $this->pdo->lastInsertId();
}
```

---

### 3. `app/Modules/Reclamo/ReclamoService.php`
**¿Qué es?** La capa de lógica de negocio del módulo de reclamos.

**¿Qué hace exactamente?**

Este servicio tiene una responsabilidad especial: debe **cruzar datos entre dos módulos**. Por eso recibe *dos repositorios* por inyección de dependencias:

```php
public function __construct(
    ReclamoRepositoryInterface $reclamoRepo,
    SocioRepositoryInterface   $socioRepo     // ← necesita al módulo de Socios
) { ... }
```

**El flujo interno del servicio al registrar un reclamo:**

```
1. Recibe: { codigo_socio, tipo_reclamo, descripcion }
2. Busca al socio en SocioRepository → verifica que exista y esté activo
3. Extrae la dirección del socio desde la BD (no hay GPS)
4. Valida el tipo_reclamo (que sea un tipo válido: AGUA_TURBIA, FUGA, etc.)
5. Llama a reclamoRepo.createReclamo() con todos los datos incluyendo la dirección
6. Devuelve: { exito: true, ticket_id: 42, mensaje: "Reclamo registrado" }
```

**¿Por qué la validación del tipo de reclamo aquí?**
Evita que n8n pueda enviar tipos arbitrarios de reclamo. La validación vive en la capa de negocio, no en el endpoint.

---

### 4. `public/api/reclamos.php`
**¿Qué es?** El punto de entrada HTTP para el módulo de reclamos.

**URL de ejemplo:** `POST http://tu-servidor/api/reclamos.php`

**Body que n8n enviará:**
```json
{
    "codigo_socio": "12345",
    "tipo_reclamo": "AGUA_TURBIA",
    "descripcion":  "El agua llega con color marrón desde ayer"
}
```

**¿Qué hace el endpoint paso a paso?**
1. Incluye `bootstrap.php`.
2. Lee el body JSON enviado por n8n.
3. Instancia `MySQLSocioRepository` y `MySQLReclamoRepository` con la conexión PDO.
4. Instancia `ReclamoService` con ambos repositorios.
5. Llama a `ReclamoService::registrarReclamo()` con los datos del body.
6. Devuelve el resultado en JSON.

**Flujo completo de una llamada:**
```
n8n                   reclamos.php           ReclamoService         SocioRepo    ReclamoRepo    MySQL
 │── POST /api/reclamos ──►│                      │                    │              │            │
 │  {codigo, tipo, desc}   │── registrarReclamo ─►│                    │              │            │
 │                         │                      │── findByCodigo() ─►│              │            │
 │                         │                      │                    │── SELECT ───►│            │
 │                         │                      │◄── {socio+dir} ────│              │            │
 │                         │                      │                                   │            │
 │                         │                      │── createReclamo() ────────────────►│           │
 │                         │                      │                                   │── INSERT ─►│
 │                         │                      │                                   │◄── ID ─────│
 │                         │                      │◄── ticket_id ─────────────────────│            │
 │                         │◄── {exito, ticket} ──│                                   │            │
 │◄── JSON response ───────│                      │                                   │            │
```

---

## Resultado al terminar la Fase 3

Al finalizar tendrás:
- ✅ n8n puede enviar `POST /api/reclamos.php` con el código del socio y el tipo de problema.
- ✅ El sistema valida automáticamente que el socio existe antes de registrar el reclamo.
- ✅ La ubicación se extrae de la BD sin pedirla al usuario (diseño "Fricción Cero").
- ✅ El reclamo se guarda con un ID de ticket que puede ser confirmado al asociado vía WhatsApp.

> [!NOTE]
> La tabla `reclamos` en MySQL debe crearse con los campos: `id`, `codigo_socio`, `tipo_reclamo`, `descripcion`, `direccion`, `estado`, `fecha_creacion`. Esto forma parte del mock de la BD local.
