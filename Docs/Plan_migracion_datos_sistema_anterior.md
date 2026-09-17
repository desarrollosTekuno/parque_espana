# Plan inicial de migración de datos

## Contexto

Este proyecto reemplazará un sistema de escritorio antiguo. No se tiene acceso al sistema anterior ni se conoce con certeza su estructura, sus tablas o sus campos. Por ahora solamente se asume que el cliente entregará información mediante archivos CSV o Excel para cargarla en el sistema nuevo.

La planeación debe hacerse a partir de lo que necesita el sistema nuevo para operar. No se debe asumir que los campos, catálogos o relaciones del sistema anterior coinciden con los actuales.

Las plantillas entregadas al cliente pueden utilizar nombres y columnas comprensibles para sus usuarios. Posteriormente, el proceso de importación será responsable de transformar esos datos a la estructura interna del sistema nuevo.

## Restricciones de trabajo

- El desarrollador que trabaja en esta tarea no es propietario del proyecto; solamente colabora en módulos o cambios específicos.
- No se debe mover, reorganizar, refactorizar ni corregir lógica preexistente fuera del alcance solicitado.
- Si se detecta un problema anterior, solo debe atenderse cuando bloquee el módulo o cuando haya sido causado por un cambio propio.
- En esta etapa solamente se está definiendo qué información debe solicitarse y en qué orden debe cargarse.
- Todavía no se deben implementar importadores ni modificar la lógica de negocio.
- No se debe exigir que los archivos del sistema anterior coincidan con nombres de tablas o campos del sistema nuevo.
- Cada registro importado debe conservar, cuando sea posible, un identificador del sistema anterior para relacionar las distintas plantillas.

## Referencia técnica disponible

Se configuró Graphify localmente para consultar únicamente:

- `app/Models`
- `database/migrations`
- `routes`

Los archivos generados por Graphify permanecen locales y están ignorados por Git. El grafo principal se encuentra en `graphify-out/graph.json` y puede consultarse para revisar modelos, migraciones y relaciones actuales.

Entidades centrales detectadas en el sistema nuevo:

- Clubes.
- Socios.
- Cuentas de membresía.
- Membresías.
- Usuarios.
- Cargos y pagos.
- Reservaciones.
- Amenidades.
- Entrenadores y clases.
- Documentos.
- Casilleros.

## Criterio de identificación

Las futuras plantillas deben incluir una columna como `id_anterior`, `numero_anterior` o equivalente. Esta referencia permitirá relacionar socios, cuentas, membresías, pagos, documentos y otros datos sin utilizar los identificadores internos de la base de datos nueva.

El modelo de socios ya contempla el campo `migration_origin_id`. Para las demás entidades se deberá definir la referencia adecuada cuando se diseñen los importadores.

## Orden propuesto de información y plantillas

### 1. Catálogos base

1. Países.
2. Estados.
3. Ciudades.
4. Nacionalidades.
5. Estados civiles.
6. Parentescos.
7. Tipos de documento.
8. Documentos requeridos por parentesco.
9. Motivos de cancelación.
10. Motivos de separación.
11. Tipos de membresía.
12. Documentos requeridos por tipo de membresía.
13. Métodos de pago.
14. Conceptos de cobro.
15. Importes de conceptos por club.
16. Reglas de descuentos.
17. Reglas de precios.
18. Especialidades de entrenadores.
19. Estatus de reservaciones.
20. Categorías de casilleros.
21. Categorías y estatus de incidentes, actas, multas o amonestaciones.

### 2. Configuración principal

22. Clubes.
23. Domicilios de clubes.
24. Métodos de pago aceptados por club.
25. Folios de cobro por club.
26. Usuarios administrativos.
27. Roles y permisos.
28. Relación de usuarios con clubes.

### 3. Socios

29. Socios.
30. Fotografías de socios.
31. Domicilios de socios.
32. Información laboral.
33. Historial clínico.
34. Contactos de emergencia.
35. Documentos de socios.
36. Fuentes de pago de socios.
37. Identificador del socio en el sistema anterior.

### 4. Cuentas y membresías

38. Grupos de cuentas.
39. Cuentas de membresía.
40. Titulares de cuenta.
41. Integrantes de cada cuenta.
42. Parentesco de los integrantes.
43. Códigos y estatus de acceso.
44. Membresías por cuenta.
45. Cuotas mensuales.
46. Fechas de inicio y término.
47. Datos fiscales.
48. Permisos de ausencia vigentes.
49. Cancelaciones.
50. Reactivaciones.
51. Transiciones pendientes por edad.
52. Separaciones de integrantes.

### 5. Información financiera inicial

53. Cargos pendientes.
54. Saldos iniciales.
55. Saldos vencidos.
56. Saldos a favor.
57. Pagos no aplicados.
58. Movimientos pendientes de saldo a favor.
59. Multas pendientes.
60. Notas de cobranza vigentes.

### 6. Historial financiero

61. Historial de cargos.
62. Historial de pagos.
63. Aplicaciones de pagos a cargos.
64. Referencias bancarias.
65. Cheques.
66. Operaciones SPEI.
67. Folios y comprobantes.
68. Cancelaciones de cargos.
69. Cancelaciones de pagos.
70. Movimientos de saldos a favor.
71. Cortes de caja.
72. Denominaciones de efectivo.
73. Cortes globales de caja.

### 7. Casilleros

74. Casilleros.
75. Asignaciones vigentes.
76. Fechas de inicio y término.
77. Importes pagados.
78. Comprobantes.
79. Cancelaciones.
80. Historial de asignaciones.

### 8. Amenidades y clases

81. Amenidades.
82. Recursos de amenidades.
83. Ubicaciones de recursos.
84. Horarios disponibles.
85. Periodos bloqueados.
86. Entrenadores.
87. Especialidades de entrenadores.
88. Disponibilidad de entrenadores.
89. Horarios de clases.
90. Inscripciones activas.
91. Historial de inscripciones, si se requiere.

### 9. Reservaciones y accesos

92. Reservaciones futuras.
93. Recursos reservados.
94. Estatus de reservaciones.
95. Listas de invitados.
96. Asistencias vigentes o próximas.
97. Pases diarios vigentes.
98. Visitantes asociados a pases.
99. Tarjetas o códigos de acceso activos.

### 10. Información administrativa complementaria

100. Actas.
101. Multas.
102. Amonestaciones.
103. Incidentes de visitantes.
104. Archivos y comprobantes relacionados.
105. Reglas del club.
106. Archivos propios del club.

## Secuencia principal de carga

La secuencia mínima para reconstruir la operación es:

1. Catálogos.
2. Clubes y configuración por club.
3. Socios.
4. Cuentas de membresía.
5. Integrantes y titulares.
6. Membresías.
7. Saldos y cargos pendientes.
8. Información complementaria.
9. Historial financiero, si se autoriza migrarlo.
10. Módulos operativos como casilleros, clases y reservaciones.

## Próximo paso

Revisar cada bloque en el orden anterior y definir:

- Si la información se migrará o se configurará directamente en el sistema nuevo.
- Si será obligatoria, opcional o solamente histórica.
- Qué columnas tendrá la plantilla CSV o Excel.
- Qué columna servirá como identificador del sistema anterior.
- Qué catálogos o valores permitidos deberá utilizar el cliente.
- Qué validaciones mínimas necesitará el importador.
- En qué orden se ejecutarán las cargas para respetar las relaciones.

No se debe comenzar la implementación de los importadores hasta aprobar estas definiciones.
