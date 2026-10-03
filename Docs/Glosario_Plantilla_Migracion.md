# Glosario de la plantilla de migración

**Archivo:** `database/data/Plantilla_Migracion_Cliente.xlsx`
**Actualizado:** 2026-10-03
**Para qué sirve:** saber, pestaña por pestaña, a qué tablas del sistema va cada dato, qué módulos afecta, para qué sirven esos módulos, para qué se usa cada campo y qué reglas revisa la carga antes de dejar subir el archivo.

---

## Cómo leer este glosario

**Obligatorio:**
- **Sí:** siempre se tiene que llenar.
- **Si…:** solo es obligatorio en ese caso (por ejemplo, "si la cuenta está CANCELADA").
- **No:** se puede dejar vacío y capturar después en el sistema sin perder nada.

**Reglas:**
- **Error (bloquea):** si se incumple, el archivo **no se carga**. La pantalla dice pestaña, fila, campo y qué corregir, y no se guarda nada.
- **Aviso (no bloquea):** se carga, pero conviene revisarlo.

**Criterio:** solo bloquea lo que rompe la carga o lo que el sistema usa para calcular o dar continuidad: cuotas, edades, titulares, bajas, saldos, mensualidades, accesos y folios. Apellido materno, teléfono, ocupación, empresa o datos médicos no bloquean.

**Cómo se carga hoy:**

| Pantalla de carga | Comando | Pestañas que lee |
|---|---|---|
| **Carga de socios** | `php artisan migrate:socios <archivo> [--dry-run] [--sin-personal]` | Personal, Usuarios, Membresias, Integrantes |
| **Carga de dinero** | `php artisan migrate:dinero <archivo> [--dry-run]` | Cargos, Pagos |
| Seeders (no se carga desde la plantilla) | `php artisan db:seed` | Catalogos, Clubes, Importes por club |
| **Aún sin código de carga** | — | Informacion medica, Contactos de emergencia, Documentos, Permisos de ausencia, Notas de cobranza, Casilleros, Entrenadores, Reservaciones, Listas de invitados |

Orden: primero la carga de socios y luego la de dinero, porque los cargos y pagos necesitan que la cuenta ya exista.

**Cómo funciona cada pantalla de carga:**
1. Se sube el archivo y se revisa completo.
2. Si no hay errores, se hace una simulación completa que no guarda nada.
3. Solo entonces se habilita **Confirmar carga**.
4. Si se corre otra vez, no duplica: reconoce cada registro por su ID DE USUARIO, NUMERO DE CUENTA, REFERENCIA DEL CARGO o FOLIO.

---

## Los módulos del sistema y para qué sirven

| Módulo (menú) | Para qué sirve |
|---|---|
| **Membresías › Membresías** | Lista de cuentas y ficha del socio: integrantes, cuota, historial, documentos, casillero, permisos, datos fiscales y número de cuenta |
| **Membresías › Nueva membresía** | Alta de una cuenta. Calcula la cuota con las reglas de precio y crea los cargos iniciales |
| **Membresías › Historial de bajas** | Cuentas dadas de baja: fecha, tipo (voluntaria o sanción), motivo, carta y quién la procesó |
| **Membresías › Transiciones por edad** | Cambia la membresía de quien cumple la edad límite (por ejemplo, de hijo a Solidaria) |
| **Configuración de membresías** | Tipos de membresía, reglas de precio, paquetes entre parques y tipos de documento |
| **Cobranza › Registro de cobros** | Buscar una cuenta por No. Cuenta o nombre del titular, ver lo que debe, cobrar y agregar conceptos. Al abrir una cuenta, el sistema crea las mensualidades que falten hasta el mes actual |
| **Cobranza › Historial de pagos** | Recibos cobrados: reimprimir, buscar por folio o cuenta, cancelar un pago |
| **Cobranza › Cortes de caja / globales** | Dinero cobrado por cajero y por día. Los pagos migrados **no** entran a los cortes |
| **Cobranza › Cuotas por año / Conceptos / Métodos** | Precios por año, conceptos de cobro y formas de pago |
| **App móvil** | El socio ve su estado de cuenta, paga, reserva y ve su perfil. Usuarios de la app y contraseña por defecto |
| **Control de acceso** | Tarjetas de los integrantes en los lectores. Se bloquea a quien debe 3 o más mensualidades vencidas |
| **Casilleros** | Inventario, asignación y si el casillero del año está pagado |
| **Amenidades / Reservaciones / Listas de invitados** | Reservar canchas, jardines y alberca, con o sin entrenador, y registrar invitados con su cobro |
| **Usuarios / Roles** | Personal que entra al panel, sus permisos, parques y serie de caja |
| **Procesos automáticos** | Mensualidades del mes (día 1), bloqueo por morosidad (diario), cambios por edad |

