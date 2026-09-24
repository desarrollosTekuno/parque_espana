# Migración de datos ParquesEsp — Progreso y contexto

Última actualización: 2026-09-24

## Contexto general

Proyecto Laravel "ParquesEsp" (Parque España). Se están preparando plantillas Excel en `database/seeders/data/` para que un cliente no técnico capture info y migrar desde el sistema anterior. Documento de referencia: `Docs/Plan_migracion_datos_sistema_anterior.md`.

Archivos en `database/seeders/data/`:
1. `01_CATALOGOS.xlsx` — ✅ terminado y entregado
2. `02_CLUBES_Y_CONFIGURACION.xlsx` — ✅ terminado y entregado
3. `03_SOCIOS.xlsx` — ✅ terminado y entregado (2026-09-24)
4. `04_CUENTAS_Y_MEMBRESIAS.xlsx` — ✅ terminado y entregado (2026-09-24)
5. `05_FINANZAS.xlsx` — ✅ terminado y entregado (2026-09-24), rediseñado (ver abajo)
6. `06_CASILLEROS.xlsx` — ✅ terminado y entregado (2026-09-24)
7. `07_AMENIDADES_Y_CLASES.xlsx` — ✅ terminado y entregado (2026-09-24)
8. `08_RESERVACIONES_Y_ACCESOS.xlsx` — ✅ terminado y entregado (2026-09-24)
9. `09_INFORMACION_ADMINISTRATIVA.xlsx` — ✅ terminado y entregado (2026-09-24)

**Los 9 archivos de migración están completos y entregados.**

## Reglas de negocio / instrucciones del cliente (aplican a TODOS los archivos, no negociables)

Estas vienen de correcciones directas y algo molestas del usuario — deben respetarse siempre:

1. **La hoja INSTRUCCIONES ya existe en cada archivo y debe dejarse TAL CUAL.** Nunca agregar contenido para el desarrollador (notas de "origen de datos", "pendientes", dudas) en esa hoja — el usuario final se confunde. Cualquier nota/caveat de tipo "esto no se pudo verificar" o "esto es un supuesto" va SOLO en el chat, nunca en el archivo entregable.
2. **Pensar en un usuario no técnico capturando datos**: usar nombres legibles, no IDs. Ejemplo: si en BD hay `pais_id`, en el Excel debe decir "PAÍS" y la persona captura "México", no "1".
3. **Llenar los catálogos con información real** extraída de seeders/migraciones (o de la BD si el usuario la comparte). No dejar vacíos "porque serían muchas filas" — eso ya fue rechazado explícitamente por el usuario.
4. **Validar cada columna del Excel contra las migraciones reales** (no solo contra los seeders) para confirmar que todos los campos de la tabla están representados. Si un campo de BD no está en el Excel, agregarlo (a menos que sea un secreto/credencial, ver abajo).
5. **Nunca incluir credenciales/API keys** (ej. Conekta `conekta_public_key`/`conekta_secret_key`) en las plantillas — esos se configuran directo en el sistema, nunca en un Excel.
6. Ejemplos en la hoja EJEMPLOS deben ser realistas y consistentes con los datos reales (nombres/códigos de club reales: "Parque España I" = "PE1", "Parque España II" = "PE2"), no genéricos tipo "COUNTRY"/"PE-001" ni placeholders tipo "DATOS DE EJEMPLO N".
7. Moneda: si el sistema no especifica otra cosa, asumir MXN.
8. No todos los campos son obligatorios — el criterio de rojo/azul (requerido/opcional) debe reflejar la realidad de la BD (nullable o no) y el uso práctico del campo, no ponerse todo en rojo.

## Estilo visual estándar (aplicado y debe repetirse en cada archivo restante)

Cada hoja de "captura" de datos sigue este patrón (construido con openpyxl):

- **Fila 1**: barra de título oscura (`FF1A252F` fondo), texto en rich-text: título grande en blanco (Arial bold 12, `FFFFFFFF`) + nombre real de la tabla de BD en chico/cursiva claro (Arial italic 9, `FFB0BEC5`). Ejemplo: `"1. SOCIOS   (members.members)"` — el nombre de tabla ayuda al usuario a verificar contra la BD directamente. Numeración secuencial por archivo (empieza en 1 en cada archivo nuevo).
- **Fila 2**: encabezados de columna. Rojo (`FFC0392B`) = campo requerido. Azul (`FF2980B9`) = campo opcional. Fuente blanca bold, Arial 10, alineado centro con wrap text. Alto de fila 28.
- **Fila 3**: fila "tooltip" — texto gris cursiva pequeño (`FF555555`, tamaño 8, itálica) sobre fondo gris claro (`FFECF0F1`) explicando qué capturar en cada columna (formato, validaciones, referencias a catálogos). Alto de fila 26.
- **Fila 4+**: datos reales precargados cuando existen en seeders con datos fijos, o vacío si es transaccional/generado por factory (datos aleatorios de prueba no cuentan como "reales").
- `ws.freeze_panes = "A4"` en cada hoja.
- Leyenda de colores en INSTRUCCIONES (ya existente, no tocar): ROJO = requerido, AZUL = opcional, VERDE = catálogo de referencia (solo lectura).
- **Hoja EJEMPLOS**: replica los mismos encabezados/colores de cada hoja de captura, en bloques separados por 2 filas en blanco (título fila con fondo oscuro fusionado, encabezados, 2-3 filas de datos con fondo `FFEBF5FB`). Debe mantenerse sincronizada con los encabezados reales de las hojas de captura — si se corrige una columna en la hoja de captura, corregir también su reflejo en EJEMPLOS.

Los scripts de build (Python/openpyxl) se generan y ejecutan dentro de la sesión de Claude — no quedan guardados en el repo del usuario entre sesiones, así que cada archivo nuevo re-implementa `build_sheet`/`rebuild_sheet` desde cero siguiendo esta especificación exacta (confirmada leyendo el XLSX de `02_CLUBES_Y_CONFIGURACION.xlsx` byte a byte: colores, fuentes, alturas, freeze panes).

## Archivo 1 — 01_CATALOGOS.xlsx (terminado)

