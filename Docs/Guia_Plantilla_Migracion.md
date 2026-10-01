# Guía de estudio: la plantilla de migración y el sistema ParquesEsp

**Archivo:** `Plantilla_Migracion_Cliente.xlsx` (19 pestañas)
**Actualizada:** 2026-10-01
**Para qué sirve esta guía:** entender, pestaña por pestaña, qué información se captura, **para qué la usa el sistema**, **en qué módulos (pantallas del menú) aparece**, **por qué hace falta** y **qué significa cada columna**.

---

## Parte 1. Lo que hay que entender antes de empezar

### 1.1 Qué es el sistema

ParquesEsp administra los dos clubes de Parque España: **PE1 (Parque España I)** y **PE2 (Parque España II)**. Tiene tres partes:

| Parte | Quién la usa | Para qué |
|---|---|---|
| **Panel de administración** (web) | Personal del club | Altas de membresías, cobranza, caja, reservaciones, casilleros, reportes |
| **App móvil** | Socios | Ver su estado de cuenta, pagar con tarjeta, reservar amenidades, registrar invitados |
| **Procesos automáticos** | Nadie (corren solos) | Generar mensualidades, bloquear morosos, cambiar membresías por edad |

### 1.2 El menú del panel (los "módulos")

Esta es la estructura real del menú. En la guía, cuando diga "módulo", me refiero a estas pantallas.

| Menú | Submenús |
|---|---|
| **Mi Club** | Datos del club |
| **Configuración de membresías** | Tipos de documento · Tipos de membresía · Reglas de precio · Paquetes intermedios |
| **Membresías** | Membresías (lista y ficha del socio) · Nueva membresía · Historial de bajas · Transiciones por edad |
| **App móvil** | Usuarios App Móvil · Variables de App Móvil |
| **Cobranza** | Cuotas por año · Conceptos de cobro · Métodos de pago · **Registro de cobros** · Cortes de caja · Cortes globales · **Historial de pagos** |
| **Reportes** | Reportes |
| **Clubs deportivos** | Alta y configuración de clubes |
| **Usuarios / Roles / Permisos** | Personal que entra al sistema y lo que puede hacer |
| **Clases** | Especialidades · Horarios de clases |
| **Amenidades** | Amenidades y recursos · Bloqueo de recursos · **Entrenadores** |
| **Reservaciones** | Reservaciones · Asistencias · Variables del sistema |
| **Listas de invitados** | Listas de invitados · Configuración · Pagos · Incidencias |
| Otros | Comunicación · Publicidad · Encuestas · Quejas y sugerencias · Formatos · Notificaciones · Página web |

### 1.3 Cuenta vs. persona (lo más importante)

En el sistema hay dos cosas distintas que se confunden fácil:

| Concepto | Qué es | Cómo se identifica en la plantilla | En el sistema |
|---|---|---|---|
| **Cuenta (membresía)** | La familia o el socio individual **en un parque**. A ella se le cobra todo. | **NUMERO DE CUENTA** (ej. `PE02-02155`) | "No. Cuenta" en pantalla (`memberships.accounts.internal_account_number`) |
| **Persona (usuario)** | Cada individuo: titular, cónyuge, hijo… | **ID DE USUARIO** (ej. `USR-352`) | Campo oculto `members.members.migration_origin_id`; no se ve en pantalla |

Ejemplo, la familia García:

| NUMERO DE CUENTA | ID DE USUARIO | Persona | ¿Titular? |
|---|---|---|---|
| PE02-02155 | USR-352 | Juan García | SI |
| PE02-02155 | USR-353 | Ana López | NO (cónyuge) |
| PE02-02155 | USR-354 | Luis García | NO (hijo) |

- **El dinero va por cuenta.** Cargos, pagos, saldo, morosidad y bloqueo se calculan por cuenta, y todo se cobra **al titular**.
- **Los datos personales van por persona.** Información médica, documentos, casillero y reservaciones son de cada individuo.
- **Una familia en los dos parques tiene dos cuentas** (una en PE1 y otra en PE2), pero cada persona tiene **un solo ID DE USUARIO**.

### 1.4 Colores de los encabezados

| Color | Significado |
|---|---|
| 🔴 **Rojo** | Obligatorio |
| 🔵 **Azul** | Opcional |
| 🟣 **Morado** | Identificador que liga la fila con otra pestaña (obligatorio) |
| 🟪 **Lila** | Identificador que liga con otra pestaña (opcional) |

Debajo del encabezado hay una **fila gris de tips** con el formato o la regla de cada columna.

### 1.5 Cómo se relacionan las pestañas