---

## Personal

**Qué es:** quién entra al panel (administradores, cobranza, cajeros) y los cajeros que ya no laboran pero aparecen en recibos viejos.
**Se carga con:** Carga de socios (se puede omitir con `--sin-personal`).
**Tablas:** `public.users`, `public.user_clubs`, `public.model_has_roles`.
**Módulos que afecta:** Usuarios / Roles (acceso y permisos) · Registro de cobros e Historial de pagos (quién cobró) · Cortes de caja (por cajero).

| Campo | Obligatorio | Se usa para | Reglas |
|---|---|---|---|
| NOMBRE | Sí | Nombre del usuario en recibos, cortes y bitácoras | Error si está vacío |
| APELLIDO PATERNO | No* | Forma parte del nombre | — (*en la plantilla está en rojo, pero la carga no lo exige) |
| APELLIDO MATERNO | No | Nombre completo | — |
| CORREO | Si no hay serie | Usuario para entrar al panel | Error si es inválido o se repite en la pestaña. Si va vacío, se crea un usuario sin acceso (`cajero-historico-<serie>@migration.invalid`) |
| ROL | No | Menús y permisos | Error si no existe en Roles. Vacío = sin permisos (cajero histórico) |
| CLUBES | Sí | Parques donde puede operar (separados por coma) | Error si un club no existe |
| SERIE DE CAJA | Si no hay correo | Serie de sus folios y liga con la columna SERIE DE CAJA de Pagos | Error si se repite o ya pertenece a otro usuario del sistema |

**Reglas de la fila:** error si CORREO y SERIE DE CAJA están vacíos los dos.
**Nota:** no se piden contraseñas; se asigna una aleatoria.

---

## Usuarios

**Qué es:** cada persona (titulares y familiares). Una fila por persona, aunque esté en los dos parques.
**Se carga con:** Carga de socios.
**Tablas:** `members.members` (persona; el ID se guarda en `migration_origin_id`), `members.addresses`, `members.employment_info`, `public.users` (acceso a la app).
**Módulos que afecta:** ficha del socio · Cobranza (búsqueda por nombre del titular) · App móvil (perfil y acceso) · Transiciones por edad · precio de Solidaria · precio de invitados.

| Campo | Obligatorio | Se usa para | Reglas |
|---|---|---|---|
| ID DE USUARIO | Sí | Clave de la persona; la liga con Integrantes y las demás pestañas | Error si está vacío o repetido |
| NOMBRE, APELLIDO PATERNO | Sí | Nombre en ficha, búsquedas y recibos | Error si están vacíos (el sistema no guarda una persona sin apellido paterno) |
| APELLIDO MATERNO | No | Nombre completo | — |
| FECHA DE NACIMIENTO | Sí | Edad: precio de Solidaria, acceso a la app (14 años o más), transiciones por edad, precio de invitados | Error si está vacía, es inválida (AAAA-MM-DD), es futura o es anterior a 1900 |
| SEXO | No | Ficha | Error si no es H o M |
| TELEFONO | No | Contacto | — |
| CORREO | Si es titular de una cuenta activa | Usuario de la app y avisos de cobro | Ver reglas de la fila |
| ESTADO CIVIL | No | Ficha | Error si no existe en el catálogo |
| NACIONALIDAD | No | Ficha | Error si no existe (se busca por gentilicio, ej. "Mexicana") |
| PAIS / ESTADO / CIUDAD DE NACIMIENTO | No | Ficha | Error si no existen o si falta el nivel anterior (ciudad sin estado, estado sin país) |
| OCUPACION, ESCUELA | No | Ficha | — |
| CALLE Y NUMERO, COLONIA, CODIGO POSTAL | No | Domicilio principal | — |
| PAIS / ESTADO / CIUDAD | No | Domicilio | Mismas reglas que el lugar de nacimiento |
| AÑOS EN LA CIUDAD | No | Ficha | Error si no es un número entero |
| EMPRESA, DOMICILIO y TELEFONO DE LA EMPRESA | No | Datos laborales | — |

