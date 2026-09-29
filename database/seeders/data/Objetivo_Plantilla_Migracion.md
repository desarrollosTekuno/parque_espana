# Objetivo de la plantilla de migracion

## Que se necesita

Crear **un solo archivo de Excel**, con varias hojas ordenadas para su llenado, donde el cliente pueda pegar o capturar la informacion que ya tiene. Esa informacion debe servir despues para cargar el sistema y dejarlo funcionando con sus usuarios, cuentas, membresias y movimientos de dinero.

La plantilla esta dirigida a una persona que conoce los datos de su operacion, pero no la estructura interna del sistema. Debe usar nombres, numeros de usuario o cuenta, fechas, conceptos, folios e importes que esa persona pueda reconocer. No se le deben pedir identificadores internos del sistema.

## Prioridad de la informacion

1. Usuarios (a quienes actualmente tambien pueden llamar socios), cuentas, titulares e integrantes.
2. Membresias asociadas a las cuentas.
3. Cargos, pagos y la relacion de cada pago con los cargos que cubrio, para conservar el historial desde el inicio del periodo y poder reconstruir saldos.
4. Los demas datos necesarios para que el sistema opere, segun lo que se confirme al revisar las fuentes y el funcionamiento real.

Un saldo actual por si solo no sustituye el historial de cargos y pagos. La informacion financiera debe permitir saber que debia cada cuenta, que se le cobro, que pago, como se aplico cada pago y cuanto queda pendiente o a favor.

## Como debe ser el archivo

- Todos los datos necesarios estaran en **un mismo libro de Excel**; cada tipo de informacion tendra una hoja cuando ayude a llenarla paso a paso.
- Los catalogos iran juntos en una hoja.
- Cada dato se pedira una sola vez cuando sea posible. En otras hojas se usara el numero de usuario o cuenta para relacionarlo, sin repetir nombres y apellidos innecesariamente.
- Se conservaran los colores de los campos y las descripciones utiles. Las descripciones no requieren acentos.
- Las columnas deben ser comprensibles y practicas para quien pegara o llenara la informacion. Su contenido definitivo se decidira despues de revisar los archivos existentes y comprobar que cubren los movimientos financieros necesarios.

## Siguiente paso, antes de modificar la plantilla

Comparar las plantillas existentes contra este objetivo y presentar, hoja por hoja, las columnas que conviene conservar, quitar, renombrar o agregar. Revisar con especial cuidado cargos, pagos, aplicaciones y saldos. Discutir esa propuesta con el usuario **antes de editar el Excel**.

## Analisis de `Plantilla_Migracion_Datos.xlsx`

**Evaluacion:** la separacion de cargos, pagos y aplicaciones es una buena base para conservar el historial de cobranza. Sin embargo, el contenido actual del libro no permite relacionar todos los registros ni garantiza que, al cargarlo, las cuentas queden con su historial y saldo correctos. Esta evaluacion es del archivo tal como esta; no se ejecuto una importacion.

### Que contiene

El libro tiene una hoja de instrucciones, una de catalogos y hojas para socios, membresias, integrantes, domicilios, empleo, cargos, pagos y aplicaciones. Sus filas de muestra incluyen 3 personas en Socios, 3 cuentas en Membresias, 4 relaciones en Integrantes, 5 cargos, 4 pagos y 4 aplicaciones de pago.

En los ejemplos financieros, la aritmetica interna coincide: los cargos suman $4,800, los pagos aplicados $3,575 y los saldos indicados $1,225. Esto demuestra la utilidad de conservar cada cargo, cada pago y su aplicacion, pero no resuelve los problemas de relacion entre hojas.

### Problemas observados