```
Catalogos ─────────────── (valores válidos para todas)
Clubes ── Metodos por club ── Importes por club
Personal ──(SERIE DE CAJA)──────────────────────────────┐
                                                         │
Usuarios ──(ID DE USUARIO)──┬── Informacion medica       │
                            ├── Contactos de emergencia  │
                            ├── Documentos               │
                            ├── Casilleros ──(REFERENCIA DEL CARGO)──┐
                            ├── Reservaciones ──(REFERENCIA DE LA RESERVACION)── Listas de invitados
                            └── Integrantes              │           │
                                     │                   │           │
Membresias ──(NUMERO DE CUENTA)──────┴── Permisos        │           │
                                     ├── Cargos ◄────────┼───────────┘
                                     │      │ (FOLIO DEL RECIBO)
                                     ├── Pagos ◄─────────┘
                                     └── Notas de cobranza
Entrenadores ──(ENTRENADOR)── Reservaciones
```

### 1.6 Conceptos que aparecen en toda la guía

- **Catálogo:** lista de valores válidos (clubes, conceptos de cobro, parentescos…). Lo que se escriba en otras pestañas debe coincidir con el catálogo.
- **Fecha de corte:** el día en que se deja de usar Fox y empieza el sistema nuevo. Todo lo que se deba a esa fecha debe venir en Cargos.
- **Carga:** el programa que lee la plantilla y la mete al sistema. Algunos datos no se capturan porque la carga los calcula (ej. saldos, folios, subtotal/IVA).
- **Fox:** el sistema anterior, de donde salen los datos.

---

## Parte 2. Pestaña por pestaña

---

### 1. Catalogos

**Qué es:** las listas de valores válidos del sistema. Casi todo ya existe en la base de datos; la pestaña sirve de **consulta** para que quien llena escriba los valores exactos.

**Por qué es necesaria:** si en Membresías alguien escribe "Familiar PE2" y el sistema lo tiene como "Familiar", la carga no sabe a qué tipo pertenece. El catálogo evita esos errores.

| Sección | Qué contiene | Módulo donde se administra | Dónde se usa en la plantilla |
|---|---|---|---|
| CLUBES | PE1 y PE2 | Clubs deportivos | Todas las columnas CLUB |
| TIPOS DE MEMBRESIA | Individual, Familiar, Solidaria, Pase… por club, con su vigencia y si permite varios integrantes | Configuración de membresías › Tipos de membresía | Membresias |
| METODOS DE PAGO | Efectivo, tarjeta de crédito/débito, transferencia, cheque, SPEI | Cobranza › Métodos de pago | Metodos por club, Pagos |
| CONCEPTOS DE COBRO | Todo lo que se puede cobrar: mensualidades, inscripción, casillero, pase diario, etc. | Cobranza › Conceptos de cobro | Importes por club, Cargos |
| PARENTESCOS | Titular, Cónyuge, Hijo(a), Madre | (fijo del sistema) | Integrantes |
| ESTADOS CIVILES | Soltero(a), Casado(a)… | (fijo) | Usuarios |
| TIPOS DE DOCUMENTO | INE, acta, comprobante de domicilio… | Configuración de membresías › Tipos de documento | Documentos |
| DOCS POR PARENTESCO / POR MEMBRESIA | Qué documentos pide el sistema según parentesco y tipo de membresía | Tipos de documento / Tipos de membresía | Referencia para Documentos |
| MOTIVOS DE CANCELACION / DE SEPARACION | Razones de baja o de separación de cuenta | Membresías › Historial de bajas | Membresias |
| REGLAS DE DESCUENTO | Descuento por pago anual (meses gratis) | (configuración) | Referencia |
| REGLAS DE PRECIOS | Cuota mensual e inscripción por tipo, club, edad y año | Configuración de membresías › Reglas de precio / Cobranza › Cuotas por año | Referencia para Membresias |
| ESPECIALIDADES | Tenis, Pádel (las crea el sistema) | Clases › Especialidades | Referencia |
| ESTATUS DE RESERVACION | ACTIVA, CANCELADA, FINALIZADA, INASISTENCIA, ASISTIDO | (fijo) | Reservaciones |
| CATEGORIAS DE CASILLERO | Niños, Niñas, Caballeros, Damas | (fijo) | Casilleros |
| PAISES / ESTADOS / CIUDADES | Lugares válidos | (fijo) | Usuarios, Clubes |
| ROLES DEL PERSONAL | admin_club, Cobranza, Gerente Cobranza… | Roles | Personal |
| AMENIDADES Y RECURSOS | Pádel, tenis, frontón, jardines, alberca y sus canchas/lugares (hoy solo PE1) | Amenidades › Amenidades y recursos | Entrenadores, Reservaciones |

---

### 2. Clubes

**Qué es:** los datos generales de cada parque. Ya viene prellenada con PE1 y PE2.
**Tablas:** `clubs.clubs`, `clubs.club_addresses`, `clubs.rules`.
**Una fila =** un club.

**Módulos:** Clubs deportivos · Mi Club · App móvil (contacto, redes, mapa) · Página web · recibos de Cobranza (razón social, RFC) · Reservaciones (reglas).