**Reglas de la fila:**
- **Error:** el titular de una cuenta ACTIVA, SUSPENDIDA o PENDIENTE sin correo.
- **Error:** un correo inválido.
- **Error:** dos personas que tendrán app (14 años o más, en una cuenta ACTIVA o SUSPENDIDA) con el mismo correo, porque cada una necesita el suyo.
- **Error:** un correo que ya usa otro usuario del sistema.
- **Aviso:** un ID DE USUARIO que no aparece en Integrantes; se crea la persona, pero sin cuenta.

**Qué hace la carga además:** crea el acceso a la app, con la contraseña por defecto de *Variables de App Móvil*, a quien tiene correo, 14 años o más y una cuenta activa o suspendida.

---

## Membresias

**Qué es:** cada cuenta en un parque: tipo, estatus, fechas, cobro, cuenta del otro parque, baja y facturación.
**Se carga con:** Carga de socios.

**Tablas:**
- `memberships.accounts`: cuenta, estatus, baja, grupo entre parques y piso de mensualidades.
- `memberships.memberships`: tipo, cuota, regla de precio, si cobra y vigencia.
- `memberships.account_groups`: une las dos cuentas de una familia en los dos parques.
- `memberships.membership_history`: alta y baja.
- `memberships.account_fiscal_data`: datos de facturación.
- `members.documents`: la carta de baja.

**Módulos que afecta:**
- Membresías (lista, ficha, historial de bajas).
- **Cobranza:** cuota, concepto de la mensualidad y las dos cuentas de un socio de ambos parques juntas.
- Mensualidades automáticas y bloqueo por morosidad.
- App móvil.

| Campo | Obligatorio | Se usa para | Reglas |
|---|---|---|---|
| NUMERO DE CUENTA | Sí | No. Cuenta que se ve y se busca en Cobranza; liga con Integrantes, Cargos y Pagos | Error si está vacío, repetido o ya existe en el sistema en otro club |
| CLUB | Sí | Parque de la cuenta | Error si no existe |
| TIPO DE MEMBRESIA | Sí | Cuota, cuántos integrantes admite, documentos, concepto de la mensualidad | Error si no existe en ese club |
| INDIVIDUAL O FAMILIAR | No | Tipo de cuenta (si va vacío, sale del tipo de membresía) | Error si no es INDIVIDUAL o FAMILIAR, o si contradice al tipo |
| ESTATUS | Sí | ACTIVA, SUSPENDIDA y PENDIENTE operan; CANCELADA = baja. Solo ACTIVA y SUSPENDIDA generan mensualidades y dan app | Error si no es uno de los cuatro |
| FECHA DE INICIO | Sí | Antigüedad e inicio de la membresía | Error si es inválida o futura (salvo PENDIENTE) |
| FECHA DE TERMINO | No | Vencimiento (pases). Si un pase mensual va sin fecha, se calcula: inicio + vigencia del tipo | Error si es inválida o anterior al inicio |
| CUOTA MENSUAL | Si GENERA COBRO = SI | Comprobar la cuota. **Se usa la del sistema** (reglas de precio) | Ver reglas de la fila |
| GENERA COBRO | Sí | Si la cuenta genera mensualidades. En ambos parques, solo una cobra | Error si no es SI o NO |
| CUENTA EN EL OTRO PARQUE | No | Une las dos cuentas del mismo socio (una por parque) para cobrar la mensualidad de ambos parques | Ver reglas de dos parques |
| TIPO DE MEMBRESIA ANTERIOR | Si el tipo viene de una familiar (Solidaria) | Regla de precio | Error si no existe, si falta en Solidaria o si el tipo anterior no es familiar |
| CUENTA DE ORIGEN | No | Cuenta de la que se separó | Error si no existe en la pestaña o es la misma cuenta |
| MOTIVO DE SEPARACION | No | Por qué se separó | Error si se llena sin CUENTA DE ORIGEN |
| FECHA DE CANCELACION | Si está CANCELADA | Fecha de baja; la membresía termina ese día | Error si falta, es inválida, es anterior al inicio o es futura |
| TIPO DE CANCELACION | Si está CANCELADA | VOLUNTARIA o SANCION (Historial de bajas) | Error si falta o es otro valor |
| MOTIVO DE CANCELACION | Si está CANCELADA | Motivo del catálogo | Error si falta o no existe |
| ARCHIVO DE LA CARTA DE CANCELACION | No | Carta de baja; se sube como documento del titular | Aviso si no se encuentra en `database/data/ARCHIVOS` |
| NOMBRE O RAZON SOCIAL, RFC, USO DE CFDI, REGIMEN FISCAL, CODIGO POSTAL FISCAL | Los cinco o ninguno | Facturación de la cuenta | Error si falta alguno, si el RFC no tiene formato (12 o 13 caracteres) o si el código postal no tiene 5 dígitos |