1. **Las hojas financieras no identifican las cuentas de las otras hojas.** Membresias usa `NO_CUENTA` con valores como `00001` y `00002`; Cargos y Pagos usan `NUMERO_SOCIO` con valores como `1001` y `1002`. No hay una equivalencia declarada. En el sistema, los cargos y pagos se relacionan con la cuenta de membresia, de modo que no se puede decidir a que cuenta pertenecen esos movimientos solo con este archivo.
2. **Hay referencias incompletas en las filas de muestra.** `S004` aparece como titular de la cuenta `00002` y en Domicilios y Empleo, pero no existe en Socios. La cuenta `00003` no tiene un titular relacionado en Integrantes.
3. **No se puede confirmar que las filas sean datos reales extraidos del sistema anterior.** Hay pocos registros, claves ilustrativas como `S001`, `C001` y `P001`, y un correo de ejemplo. El archivo parece preparado para mostrar como llenar la plantilla; no demuestra por si solo que incluya el padron o el historial real.
4. **La aplicacion de pagos se presenta como opcional.** Las instrucciones afirman que, si se omite esa hoja, el sistema aplicara pagos por orden cronologico. Esa asignacion podria no coincidir con los cargos que realmente cubrio cada pago. Para conservar un historial fiel, la relacion pago-cargo debe registrarse cuando exista; una relacion supuesta no debe aparecer como hecho historico.
5. **Algunos valores de catalogo no coinciden con los vigentes.** Por ejemplo, el libro usa `PE01`, `TRANSFER`, `CARD` y `LOCKER`; los catalogos actuales usan `PE1`, `BANK_TRANSFER`, `CREDIT_CARD` o `DEBIT_CARD`, y `LOCKERS`. Se podrian homologar al preparar la carga, pero hoy la instruccion de usar exactamente los valores del Excel induciria a errores.
6. **Faltan situaciones que afectan el saldo.** El sistema contempla descuentos, pagos cancelados y saldos a favor. Las columnas actuales no permiten reconstruir estos casos si existen en los datos del cliente. Tambien falta identificar claramente el club en los movimientos financieros cuando no pueda deducirse sin ambiguedad de la cuenta.

### Relacion con la carga actual del sistema

El proceso de importacion existente espera otra disposicion para el historial financiero: lee una hoja de historial por periodo con cargo y pago en una misma fila. Este libro, en cambio, separa Cargos, Pagos y Aplicaciones. Por eso no debe asumirse que el importador actual cargara correctamente estas tres hojas. Esta observacion describe la compatibilidad actual y no cambia el objetivo de disenar una plantilla sencilla para la persona que la llenara.

### Dictamen

**Conservar como idea la separacion entre Cargos, Pagos y Aplicaciones; no aprobar el archivo actual para solicitar su llenado y cargarlo sin cambios.** Antes de modificar el Excel, definir una referencia de cuenta que una todas las hojas y la forma de representar los movimientos que alteran el saldo. Despues presentar y discutir las columnas concretas con el usuario.

## Plan para construir la nueva plantilla

**Entrega prevista:** un nuevo libro `Plantilla_Migracion_Cliente.xlsx` en la raiz del proyecto. El archivo original `Plantilla_Migracion_Datos.xlsx` quedara como referencia. Este apartado describe la construccion; el nuevo Excel aun no se ha creado.

### 1. Fijar las referencias que usara la persona que llena el archivo

- Elegir un unico `NO_CUENTA` reconocible por el cliente y repetirlo exactamente en las hojas de cuentas, integrantes, cargos y pagos. Conservar ceros iniciales como `00001`.
- Llamar **usuario** a cada persona. Usar su numero o clave de origen si existe; comprobar primero si cada integrante tiene una clave propia. Si no la tiene, definir una clave sencilla dentro del libro para distinguir integrantes de la misma cuenta, sin pedir IDs de la base de datos.
- Para relacionar movimientos, usar `CARGO_REF` y `PAGO_REF`: folios del sistema anterior si existen o referencias sencillas del propio archivo. Una referencia debe identificar siempre el mismo cargo o pago.
- Si una persona tiene cuentas en mas de un club, conservar la cuenta y el club correctos en cada relacion. No suponer que el numero de usuario identifica por si solo una cuenta.

### 2. Definir las hojas principales y su orden de llenado