**Por qué es necesaria:** el club es la base de todo: cada cuenta, cargo, pago, casillero y reservación pertenece a un club. Además el código del club forma parte de cada folio de recibo.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| NOMBRE DEL CLUB | Nombre oficial | Se muestra en pantallas, app y recibos |
| CODIGO DEL CLUB | Clave corta (PE1, PE2) | Primera parte de cada folio de recibo (`PE2-A-260115-001`). No se debe cambiar |
| ACTIVO | Si el club opera | Un club inactivo no aparece para operar |
| APLICA IVA | Si los cobros del club llevan IVA | Desglose de subtotal/IVA en recibos |
| RAZON SOCIAL, RFC | Datos fiscales del club | Se imprimen en el recibo |
| URL DE FACTURACION | Página para pedir factura | Se muestra en recibo y app |
| CORREO, TELEFONO, SITIO WEB | Contacto | App y página web |
| WHATSAPP, INSTAGRAM, FACEBOOK, YOUTUBE, X, THREADS | Redes sociales | App y página web |
| ARCHIVO DEL LOGO / DEL MAPA | Imágenes del club | Logo en recibos y app; mapa en la app |
| CALLE … CIUDAD | Domicilio del club | Recibos y app |
| MAXIMO DE RESERVACIONES ACTIVAS | Cuántas reservaciones puede tener un socio a la vez | Regla al reservar |
| MAXIMO DE DIAS DE ANTICIPACION | Con cuántos días se puede reservar | Regla al reservar |
| PERMITE RESERVAR EL MISMO DIA | Si se puede reservar para hoy | Regla al reservar |

---

### 3. Metodos por club

**Qué es:** qué formas de pago acepta cada parque y en qué orden aparecen. Viene prellenada (12 filas).
**Tabla:** `billing.club_payment_methods`. **Una fila =** un método en un club.

**Módulos:** Cobranza › Métodos de pago · Cobranza › Registro de cobros (la lista de formas de pago al cobrar) · recibos.

**Por qué es necesaria:** sin esto, al cobrar no aparece ninguna forma de pago. También define la "clave interna" que se imprime en el recibo.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| CLUB | Parque | Cada parque tiene su propia lista |
| METODO DE PAGO | Efectivo, tarjeta, etc. | Opción en la pantalla de cobro |
| ACTIVO | Si el parque lo acepta | Solo los activos se pueden usar |
| ORDEN DE APARICION | Posición en la lista | Orden de los botones al cobrar |
| CLAVE INTERNA | Nombre corto del método en ese club | Se imprime en el recibo; distingue p. ej. "Tarjeta PE1" de "Tarjeta PE2" |

> Las credenciales de Conekta (pago con tarjeta en la app) **no** van en la plantilla; se configuran en el sistema por seguridad.

---

### 4. Importes por club

**Qué es:** el precio de cada concepto de cobro en cada parque (casillero, pase diario, credencial…). Viene prellenada (138 filas).
**Tabla:** `billing.concept_club_amounts`. **Una fila =** un concepto en un club.

**Módulos:** Cobranza › Conceptos de cobro · Registro de cobros (precio sugerido al agregar un concepto) · Casilleros (costo anual) · App.

**Por qué es necesaria:** el mismo concepto puede costar distinto en cada parque. Sin esto se usaría el precio general del concepto.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| CLUB | Parque | — |
| CONCEPTO DE COBRO | Qué se cobra | — |
| IMPORTE | Precio en ese parque | Precio que propone al cobrar |
| APLICA IVA | Si lleva IVA en ese parque | Desglose de IVA en el recibo |
| ACTIVO | Si se cobra en ese parque | Si no está activo, no se ofrece |

> Mensualidades, cuotas de permiso e inscripciones **no** van aquí: su precio sale de las **Reglas de precios** (por tipo, edad y año).

---

### 5. Personal

**Qué es:** las personas del club que van a entrar al panel (administradores, cobranza, cajeros) y los cajeros que ya no laboran pero aparecen en recibos viejos.
**Tablas:** `public.users`, `public.user_clubs`, `public.model_has_roles`. **Una fila =** una persona.

**Módulos:** Usuarios · Roles · inicio de sesión · Registro de cobros (quién cobró) · Cortes de caja (corte por cajero) · Historial de pagos (cajero del recibo).

**Por qué es necesaria:** sin usuarios nadie puede entrar al sistema. La serie de caja hace que cada cajero tenga su propia numeración de recibos y que los recibos históricos digan quién cobró.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| NOMBRE, APELLIDOS | Nombre de la persona | Se muestra en recibos y cortes |
| CORREO | Usuario para entrar | Inicio de sesión. Vacío solo en cajeros que ya no laboran |
| ROL | Qué puede hacer | Define los menús y permisos. Vacío = cajero histórico, sin acceso |
| CLUBES | Parques donde trabaja | Solo puede operar en esos parques |
| 🟪 SERIE DE CAJA | Letra de su serie (ej. A) | Forma el folio de sus recibos (`PE2-A-…`) y liga sus pagos en la pestaña Pagos |

> No se piden contraseñas: el sistema asigna una temporal. A los cajeros históricos se les pone una que nadie conoce.

---

### 6. Usuarios