**Reglas de la fila:**
- **Error:** GENERA COBRO = SI con la cuota vacía o en cero.
- **Error:** GENERA COBRO = NO con cuota capturada.
- **Error:** cuenta no CANCELADA con datos de baja (fecha, tipo, motivo o carta). Esos campos solo se llenan en cuentas canceladas.
- **Error:** cuenta sin titular en Integrantes.
- **Error:** no existe regla de precio de ambos parques ni paquete para la combinación de tipos.
- **Aviso:** la cuota de la plantilla es distinta a la que calcula el sistema. Se usa la del sistema.
- **Aviso:** cuenta de un solo parque con GENERA COBRO = NO; no tendrá mensualidades.
- **Aviso:** el tipo no tiene regla de precio con cuota; se usa la CUOTA MENSUAL de la plantilla.

**Reglas de dos parques:** el enlace se puede escribir de un solo lado o de los dos.
- **Error:** la cuenta del otro parque no existe en la pestaña, es la misma cuenta o es del mismo club.
- **Error:** la otra cuenta dice que su pareja es una tercera cuenta.
- **Error:** el titular no es el mismo en las dos cuentas.
- **Error:** si las dos están ACTIVAS o SUSPENDIDAS, las dos dicen SI en GENERA COBRO, o las dos dicen NO. Solo una cobra.

**Qué hace la carga además:**
- **Cuota:** calcula la regla de precio igual que el alta.
  - La cuenta que cobra lleva la regla de ambos parques o el paquete interclub; sin eso, Cobranza no junta las dos cuentas.
  - La otra cuenta lleva su regla propia y no cobra.
- **Historial:** escribe "Alta histórica migrada". Si la cuenta está cancelada, escribe también **"Baja voluntaria de cuenta"**, la razón que reconoce el sistema para no cobrar meses de baja si la cuenta se reactiva.
- **Carta de baja:** la sube como documento del titular (`members/{titular}/{tipo de documento}/…`).

---

## Integrantes

**Qué es:** quién forma cada cuenta y quién es el titular.
**Se carga con:** Carga de socios.
**Tablas:** `memberships.account_members`.
**Módulos que afecta:**
- Ficha del socio (integrantes).
- **Cobranza:** todo se cobra al titular, y se busca por su nombre.
- Control de acceso (tarjetas).
- App móvil (familia, rol de titular o dependiente).
- Reservaciones.

| Campo | Obligatorio | Se usa para | Reglas |
|---|---|---|---|
| NUMERO DE CUENTA | Sí | La cuenta | Error si no existe en Membresias |
| ID DE USUARIO | Sí | La persona | Error si no existe en Usuarios o si ya está en esa cuenta |
| ES TITULAR | Sí | A quién se cobra; quién aparece en las búsquedas | Error si no es SI o NO |
| PARENTESCO | Si ES TITULAR = NO | Documentos que se piden, límite de edad de hijos, transiciones por edad | Error si falta en un integrante, si se llena en el titular o si no existe en el catálogo |
| NUMERO DE TARJETA DE ACCESO | No | Tarjeta para los lectores. Vacío = el sistema asigna una nueva | Error si se repite o si ya la tiene otra persona en el sistema |

**Reglas de la cuenta:**
- **Error:** una cuenta con más de un titular (o sin titular, se reporta en Membresias).
- **Error:** una cuenta de tipo individual con más de una persona.
- **Error:** una persona en dos cuentas activas del mismo club.