21 hojas de catálogo, todas con estilo estándar arriba. Conteos finales verificados:
- PAISES=3, ESTADOS=158 (México=32, España=69, Estados Unidos=57), CIUDADES=37,536
- NACIONALIDADES=194 (extraídas exactas de `NationalitySeeder.php`)
- ESTADOS CIVILES=7, PARENTESCOS=4, TIPOS DOCUMENTO=16, DOCS POR PARENTESCO=22
- MOTIVOS CANCELACION=6, MOTIVOS SEPARACION=1
- TIPOS MEMBRESIA=23, DOCS POR MEMBRESIA=125
- METODOS DE PAGO=6, CONCEPTOS DE COBRO=160
- IMPORTES POR CLUB=0 (no hay seeder — es transaccional)
- REGLAS DE DESCUENTO=3, REGLAS DE PRECIOS=88 (incluye 12 grupos generados por loop `pe2CategoryRules()` en el seeder, reimplementado en Python)
- ESPECIALIDADES=2, ESTATUS RESERVACION=5
- CATEGORIAS CASILLERO=4 (lista plana/global — es un ENUM en la tabla `members.lockers`, NO un catálogo por club)
- INCIDENTES Y SANCIONES=0 (no existe catálogo fijo en BD)

Fuente de datos reales de ubicación: `database/data/countries+states+cities.json` (dataset dr5hn, 46.75MB), filtrado a iso2 en {MX, ES, US}.

## Archivo 2 — 02_CLUBES_Y_CONFIGURACION.xlsx (terminado, entregado 2026-09-24)

Hojas: INSTRUCCIONES (intacta), EJEMPLOS (corregida), CLUBES, DOMICILIOS, METODOS POR CLUB, CONCEPTOS E IMPORTES, FOLIOS.

- **CLUBES** (`clubs.clubs`): 2 filas reales — Parque España I (PE1, IVA=NO) y Parque España II (PE2, IVA=SÍ). 18 columnas incluyendo redes sociales, RFC, razón social, logo/mapa (archivo aparte).
- **DOMICILIOS** (`clubs.club_addresses`): 2 filas reales (PE1 en Puebla, PE2 en San Andrés Cholula).
- **METODOS POR CLUB** (`billing.club_payment_methods`): 12 filas (6 métodos × 2 clubes). Se agregó columna "CLAVE INTERNA" (opcional) que faltaba vs. la migración (`internal_key`). Se excluyeron a propósito las columnas de credenciales Conekta.
- **CONCEPTOS E IMPORTES** (`billing.concept_club_amounts`): 0 filas — sin seeder, es dato operativo.
- **FOLIOS** (`billing.folio_sequences`): 0 filas — dato transaccional, sin seeder.
- **EJEMPLOS**: corregidas inconsistencias de nombres/códigos (antes "PARQUE ESPAÑA"/"PARQUE ESPAÑA 2"/"PARQUE ESPAÑA COUNTRY" con códigos "PE-001"/"PEC-002"; ahora consistentemente "Parque España I"/"PE1" y "Parque España II"/"PE2").

### Hallazgos de calidad de datos en los seeders (mencionados al usuario, no corregidos en el Excel — el problema está en los datos reales/seeders, no en la plantilla)
- PE1 y PE2 comparten exactamente la misma razón social ("FUNDACIÓN DEPORTIVO PARQUE ESPAÑA"), mismo RFC ("FDP990423J51") y misma URL de facturación (`http://www.parqueespana2.com.mx`) — posible bug de captura de datos, el usuario debería verificarlo en el sistema en vivo.
- Ambos clubes comparten el mismo link de Threads (mismo URL en `ClubContactSeeder.php` para PE1 y PE2).
- Falta el tipo de documento `formato_datos_clinicos` referenciado por `MembershipTypeRequiredDocumentSeeder` pero no definido en `DocumentTypeSeeder` (detectado durante trabajo del archivo 1).

## Archivo 3 — 03_SOCIOS.xlsx (terminado, entregado 2026-09-24)

Este archivo ya existía pero con un formato ANTERIOR al estándar (sin barra de título, sin fila tooltip, headers directo en fila 1). Se reconstruyó completo con el estilo estándar. Hojas: INSTRUCCIONES (intacta), EJEMPLOS (reconstruida), SOCIOS, DOMICILIOS, INFORMACION LABORAL, INFORMACION MEDICA, CONTACTOS EMERGENCIA, DOCUMENTOS.

- **SOCIOS** (`members.members`): 17 columnas. Incluye `NÚMERO DE SOCIO` como referencia = `migration_origin_id` (columna que el modelo ya contempla para la migración, según `Plan_migracion_datos_sistema_anterior.md`). Se excluyeron a propósito `user_id` (se vincula por separado al crear cuentas de portal) y `conekta_customer_id` (lo genera el sistema al tokenizar, no algo que el cliente conozca).
- **DOMICILIOS** (`members.addresses`), **INFORMACION LABORAL** (`members.employment_info`), **DOCUMENTOS** (`members.documents`): ya existían con buena cobertura de columnas; solo se les aplicó el estilo estándar.
- **INFORMACION MEDICA** y **CONTACTOS EMERGENCIA**: ambas mapean a la misma tabla `members.clinical_histories` (que incluye tanto datos médicos como los 4 campos de contacto de emergencia) — se mantiene la separación en dos hojas por claridad para el usuario no técnico, es una decisión de UX válida, no un error.
- Todas las hojas de captura (SOCIOS, DOMICILIOS, INFORMACION LABORAL, INFORMACION MEDICA, CONTACTOS EMERGENCIA, DOCUMENTOS) se dejaron con **0 filas de datos reales**: el único seeder de socios (`MemberWithUserSeeder.php`) genera 15 registros con Factory/Faker (datos aleatorios de prueba, distintos en cada corrida), no son datos reales fijos como los catálogos del archivo 1 — no aplica precargar.
- **EJEMPLOS**: reescrita completa. Los ejemplos anteriores usaban placeholders genéricos "DATOS DE EJEMPLO 1/2/3" en la mayoría de columnas (violaba la regla de ejemplos realistas) y una fecha de nacimiento en el futuro (2026, imposible para un socio adulto). Ahora usa 3 socios ficticios pero realistas y consistentes entre hojas (direcciones en Puebla/San Andrés Cholula, igual que los clubes reales del archivo 2), incluyendo un caso de socio menor de edad (con escuela, sin correo/teléfono/estado civil, sin fila en INFORMACION LABORAL).