**Qué es:** cada persona: titulares y familiares. Solo los datos que pide la pantalla de alta.
**Tablas:** `members.members`, `members.addresses`, `members.employment_info`. **Una fila =** una persona (aunque esté en los dos parques).

**Módulos:** Membresías › Nueva membresía (formulario de alta) · Membresías › ficha del socio · Cobranza (nombre del titular) · App móvil (perfil) · Transiciones por edad.

**Por qué es necesaria:** es la base de todo lo personal. La **fecha de nacimiento** la usa el proceso automático de edad (ej. un hijo que cumple la edad límite cambia de membresía) y las reglas de precio por edad.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 ID DE USUARIO | Clave de la persona en Fox (USR-…) | Liga a la persona con todas las demás pestañas. Se guarda oculta |
| NOMBRE, APELLIDOS | Nombre | Búsquedas (Cobranza busca por nombre del titular), ficha, recibos |
| FECHA DE NACIMIENTO | Nacimiento | Edad: reglas de precio, transiciones por edad, límite de hijos (menores de 24), menores en reservaciones |
| SEXO | H/M | Ficha |
| TELEFONO, CORREO | Contacto | Ficha; el correo sirve para que el socio cree su acceso a la app |
| ESTADO CIVIL, NACIONALIDAD | Datos personales | Ficha |
| PAIS/ESTADO/CIUDAD DE NACIMIENTO | Lugar de nacimiento | Ficha |
| OCUPACION, ESCUELA | Ocupación (adultos) o escuela (menores) | Ficha |
| CALLE … AÑOS EN LA CIUDAD | Domicilio | Ficha (un solo domicilio por persona) |
| EMPRESA, DOMICILIO Y TELEFONO DE LA EMPRESA | Datos laborales | Ficha |

---

### 7. Informacion medica

**Qué es:** el expediente médico de cada persona.
**Tabla:** `members.clinical_histories` (la comparte con Contactos de emergencia). **Una fila =** una persona.

**Módulos:** Membresías › ficha del socio (sección médica) · App móvil (el socio la consulta y edita).

**Por qué es necesaria:** el club la necesita en caso de emergencia (alberca, deportes). No afecta cobros ni accesos.

| Columna | Qué es |
|---|---|
| 🟣 ID DE USUARIO | La persona |
| TIPO DE SANGRE, FACTOR RH | Grupo sanguíneo |
| DIABETES, TIPO DE DIABETES | Si tiene diabetes y de qué tipo |
| CARDIOPATIA, EPILEPSIA, ASMA, HIPERTENSION, PRESION ARTERIAL NORMAL | Condiciones de salud (SI/NO) |
| ALERGIA, ALERGENOS, DETALLE DE ALERGENOS | Alergias |
| TOMA MEDICAMENTOS, MEDICAMENTOS | Medicamentos |
| CONDICIONES ESPECIALES | Otra información relevante |
| MEDICO TRATANTE, TELEFONO DEL MEDICO | Su médico |
| NUMERO DE SEGURIDAD SOCIAL, SEGURO DE GASTOS MEDICOS, COMPAÑIA, POLIZA, CELULAR DEL SEGURO | Seguro médico |

---

### 8. Contactos de emergencia

**Qué es:** a quién llamar si le pasa algo a la persona.
**Tabla:** `members.clinical_histories` (mismo registro que la médica; se separó para que fuera más fácil de llenar). **Una fila =** una persona.

**Módulos:** ficha del socio · App móvil.

| Columna | Qué es |
|---|---|
| 🟣 ID DE USUARIO | La persona |
| NOMBRE, TELEFONO y CELULAR DEL CONTACTO | Contacto de emergencia |
| EN CASO NECESARIO INFORMAR A | Segunda persona a avisar |

---

### 9. Documentos

**Qué es:** los archivos de cada persona (INE, acta, comprobante…). Se entrega una carpeta y aquí se dice qué archivo es de quién.
**Tabla:** `members.documents`. **Una fila =** un documento.

**Módulos:** Membresías › ficha del socio (documentos, verificación) · Nueva membresía (el alta pide documentos según parentesco y tipo) · App móvil (mis documentos).

**Por qué es necesaria:** el sistema muestra qué documentos faltan por persona según su parentesco y tipo de membresía. Sin esto, todos aparecerían con documentos pendientes.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 ID DE USUARIO | La persona | — |
| TIPO DE DOCUMENTO | INE, acta… | Marca ese documento como entregado |
| RUTA DEL ARCHIVO | Ubicación en la carpeta (`DOCUMENTOS/USR-352/INE.pdf`) | La carga sube el archivo al almacenamiento del sistema |
| CLUB | Si el documento es solo de un parque | Algunos tipos son por club |
| VERIFICADO, FECHA DE VERIFICACION | Si el personal ya lo revisó | Estado del documento en la ficha |

---

### 10. Membresias

**Qué es:** cada cuenta: su tipo, estatus, fechas, si está en el otro parque, cancelación y datos de facturación.
**Tablas:** `memberships.accounts`, `memberships.memberships`, `memberships.account_groups`, `memberships.account_fiscal_data`, `memberships.membership_history`. **Una fila =** una cuenta en un parque.