| Orden | Hoja propuesta | Lo que capturara la persona |
| --- | --- | --- |
| 00 | Catalogos | En una sola hoja, los nombres y opciones validas para club, tipo de membresia, parentesco, estado, concepto de cobro y forma de pago. Mostrar palabras reconocibles y homologar internamente los codigos. |
| 01 | Clubes | Datos vigentes de cada club y su forma de cobrar. Precargar lo que ya existe y pedir solo confirmar o corregir. |
| 02 | Personal | Empleados administrativos o cajeros que deban operar al arrancar: nombre, correo, club y funcion. Solo pedir la referencia de cajero en pagos historicos si el cliente la conoce. |
| 03 | Usuarios | Una fila por persona, titular o integrante. Clave de usuario, nombre, apellidos y datos personales necesarios. Domicilio, contacto y empleo solo si son necesarios y estan disponibles; no repetirlos en las hojas financieras. |
| 04 | Cuentas y membresias | Una fila por cuenta y membresia de un club: numero de cuenta, club, tipo, fecha de inicio, estado y referencia del titular. Registrar relaciones entre cuentas de distintos clubes solo cuando existan. |
| 05 | Integrantes | Una fila por relacion entre cuenta y usuario: numero de cuenta, clave de usuario, titular o integrante y parentesco. No volver a pedir los nombres. |
| 06 | Cargos | Una fila por deuda o cargo: numero de cuenta, club, `CARGO_REF`, concepto, importe, fecha, vencimiento y periodo si corresponde. Incluir adeudos anteriores al inicio del historial como cargos de apertura identificados, sin inventar pagos anteriores. |
| 07 | Pagos | Una fila por pago recibido: numero de cuenta, club, `PAGO_REF`, fecha, importe, forma de pago, folio o referencia bancaria cuando exista y estado del pago. |
| 08 | Aplicaciones | Una fila por relacion pago-cargo: `PAGO_REF`, `CARGO_REF`, importe aplicado y descuento si lo hubo. Un pago puede tener varias filas y un cargo puede recibir varios pagos. |
| 09 | Saldos de control | Una fila por cuenta y club con fecha de corte, saldo pendiente, saldo vencido y saldo a favor reconocidos por el cliente. Sirve para conciliar; no crea una segunda deuda. |

Las hojas 06 a 09 son necesarias para reconstruir y comprobar el historial financiero. Si el origen tiene saldo a favor, cancelaciones o ajustes que no caben fielmente en esas hojas, definir las columnas u hoja adicional correspondientes **a partir de casos reales**. No crear movimientos ficticios para hacer cuadrar el saldo.

### 3. Completar la cobertura del sistema en el mismo libro

Las diez hojas iniciales no bastan para poner en marcha todos los modulos que aparecen en las nueve plantillas actuales. Agregar las siguientes secciones **al mismo archivo**, despues del nucleo, con una hoja por conjunto de registros que realmente se repite. Los nombres entre parentesis indican las plantillas existentes que sirven de referencia; no son archivos adicionales que se entregaran al cliente.