### Correcciones aplicadas durante la reconstrucción (mencionadas aquí, no en el archivo)
- **`INFORMACION MEDICA` tenía una columna huérfana**: "DETALLE DE ALERGIAS" no corresponde a ningún campo de la migración `create_members_clinical_histories_table` (`has_allergy` no tiene su propio campo de detalle; solo `has_allergens`/`allergen_details` sí lo tienen). Se eliminó la columna.
- **`DOCUMENTOS.CLUB` estaba marcado como requerido (rojo)** pero la migración lo define `nullable()`, y el comentario en la migración (`2026_08_19_000002_add_club_id_to_members_documents_table.php`) explica que la mayoría de documentos son válidos para todos los parques del socio (`club_id` null = documento general). Se cambió a opcional (azul).
- **`SOCIOS` tenía tooltips reciclados incorrectamente** en NOMBRE/APELLIDOS diciendo "para referencia visual (repetir el mismo dato...)" — ese texto aplica a las hojas secundarias (donde esas columnas son copias de referencia), pero en la hoja SOCIOS son el dato original. Se corrigió el texto solo para esa hoja.
- **`TIENE SEGURO MÉDICO`** se renombró a **"SEGURO MÉDICO / INSTITUCIÓN"**: el campo de BD (`medical_insurance`) es un string (nombre de la institución, ej. IMSS/particular), no un booleano SÍ/NO como sugería el nombre anterior.

### Hallazgo NO corregido en el archivo (solo reportado aquí, requiere decisión del usuario)
- **`members.members` tiene columnas `state` y `city` (string) que parecen redundantes/obsoletas.** Fueron agregadas en `add_extra_fields_to_members_table` junto con `birth_place`, aparentemente pensadas como "estado/ciudad de nacimiento" en texto libre. Pero después se agregaron `birth_country_id`/`birth_state_id`/`birth_city_id` (FK a catálogos) que cubren exactamente ese propósito, y nunca se hizo una migración para eliminar las columnas string `state`/`city` originales — parecen quedar huérfanas en el esquema. **No se agregaron al Excel** (habría sido confuso mostrar "ESTADO"/"CIUDAD" genéricos duplicando "ESTADO DE NACIMIENTO"/"CIUDAD DE NACIMIENTO"), pero conviene que el usuario confirme si esas columnas de BD deberían eliminarse con una migración, ya que actualmente no se van a llenar desde ningún lado.
- **La tabla `members.payment_sources` ("fuentes de pago de socios", ítem 36 del plan de migración) no se incluyó como hoja.** Su campo clave `conekta_payment_source_id` solo se genera al tokenizar una tarjeta directamente con Conekta — no es un dato que el cliente pueda capturar manualmente desde el sistema anterior en un Excel. Si el usuario quiere manejar este dato de otra forma (por ejemplo, que los socios vuelvan a registrar su tarjeta desde el portal nuevo), no requiere plantilla de captura.

## Archivo 4 — 04_CUENTAS_Y_MEMBRESIAS.xlsx (terminado, entregado 2026-09-24)

Igual que el archivo 3, ya existía pero en el formato ANTERIOR al estándar (sin barra de título ni fila tooltip). Se reconstruyó completo. Hojas: INSTRUCCIONES (intacta), EJEMPLOS (reconstruida), GRUPOS DE CUENTAS, CUENTAS, INTEGRANTES, MEMBRESIAS, DATOS FISCALES, AUSENCIAS, CANCELACIONES, REACTIVACIONES, TRANSICIONES DE EDAD, SEPARACIONES, y una hoja nueva: REGLAS DE PAQUETE ENTRE CLUBES.

- **GRUPOS DE CUENTAS** (`memberships.account_groups`), **INTEGRANTES** (`memberships.account_members`), **MEMBRESIAS** (`memberships.memberships`), **AUSENCIAS** (`memberships.absence_permits`), **REACTIVACIONES** (`memberships.account_reactivations`): ya tenían muy buena cobertura de columnas contra las migraciones reales; solo se les aplicó el estilo estándar y se completaron tooltips.
- **REGLAS DE PAQUETE ENTRE CLUBES** (`memberships.interclub_package_rules`): hoja de catálogo **nueva**, no existía en ningún archivo anterior a pesar de que la hoja MEMBRESIAS ya la referenciaba por nombre ("REGLA DE PAQUETE ENTRE CLUBES"). Se llenó con las 32 reglas reales extraídas de `InterclubPackageRuleSeeder.php` (paquetes PE1↔PE2 por antigüedad/tipo de membresía). No incluye cuota mensual/inscripción — esos montos ahora viven en `interclub_package_rule_fee_history` (por año), igual que se decidió para REGLAS DE PRECIOS en el archivo 1.
- **TRANSICIONES DE EDAD** (`memberships.pending_age_transitions`): se agregó la columna **NOMBRE DEL CLUB** (requerida), que faltaba — sin ella no se puede saber cuál de las membresías de la cuenta (si tiene más de un club) es la que está transicionando de tipo.
- **DATOS FISCALES** (`memberships.account_fiscal_data`): los 5 campos de datos (razón social, RFC, uso de CFDI, régimen fiscal, código postal fiscal) estaban marcados como opcionales (azul) pero ninguno es nullable en la migración — se cambiaron a requeridos (rojo).

### Corrección de duplicación entre hojas (mencionada aquí, no en el archivo)
La hoja **CUENTAS** original repetía 5 columnas que ya existían, con el mismo propósito, en otras hojas dedicadas: `MOTIVO DE SEPARACIÓN`, `FECHA DE CANCELACIÓN`, `TIPO DE CANCELACIÓN`, `MOTIVO DE CANCELACIÓN` y `ARCHIVO DE LA CARTA DE CANCELACIÓN` (todas viven en `memberships.accounts`, pero ya se capturan en las hojas CANCELACIONES/SEPARACIONES). Esto significaba que el mismo dato se podía llenar en dos lugares distintos del archivo y terminar contradictorio. Se quitaron de CUENTAS (que ahora solo captura identidad/estatus de la cuenta) y se dejaron únicamente en CANCELACIONES y SEPARACIONES. También se quitó `MOTIVO DE SEPARACIÓN` de la hoja CANCELACIONES (donde también estaba duplicada) — ese dato es exclusivo de SEPARACIONES, ya que `cancelled_at`/`cancellation_type`/etc. y `origin_account_id`/`separation_reason` son dos eventos de negocio independientes aunque vivan en la misma tabla.

### Hallazgo NO corregido en el archivo (requiere decisión del usuario)
- **La hoja SEPARACIONES tiene dos columnas sin campo de BD que las respalde**: `FECHA DE SEPARACIÓN` y `NOMBRE DEL ARCHIVO DE SOPORTE`. Revisando las migraciones, `memberships.accounts` solo tiene `origin_account_id` y `separation_reason` (string) para este evento — no existe ninguna columna para guardar una fecha de separación explícita ni la ruta de un documento de soporte. Esto es inconsistente con el catálogo `memberships.separation_reasons`, que sí tiene un flag `requires_document` (implica que se espera un documento pero no hay dónde guardarlo). Se dejaron las columnas en el Excel porque es información que tiene sentido capturar, pero conviene que el equipo de desarrollo agregue una migración a `memberships.accounts` (por ejemplo `separated_at` y `separation_document_path`) antes de construir el importador de este archivo, o de lo contrario esos dos datos no tendrán dónde aterrizar.

