# Resumen Ejecutivo — Chatbot COSMOL R.L.

> **Proyecto:** Sistema Automatizado de Atención al Asociado vía WhatsApp (Fricción Cero)  
> **Institución:** Cooperativa de Servicios Públicos Montero "COSMOL" R.L.  
> **Canal Oficial:** Meta WhatsApp Cloud API  
> **Infraestructura:** Contenerizada con Docker, Proxy Caddy SSL y Ubuntu Server  
> **Estado:** 100% Operativo y Validado en Servidor de Pruebas

---

## 1. Visión General del Proyecto

El **Chatbot COSMOL** es una solución digital de atención continua (24/7) diseñada para acercar los servicios de la Cooperativa a sus más de 30.000 asociados en Montero, permitiéndoles realizar consultas de deuda, pagar en línea, reportar reclamos técnicos con geolocalización y solicitar reconexiones de servicio directamente desde WhatsApp.

El sistema fue concebido bajo el principio de **fricción cero**: el asociado interactúa de forma natural identificándose únicamente con su **Código de Asociado (Código Fijo)**, sin contraseñas difíciles de recordar, sin registros previos y sin necesidad de instalar aplicaciones adicionales.

---

## 2. Problemática Resuelta

| Situación Tradicional en Oficinas | Solución Provista por el Chatbot |
|---|---|
| **Colas y saturación:** Aglomeración física de asociados en ventanillas solo para consultar montos adeudados o pedir duplicados de factura. | **Autoconsulta inmediata 24/7:** Desglose instantáneo de facturas vencidas, metros cúbicos consumidos y monto total en menos de 3 segundos. |
| **Reclamos sin ubicación:** Quejas técnicas recibidas por teléfono o ventanilla con direcciones imprecisas (*"frente a la tienda de don Juan"*), dificultando el trabajo de las cuadrillas. | **Captura técnica obligatoria con GPS:** El bot exige la ubicación GPS nativa de WhatsApp, fotografía del problema y descripción antes de despachar la orden. |
| **Horario limitado:** Atención técnica y comercial sujeta exclusivamente al horario administrativo de oficina. | **Disponibilidad continua:** Recepción de reclamos y consultas los 365 días del año a cualquier hora. |
| **Morosidad y cobros:** Necesidad de acudir a puntos de cobranza físicos para regularizar deudas. | **Pasarelas de pago duales:** Enlaces directos hacia **Multipago** y **Pago al Paso** para cancelar con tarjeta o QR desde el móvil. |
| **Costes de comunicación:** Los SMS masivos o llamadas tradicionales representan costos fijos recurrentes. | **Modelo $0.00 USD en Meta:** Solo atiende mensajes iniciados por el socio (*User-Initiated*), con cero coste por mensaje para la cooperativa. |

---

## 3. Módulos Operativos y Experiencia del Asociado

```
                           ┌────────────────────────────┐
                           │   Socio escribe al Bot     │
                           └─────────────┬──────────────┘
                                         │ Ingresa Código Fijo
                                         ▼
                           ┌────────────────────────────┐
                           │     Menú Principal         │
                           │   (Opciones Interactivas)  │
                           └─────────────┬──────────────┘
           ┌─────────────────────────────┼─────────────────────────────┐
           ▼                             ▼                             ▼
┌──────────────────────┐      ┌──────────────────────┐      ┌──────────────────────┐
│  Consulta y Deudas   │      │   Reclamos Técnicos  │      │  Reconexión de Agua  │
├──────────────────────┤      ├──────────────────────┤      ├──────────────────────┤
│ • Desglose facturas  │      │ • Captura GPS nativo │      │ • Validación de mora │
│ • Consumo en m³      │      │ • Foto de evidencia  │      │   (Corte si > 2 fac) │
│ • Enlaces Multipago  │      │ • Glosa del problema │      │ • Foto del medidor   │
│   y Pago al Paso     │      │ • Envío a cuadrillas │      │ • Emisión de orden   │
└──────────────────────┘      └──────────────────────┘      └──────────────────────┘
```

### 3.1 Consulta de Cuentas y Pasarelas de Pago
El socio ingresa su código fijo y el bot recupera de inmediato sus facturas pendientes con período, fecha de vencimiento y monto total. Se proporcionan botones directos a las dos pasarelas habilitadas:
* **Multipago:** `https://multipago.com/service/cosmol_payment/first`
* **Pago al Paso:** `https://red.pagoalpaso247.net/servicio/cosmol`