**Nota:** las tarjetas se guardan, pero **no se mandan a los lectores** en esta carga.

---

## Cargos

**Qué es:** todo lo que se cobró o se debe (mensualidades, inscripciones, adeudos, casilleros, pases, listas, cursos…) y qué recibo pagó cada cargo.
**Se carga con:** Carga de dinero.
**Tablas:** `billing.charges` (cargo, saldo, estatus) y `billing.payment_applications` (qué parte de qué pago cubrió el cargo, con su descuento).

**Módulos que afecta:**
- **Cobranza › Registro de cobros:** pendientes, vencidos, última mensualidad pagada.
- **Historial de pagos y cancelación de pagos:** qué cargos cubrió cada recibo.
- **App móvil:** estado de cuenta y pago en línea.
- **Bloqueo por morosidad:** 3 o más mensualidades vencidas.
- **Casilleros:** si el casillero está pagado.

| Campo | Obligatorio | Se usa para | Reglas |
|---|---|---|---|
| REFERENCIA DEL CARGO | Sí | Clave del cargo. Se repite la fila cuando un cargo se pagó con varios recibos | Error si se repite con otros datos (cuenta, concepto, importe, periodo o cancelado) |
| NUMERO DE CUENTA | Sí | A quién se cobra (el cargo queda a nombre del titular) | Error si la cuenta no existe en el sistema (falta la carga de socios) |
| CONCEPTO DE COBRO | Sí | Qué se cobra; si es mensualidad cuenta para la morosidad y para no repetir meses | Error si no existe en el catálogo |
| DESCRIPCION | No | Texto en Cobranza y en el recibo | — |
| IMPORTE | Sí | Monto total; base del saldo | Error si no es número o no es mayor que cero |
| FECHA DE EMISION | Si no es mensualidad y no tiene pago | Desde cuándo se debe (reportes, app) | Error si es inválida, o si falta en un cargo sin pago que no es mensualidad |
| FECHA DE VENCIMIENTO | No | Cuándo cuenta como vencido (morosidad) | Error si es inválida o anterior a la emisión |
| AÑO / MES DEL PERIODO | Si es mensualidad | Qué mes cubre: evita cobrarlo dos veces, morosidad, estado de cuenta | Error si falta en una mensualidad, si va solo uno de los dos o si es inválido |
| PAGO EN PARCIALIDADES | No | Permite abonos en caja | Error si no es SI o NO |
| FOLIO DEL RECIBO | No | Con qué recibo se pagó | Error si el folio no existe en Pagos o es de otra cuenta |
| IMPORTE PAGADO | No | Cuánto pagó ese recibo (vacío = IMPORTE − DESCUENTO) | Error si va sin folio, no es número o es 0 sin descuento |
| DESCUENTO | No | Descuento al pagar (por ejemplo, mes gratis del pago anual) | Error si va sin folio, es negativo o es mayor que el importe |
| CANCELADO | No | El cargo ya no se debe | Error si no es SI o NO |
| FECHA y MOTIVO DE CANCELACION | Si CANCELADO = SI | Rastro de la cancelación | Error si faltan con SI, o si se llenan sin SI |
| NOTAS | No | Observaciones | — |

**Reglas del cargo:**
- **Error:** el cargo queda sobrepagado (lo pagado más el descuento es mayor que el importe).
- **Error:** el cargo está cancelado y tiene un recibo vigente; hay que cancelar también el recibo o quitar el folio.
- **Error:** dos mensualidades vigentes del mismo mes en la misma cuenta, con referencias distintas, porque sería un cobro doble.
- **Aviso:** el cargo queda con saldo y no dice SI en PAGO EN PARCIALIDADES.

**Qué hace la carga además:**
- **Fechas de mensualidad:** sin fechas, toma emisión el día 1 del periodo y vencimiento el día 10, igual que el sistema. Los demás cargos sin fecha toman la del pago.
- **Piso de mensualidades:** marca en cada cuenta y en su cuenta del otro parque el mes siguiente a la última mensualidad cargada. Así, al abrir la cuenta en Cobranza, el sistema **no vuelve a crear** meses condonados ni meses que no se mandaron por estar pagados; a partir de ese mes genera las mensualidades normalmente.
- **Saldo y estatus:** calcula el saldo y el estatus (pagado, parcial, pendiente o cancelado) de cada cargo.

