# Criterios de alcance — 05_FINANZAS.xlsx

Notas de por qué el archivo `05_FINANZAS.xlsx` se redujo de 13 hojas a 4 (SALDOS INICIALES, CARGOS PENDIENTES, MULTAS PENDIENTES, NOTAS DE COBRANZA). Esto es contexto de decisión, no forma parte del Excel entregado — es para retomar el criterio más adelante sin tener que reconstruir el razonamiento desde cero.

## Qué se quitó y por qué

### Saldos a favor → se fusionó dentro de Saldos Iniciales

En la base de datos, `billing.credit_balances` tiene un constraint `unique('membership_account_id')`: solo puede existir **un registro de saldo a favor por cuenta**. No hay forma de que una cuenta tenga varias filas de saldo a favor, así que no tenía sentido darle su propia hoja de captura con su propio flujo — se convirtió en una sola columna (`SALDO A FAVOR`) dentro de SALDOS INICIALES.

### Movimientos de saldo, Pagos, Aplicaciones de pagos, Cortes de caja / Desglose de efectivo / Cortes globales → fuera de alcance (bloque 6)

Todo esto es historial operativo o reconstrucción completa de movimientos, no un "saldo inicial":

- `billing.credit_movements` (Movimientos de saldo), `billing.payments` y `billing.payment_applications` (Pagos y sus aplicaciones), y las tablas de cortes de caja (`billing.cash_cuts` y relacionadas) son historial, no estado inicial.
- El propio `Plan_migracion_datos_sistema_anterior.md` separa esto en un "bloque 6: historial financiero" y dice explícitamente que **requiere autorización aparte** ("si se autoriza migrarlo") — no es parte del alcance inicial (bloque 5, "información financiera inicial").
- Además, reconstruir el historial completo de pagos y a qué cargo se aplicó cada uno sería una carga de captura enorme para un usuario no técnico. Con el saldo neto que debe cada cuenta hoy (SALDOS INICIALES) basta para arrancar el sistema; el detalle de cómo se llegó ahí no es indispensable.

### Operaciones/Aplicaciones SPEI y Fuentes de pago → no se pueden capturar a mano

Estructuralmente imposible de llenar en un Excel:

- `billing.spei_orders` (Operaciones/Aplicaciones SPEI): la CLABE, la referencia bancaria y el estatus del webhook los genera el sistema al crear una orden SPEI vía Conekta.
- `members.payment_sources` (Fuentes de pago): el token de la tarjeta solo se genera al tokenizarla directamente con Conekta.
- Mismo problema ya identificado antes en `03_SOCIOS.xlsx` con esta misma tabla de fuentes de pago — es un patrón recurrente: cualquier dato que dependa de la tokenización/webhooks de Conekta no es capturable manualmente, sin importar el archivo.

## Pendientes que sí requieren decisión (no resueltos, solo documentados)

- No existe todavía un concepto de cobro tipo "SALDO INICIAL" en el catálogo `CONCEPTOS DE COBRO` (archivo 1). El importador de SALDOS INICIALES va a necesitar uno nuevo (ej. "SALDO INICIAL POR MIGRACIÓN") para poder convertir cada fila en un `billing.charges` real.
- En MULTAS PENDIENTES se combinaron los datos mínimos de un acta (`members.acts`) directamente con la multa (`members.fines`) en una sola fila, en vez de pedir una hoja ACTAS separada — más fácil de llenar, pero el importador tendrá que crear primero el acta y luego la multa como dos pasos internos.
