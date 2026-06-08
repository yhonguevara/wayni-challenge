# BCRA – Central de Deudores del Sistema Financiero
## Especificación de Formato de Archivos

> **Fuente:** Banco Central de la República Argentina (BCRA)
> **Documento original:** LEAME DEUDORES.pdf
> **Nota editorial:** Las columnas `Pos. Inicio` y `Pos. Fin` no figuran en el documento original. Fueron calculadas a partir de los campos de longitud para uso directo en el parser.

---

## Aviso Legal

La información contenida en esta base de datos no incluye las novedades presentadas por las entidades informantes ni requerimientos de organismos competentes de los cuales se haya tomado conocimiento con posterioridad a la fecha de corte utilizada para la elaboración de la presente publicación.

Deben tenerse en cuenta las Comunicaciones "C" relacionadas con la Central de Deudores del Sistema Financiero, por medio de las cuales se hubieran suprimido o rectificado registros.

Se difundirá por deudor, la información que en cada caso se expone, remitida por:

- Entidades Financieras
- Proveedores no financieros de crédito
- Fiduciarios de fideicomisos financieros
- Sociedades de garantía recíproca y Fondos de garantía de carácter público
- Proveedores de servicios de créditos entre particulares a través de plataformas.

No se incluirán aquellos deudores identificados por las entidades por estar alcanzados por los términos del artículo 16 inciso 6 (parte pertinente) y/o artículo 26 inciso 4 y/o del artículo 38 inciso 4 de la Ley 25.326 de Protección de los Datos Personales, en los meses que correspondiere.

Asimismo, se identificarán aquellos deudores cuya información se encuentre sometida a revisión o en proceso judicial de acuerdo con lo establecido en los artículos 16 inciso 6 y/o en el artículo 38 inciso 3 de la mencionada ley, respectivamente.

> ⚠️ **Los importes están expresados en miles de pesos con un decimal.**

---

## Diseños de Registro

---

### 1. Archivo principal: `deudores.txt`

> **Diseño de registro de la "Central de deudores del sistema financiero"**
> Longitud total por registro: **171 caracteres** (+ terminador de línea)

| N° Campo | Nombre | Tipo | Long. | Pos. Inicio | Pos. Fin | Observaciones |
|:---:|---|:---:|:---:|:---:|:---:|---|
| 1 | Código de entidad | Numérico | 5 | 1 | 5 | Código de la entidad |
| 2 | Fecha de información | Numérico | 6 | 6 | 11 | AAAAMM |
| 3 | Tipo de identificación | Numérico | 2 | 12 | 13 | `11` = clave de identificación fiscal (CUIT, CUIL o CDI). Punto 2.1. del apartado A del T.O. |
| 4 | N° de identificación | **Carácter** | 11 | 14 | 24 | Número de identificación (punto 2.2. del apartado A del T.O.) |
| 5 | Actividad | Numérico | 3 | 25 | 27 | Código de actividad (punto 7. del apartado A del T.O.) y Anexo II de la Sección 3. Deudores del Sistema Financiero del Régimen Informativo Contable Mensual. |
| 6 | Situación `(*)` | Numérico | **2** | 28 | 29 | Situación (punto 1. del apartado B del T.O.). Ver tabla de códigos abajo. |
| 7 | Préstamos / Total de garantías afrontadas | Numérico | 12 | 30 | 41 | Préstamos, Otros créditos por intermediación financiera, Créditos por arrendamientos financieros, Créditos diversos y Obligaciones Negociables y Títulos de deuda de FF (punto 2.1. del apartado B del T.O.). Total de garantías afrontadas (SGR y FGCP). **Once enteros y un decimal.** |
| 8 | Sin uso | Numérico | 12 | 42 | 53 | — |
| 9 | Garantías otorgadas | Numérico | 12 | 54 | 65 | Garantías otorgadas y Responsabilidades eventuales (punto 2.2 y 2.3 del apartado B del T.O.). Once enteros y un decimal. |
| 10 | Otros conceptos | Numérico | 12 | 66 | 77 | Otros conceptos (puntos 3.1. y 3.2. del apartado B del T.O.). Once enteros y un decimal. |
| 11 | Garantías preferidas "A" | Numérico | 12 | 78 | 89 | Con garantía preferida "A" (punto 2.1.6.1. del apartado B del T.O.). Once enteros y un decimal. |
| 12 | Garantías preferidas "B" | Numérico | 12 | 90 | 101 | Con garantía preferida "B" (punto 2.1.6.2. del apartado B del T.O.). Once enteros y un decimal. |
| 13 | Sin garantías preferidas | Numérico | 12 | 102 | 113 | Sin garantía preferida (punto 2.1.6.3. del apartado B del T.O.). Once enteros y un decimal. |
| 14 | Contragarantías preferidas "A" | Numérico | 12 | 114 | 125 | Con contragarantía preferida "A" (puntos 2.3.1., 2.3.1.1. y 2.3.2.1. del apartado B del T.O.). Once enteros y un decimal. |
| 15 | Contragarantías preferidas "B" | Numérico | 12 | 126 | 137 | Con contragarantía preferida "B" (puntos 2.3.2., 2.3.1.2. y 2.3.2.2. del apartado B del T.O.). Once enteros y un decimal. |
| 16 | Sin contragarantías preferidas | Numérico | 12 | 138 | 149 | Sin contragarantía preferida (puntos 2.3.1.3. y 2.3.2.3. del apartado B del T.O.). Once enteros y un decimal. |
| 17 | Previsiones | Numérico | 12 | 150 | 161 | Previsiones (punto 6 del apartado B del T.O.). Once enteros y un decimal. |
| 18 | Deuda cubierta | Numérico | 1 | 162 | 162 | `0`: Deudores no cubiertos totalmente por garantías y contragarantías preferidas "A". `1`: Deudores totalmente cubiertos por garantías y contragarantías preferidas "A" y que no son objeto de clasificación. |
| 19 | Proceso Judicial / Revisión | Numérico | 1 | 163 | 163 | `0`: Dato no observado. `1`: Información sometida a proceso judicial (artículo 38, inciso 3 Ley 25.326). `2`: Información sometida a revisión (artículo 16 inc. 6 Ley 25.326). |
| 20 | Refinanciaciones `(**)` | Numérico | 1 | 164 | 164 | `0` = NO; `1` = SÍ; `9` = No Aplicable. Punto 8.1. del apartado B del T.O. |
| 21 | Recategorización obligatoria | Numérico | 1 | 165 | 165 | `0` = NO; `1` = SÍ. Punto 8.1. del apartado B del T.O. |
| 22 | Situación jurídica `(**)` | Numérico | 1 | 166 | 166 | `0` = NO; `1` = SÍ; `9` = No Aplicable. Punto 8.1. del apartado B del T.O. |
| 23 | Irrecuperables por disposición técnica `(**)` | Numérico | 1 | 167 | 167 | `0` = NO; `1` = SÍ; `9` = No Aplicable. Punto 8.1. del apartado B del T.O. |
| 24 | Días de atraso | Numérico | 4 | 168 | 171 | Punto 8.2. del apartado B del T.O. |

