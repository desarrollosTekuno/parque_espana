# Análisis de `public/Plantilla_Migracion_DatosDP_230626.xlsx`

**Fecha:** 2026-09-30
**Archivo analizado:** `public/Plantilla_Migracion_DatosDP_230626.xlsx` (2.5 MB, última modificación 2026-09-08)
**Origen de los datos:** sistema **Fox** de Parque España (así lo dice la hoja Instrucciones).
**Contra qué se comparó:** estructura real de la BD `PARQUES` y lógica del sistema (ver `Docs/Analisis_Sistema_Carga_Inicial.md`).

> Este documento solo analiza. No se modificó ningún archivo.

---

## 1. Resumen

- Es la plantilla que alimenta el importador actual (`php artisan migrate:data`): las hojas están en las posiciones y con los encabezados exactos que ese importador espera.
- **Sí trae datos reales y en volumen**, pero **solo de Parque España II**:

| Hoja | Filas con datos | Contenido real |
| --- | ---: | --- |
| 1. Usuarios | 13,137 | Personas (titulares y familiares) |
| 2. Membresias | 5,398 (5,369 cuentas distintas) | Cuentas PE2 (4,930 activas, 468 inactivas) |
| 3. Integrantes | 13,620 | Relación persona ↔ cuenta |
| 4. Domicilios | 5,405 | Domicilios, casi todos de titulares |
| 5. Empleo | 0 | Vacía |
| 6. Catálogos | — | Catálogos de referencia (desactualizados respecto a la BD) |
| 8. Historial por Período | 15,291 | Recibos cobrados en PE2 del 2026-01-02 al 2026-06-22 (9,537 recibos) |
| 9. Casilleros | 3,632 | Inventario completo de casilleros PE2 + 2,827 asignaciones |

- **Del formato** (colores, filas de título, encabezado e instrucciones) se toma el estándar a replicar (sección 2).
- **De las hojas y columnas** hay que corregir bastante: faltan campos que la BD necesita, la marca de obligatorio/opcional no refleja la BD, los catálogos no coinciden con los del sistema, y el historial de pagos viene en una forma que **no permite reconstruir correctamente pagos y aplicaciones** (sección 4).
- **Falta por completo Parque España I**, los adeudos pendientes (saldos), y varios bloques que el sistema necesita para operar (sección 5).

---

## 2. Formato a replicar (estándar visual)

Medido directamente del archivo:

### Hojas de captura

| Fila | Uso | Estilo |
| --- | --- | --- |
| 1 | Título de la hoja (ej. `1. USUARIOS`), celda combinada a lo ancho de las columnas | Fondo `#1A252F`, Arial 12 negrita blanca, centrado. Alto 25.5 |
| 2 | Encabezados de columna | Arial 10 negrita blanca, centrado con ajuste de texto. **Rojo `#C0392B` = obligatorio**, **azul `#2980B9` = opcional**. Alto 31.5 |
| 3 | Instrucción corta por columna | Fondo `#ECF0F1`, Arial 8 cursiva gris `#555555`, centrado con ajuste. Alto 42 |
| 4 en adelante | Datos | Sin formato especial |

- **Encabezados** en MAYÚSCULAS con guion bajo y sin acentos: `ID_ORIGEN`, `NO_CUENTA`, `FECHA_NACIMIENTO`, `MONTO_PAGADO`. El importador busca las columnas por este texto exacto, así que el encabezado funciona también como "llave técnica".
- **Instrucciones** cortas y concretas: formato (`Formato: YYYY-MM-DD`, `10 dígitos`, `Sí / No`), dependencia entre hojas (`Debe existir en hoja usuarios`) o catálogo (`Ver hoja Catálogos`).
- Nombre de hoja con número de orden: `1. Usuarios`, `2. Membresias`…
- Al final de algunas hojas hay una nota con `⚠` (ej. en Empleo), fuera del área de datos.
- Inmovilizar paneles: debería ser en `A4` (así está en Membresias, Integrantes, Empleo y Catálogos). En Usuarios está en `A7`, en Domicilios en `A1712` y en Historial/Casilleros no hay; son descuidos a corregir.

### Hoja Instrucciones