---

## Pagos

**Qué es:** los recibos cobrados y con qué forma de pago. Una fila por forma de pago (efectivo y tarjeta en el mismo recibo = 2 filas con el mismo folio).
**Se carga con:** Carga de dinero.
**Tablas:** `billing.payments` (las filas de un recibo comparten `payment_group_id`).
**Módulos que afecta:** Historial de pagos (buscar y reimprimir por folio) · cancelación de pagos · Reportes · App móvil (historial). **No** entran a los cortes de caja.

| Campo | Obligatorio | Se usa para | Reglas |
|---|---|---|---|
| FOLIO DEL RECIBO | Sí | Número del recibo de Fox; agrupa sus formas de pago | Error si ya lo tiene un cobro hecho en el sistema |
| NUMERO DE CUENTA | Sí | Cuenta que pagó | Error si no existe en el sistema |
| CLUB DONDE SE COBRO | Sí | Caja donde se cobró | Error si no existe |
| FECHA DEL PAGO | Sí | Historial y reportes | Error si es inválida o futura |
| HORA DEL PAGO | No | Historial | Error si no es HH:MM de 24 horas |
| METODO DE PAGO | Sí | Forma de pago | Error si no existe en el catálogo |
| PARQUE DE LA FORMA DE PAGO | No | Si la terminal o cuenta era del otro parque | Error si el club no existe |
| IMPORTE | Sí | Lo cobrado con esa forma | Error si no es número o no es mayor que cero |
| REFERENCIA, BANCO, NUMERO DE CHEQUE | No | Conciliación | Aviso si el método los pide y van vacíos |
| SERIE DE CAJA | No | Cajero que cobró | Error si la serie no existe (debe venir en Personal) |
| CANCELADO | No | Recibo anulado: no cuenta como pagado y sus cargos quedan pendientes | Error si no es SI o NO |
| FECHA y MOTIVO DE CANCELACION | Si CANCELADO = SI | Rastro de la cancelación | Error si faltan con SI, o si se llenan sin SI |
| NOTAS | No | Observaciones | — |

**Reglas del recibo:**
- **Error:** las filas de un mismo folio tienen distinta cuenta, club, fecha o estado de cancelación. Un recibo se cancela completo.
- **Error:** ningún cargo tiene ese folio, así que no se sabe qué pagó.
- **Error (cuadre):** la suma del recibo en Pagos debe ser igual a la suma de sus cargos (IMPORTE PAGADO, o IMPORTE − DESCUENTO).

**Qué hace la carga además:**
- **Folio:** la primera forma de pago conserva el folio de Fox; las demás reciben un folio del sistema dentro del mismo recibo.
- **Cajero:** liga el cobro con el cajero por su serie.
- **Cortes de caja:** marca el pago como histórico para que no entre a los cortes.

---

## Pestañas que se cargan con los seeders

| Pestaña | Tablas | Módulos | Uso |
|---|---|---|---|
| **Catalogos** | Varias (consulta) | Todos | Valores válidos que deben escribirse igual en las demás pestañas: clubes, tipos, conceptos, métodos, parentescos, motivos, roles, amenidades |
| **Clubes** | `clubs.clubs`, `clubs.club_addresses`, `clubs.rules` | Mi Club, recibos, App, Reservaciones | Datos del club. El CODIGO DEL CLUB forma parte de los folios |
| **Importes por club** | `billing.concept_club_amounts` | Conceptos de cobro, Registro de cobros, Casilleros | Precio de cada concepto por parque. Ningún seeder lo llena hoy |

---

## Pestañas que aún no se cargan (sin código)

Se capturan en la plantilla, pero ninguna de las dos pantallas las lee todavía.