## Archivo 5 — 05_FINANZAS.xlsx (terminado, entregado 2026-09-24) — REDISEÑADO

Este era, por lejos, el archivo más complejo: la versión original tenía **13 hojas de datos** (SALDOS INICIALES, CARGOS, PAGOS, APLICACIONES DE PAGOS, SALDOS A FAVOR, MOVIMIENTOS DE SALDO, NOTAS DE COBRANZA, OPERACIONES SPEI, APLICACIONES SPEI, FUENTES DE PAGO, CORTES DE CAJA, DESGLOSE DE EFECTIVO, CORTES GLOBALES) que excedían por mucho el alcance real de "información financiera inicial" (bloque 5 del `Plan_migracion_datos_sistema_anterior.md`) y mezclaban ahí varias cosas que en realidad son historial completo (bloque 6, que el plan dice explícitamente que solo se migra "si se autoriza") o datos que ni siquiera se pueden capturar a mano. El usuario pidió explícitamente analizar una forma más fácil de cargar esto y autorizó modificar lo que fuera necesario.

**Se colapsó a 3 hojas de captura**, alineadas estrictamente con el bloque 5 del plan: INSTRUCCIONES (intacta), EJEMPLOS (reconstruida), SALDOS INICIALES, CARGOS PENDIENTES, NOTAS DE COBRANZA.

- **SALDOS INICIALES** (`billing.charges` / `billing.credit_balances`): la "ruta fácil". Una fila por cuenta+club con `FECHA DE CORTE`, `NÚMERO DE MEMBRESÍA`, `NOMBRE DEL CLUB`, `SALDO TOTAL PENDIENTE` (rojo), `SALDO VENCIDO` (azul), `SALDO A FAVOR` (azul — absorbe la antigua hoja separada SALDOS A FAVOR, ver abajo por qué), `NOTAS` (azul). El tooltip aclara explícitamente que llenar solo esta hoja es suficiente para migrar; no hace falta ir cargo por cargo.
- **CARGOS PENDIENTES** (`billing.charges`): alternativa **opcional** de detalle, para quien sí quiera capturar cargo por cargo en vez de un total. Se recortó a solo los campos relevantes para un cargo todavía pendiente (se quitaron campos de cargos ya cancelados, porque `ESTATUS` en esta hoja solo admite PENDIENTE/PARCIAL). El tooltip advierte no duplicar el mismo adeudo aquí y en SALDOS INICIALES a la vez.
- **NOTAS DE COBRANZA** (`billing.collection_notes`): se mantuvo prácticamente igual a la original; `NOMBRE DEL CLUB` correctamente en azul (opcional) porque la nota puede ser general de la cuenta, no de un club específico.

### Corrección posterior (2026-09-24, al trabajar el archivo 9): se eliminó la hoja MULTAS PENDIENTES
Este archivo originalmente incluía una hoja **MULTAS PENDIENTES** (`members.fines`/`members.acts`) que combinaba lo mínimo de un acta con los datos de la multa en una sola fila. Al construir `09_INFORMACION_ADMINISTRATIVA.xlsx` (bloque 10 del plan, "Información administrativa complementaria") se confirmó que ese bloque ya incluye una estructura **completa y correcta** para lo mismo: hoja ACTAS (la entidad completa, con folio/socio/cuenta/club/tipo de infracción/descripción/fecha) + hoja MULTAS (ligada por folio de acta) — y además AMONESTACIONES y ARCHIVOS DE ACTAS, que la versión simplificada de este archivo nunca cubrió. Tener el mismo par acta+multa capturable en dos archivos distintos habría creado el mismo riesgo de captura duplicada/contradictoria ya evitado en otros archivos (ver archivo 4). Se eliminó MULTAS PENDIENTES de este archivo; **las multas y actas ahora se capturan únicamente en `09_INFORMACION_ADMINISTRATIVA.xlsx`**.

### Hojas eliminadas del archivo (mencionado aquí, no en el archivo)
- **SALDOS A FAVOR**: redundante — se absorbió como columna dentro de SALDOS INICIALES. Técnicamente no se podía tener de otra forma: `billing.credit_balances` tiene `unique('membership_account_id')`, o sea, solo puede existir **una fila de saldo a favor por cuenta**, así que no tenía sentido una hoja aparte con su propio flujo de captura.
- **MOVIMIENTOS DE SALDO** (`billing.credit_movements`): esto es historial de movimientos, no un saldo inicial — cae en el bloque 6 ("historial financiero") que el plan de migración dice que requiere autorización aparte del usuario, no es parte del alcance inicial.
- **OPERACIONES SPEI** y **APLICACIONES SPEI** (`billing.spei_orders`): estructuralmente no se pueden capturar a mano. Los campos clave (CLABE, referencia del banco, estatus del webhook) los genera el sistema al crear una orden SPEI vía Conekta — es el mismo problema que ya se había identificado con `members.payment_sources` en el archivo 3.
- **FUENTES DE PAGO** (`members.payment_sources`): mismo caso — solo se genera al tokenizar una tarjeta con Conekta, ya excluida también en el archivo 3.
- **CORTES DE CAJA, DESGLOSE DE EFECTIVO, CORTES GLOBALES** (`billing.cash_cuts` y relacionadas): esto es historial operativo diario de caja, claramente bloque 6, no algo que tenga sentido "migrar" como saldo inicial.
- **PAGOS y APLICACIONES DE PAGOS** (`billing.payments` / `billing.payment_applications`): reconstruir el historial completo de pagos y a qué cargo se aplicó cada uno es exactamente el tipo de carga que el plan separa en bloque 6 — además de ser una carga de captura enorme para un usuario no técnico. Con SALDOS INICIALES (el neto que debe cada cuenta hoy) basta para arrancar; el historial de cómo se llegó ahí no es indispensable para operar el sistema nuevo.

