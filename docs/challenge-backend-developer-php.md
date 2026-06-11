# 🧪 Desafío Técnico – Wayni Móvil

## Introducción

A continuación se detalla una evaluación de conocimiento técnica de la compañía Waynimóvil, en la cual se busca conocer las habilidades técnicas del futuro colaborador. Se evalúa principalmente la finalización de la actividad, luego el desarrollo y solución propuesta y finalmente, el tiempo transcurrido para su finalización.

---

## 🎯 Objetivo del Desafío

Desarrollar e implementar una solución basada en arquitectura de microservicios, destinada al procesamiento del archivo TXT proporcionado por el Banco Central de la República Argentina (BCRA), correspondiente al padrón de deudores. La solución deberá realizar la transformación de los datos según las reglas de negocio preestablecidas y proceder al almacenamiento de los mismos en una base de datos. Una vez finalizado el procesamiento, el sistema deberá emitir una notificación, ya sea vía correo electrónico, webhook o consola, informando la culminación exitosa del proceso.

---

## 🛠 Tecnologías Permitidas

### Lenguajes / Frameworks
- **PHP** (únicamente con **Laravel**)

### Bases de datos permitidas
- **MySQL**
- **PostgreSQL**

### Infraestructura y herramientas adicionales
- **Docker / Docker Compose**
- **LocalStack** (para simular servicios AWS como DynamoDB, S3 o SQS)
- **Git** para versionado

---

## 📦 Material Proporcionado

- Se entrega un ZIP con información pública provista por el banco central: `deudores_bcra.txt`
- Un documento PDF con especificaciones de formato del archivo original del BCRA.

---

## 📐 Requisitos Funcionales

### Importador de Archivo

- Debe exponer un endpoint `/upload` o aceptar la ruta del archivo localmente.
- Lee el archivo TXT en base a las especificaciones proporcionadas.
- Procesa los datos y genera dos estructuras:

**Deudores:**
- `nro_identificacion` (Campo 4 - CUIT/CUIL)
- `situacion_maxima` (Máximo valor entre registros coincidentes - Campo 6)
- `suma_total_prestamos` (Suma de préstamos - Campo 7)

**Entidades:**
- `codigo_entidad` (Campo 1)
- `suma_total_prestamos` (Suma préstamos agrupado por entidad)

- Inserta o actualiza los datos en la base seleccionada.
- **Opcional:** guarda el archivo original en S3 (LocalStack) y/o envía una notificación a una cola (SQS).

### API de Consulta

Expone los siguientes endpoints:

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| `GET` | `/deudores/{cuit}` | Consulta deudor por CUIT |
| `GET` | `/entidades/{codigo}` | Consulta entidad por código |
| `GET` | `/deudores/top/{n}` | Devuelve los N deudores con mayor suma de préstamos |
| `GET` | `/deudores?situacion=X` | *(Opcional)* Filtros por situación |

### Notificación de Finalización

Al finalizar el procesamiento del archivo:
- Envía un correo electrónico o realiza un webhook POST notificando el fin del proceso.
- **Alternativa opcional:** imprime un log estructurado indicando tiempo total y resumen de carga.

---

## 🧱 Requisitos Técnicos

- El sistema debe estar contenerizado con Docker, permitiendo levantar todos los servicios con `docker-compose up`.
- Las credenciales y configuraciones deben estar externalizadas mediante `.env`.
- Documentación clara de endpoints y cómo ejecutar el sistema (`README`).
- Estructura limpia, modular y coherente entre servicios.

---

## ✅ Criterios de Evaluación

| Criterio | Peso |
|----------|------|
| Funcionalidad completa | **Alto** |
| Uso de microservicios | Medio |
| Uso correcto de Laravel (Funcionalidad del Framework) | **Alto** |
| Calidad del código y organización | **Alto** |
| UnitTest o algún concepto de pruebas | **Alto** |
| Persistencia en base permitida | Medio |
| Integración con Docker | Medio |
| Notificación al finalizar proceso | Medio |
| Documentación y facilidad de uso | Bajo |
| Uso de LocalStack (si aplica) | Bajo |
| Tiempo de entrega | Bajo |

---

## 📝 Bonus (No Obligatorios)

- Uso de SQS para procesamiento asíncrono
- Logs estructurados con duración del proceso
- Tests unitarios o de integración
- Interface de administración sencilla (FrontEnd)

---

## 🔨 Materiales de trabajo

- **ZIP con la información del padrón BCRA:**
  [Descargar archivo](https://drive.google.com/file/d/1JTe5nPrkEsQGToQaASlJLTAvHwmd_fS3/view?usp=drive_link)

- **Documentación sobre la Especificación del Archivo:**
  `LEAME DEUDORES.pdf` *(incluido en el ZIP)*

---

## 🌐 Links de Referencia

> No hace falta la lectura, solo de referencia.

| Recurso | URL |
|---------|-----|
| BCRA – Especificación oficial del archivo | [http://www.bcra.gov.ar/pdfs/texord/t-SO-s03.pdf](http://www.bcra.gov.ar/pdfs/texord/t-SO-s03.pdf) |
| Node.js | [https://nodejs.org/](https://nodejs.org/) |
| LocalStack | [https://www.localstack.cloud/](https://www.localstack.cloud/) |
| Laravel Framework Docs | [https://laravel.com/docs](https://laravel.com/docs) |

---

*¡Mucha Suerte!*