| Pestaña | Tablas destino | Módulos que afecta | Para qué sirve | Reglas que deberá tener |
|---|---|---|---|---|
| **Informacion medica** | `members.clinical_histories` | Ficha del socio, App | Datos médicos para emergencias | ID existente; SI/NO válidos. No bloquea por campos vacíos |
| **Contactos de emergencia** | `members.clinical_histories` | Ficha, App | A quién avisar | ID existente |
| **Documentos** | `members.documents` | Ficha (documentos entregados y verificados), Nueva membresía, App | Que no aparezcan documentos pendientes | ID y tipo existentes; el archivo debe estar en `ARCHIVOS/` |
| **Permisos de ausencia** | `memberships.absence_permits` | Ficha, Cobranza (cuota de 25% o 75%), acceso, Reservaciones | Cobrar la cuota reducida y bloquear el acceso durante el permiso | Cuenta existente; fin igual o posterior al inicio; porcentaje 25 o 75; sin permisos que se crucen |
| **Notas de cobranza** | `billing.collection_notes` | Registro de cobros (recuadro de notas) | Acuerdos de pago, prórrogas, saldos a favor de Fox | Cuenta existente; nota no vacía |
| **Casilleros** | `members.locker_assignments`, `members.locker_assignment_histories` | Ficha, Registro de cobros, App | Que el casillero salga ocupado y, si tiene cargo, pagado | El casillero debe existir en el inventario; la persona debe existir; el cargo debe estar en Cargos y debe ligarse al casillero (sin esa liga sale "no pagado") |
| **Entrenadores** | `classes.coaches`, `classes.coach_availabilities` | Amenidades › Entrenadores, Reservaciones, App | Profesor y horario para reservar clase | Amenidad existente; hora de fin posterior al inicio |
| **Reservaciones** | `reservations.reservations` | Reservaciones, Asistencias, App | Respetar lugares apartados e historial; inasistencia = suspensión de 48 h | Amenidad y recurso existentes; sin empalmes; entrenador dentro de su horario |
| **Listas de invitados** | `guest_lists.guest_lists`, `guest_lists.guest_list_items` | Listas de invitados, App, Cobranza | Listas aprobadas y su cobro | La lista y su cargo deben cuadrar; la edad define el precio |

---

## Pendientes en la plantilla (colores y tips)

La carga ya aplica estas reglas, pero la plantilla todavía muestra otra cosa:
- **Membresias › GENERA COBRO:** está en azul (opcional) y es obligatorio.
- **Membresias › CUOTA MENSUAL:** está en azul; es obligatoria cuando GENERA COBRO = SI.
- **Usuarios › CORREO:** el tip dice "obligatorio para el titular", y así lo aplica la carga.
- **Personal › APELLIDO PATERNO:** está en rojo y la carga no lo exige. Se puede dejar en rojo o pasarlo a azul.
- La pestaña **Instrucciones** (con la fecha de corte) ya no existe. La carga usa la última mensualidad de cada cuenta como piso, así que la fecha de corte ya no es necesaria.

---

## Glosario de términos

- **Cuenta:** el socio o la familia en **un** parque. Se identifica con el NUMERO DE CUENTA y a ella se le cobra todo.
- **Persona / usuario:** cada individuo. Se identifica con el ID DE USUARIO y es una sola, aunque esté en los dos parques.
- **Titular:** el responsable de la cuenta. Todo se le cobra a él y Cobranza lo busca por su nombre.
- **Grupo (dos parques):** las dos cuentas del mismo socio, una por parque, unidas para cobrar una sola mensualidad de "ambos parques".
- **GENERA COBRO:** si la cuenta genera mensualidades. En un grupo, solo una lo hace.
- **Regla de precio:** la cuota que el sistema calcula por tipo, tipo anterior, edad y si está en dos parques. El **paquete interclub** es la regla especial para tipos distintos en cada parque.
- **Concepto de cobro:** el tipo de cargo. Los conceptos de **mensualidad** son CUOTA MENSUALIDAD, de ambos parques, intermedio, pase y cuota de permiso.
- **Periodo:** año y mes que cubre una mensualidad.
- **Aplicación de pago:** qué parte de un pago cubrió qué cargo, y su descuento.
- **Folio:** número de recibo. Los de Fox se conservan; los del sistema son `CLUB-SERIE-AAMMDD-NNN`.
- **Serie de caja:** clave del cajero en sus folios.
- **Piso de mensualidades:** mes desde el cual el sistema puede crear mensualidades faltantes (`billing_backfill_floor`).
- **Baja voluntaria de cuenta:** la razón del historial con la que el sistema sabe qué meses no cobrar.
- **Morosidad:** 3 o más mensualidades vencidas (vencimiento + 1 día de gracia) bloquean el acceso.
- **Simulación (dry-run):** corre la carga completa y la deshace al final. Sirve para revisar sin guardar nada.
