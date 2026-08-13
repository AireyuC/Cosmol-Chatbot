# Fase 6 — Hardening del `.env` y Separación dev/prod (Prioridad MEDIA)

**Problema actual:** El `.env` actual mezcla configuración de desarrollo y producción.
`COSMOL_API_URL` apunta a producción real mientras `APP_ENV=development`.
`env.example` no documenta las nuevas variables de seguridad.

**Solución:** Limpiar y documentar bien el `.env` y el `env.example`. No crear archivos
separados (para mantener la arquitectura Docker actual), sino establecer convenciones
claras.

### Archivos afectados

#### [MODIFY] [.env](file:///c:/Proyectos/Cosmol-Chatbot/.env)
- Agregar `API_INTERNAL_TOKEN` con valor real generado
- Agregar `ALLOWED_ORIGIN`
- Verificar que `APP_ENV=development` y `COSMOL_API_URL=` (vacío) durante desarrollo
- Separar con comentarios claras cada sección

#### [MODIFY] [env.example](file:///c:/Proyectos/Cosmol-Chatbot/env.example)
- Documentar **todas** las variables (incluyendo las nuevas de Fases 1, 2 y 3)
- Agregar instrucciones de cómo generar el token (`openssl rand -hex 32`)
- Agregar comentarios indicando qué cambiar en producción vs desarrollo