- Título fondo `#1A252F`, Arial 14 negrita blanca; subtítulo Arial 10 cursiva gris.
- Secciones (`ESTRUCTURA DE LA PLANTILLA`, `REGLAS GENERALES`, `LEYENDA DE COLORES`) con fondo `#2C3E50`, Arial 10 negrita blanca.
- Dos columnas de texto: B (28) = concepto, C (60) = explicación.
- Reglas generales ya redactadas: no modificar encabezados, `ID_ORIGEN` como llave entre hojas, fechas `YYYY-MM-DD`, celdas vacías sin "N/A", usar valores exactos del catálogo.
- Leyenda: rojo obligatorio, azul opcional, verde catálogo.
- Quedó incompleta: la sección `HISTORIAL DE PAGOS (hoja 8)` no tiene texto, y la tabla de estructura no menciona las hojas 8 y 9.

### Hoja Catálogos

- Encabezados verdes (`#1E8449` y `#27AE60`), un catálogo por bloque de columnas separado por una columna vacía.

> Nota: los 9 archivos de `database/seeders/data` usaban encabezados legibles con acentos ("NÚMERO DE USUARIO"). Siguiendo tu indicación, el estándar será el de este archivo (`NUMERO_USUARIO` en mayúsculas, sin acentos), que además es compatible con el importador.

---

## 3. Hoja por hoja

### 3.1 `1. Usuarios` → `members.members`

| Columna | Marca | Datos reales | Contra la BD / observaciones |
| --- | --- | --- | --- |
| `ID_ORIGEN` | Rojo | 13,137, todos únicos, formato `USR-<n>` | → `migration_origin_id`. Correcto. |
| `NOMBRE` | Rojo | 100 % | → `first_name`. Algunos nombres truncados por Fox (ej. `MARIA DEL CARME`). |
| `APELLIDO_PATERNO` | Rojo | 100 % | → `last_name` |
| `APELLIDO_MATERNO` | Azul | 94 % | → `second_last_name` |
| `FECHA_NACIMIENTO` | Rojo | 100 % llena, pero: 62 con `1900-01-01` (valor de relleno), 7 vacías como `    -  -`, años imposibles (0201, 9881) y 8 en el futuro | → `birthdate` (opcional en BD, pero la usan transiciones de edad y precios por edad). Hay que limpiar. |
| `TELEFONO` | Azul | 0 % | Vacía en todo el archivo. |
| `CORREO` | Azul | 0 % | Vacía. Sin correo no se puede crear usuario de app. |
| `PAIS_NACIMIENTO` | Azul | 68 %, pero contiene **estados y ciudades** (`PUEBLA` 7,894, `DF`, `VERACRUZ`, `BARCELONA`…) con errores (`PEUBLA`) | En realidad es "lugar de nacimiento" en texto libre → encaja en `members.birth_place`, no en `birth_country_id`. |
| `ESTADO_NACIMIENTO` / `CIUDAD_NACIMIENTO` | Azul | 0 % | Vacías. |
| `NACIONALIDAD` | Azul | 74 %, 13 variantes: `MEXICANA`/`Mexicana`/`\|MEXICANA`/`MEXIICANA`/`OMEXICANA`, `ESPAÑOLA`/`ESPA¥OLA` (acento dañado), 277 con valor `1`, `GRECIA`, `FRANCIA`… | En BD `nationality_id` apunta a `catalogs.countries` (solo 3 países). Hay que normalizar y resolver el hallazgo de la BD. |
| `ESTADO_CIVIL` | Azul | 0 % | Vacía. |
| `OCUPACION` / `COLEGIO` | Azul | 0 % | Vacías. |
| `GENERO` | Rojo | 100 %, solo `H`/`M` | → `gender`. Correcto. |

**Falta respecto a la BD:** `LUGAR_NACIMIENTO` (texto, `birth_place`), `FOTO` (nombre de archivo).
**Calidad:** 116 filas parecen la misma persona repetida (mismo nombre, apellidos y fecha de nacimiento con distinto `ID_ORIGEN`). 5 usuarios no aparecen en ninguna cuenta.