**Módulos:** Membresías (lista, ficha, historial de bajas) · Cobranza (todo se busca por No. Cuenta) · procesos automáticos de mensualidades y morosidad · App móvil.

**Por qué es necesaria:** es la pieza central. De aquí salen:
- **La cuota mensual:** el sistema la calcula con las reglas de precios (tipo, tipo anterior, edad del titular, si tiene cuenta en el otro parque).
- **El concepto de la mensualidad:** un parque, ambos parques, intermedio…
- **Si se generan cargos cada mes** (proceso automático del día 1 a la 01:00).
- **Los periodos de baja,** para no cobrar meses en que la cuenta estuvo cancelada.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 NUMERO DE CUENTA | No. Cuenta de Fox | El "No. Cuenta" que se ve y se busca en pantalla. Liga con todas las pestañas de dinero |
| CLUB | Parque de la cuenta | Cada cuenta es de un solo parque |
| TIPO DE MEMBRESIA | Individual, Familiar, Solidaria… | Define la cuota, cuántos integrantes admite y qué documentos pide |
| INDIVIDUAL O FAMILIAR | Tipo de cuenta | Si admite varios integrantes |
| ESTATUS | ACTIVA, SUSPENDIDA, CANCELADA, PENDIENTE | Solo las activas generan cargos y dan acceso |
| FECHA DE INICIO | Alta de la membresía | Antigüedad; base del historial; paquetes entre parques |
| FECHA DE TERMINO | Vencimiento (pases temporales) | Deja de generar cargos al vencer |
| CUOTA MENSUAL | Lo que paga hoy en Fox | Solo para comprobar la cuota que calcula el sistema |
| GENERA COBRO | Si a esta cuenta se le generan mensualidades | En familias de ambos parques, solo una cuenta genera la mensualidad combinada |
| 🟪 CUENTA EN EL OTRO PARQUE | La cuenta de la misma familia en el otro parque | Las une en un "grupo" para cobrar la mensualidad de ambos parques |
| TIPO DE MEMBRESIA ANTERIOR | De qué membresía viene (obligatorio en Solidaria) | Regla de precio y validación de Solidaria |
| 🟪 CUENTA DE ORIGEN, MOTIVO DE SEPARACION | Si la cuenta se separó de otra (divorcio, hijo que crece) | Historial de la cuenta |
| FECHA, TIPO y MOTIVO DE CANCELACION, ARCHIVO DE LA CARTA | Datos de la baja | Historial de bajas; el sistema no cobra meses de baja |
| NOMBRE O RAZON SOCIAL, RFC, USO DE CFDI, REGIMEN FISCAL, CODIGO POSTAL FISCAL | Datos de facturación | Facturación de la cuenta |

---

### 11. Integrantes

**Qué es:** quién forma cada cuenta y quién es el titular.
**Tabla:** `memberships.account_members`. **Una fila =** una persona en una cuenta.

**Módulos:** Membresías › ficha (integrantes) · Cobranza (nombre del titular, buscador por titular) · control de acceso (tarjetas) · App móvil (familia) · Reservaciones (quién puede reservar).

**Por qué es necesaria:** une a las personas (Usuarios) con las cuentas (Membresias). Sin ella el sistema tendría cuentas sin socios. Además:
- El **titular** es a quien se cobra todo.
- Cada integrante tiene su **tarjeta de acceso** a las instalaciones.
- El bloqueo por morosidad desactiva las tarjetas de toda la cuenta.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 NUMERO DE CUENTA | La cuenta | — |
| 🟣 ID DE USUARIO | La persona | — |
| ES TITULAR | Si es el titular | Solo uno por cuenta; a él se le cobra y aparece en búsquedas |
| PARENTESCO | Cónyuge, Hijo(a)… | Documentos que se le piden; hijos con límite de edad |
| NUMERO DE TARJETA DE ACCESO | Tarjeta/credencial actual | Se da de alta en los lectores de acceso. Vacío = se asigna una nueva |

---

### 12. Permisos de ausencia

**Qué es:** permisos vigentes o futuros para que una cuenta pague solo una parte de la mensualidad mientras no asiste (viaje, enfermedad).
**Tabla:** `memberships.absence_permits`. **Una fila =** un permiso.

**Módulos:** Membresías › ficha (permisos) · Cobranza (cuota de permiso) · control de acceso y Reservaciones (bloqueos).

**Por qué es necesaria:** si no se carga, a partir del siguiente mes el sistema cobraría la mensualidad completa y dejaría entrar a quien tiene el acceso suspendido.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 NUMERO DE CUENTA | La cuenta | Si la familia está en ambos parques, aplica al grupo |
| INICIO / FIN DEL PERMISO | Periodo | Meses en que cobra la cuota reducida |
| PORCENTAJE A COBRAR | 25 o 75 | Usa el concepto CUOTA PERMISO (25%) o CUOTA 75% PERMISO |
| BLOQUEA ACCESO | Si no puede entrar durante el permiso | Desactiva la tarjeta |
| BLOQUEA RESERVACIONES | Si no puede reservar | Regla al reservar |
| ARCHIVO DEL PERMISO, NOTAS | Documento y observaciones | Ficha |