| Bloque y prioridad | Hojas o datos que debe prever el libro nuevo | Criterio de captura |
| --- | --- | --- |
| Clubes y cobro vigente — necesario para operar (`02_CLUBES_Y_CONFIGURACION`) | Clubes, datos fiscales y de contacto, domicilio, metodos aceptados por club, importes vigentes por concepto y serie/ultimo folio si se continuara la numeracion anterior. | Precargar lo que ya exista en el sistema y pedir al cliente confirmar o corregir datos actuales. No pedir contrasenas, llaves de pasarelas ni configuraciones tecnicas. |
| Datos del usuario — necesarios segun el servicio (`03_SOCIOS`) | Domicilios, contacto de emergencia, fotografia y documentos que acrediten derechos o acceso; informacion laboral y medica solo cuando el cliente la tenga y se use en la operacion. | Relacionar todo con la clave del usuario. En hojas complementarias no repetir sus nombres y apellidos. Los archivos se identificaran por nombre o referencia de entrega, no se pegaran dentro de celdas. |
| Estado real de cuentas y membresias — necesario para operar (`04_CUENTAS_Y_MEMBRESIAS`) | Grupo de cuentas entre clubes, membresias vigentes, tipo y cuota vigente, titulares e integrantes, datos fiscales, fecha desde la que corresponde generar nuevos cargos y derechos de acceso. Registrar ausencias o suspensiones activas, cancelaciones o reactivaciones que sigan afectando la cuenta y transiciones de edad pendientes. | Una cuenta y un club deben quedar inequívocos. No pedir al cliente reglas de precio internas si basta con elegir un tipo de membresia y confirmar la cuota aplicable. |
| Finanzas de apertura e historial — imprescindible (`05_FINANZAS` y `Plantilla_Migracion_Datos.xlsx`) | Fecha de corte; saldo de control por cuenta y club; adeudos anteriores y cargos desde el inicio del periodo; pagos recibidos; aplicaciones pago-cargo; descuentos; pagos y cargos cancelados; saldo a favor y movimientos que lo expliquen cuando existan; pagos sin aplicar y notas de cobranza vigentes. | El saldo de control verifica el resultado y no se carga como segunda deuda. Un adeudo de apertura se representa una sola vez. Si una multa pendiente tambien aparece como cargo, se vincula y no se cobra dos veces. Folios de recibo o referencias bancarias se conservan cuando el origen los tenga. |
| Casilleros — necesarios si estan en uso (`06_CASILLEROS`) | Inventario y estado actual de casilleros, asignaciones vigentes, titular de cada asignacion, fechas e importes relacionados. Historial de bajas o cambios solo si el cliente lo consulta o necesita continuidad. | Relacionar usuario, club y numero de casillero; los cobros asociados se registran en las hojas financieras para evitar duplicarlos. |
| Amenidades y clases — necesarios para ofrecer reservas o clases (`07_AMENIDADES_Y_CLASES`) | Amenidades, recursos, horarios, bloqueos vigentes, entrenadores, especialidades, disponibilidad, clases programadas e inscripciones activas. | Conservar solo la configuracion y actividad vigente o futura necesaria al arrancar. Usar una referencia sencilla de entrenador o clase cuando se repita en varias hojas. |
| Reservaciones y accesos — necesarios para cumplir compromisos futuros (`08_RESERVACIONES_Y_ACCESOS`) | Reservaciones futuras, recursos reservados, listas e invitados asociados, pases diarios programados y tarjetas o permisos de acceso que deban seguir activos. | Referencias de reserva y pase deben enlazar sus detalles. La configuracion de dispositivos y sus credenciales se prepara por el equipo tecnico, no se solicita como captura ordinaria al cliente. |
| Administracion con efectos vigentes — necesaria si hay casos abiertos (`09_INFORMACION_ADMINISTRATIVA`) | Actas, multas y amonestaciones sin resolver, suspensiones vigentes, incidentes de visitantes con seguimiento pendiente, reglas actuales del club y documentos asociados que el sistema pueda recibir. | Relacionar actas y sanciones mediante folio. Si una sancion genera deuda, conciliarla con Cargos. No pedir historial administrativo antiguo que no afecte dinero, acceso o casos activos. |

La hoja unica de **Catalogos** reunira las opciones utiles para llenar todas estas secciones: clubes, tipos de membresia, parentescos, conceptos, formas de pago, estados y las opciones de casilleros, clases y reservas que se usen. No duplicar en el libro los catalogos enormes que ya estan cargados, como todas las ciudades del pais; mostrar las opciones necesarias para que el cliente capture sin adivinar codigos. Las reglas internas, roles, permisos y datos generados por el sistema no son informacion de migracion que deba transcribir una persona.

Antes de cerrar la lista de hojas, revisar si hay otros modulos con **registros activos heredados** que no figuran en las nueve plantillas, como anuncios, publicidad, encuestas o solicitudes en seguimiento. Si existen y el cliente necesita continuidad, agregar su captura al mismo libro; si no hay datos heredados, dejar que se creen normalmente en el sistema nuevo. La documentacion antigua sobre tablas faltantes puede estar desactualizada: contrastar cada bloque con el esquema vigente al disenar sus columnas.