### 3.2 `2. Membresias` → `memberships.accounts` + `memberships.memberships` + `account_groups`

| Columna | Marca | Datos reales | Contra la BD / observaciones |
| --- | --- | --- | --- |
| `NO_CUENTA` | Rojo | 5,369 distintas. Prefijos: `PE02-0xxxx` (5,146), `PE02-Mxxxx` (141, pases mensuales), `PE02-Pxxxx` (75), `PE02-Lxxxx` (17), `PE02-Bxxxx` (8, doctores), `PE02-varix` (6), `C`, `N`, `E` | Hoy el importador lo guarda en `membership_number`. Falta decidir si va a `internal_account_number`. Conviene saber qué significa cada prefijo. |
| `TIPO_MEMBRESIA` | Rojo | 10 códigos, todos válidos en BD (`PE2_FAM_EXT` 1,678, `PE2_IND_EXT` 1,350, `PE2_FAM_PE1` 1,094…). **29 vacíos** (cuentas `L`, `varix`, `N`, `E`, `P`) | Obligatorio en BD. Hay que definir qué son esas 29 cuentas. |
| `PARQUE` | Rojo | Solo `PE2` | → club. |
| `GRUPO_FAMILIAR` | **Rojo** (debería ser azul) | 1,940 filas, formato `PE01-11907:PE02-00020` (la cuenta de PE1 y la de PE2 de la misma familia) | → `account_groups`. Hace referencia a **1,915 cuentas de PE1 que no vienen en el archivo**. 29 cuentas PE2 aparecen dos veces porque están ligadas a dos cuentas PE1 distintas. |
| `CUENTA_ORIGEN` | **Rojo** (debería ser azul) | 1,823 filas; 1,815 existen en la hoja | → `origin_account_id` (cuenta de la que se separó). Muy usado en individuales (803) y solidarias. |
| `FECHA_INICIO` | Rojo | 100 %, desde 1999; 5 inválidas (`0203-12-22`, `6201-04-11`, `    -  -`) | → `memberships.start_date`. |
| `ESTATUS` | Rojo | `Activo` 4,930 / `Inactivo` 468 | BD usa `pending/active/suspended/cancelled`. "Inactivo" se convierte en `cancelled`, pero sin fecha ni motivo de cancelación. |

**Falta respecto a la BD y a la lógica de cobro:**
- `TIPO_CUENTA` (individual/familiar). Hoy se deduce del código, pero hay 135 cuentas individuales o solidarias con más de un integrante.
- `CUOTA_MENSUAL` real que paga hoy. El importador la recalcula con las reglas de precio vigentes, lo que puede no coincidir con lo que cobraba Fox.
- `ES_FACTURABLE` (en combos PE1+PE2 solo una se cobra).
- `FECHA_TERMINO`, y para inactivas: `FECHA_CANCELACION`, `MOTIVO_CANCELACION`.
- Fecha desde la cual se permite rellenar mensualidades (`billing_backfill_floor`).

### 3.3 `3. Integrantes` → `memberships.account_members`

| Columna | Marca | Datos reales | Observaciones |
| --- | --- | --- | --- |
| `ID_ORIGEN` | Rojo | 13,620 filas | Todos existen en Usuarios. Cada persona está en una sola cuenta. La instrucción está invertida: dice "Debe existir en hoja Membresias" (es la de NO_CUENTA). |
| `NO_CUENTA` | Rojo | 5,344 cuentas | Todas existen en Membresias. Instrucción invertida ("Debe existir en hoja usuarios"). |
| `ES_TITULAR` | Rojo | `SI`/`NO` | 94 cuentas sin titular y 13 con más de uno. |
| `PARENTESCO` | Azul | `Hijo(a)` 4,769, `Cónyuge` 2,769, `Padre/Madre` 335, `Otro` 279, `Hermano(a)` 131; 87 no titulares sin parentesco | **La BD solo tiene Titular, Cónyuge, Hijo(a) y Madre.** Faltan `Padre`, `Hermano(a)` y `Otro` en el catálogo. |

**Calidad:** 488 filas duplicadas exactas (misma cuenta y persona). 25 cuentas no tienen integrantes.
**Falta respecto a la BD:** `CODIGO_ACCESO` (número de tarjeta física actual, si se conservará) y `ESTATUS_ACCESO`.