### Hallazgos NO corregidos en el archivo (requieren decisión del usuario)
- **No existe un concepto de cobro tipo "SALDO INICIAL" en el catálogo CONCEPTOS DE COBRO** (hoja del archivo 1, 160 conceptos reales extraídos de seeders). Cuando se construya el importador de este archivo, todas las filas de SALDOS INICIALES tendrán que convertirse en un `billing.charges` real con algún `concept_id` — y ahora mismo no hay ningún concepto sembrado pensado para eso (todos son conceptos operativos tipo mantenimiento/inscripción/etc.). Habrá que agregar un concepto nuevo (ej. "SALDO INICIAL POR MIGRACIÓN") antes de programar el importador, o decidir cómo mapear estos montos de otra forma.

## Archivo 6 — 06_CASILLEROS.xlsx (terminado, entregado 2026-09-24)

Ya existía pero en el formato ANTERIOR al estándar (sin barra de título ni fila tooltip). Se reconstruyó completo. Hojas: INSTRUCCIONES (intacta), EJEMPLOS (reconstruida), CASILLEROS, ASIGNACIONES VIGENTES, BAJAS DE ASIGNACIONES, HISTORIAL DE ASIGNACIONES.

A diferencia del archivo 5, aquí el "historial de asignaciones" **sí está dentro del alcance**: el `Plan_migracion_datos_sistema_anterior.md` lista explícitamente "80. Historial de asignaciones" como parte del bloque 7 (Casilleros), a diferencia del historial financiero del archivo 5 que requiere autorización aparte. Por eso esa hoja se conservó y se mejoró, no se eliminó.

- **CASILLEROS** (`members.lockers`): 4 columnas (club, número, categoría, estatus). Se dejó con **0 filas de datos reales** a propósito — ver hallazgo abajo, requiere decisión del usuario.
- **ASIGNACIONES VIGENTES** y **BAJAS DE ASIGNACIONES** (ambas mapean a `members.locker_assignments`; la diferencia es si tienen `cancellation_reason`/`deleted_at` o no): se les quitó la columna `NÚMERO DE MEMBRESÍA` (ver corrección abajo) y se les agregó `CATEGORÍA DEL CASILLERO` (ver corrección abajo).
- **HISTORIAL DE ASIGNACIONES** (`members.locker_assignment_histories`): se le quitó `NÚMERO DE MEMBRESÍA` (mismo motivo) y se reemplazó la columna única `NOMBRE DEL CLUB` por dos columnas separadas, `CLUB DEL CASILLERO ANTERIOR` y `CLUB DEL CASILLERO NUEVO`, y se agregó `CATEGORÍA DEL CASILLERO ANTERIOR`/`CATEGORÍA DEL CASILLERO NUEVO` — ver corrección abajo.
- **EJEMPLOS**: reescrita completa. Los ejemplos anteriores usaban números de casillero tipo "A-101"/"B-204" (con letras), un club genérico "PARQUE ESPAÑA" (no PE1/PE2), una `CATEGORÍA` con placeholders "DATOS DE EJEMPLO 1/2/3" y un `ESTATUS` con valores "ACTIVO"/"PENDIENTE" que no existen en el enum real de la BD. Ahora usa números enteros reales, "Parque España I"/"Parque España II", y los valores reales de los enums (`Niños/Niñas/Caballeros/Damas` y `Disponible/Ocupado/Mantenimiento/Pago pendiente`).

### Correcciones aplicadas durante la reconstrucción (mencionadas aquí, no en el archivo)
- **Faltaba la columna `CATEGORÍA DEL CASILLERO` en ASIGNACIONES VIGENTES, BAJAS DE ASIGNACIONES e HISTORIAL DE ASIGNACIONES.** La migración `2026_04_22_224129_create_lockers_table.php` define el índice único como `(club_id, number, category)`, es decir, el mismo número de casillero se reutiliza en cada categoría (confirmado en `LockerSeeder.php`: en cada club, "Niños" empieza en el número 1, "Niñas" empieza en el número 1, "Caballeros" empieza en el número 1, etc. — son secuencias independientes). Sin la categoría, club + número no alcanzan para identificar un casillero único, y el importador no podría saber a cuál casillero se refiere cada fila. Se agregó esta columna como requerida en las tres hojas.
- **`NÚMERO DE MEMBRESÍA` no corresponde a ningún campo real de `members.locker_assignments` ni de `members.locker_assignment_histories`.** Ambas tablas solo referencian `member_id` (el socio), nunca una cuenta/membresía — un casillero se asigna a la persona, no a su membresía. Se quitó de las tres hojas (VIGENTES, BAJAS, HISTORIAL) porque era información que no tenía dónde aterrizar y podía sugerir, incorrectamente, que el importador la necesitaría.
- **En HISTORIAL DE ASIGNACIONES, la columna única `NOMBRE DEL CLUB` era insuficiente.** `old_locker_id` y `new_locker_id` son dos llaves foráneas independientes hacia `members.lockers`, y cada casillero (anterior y nuevo) tiene su propio `club_id` — en teoría un socio podría cambiar de casillero entre PE1 y PE2 si tiene membresía en ambos. Se reemplazó por dos columnas: `CLUB DEL CASILLERO ANTERIOR` (opcional, ya que `old_locker_id` es nullable — no hay casillero anterior en la primera asignación de un socio) y `CLUB DEL CASILLERO NUEVO` (requerida).
- **El número de casillero en los ejemplos anteriores usaba letras** ("A-101", "B-204"), pero la migración define `number` como `integer`. Se corrigió a números enteros simples en EJEMPLOS.

### Hallazgo NO corregido en el archivo (requiere decisión del usuario)
- **La hoja CASILLEROS se dejó sin filas de datos reales a propósito, a pesar de que existe `LockerSeeder.php` con conteos específicos y aparentemente reales** (Parque España I: 65 Niños + 65 Niñas + 1400 Caballeros + 582 Damas = 2,112 casilleros; Parque España II: 1,170 Damas + 1,926 Caballeros + 288 Niñas + 242 Niños = 3,626 casilleros; total 5,738). A diferencia de los catálogos del archivo 1 (donde si se precargó todo, ej. CIUDADES=37,536), aquí **no se precargó** porque el campo `ESTATUS` de esta tabla no es un dato fijo de catálogo sino el estado real y cambiante de cada casillero (ocupado/disponible/etc.), y el seeder simplemente marca todos como "disponible" por default. **Decisión del usuario (2026-09-24): confirmó que está bien que el cliente capture los casilleros directamente, sin prellenar la hoja.** Punto cerrado, no requiere seguimiento.

## Archivo 7 — 07_AMENIDADES_Y_CLASES.xlsx (terminado, entregado 2026-09-24)