### 3.1. Auditoria de lo que falta definir para crear el archivo

La revision de las nueve plantillas, los modulos visibles del sistema y sus modelos muestra que **aun no esta cerrada la especificacion del Excel**. Las nueve plantillas cubren muchos temas, pero no prueban que sus filas sean exportaciones reales del cliente. En particular, `05_FINANZAS.xlsx` tiene saldos y cargos pendientes, pero no tiene hojas de pagos historicos ni de aplicaciones. `Plantilla_Migracion_Datos.xlsx` si muestra esas tres relaciones, aunque sus numeros de cuenta y socio no coinciden entre hojas. Las hojas de casilleros, reservaciones, accesos y administracion de los archivos separados estan vacias; eso no demuestra que el cliente carezca de registros.

**Datos adicionales que debe contemplar el diseno del libro:**

| Area | Dato de captura o decision concreta | Por que hace falta |
| --- | --- | --- |
| Personal que opera el sistema | Una hoja corta de empleados administrativos/cajeros vigentes: nombre, correo, club y funcion. Para pagos antiguos, identificacion del cajero solo si el cliente la conoce. | El sistema distingue a las personas del club de las cuentas de acceso administrativo; pagos, folios y cortes pueden quedar asociados a un usuario de caja. Contraseñas y permisos internos se configuran aparte. |
| Acceso de usuarios a la app | Correo y la indicacion de quien debe tener acceso a la app, si el cliente puede proporcionarlos. | La persona del club y la cuenta de inicio de sesion son registros distintos. El acceso se provisiona despues; no se piden contraseñas ni datos de tarjeta en Excel. |
| Identidad de cada persona y cuenta | Confirmar si el origen tiene numero unico para cada integrante, numero de cuenta por club y alguna referencia de grupo entre clubes. | Sin esas claves no se pueden relacionar integrantes, membresias, cargos, pagos, casilleros y reservas. No usar el nombre como clave. |
| Corte financiero | Fecha inicial del historial, fecha de corte, deuda que ya existia al inicio y saldo reconocido por cuenta y club al corte. | Permite separar adeudo previo de cargos del periodo y detectar diferencias sin crear dos veces la misma deuda. |
| Detalle de cobros | Cargo y pago con folio o referencia, cuenta, club, concepto/metodo, importe y fecha; aplicacion entre ambos; parcialidades, descuentos, cancelaciones y saldo a favor cuando existan. | Es el minimo para mostrar historial de pagos y reconstruir saldos. Si un pago cubrio varios cargos o uso varios metodos, se necesitan relaciones separadas sin duplicar el pago. |
| Comprobantes y reportes financieros | Folio anterior del recibo, referencia bancaria, cheque y separacion de subtotal/IVA solo si el origen los conserva. Cortes de caja historicos solo si el cliente requiere consultarlos en el sistema nuevo. | Los cortes no se reconstruyen automaticamente a partir de saldos: incluyen cajero, apertura, efectivo contado y cierre. La serie de folios debe continuar sin repetir numeros. |
| Documentos y fotografias | En el Excel: usuario/cuenta, tipo de documento y nombre o referencia del archivo. Obtener los archivos reales por una entrega asociada si se pretende que aparezcan en el sistema. | Una ruta escrita en una celda no aporta el contenido de una foto o PDF. El libro unico organiza los datos, pero los binarios necesitan entregarse o cargarse despues. |
| Actividad vigente | Casilleros ocupados; clases e inscripciones activas; reservas futuras, listas de invitados y pases programados; ausencias, sanciones y notas que sigan abiertas. | Son compromisos y restricciones que el sistema debe respetar desde el primer dia. El historial cerrado de estos modulos se solicita solo si hay un uso concreto. |

**Modulos visibles no cubiertos por las nueve plantillas:** pagina web, anuncios y publicidad, encuestas, quejas/sugerencias, notificaciones y formatos. Para cada uno se debe verificar si existe contenido publicado, una campaña vigente, una encuesta activa, un caso abierto o un formato que deba continuar. Si la respuesta es si, añadir hojas de captura con sus referencias al **mismo libro**. Si no hay registros que conservar, basta la configuracion normal del modulo y no se obliga al cliente a rellenar hojas vacias. Los mensajes de contacto, respuestas y bitacoras antiguas se incluyen solo si se necesita conservar ese historial.