### 3.4 `4. Domicilios` → `members.addresses`

| Columna | Marca | Datos reales | Observaciones |
| --- | --- | --- | --- |
| `ID_ORIGEN` | Rojo | 5,405 | 35 personas que no existen en Usuarios; 20 personas con dos domicilios (la BD lo permite con `is_primary`). |
| `CALLE` | Rojo | 99 % | Calle y número juntos (la BD solo tiene `street`, está bien). |
| `COLONIA` | Rojo | 92 % | En BD es opcional. |
| `CODIGO_POSTAL` | Azul | 84 %; 36 no son de 5 dígitos | |
| `PAIS` | Rojo | **0 %** | Marcado obligatorio pero viene vacío. Casi todo es México. |
| `ESTADO` | Rojo | 42 %, con variantes (`PEUBLA`, `CHOLULA,PUE`, `PUE`) y ciudades puestas como estado | Hay que llevarlo a `state_id`. |
| `CIUDAD` | Azul | 98 %, 136 variantes cortadas a 10 caracteres por Fox (`PUEBLA,PUE`, `SN.A.CHOLU`, `SAN ANDRES`) | Hay que llevarlo a `city_id` con una tabla de equivalencias. |
| `ANOS_RADICANDO` | Azul | 0 % | |

**Marca:** en BD todos los campos son opcionales; `CALLE`, `COLONIA`, `PAIS` y `ESTADO` no deberían ir en rojo.

### 3.5 `5. Empleo` → `members.employment_info`

Sin datos. Los 4 encabezados están en rojo aunque la información es opcional en BD y en la operación.

### 3.6 `6. Catálogos`

| Catálogo | En la plantilla | En la BD | Diferencia |
| --- | --- | --- | --- |
| Parentesco | Cónyuge, Hijo(a), Padre/Madre, Hermano(a), Otro | Titular, Cónyuge, Hijo(a), Madre | Faltan valores en la BD, o hay que mapear. |
| Estado civil | 5 valores | 7 valores | Alinear con la BD. |
| Estatus membresía | Activo, Inactivo, Suspendido, Pendiente | active, suspended, cancelled, pending | "Inactivo" no existe: equivale a cancelada. |
| Tipos de membresía | 23 códigos | 23 códigos | Coinciden. |
| Conceptos de cobro | **6 genéricos** (`MONTHLY_FEE`, `INSCRIPTION`, `LOCKERS`, `EVENT`, `BUSINESS_AD`, `OTHER`) | 162 conceptos, la mayoría con el mismo nombre que en Fox | La plantilla pierde información (ver 3.7). |
| Métodos de pago | Códigos inventados con parque: `CREDIT_CARD_PE1`, `CHECK_PE2`… (y `BANK_TRANSFER_PE1` aparece dos veces, la segunda rotulada "Parque España 2") | 6 códigos sin parque: `CASH`, `BANK_TRANSFER`, `CHECK`, `CREDIT_CARD`, `DEBIT_CARD`, `APP_PAYMENT` | **Ninguno de los códigos con `_PE1`/`_PE2` existe en la BD.** El importador, al no encontrar el método, usa el primero del catálogo (`CASH`): todos los pagos con tarjeta quedarían como efectivo. |

### 3.7 `8. Historial por Período` → `billing.charges` + `payments` + `payment_applications`

Es la hoja más importante y la que más problemas tiene. Detalle en la sección 4.