### 3.2 Registro Georreferenciado de Reclamos (Fugas y Agua Turbia)
Para resolver la falta de catastro georreferenciado en sistemas heredados, el bot aplica un flujo guiado estricto donde el socio envía:
1. **Ubicación GPS en tiempo real** enviada desde la herramienta de ubicación de WhatsApp.
2. **Fotografía del medidor o daño**, descargada y almacenada en el servidor para visualización técnica.
3. **Glosa o referencias escritas** para orientar a los operadores de plomería o alcantarillado.

### 3.3 Reconexiones Automáticas con Regla Financiera
El sistema valida de forma autónoma la situación de mora del asociado:
* Si el socio adeuda **más de 2 facturas**, la reconexión es **rechazada automáticamente**, explicándole que debe regularizar su deuda antes de solicitar el servicio.
* Si cumple la regla (≤ 2 facturas), se registra la orden con coordenadas GPS y fotografía del medidor para su restitución inmediata.

---

## 4. Arquitectura Tecnológica e Innovación

El sistema destaca por una arquitectura moderna, segura, contenerizada y completamente desacoplada:

1. **Canal Oficial Meta Cloud API (v25.0):** Empleo de las últimas especificaciones de Graph API, utilizando mensajes interactivos nativos (List Messages y Quick Reply Buttons).
2. **Experiencia Visual Humanizada en n8n:**
   * **Doble Check Azul Inmediato:** El mensaje del socio se marca como leído en cuanto entra al sistema.
   * **Animación "COSMOL está escribiendo...":** El socio ve la animación de digitación mientras el sistema consulta las bases de datos, eliminando la sensación de congelamiento o lentitud.
   * **Resiliencia Total:** Nodos estéticos con tolerancia a fallos que jamás bloquean el mensaje ante micro-cortes.
3. **Backend PHP 7.3 Estructurado y Seguro:**
   * Desarrollado en PHP Vanilla modular sin frameworks pesados, garantizando máxima velocidad y mínimo consumo de memoria RAM.
   * Blindaje perimetral: Autenticación por token secreto interno (`X-Internal-Token`), Rate Limiting por IP, sanitización de inputs y control de modo mantenimiento con filtro anti-spam.
4. **Infraestructura con Caddy y Docker:**
   * Alojado en **Ubuntu Server** institucional.
   * **Caddy** administra automáticamente los certificados SSL (HTTPS), aislando la API PHP y PostgreSQL del tráfico público exterior.
5. **Transición Transparente hacia el Sistema Central (SAI Informix):**
   * El sistema opera actualmente con una base de datos PostgreSQL de pruebas bajo el estándar **ANSI SQL**.
   * Diseñado mediante el **Patrón Repositorio**: cuando el departamento de sistemas de COSMOL habilite los endpoints REST del servidor Informix, la conexión se activará únicamente cambiando una variable en `.env`, sin necesidad de reprogramar el bot.

---

## 5. Sinergia con el Ecosistema "COSMOL-Reportes"

El Chatbot no es una isla; se comunica en tiempo real con el software hermano **COSMOL-Reportes**:
* **Estadísticas y Auditoría:** Cada interacción, consulta y reclamo se reporta de forma asíncrona a la plataforma de administración web.
* **Cola de Resiliencia ante Contingencias:** Si el servidor de reportes entra en mantenimiento o experimenta cortes, el chatbot almacena las consultas en un buffer local (`cola_reportes`) y un despachador programado (`cron_flush_reportes.php`) las sincroniza automáticamente al restablecerse la red.
* **Gestión Operativa:** Los reclamos y reconexiones recibidos por WhatsApp alimentan directamente la bandeja de trabajo de los operadores técnicos de la Cooperativa.

---

## 6. Impacto y Beneficios Institucionales

* **Descongestión Operativa:** Reducción estimada de más del 60% en la afluencia física de socios a ventanillas para consultas básicas de saldo.
* **Optimización en Cuadrillas Técnicas:** Llegada directa a la ubicación exacta de las fugas gracias a las coordenadas GPS y fotos capturadas, reduciendo el tiempo de respuesta y los costes de combustible.
* **Aceleración de Cobranza:** Facilita el pago inmediato desde el teléfono a través de pasarelas digitales oficiales.
* **Disponibilidad 24/7 sin Coste en Recursos Humanos:** Atención automatizada permanente sin necesidad de turnos nocturnos o de fin de semana para atención básica.
* **Cero Costo en Facturación Meta:** Diseñado estratégicamente para no pagar ni un centavo en conversaciones comerciales iniciadas por el negocio.
