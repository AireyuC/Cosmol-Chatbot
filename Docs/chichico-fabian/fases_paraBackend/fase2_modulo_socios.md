# Fase 2 — Módulo de Socios (Autenticación y Consultas)

> [!IMPORTANT]
> Este módulo es el **corazón de la autenticación**. Implementa el principio de "Fricción Cero": el asociado solo necesita su **Código de Asociado** para validarse. No hay usuarios, contraseñas ni sesiones complejas.

---

## ¿Qué se construye en esta fase?

El flujo completo que permite a n8n preguntarle al backend: *"¿Existe este asociado y cuánto debe?"* — desde el endpoint HTTP hasta la consulta SQL, pasando por la lógica de negocio.

Se construyen exactamente **4 archivos** que siguen el **patrón repositorio** (separación de capas).

---

## El Patrón Repositorio — ¿por qué usarlo?

```
[n8n Webhook] → [Endpoint PHP] → [Servicio (lógica)] → [Repositorio (datos)] → [Base de Datos]
```

La idea clave: **el Servicio nunca sabe si está hablando con MySQL o Informix**. Solo le pide datos al Repositorio, y el Repositorio se encarga de ejecutar la consulta correcta para cada motor. Esto hace posible la migración de la Fase 5 sin tocar la lógica de negocio.

---

## Archivos de esta fase

### 1. `app/Data/Interfaces/SocioRepositoryInterface.php`
**¿Qué es?** Un "contrato" en PHP puro — define *qué métodos deben existir* pero no *cómo funcionan*.

**¿Qué métodos declara?**
```php
interface SocioRepositoryInterface {
    public function findByCodigo(string $codigo): ?array;
    public function getDeuda(string $codigo): ?array;
    public function getHistorialFacturas(string $codigo): array;
}
```

**¿Por qué es importante?**
Garantiza que tanto el repositorio MySQL (Fase 2) como el repositorio Informix (Fase 5) tendrán exactamente los mismos métodos. Si creas uno nuevo sin implementar todos los métodos, PHP arroja un error inmediatamente.

> [!TIP]
> Piénsalo como una "promesa de contrato": cualquier repositorio que firme esta interfaz se compromete a proveer todos esos métodos. El `SocioService` confía en esa promesa sin importar con qué repositorio esté hablando.

---

### 2. `app/Data/Repositories/MySQL/SocioRepository.php`
**¿Qué es?** La implementación concreta del repositorio para **MySQL** (tu XAMPP local, simulando SAI).

**¿Qué hace?**
- Recibe la conexión PDO (de `Database.php`).
- Implementa `findByCodigo()`: ejecuta una consulta `SELECT` buscando al asociado por su código.
- Implementa `getDeuda()`: consulta las facturas pendientes del asociado.
- Implementa `getHistorialFacturas()`: devuelve el historial de facturas.

**Ejemplo conceptual de `findByCodigo()`:**
```php
public function findByCodigo(string $codigo): ?array {
    $stmt = $this->pdo->prepare(
        "SELECT * FROM socios WHERE codigo_fijo = :codigo LIMIT 1"
    );
    $stmt->execute([':codigo' => $codigo]);
    return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
}
```

**Punto clave:** Si el asociado no existe, devuelve `null`. El Servicio (capa superior) decide qué hacer con ese `null`.

---

### 3. `app/Modules/Socio/SocioService.php`
**¿Qué es?** La capa de **lógica de negocio** — aquí viven las reglas que hacen que el sistema sea inteligente.

**¿Qué hace?**
- Recibe un `SocioRepositoryInterface` por **inyección de dependencias** (no lo instancia él mismo).
- Implementa la lógica de "Fricción Cero":
  1. Llama a `findByCodigo()` con el código recibido.
  2. Si devuelve `null` → responde "Asociado no encontrado".
  3. Si existe → llama a `getDeuda()` para obtener el balance.
  4. Formatea y devuelve los datos listos para ser enviados por WhatsApp.

**¿Por qué la inyección de dependencias?**
```php
// El servicio NO hace esto (mal):
$repo = new MySQLSocioRepository($pdo);

// El servicio SÍ hace esto (bien):
public function __construct(SocioRepositoryInterface $repository) {
    $this->repository = $repository;
}
```
Al recibir el repositorio desde afuera, el Servicio es 100% agnóstico al motor de base de datos. En la Fase 5 solo cambias qué repositorio le pasas — el Servicio no se toca.

---

### 4. `public/api/socio.php`
**¿Qué es?** El **punto de entrada HTTP** — el único archivo que n8n "llama" directamente.

**URL de ejemplo:** `POST http://tu-servidor/api/socio.php`

**¿Qué hace paso a paso?**
1. Incluye `bootstrap.php` (que carga el autoloader y la configuración).
2. Lee el body de la petición POST enviada por n8n (ej. `{"codigo": "12345"}`).
3. Instancia el `MySQLSocioRepository` con la conexión PDO.
4. Instancia el `SocioService` pasándole el repositorio.
5. Llama al servicio con el código recibido.
6. Devuelve el resultado en JSON.

**Flujo completo de una llamada:**
```
n8n                    socio.php              SocioService           SocioRepository         MySQL
  │─── POST /api/socio ──►│                       │                       │                    │
  │   {"codigo":"12345"}  │─── validarSocio() ───►│                       │                    │
  │                       │                       │─── findByCodigo() ───►│                    │
  │                       │                       │                       │─── SELECT ────────►│
  │                       │                       │                       │◄── resultado ──────│
  │                       │                       │◄── array/null ────────│                    │
  │                       │◄── datos formateados ─│                       │                    │
  │◄─── JSON response ────│                       │                       │                    │
```

---

## Resultado al terminar la Fase 2

Al finalizar tendrás:
- ✅ n8n puede llamar a `POST /api/socio.php?codigo=12345` y recibir en JSON si el asociado existe y su deuda.
- ✅ El código está organizado en capas: Endpoint → Servicio → Repositorio → BD.
- ✅ La arquitectura está lista para recibir el módulo de Reclamos (Fase 3) sin conflictos.
- ✅ La migración a Informix (Fase 5) no requerirá tocar nada de esta fase.

> [!NOTE]
> La base de datos MySQL local debe tener una tabla `socios` con al menos los campos `codigo_fijo`, `nombre`, `direccion`, `estado`. Esto se define al montar el mock de SAI en XAMPP.