---

### 13. Cargos

**Qué es:** todo lo que se cobró o se debe: mensualidades, inscripciones, adeudos, casilleros, pases, listas de invitados, cursos, credenciales, renta de local, multas. Incluye **qué recibo pagó cada cargo**.
**Tablas:** `billing.charges`, `billing.payment_applications`. **Una fila =** un cargo (se repite si se pagó con varios recibos).

**Módulos:**
- **Cobranza › Registro de cobros:** lo pendiente de la cuenta, meses vencidos, última mensualidad pagada.
- **Cobranza › Historial de pagos** y **cancelación de pagos:** qué cargos cubrió cada recibo.
- **App móvil:** estado de cuenta por periodo y pago en línea.
- **Morosidad (automático, diario 02:00):** 3 o más mensualidades vencidas **bloquean el acceso**.
- **Casilleros:** un casillero sale "pagado" solo si su cargo está pagado.

**Por qué es necesaria:** es **lo que hace funcionar el sistema el primer día**. Si la deuda no se carga, nadie aparece debiendo; si se carga mal, se bloquea a quien no debe. Además, el sistema **rellena solo los meses de mensualidad que falten** entre la primera mensualidad cargada y hoy, así que deben venir completas hasta la fecha de corte.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 REFERENCIA DEL CARGO | Clave única del cargo | Liga el cargo con Casilleros |
| 🟣 NUMERO DE CUENTA | A quién se cobra | El cargo queda a nombre del titular; el parque sale de la cuenta |
| CONCEPTO DE COBRO | Qué se cobra | Define en qué parte de Cobranza aparece y si cuenta como mensualidad para la morosidad |
| DESCRIPCION | Texto del cargo | Se ve en Cobranza y en el recibo |
| IMPORTE | Monto total | Base del saldo |
| FECHA DE EMISION | Cuándo se generó | En la app, los cargos que no son mensualidad se filtran por esta fecha |
| FECHA DE VENCIMIENTO | Cuándo vence | Vencido = cuenta para morosidad. Mensualidades: día 10 |
| AÑO / MES DEL PERIODO | Mes que cubre (mensualidades y permisos) | Evita duplicar meses; morosidad; estado de cuenta |
| PAGO EN PARCIALIDADES | Si se puede pagar en partes | Permite abonos (ej. inscripción a plazos) |
| 🟪 FOLIO DEL RECIBO | Con qué recibo se pagó | Crea la "aplicación": qué parte de qué pago cubrió este cargo |
| IMPORTE PAGADO | Cuánto se pagó con ese recibo | Saldo = importe − pagado − descuento; estatus pendiente/parcial/pagado |
| DESCUENTO | Descuento al pagar | Ej. mes gratis del pago anual |
| CANCELADO, FECHA y MOTIVO DE CANCELACION | Si ya no se debe | Un cargo cancelado no cuenta como deuda |
| NOTAS | Observaciones | — |

---

### 14. Pagos

**Qué es:** los recibos cobrados y con qué forma de pago.
**Tabla:** `billing.payments`. **Una fila =** una forma de pago de un recibo (efectivo y tarjeta en el mismo recibo = 2 filas con el mismo folio).

**Módulos:**
- **Cobranza › Historial de pagos:** reimprimir recibos; busca por folio, referencia, No. Cuenta o titular.
- **Cancelación de pagos:** muestra qué cargos pagó cada forma de pago.
- **Cortes de caja / Cortes globales:** dinero por cajero y día (los históricos no se mezclan con cortes actuales).
- **Reportes** · **App móvil** (historial de pagos).

**Por qué es necesaria:** es el historial de pagos del socio y la prueba de lo que ya pagó. El folio de Fox se conserva, así el personal puede encontrar un recibo viejo con el número que conoce.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 FOLIO DEL RECIBO | Número del recibo | Agrupa las formas de pago de un mismo recibo; búsqueda en Historial de pagos |
| 🟣 NUMERO DE CUENTA | Cuenta que pagó | Historial de la cuenta |
| CLUB DONDE SE COBRO | Caja donde se cobró | El recibo aparece en el Historial de pagos de ese parque |
| FECHA y HORA DEL PAGO | Cuándo | Reportes, cortes, historial |
| METODO DE PAGO | Efectivo, tarjeta… | Reportes; si entra o no al corte de caja |
| PARQUE DE LA FORMA DE PAGO | Si la terminal/cuenta era del otro parque | Reparto entre parques y cheques rebotados |
| IMPORTE | Lo cobrado con esa forma | Debe cuadrar con lo pagado en Cargos |
| REFERENCIA, BANCO, NUMERO DE CHEQUE | Datos bancarios | Búsqueda y conciliación |
| 🟪 SERIE DE CAJA | Cajero que cobró | Nombre del cajero en el recibo y en los cortes |
| CANCELADO, FECHA y MOTIVO | Pago anulado | No cuenta como pagado; los cargos que cubría quedan pendientes |
| NOTAS | Observaciones | — |