#### Notas al pie

> `(*)` Codificación de situación — ver tabla en §1.1.
>
> `(**)` `9` = No aplicable. Campo no aplicable para Proveedores no Financieros de crédito, SGR/FGCP y/o Proveedores de servicios de créditos entre particulares a través de plataformas.

#### 1.1 Tabla de códigos de situación (Campo 6)

| Código | Situación |
|:---:|---|
| `01` | Situación Normal |
| `21` | Riesgo Bajo |
| `23` | En tratamiento especial *(a partir de la información de abril 2020)* |
| `03` | Riesgo Medio |
| `04` | Riesgo Alto |
| `05` | Irrecuperable |
| `11` | Con asistencias cubiertas en su totalidad con garantías preferidas "A" |

---

### 2. Archivos rectificativos: `inf_ret.txt` e `inf_retparc.txt`

> Rectificativas completas del R.I. (`inf_ret.txt`) y rectificativas parciales (`inf_retparc.txt`) según Sección 61 de Presentación de Informaciones al BCRA.
> Corresponden a informaciones rectificativas cuando estuvieren originadas en cambio de situación y/o monto de deuda.
> **La situación y monto se mostrarán en ceros para casos de eliminación de registros de la Central.**

| N° Campo | Nombre | Tipo | Long. | Pos. Inicio | Pos. Fin | Observaciones |
|:---:|---|:---:|:---:|:---:|:---:|---|
| 1 | Código de entidad | Numérico | 5 | 1 | 5 | Código de la entidad |
| 2 | Fecha de información | Numérico | 6 | 6 | 11 | AAAAMM |
| 3 | Tipo de identificación | Numérico | 2 | 12 | 13 | `11` = clave de identificación fiscal (CUIT, CUIL o CDI) |
| 4 | Número de identificación | Carácter | 11 | 14 | 24 | — |
| 5 | Denominación | Carácter | 55 | 25 | 79 | — |
| 6 | Situación | Numérico | 2 | 80 | 81 | — |
| 7 | Monto | Numérico | 12 | 82 | 93 | Once enteros y un decimal |

---

### 3. Archivo de morosos de ex entidades: `morexent.txt`

> El archivo `morexent.txt` solo se difundirá si hubiera datos informados por las ex entidades financieras.

| N° Campo | Nombre | Tipo | Long. | Pos. Inicio | Pos. Fin | Observaciones |
|:---:|---|:---:|:---:|:---:|:---:|---|
| 1 | Fecha de información | Numérico | 6 | 1 | 6 | AAAAMM |
| 2 | Denominación | Carácter | 120 | 7 | 126 | Denominación del ente residual informante |
| 3 | Tipo de identificación | Numérico | 2 | 127 | 128 | `11` = clave de identificación fiscal (CUIT, CUIL o CDI) |
| 4 | Número de identificación | Carácter | 11 | 129 | 139 | — |
| 5 | Proceso Judicial / Revisión | Numérico | 1 | 140 | 140 | `0`: Dato no observado. `1`: Información sometida a proceso judicial (Artículo 38, inciso 3 Ley 25326). `2`: Información sometida a revisión (Artículo 16 inc. 6 Ley 25326). |