Ya existía pero en el formato ANTERIOR al estándar. Se reconstruyó completo. Hojas finales: INSTRUCCIONES (intacta), EJEMPLOS (reconstruida), AMENIDADES, HORARIOS AMENIDADES, RECURSOS, UBICACIONES RECURSOS, PERIODOS BLOQUEADOS, ENTRENADORES, ESPECIALIDADES ENTRENADOR, DISPONIBILIDAD ENTRENADORES, HORARIOS DE CLASES, INSCRIPCIONES A CLASES. Se eliminó la hoja **ESPECIALIDADES** (ver corrección abajo).

A diferencia de los archivos 3/4/6 (donde la única fuente de datos era Factory/Faker y no había nada real que precargar), aquí `AmenitySeeder.php`, `CoachSeeder.php` y `ClassScheduleSeeder.php` sí contienen datos reales, fijos y deterministas (no aleatorios) para Parque España I: 5 amenidades con sus recursos y horarios, 4 entrenadores con sus especialidades, y 7 horarios de clases. Siguiendo el mismo criterio que en el archivo 2 (CLUBES/DOMICILIOS), **se precargaron estas hojas con esos datos reales**, tratándolas igual que cualquier otra hoja de captura (rojo/azul, no catálogo verde) porque el cliente puede seguir editándolas:

- **AMENIDADES**: 5 filas (Canchas de pádel, Jardines, Canchas de tenis, Canchas de frontón, Alberca), todas de Parque España I.
- **HORARIOS AMENIDADES**: 30 filas (5 amenidades × 6 días — abren de martes a domingo, cerradas los lunes, 07:00–19:00).
- **RECURSOS**: 23 filas (5 canchas de pádel, 11 recursos de jardines/asadores, 4 canchas de tenis, 2 canchas de frontón, 1 alberca).
- **ENTRENADORES**: 4 filas (Carlos Mendoza, Ana Gutiérrez, Roberto Sánchez, Laura Torres).
- **ESPECIALIDADES ENTRENADOR**: 6 filas (relación entrenador↔especialidad según el seeder).
- **UBICACIONES RECURSOS** y **PERIODOS BLOQUEADOS**: 0 filas — ningún seeder define coordenadas GPS ni bloqueos, no hay nada real que precargar ahí.
- **DISPONIBILIDAD ENTRENADORES** e **INSCRIPCIONES A CLASES**: 0 filas — no existe seeder para `classes.coach_availabilities` ni para `classes.class_enrollments`.
- **EJEMPLOS**: reescrita completa con datos realistas (algunos de Parque España II, ya que todo lo prellenado en las hojas de captura es de Parque España I y conviene mostrar también cómo se vería un registro de otro club) y corrigiendo bugs claros del archivo anterior: la columna ACTIVA tenía números sueltos en vez de SÍ/NO (aparentemente pegados por error desde la columna de capacidad/coordenadas), TIPO DE RESERVACIÓN y TIPO DE CLASE tenían placeholders "DATOS DE EJEMPLO N" en vez de valores reales, HORA DE APERTURA y HORA DE CIERRE eran siempre la misma hora, y la DESCRIPCIÓN/MOTIVO DEL BLOQUEO tenían texto genérico de otro contexto ("Registro de ejemplo para el socio...").

### Corrección aplicada durante la reconstrucción (mencionada aquí, no en el archivo)
- **Se eliminó la hoja ESPECIALIDADES.** Mapea a `classes.specialties`, que es exactamente la misma tabla que ya tiene su propio catálogo completo en `01_CATALOGOS.xlsx` (hoja ESPECIALIDADES, 2 registros reales: Tenis y Padel, vía `SpecialtySeeder.php`). Tener la misma tabla capturable en dos archivos distintos habría creado dos lugares para mantener el mismo dato y riesgo de que quedaran desincronizados. La hoja ESPECIALIDADES ENTRENADOR (tabla distinta, de relación) se conservó normalmente, solo referenciando el catálogo por nombre.
- **Se quitó `NÚMERO DE MEMBRESÍA` de INSCRIPCIONES A CLASES**, mismo motivo que en el archivo 6: `classes.class_enrollments` solo referencia `member_id` (el socio), nunca una cuenta o membresía.

### Hallazgo NO corregido en el archivo (contexto, no requiere acción inmediata)
- **El seeder de amenidades (`AmenitySeeder.php`) solo tiene datos para Parque España I; el arreglo de Parque España II está vacío (`'amenities' => []`).** No es un error del Excel, es que ese club simplemente no tiene amenidades configuradas todavía en el sistema. El cliente deberá capturar manualmente las amenidades, recursos y horarios reales de Parque España II — no hay nada que migrar de ahí porque nunca se sembró.

## Archivo 8 — 08_RESERVACIONES_Y_ACCESOS.xlsx (terminado, entregado 2026-09-24)

Ya existía pero en el formato ANTERIOR al estándar (sin barra de título ni fila tooltip). Se reconstruyó completo. Hojas finales: INSTRUCCIONES (intacta), EJEMPLOS (reconstruida), RESERVACIONES, LISTAS DE INVITADOS, INVITADOS, VARIABLES LISTAS, DISPOSITIVOS DE ACCESO, USUARIOS INVITADOS, TARJETAS DE PASE DIARIO, PASES DIARIOS PROGRAMADOS, VISITANTES PASE PROGRAMADO. Ninguna tabla de este archivo tiene datos reales sembrados en seeders (a diferencia del archivo 7), así que todas las hojas quedaron con 0 filas de datos, solo estructura.