---

### 15. Notas de cobranza

**Qué es:** bitácora de comentarios de cobranza por cuenta.
**Tabla:** `billing.collection_notes`. **Una fila =** una nota.

**Módulo:** Cobranza › Registro de cobros, recuadro "Notas de cobranza" (últimas 20, con fecha y autor), junto a Incidencias y a las señales de morosidad.

**Por qué sirve:** no mueve dinero. Sirve para que quien atienda sepa lo acordado: acuerdos de pago, prórrogas y **saldos a favor de Fox**, porque el sistema no tiene dónde mostrar un saldo a favor suelto.

| Columna | Qué es |
|---|---|
| 🟣 NUMERO DE CUENTA | La cuenta |
| NOTA | El texto |
| FECHA DE LA NOTA | Cuándo se escribió (vacío = fecha de la carga) |

---

### 16. Casilleros

**Qué es:** quién tiene asignado cada casillero hoy. El inventario de casilleros **ya existe** en el sistema; aquí solo se asignan.
**Tablas:** `members.locker_assignments`, `members.locker_assignment_histories`. **Una fila =** un casillero asignado.

**Módulos:** Membresías › ficha del socio (casillero e historial) · Cobranza › Registro de cobros (casilleros de la cuenta, pagados o pendientes) · App móvil (el socio ve su casillero y puede rentar uno libre).

**Por qué es necesaria:** sin esto todos los casilleros aparecerían libres y se podrían asignar a otra persona.
- El sistema **solo muestra los casilleros del año actual**.
- Un casillero sale **pagado** solo si su cargo (en Cargos) es de la misma persona y del mismo casillero.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| CLUB, CATEGORIA, NUMERO DE CASILLERO | Qué casillero | Lo busca en el inventario y lo marca "ocupado" |
| 🟣 ID DE USUARIO | Quién lo usa | Aparece en la ficha de esa persona |
| FECHA DE INICIO / FIN | Desde y hasta cuándo | Vigencia (vacío = año de corte) |
| 🟪 REFERENCIA DEL CARGO | Su cargo en Cargos | Estatus pagado/pendiente |
| ARCHIVO | Comprobante | Ficha del socio |

---

### 17. Entrenadores

**Qué es:** los profesores que el socio puede escoger al reservar una amenidad, y su horario.
**Tablas:** `classes.coaches`, `classes.coach_availabilities`. **Una fila =** un bloque de horario (los datos del entrenador se repiten en cada fila).

**Módulos:** Amenidades › Entrenadores · Reservaciones (reservar con profesor) · App móvil (lista de profesores y su horario al reservar).

**Por qué es necesaria:** en el sistema **una clase es una reservación de amenidad con profesor**. El socio solo puede escoger profesor si el horario del profesor coincide con su reserva. Además, los **menores deben reservar como clase**.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| CLUB | Parque | — |
| NOMBRE, APELLIDOS | Nombre del profesor | Se muestra al reservar. El nombre completo es lo que se escribe en Reservaciones › ENTRENADOR |
| TELEFONO, CORREO | Contacto | Ficha del entrenador |
| AMENIDAD | Dónde da clases (tenis, pádel…) | Solo se le puede escoger en esa amenidad |
| DIA, HORA DE INICIO, HORA DE FIN | Su disponibilidad | La app solo lo ofrece si coincide con la reserva |

---

### 18. Reservaciones

**Qué es:** reservaciones de amenidades, pasadas (historial) y futuras (lugares ya apartados).
**Tabla:** `reservations.reservations`. **Una fila =** una reservación.

**Módulos:** Reservaciones › Reservaciones (calendario) · Reservaciones › Asistencias · App móvil (mis reservaciones) · Listas de invitados.

**Por qué es necesaria:** las futuras evitan que alguien pierda su lugar o que se reserve dos veces lo mismo. Las pasadas sirven de historial y de prueba. Reglas que aplica el sistema:
- No empalmar reservaciones.
- Respetar cupo y reservaciones por día.
- **Suspender 48 h a quien tuvo inasistencia.**

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟪 REFERENCIA DE LA RESERVACION | Clave propia | Solo para ligar la reservación con su lista de invitados; no se guarda |
| CLUB | Parque | — |
| 🟣 ID DE USUARIO | Quién reserva | Mis reservaciones en la app; reglas por persona |
| AMENIDAD, RECURSO | Qué se reservó (ej. Canchas de tenis, Cancha 2) | Ocupa ese recurso en el calendario |
| FECHA, HORA DE INICIO, HORA DE FIN | Cuándo | Calendario y reglas de empalme |
| ESTATUS | ACTIVA, CANCELADA, FINALIZADA, INASISTENCIA, ASISTIDO | Asistencias; inasistencia = suspensión de 48 h |
| FECHA DE CANCELACION | Si se canceló | Límite de cancelaciones por semana |
| 🟪 ENTRENADOR | Si reservó con profesor | La marca como clase |
| ASADOR | En jardines: asador apartado con el jardín | El sistema guarda jardín y asador como 2 reservaciones ligadas |
| REQUIERE CARPA, MESAS, SILLAS | Solo jardines | Lo que el club debe preparar |
| NOTAS | Observaciones | — |