| Columna | Datos reales | Observaciones |
| --- | --- | --- |
| `NO_CUENTA` | 2,026 cuentas | 3 cuentas no existen en Membresias (`PE02-04614`, `PE02-04227`, `PE02-05145`). |
| `PARQUE` | Solo `PE2` | |
| `CONCEPTO_CODIGO` | Solo 5 valores | Se colapsaron 41 conceptos de Fox en 5 genéricos. Errores de mapeo: `Cuota Mantto. Local` (renta de locales, conceptos 70x) quedó como `MONTHLY_FEE`, y el sistema la contaría como mensualidad de membresía; `CUOTA PASE DIARIO` quedó como `EVENT`; adeudos, credenciales, anuncios y cursos quedaron en `OTHER`. |
| `DESCRIPCION` | 41 valores | **Es la columna valiosa:** 40 de 41 coinciden exactamente con el nombre de un concepto en la BD (ej. `Cuota Mens Parques` → `MONTHLY_FEE_PARKS`, `CUOTA ADEUDO ANTERIOR` → `CUOTA_ADEUDO_ANTERIOR`, `CUOTA PASE DIARIO` → `20`). La restante (`CUOTA REINSCRIPCION`) solo difiere por el acento. |
| `AÑO` / `MES` | Periodos de 2018 a 2028; mes vacío en conceptos no mensuales | 339 filas con periodos posteriores a junio 2026 (pagos adelantados). |
| `MONTO_CARGO` | Importe original del cargo | 5 cargos en 0. |
| `FECHA_VENCIMIENTO` | 0 % | Marcado en rojo pero la instrucción dice opcional. |
| `MONTO_PAGADO` | 100 % | **No es lo pagado a ese cargo**: es el importe de una línea de pago del recibo, repetido en cada cargo del recibo. |
| `FECHA_PAGO` | 2026-01-02 a 2026-06-22 | Solo fecha, sin hora. |
| `METODO_PAGO` | 8 códigos con parque | Ver catálogo. 2,226 filas son de terminal PE1 dentro de recibos PE2 (1,535 recibos mezclan terminal PE1 y PE2: combos pagados en ambos parques). |
| `REFERENCIA` / `BANCO` | 65 % | Referencia de terminal/autorización; bancos con variantes (`BBVA`/`bbva`/`BANCOMER`, y `VISA`/`MASTERCARD` como si fueran banco). |
| `NUM_CHEQUE` | 0 % | Aunque hay 30 pagos con cheque. |
| `PAGO_REF` | 9,537 valores | **Es el folio del recibo de Fox**, con serie por letra (`A`, `C`, `D`, `F`, `H`, `I`, `J`, que corren en paralelo, lo que sugiere una serie por caja o cajero). Encaja en `payments.folio` y en la serie del cajero (`users.code`). |
| `NOTAS` | 0 % | |

### 3.8 `9. Casilleros` → `members.lockers` + `members.locker_assignments`

| Columna | Datos reales | Observaciones |
| --- | --- | --- |
| `NO_CUENTA` / `ID_ORIGEN` | Solo en filas `asignado` | 99.8 % de las personas pertenecen a la cuenta indicada. 845 personas tienen más de un casillero. |
| `CATEGORIA` | `caballeros` 1,926, `damas` 1,170, `ninas` 294, `ninos` 242 | Coincide con el inventario sembrado en BD para PE2 (1,926 / 1,170 / 288 / 242), salvo 6 casilleros de niñas con letra. |
| `NUMERO_CASILLERO` | Texto con ceros (`00001`); 6 con letra (`0029A`, `0115A`…) | En BD `number` es entero: los que llevan letra no caben. |
| `AÑO` | Asignados desde 2003; 34 con `0` | Es el año en que empezó la asignación, no el año que cubre. La BD maneja asignaciones **anuales** (único por casillero + año, con fecha de fin obligatoria). |
| `FECHA_INICIO` | 69 vacías | |
| `FECHA_FIN` | 0 % | **Obligatoria en BD** (`end_date NOT NULL`). |
| `ESTATUS` | `asignado` 2,827 / `libre` 805 | BD usa `disponible`, `ocupado`, `mantenimiento`, `pago_pendiente`. |
| `NOTAS` | 0 % | En rojo sin necesidad. |

**Falta:** `IMPORTE_PAGADO` (`amount_paid`, obligatorio en BD) y la referencia al cargo `LOCKERS` que la pagó.
**Estructura:** la hoja mezcla dos cosas: el **inventario** (estatus de cada casillero) y las **asignaciones**. Conviene separarlas.

---

## 4. El problema del historial de pagos

### Cómo vienen realmente los datos