- **RESERVACIONES** (`reservations.reservations`): se agregaron columnas que faltaban tras revisar la evolución completa de la migración: `REQUIERE CARPA`/`NÚMERO DE MESAS`/`NÚMERO DE SILLAS`/`NOTAS` (agregados por `add_garden_details_to_reservations_table`, para reservas de jardín), `RESERVACIÓN VINCULADA` (`linked_reservation_id`), y `ES CLASE`/`NOMBRE DE LA CLASE`/`NOMBRE DEL ENTRENADOR` (agregados por `add_class_fields_to_reservations_table`, para cuando la reservación es en realidad una clase). Se quitó `NÚMERO DE MEMBRESÍA` (mismo motivo que en archivos 6/7: la tabla solo referencia `member_id`, nunca cuenta/membresía).
- **LISTAS DE INVITADOS** e **INVITADOS** (`guest_lists.guest_lists` / `guest_lists.guest_list_items`): se confirmó que estas hojas ya apuntaban al esquema correcto y vigente (la migración `move_guest_list_tables_to_new_schema` reemplazó las tablas viejas `reservations.guest_lists`/`guest_list_items`, ya eliminadas, por estas). Se quitó `NÚMERO DE MEMBRESÍA` y `COLOR` de LISTAS DE INVITADOS (ninguna corresponde a un campo real), y se agregó `CORREO DE QUIEN CREÓ LA LISTA` (opcional) que faltaba.
- **VARIABLES LISTAS** (`guest_lists.variables`): catálogo de variables configurables por club para las listas de invitados (ej. tope de invitados, precio por invitado). Se dejó igual, solo estilo estándar.
- **DISPOSITIVOS DE ACCESO** (`devices.devices`): se agregaron columnas que faltaban (`PUERTO`, `USA HTTPS`, `USUARIO DEL DISPOSITIVO`, `MÁXIMO DE TARJETAS POR PERSONA`) tras revisar las migraciones de agosto/septiembre. **Se excluyó a propósito `CONTRASEÑA DEL DISPOSITIVO`** (columna `password` de la tabla) — es una credencial real de acceso al hardware de control de acceso, no un dato de captura de negocio; aplica la misma regla que ya protege llaves de Conekta en otros archivos.
- **USUARIOS INVITADOS** (`devices.guest_users`): hoja sin cambios de estructura mayores, solo estilo estándar.
- **TARJETAS DE PASE DIARIO** (`devices.daily_pass_cards`): aquí **si se conservó** `NÚMERO DE MEMBRESÍA` junto con `NÚMERO DE SOCIO` — a diferencia de las demás tablas de este archivo, esta sí referencia `account_member_id` (la tabla pivote `memberships.account_members`), que requiere ambos datos para resolverse correctamente. Se revisó la evolución de nulabilidad de `device_id`/`guest_user_id`/`status` (ahora aceptan `NULL`/`'scheduled'` para tarjetas programadas con anticipación) y se ajustaron los colores rojo/azul en consecuencia.
- **PASES DIARIOS PROGRAMADOS** (`devices.scheduled_daily_passes`) y **VISITANTES PASE PROGRAMADO** (`devices.scheduled_daily_pass_visitors`): mismo tratamiento, `NÚMERO DE MEMBRESÍA` conservado en PASES DIARIOS PROGRAMADOS por el mismo motivo (`account_member_id`).
- **EJEMPLOS**: reescrita completa. Los ejemplos anteriores tenían bugs claros: club genérico "PARQUE ESPAÑA" (no PE1/PE2), placeholders "DATOS DE EJEMPLO N" en columnas enteras (títulos de lista, totales, estatus de dispositivo, número de tarjeta, fecha de validez, mensaje de error), texto boilerplate reciclado ("Registro de ejemplo para el socio...") repetido en descripciones que deberían ser distintas entre sí, y — más grave — **el valor literal "DATOS DE EJEMPLO 1" aparecía en la columna de contraseña del dispositivo**, exactamente el tipo de dato que la regla 5 prohíbe mostrar en el archivo (aunque fuera falso, no debía estar ahí ni como ejemplo). Ahora usa los mismos 3 socios consistentes de archivos anteriores (1001 Juan Carlos Pérez Hernández / 1002 María Fernanda López García / 1003 Roberto Sánchez Morales), Parque España I/II, y ya no incluye ninguna columna de contraseña.

### Decisión de alcance NO puesta en el archivo (mencionada aquí)
- **Se excluyeron del archivo las tablas `guest_lists.day_passes` y `guest_lists.day_pass_visitors`** (pases de un día ya pagados/con fecha pasada). Es historial de visitas ya ocurridas y cobradas, el mismo tipo de dato que el "bloque 6" del archivo 5 (historial financiero) — no es un dato inicial que el cliente deba capturar a mano, es información operativa que se irá generando en el sistema nuevo hacia adelante. El plan de migración menciona "97/98. Pases diarios vigentes y sus visitantes", que sí quedó cubierto (son PASES DIARIOS PROGRAMADOS/VISITANTES PASE PROGRAMADO, que son a futuro, no historial). **`guest_lists.visitor_incidents` NO se excluyó** — a diferencia de day_passes/day_pass_visitors, un incidente de un visitante es un registro administrativo (análogo a un acta), no historial operativo de visitas rutinarias; quedó como hoja INCIDENTES DE VISITANTES en `09_INFORMACION_ADMINISTRATIVA.xlsx` (bloque 10 del plan, item 103), no en este archivo.
- Se revisó `devices.commands` (cola de comandos transitorios hacia el hardware de acceso) y se confirmó que no aplica a este archivo — no es un dato de negocio capturable, es un log operativo de la integración con los dispositivos.
- **`TARJETAS DE PASE DIARIO` y `PASES DIARIOS PROGRAMADOS` tienen una columna `FOLIO O REFERENCIA DEL CARGO ANTERIOR`** que hace referencia a `charge_id` (un cargo de `billing.charges`, ver archivo 5). Es una dependencia cruzada entre archivos: si el cliente llena estas hojas, el folio de cargo que capture debería idealmente coincidir con uno de los que ya cargó (o cargará) en `05_FINANZAS.xlsx`. No se puede validar automáticamente desde el Excel, pero conviene que el equipo de desarrollo lo tenga presente al construir el importador.

### Corrección posterior (2026-09-24, al trabajar el archivo 9): se eliminó la hoja REGLAS DE RESERVACIÓN
Este archivo originalmente incluía una hoja nueva **REGLAS DE RESERVACIÓN** (`clubs.rules`), agregada porque no existía en ningún archivo hasta ese momento. Al construir `09_INFORMACION_ADMINISTRATIVA.xlsx` se descubrió que ese archivo **ya traía, desde antes, una hoja REGLAS DEL CLUB** con exactamente la misma tabla y columnas — y que el plan de migración ubica explícitamente "105. Reglas del club" en el bloque 10 (Información administrativa complementaria), no en el bloque 9 (Reservaciones y accesos). Se eliminó REGLAS DE RESERVACIÓN de este archivo para no tener la misma tabla capturable en dos archivos distintos; **`clubs.rules` se captura únicamente en `09_INFORMACION_ADMINISTRATIVA.xlsx`, hoja REGLAS DEL CLUB**.

## Archivo 9 — 09_INFORMACION_ADMINISTRATIVA.xlsx (terminado, entregado 2026-09-24) — último archivo