---

### 19. Listas de invitados

**Qué es:** los invitados que un socio registra para un día o evento, con su aprobación.
**Tablas:** `guest_lists.guest_lists`, `guest_lists.guest_list_items`. **Una fila =** un invitado (los datos de la lista se repiten en cada fila).

**Módulos:** Listas de invitados › Listas (aprobar o rechazar) · Listas de invitados › Pagos · Listas de invitados › Configuración (precios) · App móvil · Cobranza (cargo GUEST_LIST al aprobar).

**Por qué es necesaria:** las listas futuras ya aprobadas deben respetarse. El precio de cada invitado depende de su **edad**: tarifa normal (7 años o más) o especial; PE1 $300/$150, PE2 $400/$200.

| Columna | Qué es | Cómo la usa el sistema |
|---|---|---|
| 🟣 REFERENCIA DE LA LISTA | Clave propia | Agrupa los invitados de la misma lista; no se guarda |
| CLUB | Parque | Tarifas del parque |
| 🟣 ID DE USUARIO | Socio que la hizo | Se le cobra a su cuenta |
| 🟪 REFERENCIA DE LA RESERVACION | Si es de una reservación | Liga la lista con esa reservación |
| TITULO, DESCRIPCION, FECHA, HORA | Datos del evento | Pantalla de listas |
| ESTATUS | PENDIENTE, ACEPTADA, RECHAZADA | Al aceptar se genera el cargo |
| FECHA DE APROBACION, DESCUENTO, COMENTARIOS | Aprobación | Total = subtotal − descuento |
| NOMBRE, APELLIDO, CORREO, TELEFONO DEL INVITADO | Datos del invitado | Registro del invitado (el sistema pide correo) |
| EDAD | Años | Define el precio |
| SE COBRA AL SOCIO | Si el socio paga por él | Entra o no al subtotal cobrable |
| CORTESIA | Invitado sin costo | No se cobra |

---

## Parte 3. Lo que NO va en la plantilla (y por qué)

| Qué | Motivo |
|---|---|
| Contraseñas, llaves de Conekta, contraseñas de lectores y de correo | Seguridad: se configuran directo en el sistema |
| Saldo a favor (`credit_balances`) | Solo lo usa el pago anual internamente; ninguna pantalla lo muestra. Va como Nota de cobranza |
| Clases grupales con horario fijo e inscritos | Decisión: la clase es una reservación con profesor; además, el sistema no tiene pantalla para inscribir |
| Anuncios | Son pocos; se capturan en el sistema |
| Tarjetas domiciliadas | No se pueden migrar por seguridad; el socio la registra en la app |
| Acceso de los socios a la app | Cada socio lo crea al registrarse |
| Actas e incidencias | Solo informativas; las multas pendientes entran por Cargos |
| Cortes de caja, bitácoras, notificaciones, encuestas, página web | Operación diaria o contenido; empiezan desde cero |

## Parte 4. Procesos automáticos que dependen de lo que se cargue

| Proceso | Cuándo | Qué necesita |
|---|---|---|
| Generación de mensualidades | Día 1, 01:00 | Membresías activas con GENERA COBRO |
| Cobro a tarjetas domiciliadas | Día 1, 07:00 | Tarjetas registradas en la app |
| Relleno de meses faltantes | Al abrir una cuenta en Cobranza o la app | Mensualidades completas hasta la fecha de corte (la carga pone un "piso" en la fecha de corte) |
| Bloqueo por morosidad | Diario, 02:00 | Cargos pendientes con periodo y vencimiento correctos (3+ meses = bloqueo) |
| Transiciones por edad | Continuo | Fecha de nacimiento y parentesco |
| Suspensión por inasistencia | Al reservar | Reservaciones con estatus INASISTENCIA |

## Glosario rápido

- **Titular:** el responsable de la cuenta; a él se cobra.
- **Cuenta / membresía:** la familia o el socio en un parque (NUMERO DE CUENTA).
- **Grupo:** dos cuentas de la misma familia, una en cada parque.
- **Concepto de cobro:** el tipo de cargo (mensualidad, casillero…).
- **Aplicación de pago:** qué parte de un pago cubrió qué cargo.
- **Folio:** número de recibo. El sistema nuevo los genera como `CLUB-SERIE-AAMMDD-NNN`; los de Fox se conservan.
- **Serie de caja:** letra que identifica al cajero en sus folios.
- **Recurso:** lo que se reserva dentro de una amenidad (Cancha 2, Jardín 1, Asador 3).
- **Fecha de corte:** día del cambio de Fox al sistema nuevo.