Cada **recibo** de Fox (`PAGO_REF`) está "explotado" como **producto cartesiano**: una fila por cada combinación de *cargo del recibo × línea de pago del recibo*. Se comprobó en 9,462 de 9,537 recibos (los 75 restantes tienen cargos idénticos repetidos dentro del recibo, por ejemplo dos credenciales de $200).

Ejemplo real, recibo `A28438` (cuenta `PE02-01775`): se pagaron noviembre y diciembre 2025 ($3,500 c/u) con dos tarjetas, una en la terminal de PE1 y otra en la de PE2, $3,500 cada una:

| MES | MONTO_CARGO | MONTO_PAGADO | METODO_PAGO |
| --- | ---: | ---: | --- |
| 11 | 3,500 | 3,500 | CREDIT_CARD_PE1 |
| 11 | 3,500 | 3,500 | CREDIT_CARD_PE2 |
| 12 | 3,500 | 3,500 | CREDIT_CARD_PE2 |
| 12 | 3,500 | 3,500 | CREDIT_CARD_PE1 |

Recibo real: 2 cargos ($7,000) y 2 pagos ($7,000). Sin embargo la hoja no dice **qué tarjeta cubrió qué mes**.

### Qué se puede recuperar y qué no

| Dato | ¿Se puede obtener? |
| --- | --- |
| Cargos de cada recibo (concepto, periodo, importe) | Sí, quitando duplicados. |
| Líneas de pago de cada recibo (método, importe, referencia) | Sí, quitando duplicados. |
| Total del recibo | Sí. En 9,462 recibos Σ pagos = Σ cargos. |
| Aplicación pago → cargo | Solo cuando el recibo tiene un solo pago o un solo cargo. En **666 recibos** con varios pagos y varios cargos, no se sabe. |
| Si un cargo quedó pagado completo o parcial | No directamente: `MONTO_CARGO` repite el importe original aunque el pago sea parcial, y un mismo cargo puede aparecer en varios recibos (48 mensualidades aparecen en dos recibos distintos). |
| Cargos **pendientes** (adeudo) | **No.** Todas las filas tienen pago; la hoja solo trae lo que sí se cobró. |
| Pagos cancelados, descuentos, saldo a favor | No. |

### Qué haría hoy el importador con esta hoja

- **Duplicaría el dinero** en recibos con varios pagos. En `A28438` crearía un pago con tarjeta PE1 por $7,000 y otro con tarjeta PE2 por $7,000 ($14,000 en total, contra $7,000 reales) y aplicaría $7,000 a cada mes.
- Registraría todos los pagos como **efectivo**, porque no reconoce los códigos de método con `_PE1`/`_PE2`.
- Mandaría los cargos de renta de locales (`Cuota Mantto. Local`) como mensualidad de membresía.
- Fusionaría en un solo cargo los cargos iguales de distintos recibos (misma cuenta, concepto, periodo e importe). Es correcto para abonos a una misma mensualidad, pero incorrecto para, por ejemplo, 253 pases diarios distintos con el mismo importe.

### Qué necesita la plantilla para que el historial sea fiel

Separar en tres hojas, como ya se había propuesto:

1. **Cargos:** una fila por cargo, con folio o referencia propia, cuenta, concepto (nombre de Fox = nombre en BD), periodo, importe y saldo o estatus.
2. **Pagos:** una fila por línea de pago, con folio del recibo, método, importe, referencia, banco o cheque y **en qué parque se cobró** (PE1/PE2 sale de la terminal).
3. **Aplicaciones:** pago → cargo → importe. Si Fox no guarda esta relación, se acuerda una regla explícita (por ejemplo, aplicar en orden al cargo más antiguo) y se marca como "asignada por regla", no como dato histórico.

Y agregar la **deuda pendiente**: cargos no pagados, o el saldo al corte por cuenta.

---

## 5. Lo que el archivo no cubre y el sistema necesita