Ya existía pero en el formato ANTERIOR al estándar. Se reconstruyó completo. Hojas finales: INSTRUCCIONES (intacta), EJEMPLOS (reconstruida), ACTAS, ARCHIVOS DE ACTAS, MULTAS, AMONESTACIONES, INCIDENTES DE VISITANTES, TIPOS DE ARCHIVO, ARCHIVOS DEL CLUB, REGLAS DEL CLUB. Corresponde al bloque 10 del plan ("Información administrativa complementaria", items 100-106). Ninguna tabla tiene datos reales sembrados en seeders, todas las hojas quedaron con 0 filas de datos, solo estructura.

- **ACTAS** (`members.acts`): la entidad raíz de todo el archivo — folio, club, socio, membresía (para resolver la cuenta), tipo de infracción, descripción, fecha, hora. `ARCHIVOS DE ACTAS`, `MULTAS` y `AMONESTACIONES` se relacionan con ella por el folio del acta, nunca directamente entre sí.
- **MULTAS** (`members.fines`) y **AMONESTACIONES** (`members.warnings`): ambas dependen de un acta (`act_id` requerido en la migración) — coinciden exactamente con la estructura ya existente en el archivo, solo se les aplicó el estilo estándar.
- **INCIDENTES DE VISITANTES** (`guest_lists.visitor_incidents`): confirmado contra la migración y su corrección posterior (`replace_phone_with_email_in_day_pass_tables`, que cambió `visitor_phone` por `visitor_email`) — la hoja ya reflejaba correctamente el campo de correo, no de teléfono. `FOLIO O REFERENCIA DEL PASE DIARIO` es opcional (el incidente puede no estar ligado a ningún pase diario capturado, sobre todo porque los pases diarios ya ocurridos/históricos están fuera de alcance en el archivo 8).
- **REGLAS DEL CLUB** (`clubs.rules`): ya existía en este archivo con la estructura correcta; se confirmó que es la misma tabla que se había agregado por error como "REGLAS DE RESERVACIÓN" en el archivo 8 (ver corrección arriba) — se conservó únicamente aquí.
- **EJEMPLOS**: reescrita completa. Los ejemplos anteriores tenían los mismos bugs recurrentes: club genérico "PARQUE ESPAÑA", placeholders "DATOS DE EJEMPLO N" en columnas enteras (tipo de infracción, tipo de amonestación, tiene suspensión, módulo, tipos de archivo permitidos, orden de visualización), texto boilerplate reciclado, y números sueltos de otro contexto pegados en columnas de texto (ej. tamaño de archivo con valores como "2026"). Ahora usa los mismos 3 socios consistentes de archivos anteriores y Parque España I/II, con datos de ejemplo propios de cada hoja (una infracción de jardín/asador, una discusión con recepción, un incidente de acceso no autorizado, etc.).

### Hallazgo importante NO corregido en el archivo — 3 hojas sin tabla en el sistema todavía (requiere decisión del equipo de desarrollo)
Se revisaron **todas** las migraciones del proyecto (`grep` de cada `Schema::create(...)` en `database/migrations/`) y se confirmó que **no existe ninguna tabla que respalde 3 de las 8 hojas de este archivo**:
- **ARCHIVOS DE ACTAS** (comprobantes/evidencia ligados a una acta).
- **TIPOS DE ARCHIVO** (catálogo de tipos de archivo permitidos por módulo, con reglas de tamaño/extensión).
- **ARCHIVOS DEL CLUB** (documentos propios del club, ej. reglamento interno).

Estas tres hojas ya existían en el archivo (con buen diseño de columnas) y corresponden exactamente a los items **104 ("Archivos y comprobantes relacionados")** y **106 ("Archivos propios del club")** del `Plan_migracion_datos_sistema_anterior.md` — es decir, el plan sí las contempla, pero el desarrollo del sistema nuevo todavía no tiene ninguna migración para guardarlas (no hay tabla `members.act_files`, `catalogs.file_types`, `clubs.files` ni nada equivalente — se buscó también algún sistema polimórfico genérico de archivos, como el que usa Spatie Media Library, y no se encontró ninguno; `members.documents` es específico de documentos de socios, no sirve para esto). **Se dejaron las tres hojas en el archivo** (mismo criterio que la columna `state`/`city` huérfana del archivo 3 y las columnas de SEPARACIONES del archivo 4: información que tiene sentido capturar, aunque hoy no tenga dónde aterrizar), pero el equipo de desarrollo necesita agregar las migraciones correspondientes antes de poder construir el importador de esta parte del archivo 9.

## Limitaciones conocidas

- No hay acceso a base de datos en vivo en las sesiones de Claude — todo el llenado de catálogos se basa en análisis estático de seeders y migraciones. El usuario sí tiene acceso directo a la BD y ha verificado datos vía `SELECT * FROM ...` en vivo.
- Si el usuario quiere verificación cruzada contra datos reales de producción (por si hay registros agregados manualmente que no están en seeders), puede compartir un export/CSV de las tablas relevantes.
- Al escribir archivos de vuelta a la carpeta del usuario en Windows (`device_commit_files`), si el archivo está abierto en Excel en su máquina, la escritura falla con error de archivo bloqueado — hay que avisarle que lo cierre antes de reintentar.

## Siguiente paso

**Los 9 archivos de migración de `database/seeders/data/` están completos, reconstruidos al estilo estándar y entregados.** No hay un "siguiente archivo" pendiente. Pendientes reales que quedan para el equipo de desarrollo (no para Claude, salvo que el usuario pida retomarlos):

1. **Agregar migraciones faltantes** para las 3 hojas sin tabla del archivo 9 (`ARCHIVOS DE ACTAS`, `TIPOS DE ARCHIVO`, `ARCHIVOS DEL CLUB` — ver esa sección arriba) antes de poder importarlas.
2. **Agregar un concepto de cobro "SALDO INICIAL POR MIGRACIÓN"** en `billing.concepts` antes de programar el importador del archivo 5 (`SALDOS INICIALES`).
3. **Confirmar/agregar migración para `separated_at`/`separation_document_path`** en `memberships.accounts` (hallazgo del archivo 4, hoja SEPARACIONES).
4. **Decidir si las columnas huérfanas `state`/`city` de `members.members`** (archivo 3) deben eliminarse con una migración.
5. Si el usuario quiere una revisión cruzada final de todos los archivos entregados (por ejemplo, un repaso rápido de las 9 plantillas juntas antes de que el cliente empiece a capturar), se puede hacer bajo petición.

Si el usuario pide cambios a algún archivo ya entregado, releer primero la sección correspondiente de este documento y el archivo `.xlsx` actual en la carpeta antes de modificar, para no perder ninguna de las correcciones ya aplicadas.