**Lo que no debe llenar el cliente:** los miles de municipios del catalogo geografico, IDs de base de datos, roles y permisos tecnicos, claves de Conekta, SMTP o dispositivos, tokens de tarjetas, claves de acceso, tareas programadas y datos generados automaticamente por el nuevo sistema. Los catalogos y clubes ya sembrados sirven como opciones precargadas que se confirman; no deben copiarse ciegamente desde ejemplos del Excel.

**Informacion de origen indispensable antes de fijar los encabezados definitivos:** un export o muestra real de usuarios e integrantes, cuentas y membresias, cargos del periodo, pagos del periodo, aplicaciones o la mejor relacion disponible, saldos al corte y lista de modulos con registros vigentes. Si el origen no conserva alguna relacion, documentar exactamente cual falta y decidir como mostrar ese limite; no presentarla como reconstruida. La lista final de hojas y columnas se cerrara con esa evidencia, no suponiendo que todo dato de una tabla puede o debe pedirseles a las personas.

### 4. Disenar los encabezados para captura sencilla

- Conservar el significado de colores: rojo obligatorio, azul opcional y verde para catalogos. Poner una descripcion corta bajo cada encabezado, sin necesidad de acentos.
- Usar lenguaje de operacion: `NO_CUENTA`, `USUARIO`, `IMPORTE`, `FECHA_PAGO`, `CONCEPTO`; evitar nombres de tablas, IDs internos y abreviaturas que el cliente no conozca.
- No repetir nombre, apellido paterno y materno en cuentas, cargos o pagos. Mostrar la clave de usuario o el numero de cuenta que los relaciona.
- Diferenciar claramente una celda vacia de un importe cero. No poner ejemplos dentro del area que recibira los datos definitivos sin marcarlos como ejemplos.

### 5. Comprobar relaciones y dinero antes de darla por terminada

Probar el diseño con casos representativos: cuenta individual, cuenta familiar con integrantes, cuenta en mas de un club, pago que cubre varios cargos, cargo con pagos parciales, descuento, pago cancelado, pago sin aplicar y saldo a favor si existen en el origen. Verificar que toda referencia de usuario, cuenta, cargo y pago encuentre su registro y que no haya titulares ausentes. Probar tambien un casillero ocupado, una clase con inscritos y una reservacion futura si esos modulos se migraran.

Por cada cuenta y club, comparar el saldo reconstruido a la fecha de corte con el saldo que el cliente reconoce. La comprobacion debe distinguir cargos pendientes, pagos aplicados, descuentos, cancelaciones y saldos a favor. Si falta detalle historico, dejarlo identificado como dato no disponible en vez de atribuir pagos a cargos por suposicion. Revisar que el sistema no vuelva a generar mensualidades del periodo ya importado.

### 6. Entregar la plantilla y preparar su carga

Crear el Excel en la raiz solo despues de cerrar la lista de hojas y columnas. Revisar que alguien ajeno al codigo pueda seguir el orden y pegar sus exportaciones sin transcribir datos repetidos. Finalmente, ajustar o construir la carga del sistema para **esa** estructura y probarla con una copia de datos, ya que el importador actual no corresponde a las tres hojas financieras separadas. La aprobacion final requiere que usuarios, cuentas, membresias, historial de pagos y saldos coincidan con los datos de origen, y que los registros vigentes de los demas modulos puedan encontrarse y usarse en el sistema.

No llamar "completa" a la plantilla hasta que cada modulo visible tenga una decision registrada: **se captura en una hoja**, **ya esta precargado y solo se confirma**, **se configura tecnicamente fuera del Excel**, o **inicia vacio porque no hay datos heredados que conservar**. Para dinero, ademas, el total y saldo por cuenta y club deben conciliar con el reporte del cliente y una prueba de carga debe mostrar el historial en la interfaz.