| Bloque | Estado en el archivo | Por qué hace falta |
| --- | --- | --- |
| **Parque España I completo** (socios, cuentas, historial, casilleros) | No viene. Solo hay referencias a 1,915 cuentas PE1 en `GRUPO_FAMILIAR` | Sin PE1 no se pueden armar los grupos PE1+PE2 ni los paquetes interclub. |
| **Adeudos pendientes / saldo al corte** | No viene | Es lo mínimo para que Cobranza arranque con saldos correctos. |
| Cuota mensual real por membresía y si se factura | No viene | Evita que el sistema cobre una cuota distinta a la de Fox. |
| Fecha de corte y fecha desde la que rellenar mensualidades | No viene | Evita que el relleno automático genere meses de más (ver análisis del sistema). |
| Cancelaciones (fecha, motivo) de cuentas inactivas | Solo "Inactivo" | Necesario para `cancelled_at`, `cancellation_reason_id` y para no cobrar esos meses. |
| Personal / cajeros | No viene | Las series `A`, `C`, `D`, `F`, `H`, `I` y `J` de los recibos corresponden a cajas o cajeros; en el sistema son `users.code`. |
| Pagos cancelados, descuentos, saldo a favor | No viene | Cambian el saldo. |
| Permisos por ausencia activos | No viene | Hay 671 cobros de `CUOTA PERMISO` en 2026: existen permisos vigentes. |
| Anuncios físicos vigentes | No viene | Hay cobros de anuncios (`CUOTA ANUNCIO C CARTA`…): hay contratos activos. |
| Renta de locales (`Cuota Mantto. Local 3…18`) | Solo como cobros | El sistema tiene los conceptos, pero no un módulo de locales. Hay que decidir cómo se manejan. |
| Tarjetas de acceso actuales | No viene | Define si se conservan las tarjetas físicas o se reemplazan. |
| Datos fiscales, historia clínica, contacto de emergencia, documentos | No viene | Opcionales, según lo que tenga Fox. |
| Correo y teléfono de los socios | Columnas vacías | Sin correo no hay usuario de app ni notificaciones. |
| Actas, multas, reservaciones y pases futuros | No viene | Solo si hay casos abiertos. |

---

## 6. Conclusión y siguiente paso

**Se conserva:**
- El formato visual (sección 2) y el estilo de encabezados en MAYÚSCULAS sin acentos.
- `ID_ORIGEN` (número de usuario Fox) y `NO_CUENTA` como llaves entre hojas.
- La idea de hojas por tema y la hoja de catálogos.
- Los datos reales de Usuarios, Membresias, Integrantes, Domicilios y el inventario de Casilleros, previa limpieza.
- `DESCRIPCION` del historial como concepto (coincide con el catálogo de la BD) y `PAGO_REF` como folio del recibo.

**Se corrige:**
- Obligatorio/opcional según la BD (hoy casi todo está en rojo).
- Catálogos de métodos de pago, conceptos, parentescos, estatus y estado civil con los valores reales de la BD.
- El historial se separa en Cargos, Pagos y Aplicaciones, y se agregan adeudos pendientes y saldos.
- Casilleros se separa en inventario y asignaciones, agregando importe y fecha de fin.
- Se agregan las columnas faltantes de Membresias (tipo de cuenta, cuota, facturable, cancelación).
- Instrucciones invertidas en Integrantes, hoja Instrucciones incompleta e inmovilizar paneles inconsistente.

**Preguntas para el cliente o para quien extrae de Fox:**
1. ¿Se puede sacar la misma información de **Parque España I**?
2. ¿Fox guarda los **cargos pendientes** (adeudo) por cuenta? ¿Y la relación de qué pago cubrió qué cargo?
3. ¿Qué significan los prefijos de cuenta `M`, `P`, `L`, `B`, `C`, `N`, `E` y `vari`? ¿Qué tipo tienen las 29 cuentas sin tipo?
4. ¿Las letras de serie de los recibos (`A`, `C`, `D`, `F`, `H`, `I`, `J`) son cajas o cajeros? ¿De quién?
5. ¿Los pagos con terminal PE1 dentro de un recibo PE2 se deben registrar como cobrados en PE1?
6. ¿Qué cuota mensual paga hoy cada cuenta en Fox?
7. ¿Hay correos y teléfonos de socios en Fox (vienen vacíos)?
8. ¿Cómo se manejarán los locales en renta (conceptos 70x)?
9. ¿Cuál será la fecha de corte y desde cuándo se conserva el historial?