> Los deudores y los morosos de ex entidades financieras cuyas identificaciones no están empadronadas se encuentran en los archivos `nomdeu.txt` y `nommor.txt` respectivamente.
> El archivo `nommor.txt` solo se difundirá si hubiera datos informados por las ex entidades financieras.

---

### 4. Archivos de denominaciones: `nomdeu.txt` y `nommor.txt`

| N° Campo | Denominación | Tipo | Long. |
|:---:|---|:---:|:---:|
| 1 | Número de identificación | Numérico | 11 |
| 2 | Denominación | Carácter | 55 |

---

### 5. Maestro de entidades: `maeent.txt`

| N° Campo | Denominación | Tipo | Long. |
|:---:|---|:---:|:---:|
| 1 | Código de entidad | Numérico | 5 |
| 2 | Nombre de entidad | Carácter | 70 |

---

### 6. Últimos 24 meses de información: `24DSF.txt`

> Los archivos pueden descargarse comprimidos con nombre `24DSFAAAAMM`.
>
> Solo se incluirán en este archivo los deudores de entidades activas y los registros de aquellos deudores en situación normal de entidades dadas de baja.
>
> ⚠️ Los campos marcados con `(*)` se repiten para cada uno de los 24 meses de información hasta llegar al **campo 75**. Al descomprimir el archivo, el primer conjunto de 6 campos corresponde al período de la Central de deudores mensual difundida en `deudores.txt`, aun cuando el deudor no tenga información de ese mes para una determinada entidad. Contiguamente se exponen los 23 períodos subsiguientes.

| N° Campo | Nombre | Tipo | Long. | Observaciones |
|:---:|---|:---:|:---:|---|
| 1 | Código de entidad | Numérico | 5 | Código de la entidad |
| 2 | Tipo de identificación | Numérico | 2 | Tipo de identificación (punto 2.1 del apartado A del T.O.) |
| 3 | N° de identificación | Carácter | 11 | Número de identificación (punto 2.2 del apartado A del T.O.) |
| 4 `(*)` | Situación | Numérico | 2 | Situación (punto 1. del apartado B del T.O.) |
| 5 `(*)` | Monto | Numérico | 12 | Financiaciones y Otros conceptos (puntos 2 y 3 del apartado B del T.O.). Once enteros y un decimal. |
| 6 `(*)` | Proceso Judicial / Revisión | Numérico | 1 | `0`: Dato no observado. `1`: Información sometida a proceso judicial (artículo 38, inciso 3 Ley 25.326). `2`: Información sometida a revisión (artículo 16 inc. 6 Ley 25.326). |

---

### 7. Fecha de inicio en situación normal: `1DSF.txt`

> Se difundirá respecto de los deudores que en la última Central de Deudores se encuentren en **situación 1 (normal) en todas las entidades**.
>
> La fecha de origen será la primera desde la cual el deudor registre situación 1 en forma ininterrumpida, sin perjuicio de que existiera algún período intermedio donde no hubiera sido incluido en la Central de Deudores.

| N° Campo | Nombre | Tipo | Long. | Observaciones |
|:---:|---|:---:|:---:|---|
| 1 | Tipo de identificación | Numérico | 2 | Tipo de identificación (punto 2.1 del apartado A del T.O.) |
| 2 | N° de identificación | Carácter | 11 | Número de identificación (punto 2.2 del apartado A del T.O.) |
| 3 | Fecha de origen situación 1 | Numérico | 6 | AAAAMM |

---

## Aclaración sobre actualización

> A fin de brindar la fecha de actualización de los datos que componen la Central de Deudores del Sistema Financiero, se incluye un archivo cuya denominación es la correspondiente a la fecha de generación de la base.

---

## ⚠️ Correcciones al SDD respecto de este documento

Las siguientes diferencias fueron detectadas entre el SDD preliminar y la especificación oficial. **Deben aplicarse antes de implementar el parser:**

| Campo | Valor en SDD (incorrecto) | Valor real (este documento) |
|---|---|---|
| Campo 3 – Tipo de identificación | `80` | **`11`** |
| Campo 6 – Situación (longitud) | 1 carácter | **2 caracteres** |
| Campo 6 – Situación (códigos válidos) | `1`–`6` | **`01`, `03`, `04`, `05`, `11`, `21`, `23`** |
| Campo 4 – Tipo de campo | Numérico | **Carácter** (puede contener espacios o ceros iniciales) |
| Campo 7 – Longitud | 11 | **12** (once enteros + un decimal) |
| Posición Campo 6 | byte 27 | **bytes 28–29** |
| Posición Campo 7 | bytes 28–38 | **bytes 30–41** |