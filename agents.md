# AGENTS - Guia para registrar un modulo nuevo

Este proyecto usa Laravel + Inertia + Vue 3 + Vuetify. La forma correcta de dar de alta un modulo nuevo es seguir una estructura consistente para backend, frontend, permisos y datos iniciales.

## 1) Estructura general del proyecto

- Controladores web: `app/Http/Controllers/Web/...`
- Controladores API: `app/Http/Controllers/Api/V1/...`
- Modelos: `app/Models/...` (por dominio)
- Rutas:
  - Web principal: `routes/web.php`
  - Modulos admin club: `routes/adminclubs.php`
  - Modulos superadmin: `routes/administrator.php`
  - API: `routes/api.php`
- Migraciones: `database/migrations/*.php`
- Seeders: `database/seeders/*.php`
- Vistas Inertia (Vue): `resources/js/Pages/...`
- Layout base: `resources/js/Layouts/AppLayout.vue`
- Template de referencia: `resources/js/Pages/Template.vue`

## 2) Como registrar un modulo nuevo (checklist)

1. Definir dominio y namespace
- Elegir contexto: `AdminClub`, `Administrator`, `Feedback`, `Billing`, etc.
- Mantener nombres consistentes entre tabla, modelo, controlador, rutas y vista.

2. Crear migracion(es)
- Crear archivo con timestamp en `database/migrations`.
- Si se usan esquemas (ej. `feedback.categories`), asegurar `CREATE SCHEMA IF NOT EXISTS ...` antes de `Schema::create`.
- Incluir: llaves foraneas, indices, `timestamps`, y `softDeletes` cuando aplique.
- Ejemplo real: `database/migrations/2026_04_23_232227_create_feedback_categories_table.php`.

3. Crear modelo
- Ubicarlo por dominio, por ejemplo: `app/Models/Feedback/Category.php`.
- Definir `protected $table` cuando la tabla no sea convencional o tenga schema.
- Definir `guarded`/`fillable`, `casts`, relaciones y traits (`SoftDeletes`, etc.).

4. Crear controlador
- Web: `app/Http/Controllers/Web/<Contexto>/<Modulo>Controller.php`
- API: `app/Http/Controllers/Api/V1/<Modulo>Controller.php`
- Registrar middleware de permisos en `__construct()`.
- Implementar validaciones, manejo de excepciones, respuestas consistentes y paginacion.
- Para vistas web, retornar `Inertia::render('Ruta/De/Vista', [...props])`.

5. Registrar rutas
- Si es modulo de club: editar `routes/adminclubs.php`.
- Si es superadmin: editar `routes/administrator.php`.
- Si es API: editar `routes/api.php`.
- Usar `Route::resource` cuando sea CRUD estandar y nombrar rutas con `->names('modulo')`.
- En frontend se consumen con Ziggy via `route('nombre.ruta')`.

6. Crear vista Vue (Inertia)
- Ubicar segun contexto, por ejemplo:
  - `resources/js/Pages/AdminClubs/<Modulo>/Index.vue`
  - `resources/js/Pages/Administrator/<Modulo>/Index.vue`
- Basarse en `resources/js/Pages/Template.vue` para:
  - `Head` + `AppLayout`
  - Tabla server-side (`v-data-table-server`)
  - Busqueda, paginacion, ordenamiento
  - Modal de alta/edicion
  - `useForm` con `form.post/put/delete`
  - Alertas con `customToastSwal` y confirmaciones con `customConfirmSwal`
- Control de permisos en UI con `can.includes('permiso')`.
- En tablas, mantener acciones con iconos (sin texto) para `edit/delete`, siguiendo el patron existente con `BaseButton action="edit|delete"`.

7. Crear permisos y seeders
- Definir permisos del modulo (index, store, update, destroy, extras) en `PermissionSeeder.php`.
- Asignar permisos a roles donde corresponda (`RoleSeeder` o seeders relacionados).
- Si hay catalogos iniciales del modulo, crear seeder dedicado y enlazar en `DatabaseSeeder.php`.

8. Integrar en navegacion (si aplica)
- Agregar opcion en el menu/layout correspondiente para llegar a la vista del modulo.
- Validar que el item solo aparezca cuando el usuario tenga permisos.

9. Probar punta a punta
- Migraciones: `php artisan migrate`
- Seeders: `php artisan db:seed`
- Revisar flujo web/API completo: listar, crear, editar, eliminar, permisos, errores.

## 2.1) Instruccion rapida: "iniciar modulo"

Cuando se pida iniciar un modulo (solo dejar entrada funcional sin logica de negocio), realizar unicamente estos pasos minimos:

1. Crear permisos base del modulo
- Agregar al menos permiso `modulo.index` en `PermissionSeeder.php`.
- Asignar el permiso al rol inicial requerido (por ejemplo en `SuperAdminSeeder.php`).

2. Registrar ruta web del modulo
- Registrar `Route::resource(...)->only(['index'])->names('modulo')` en el archivo de rutas que corresponda (`routes/adminclubs.php` o `routes/administrator.php`).

3. Crear controlador minimo
- Crear controlador web con `index()` y middleware de permiso en `__construct()`.
- Retornar `Inertia::render('.../Index')`.

4. Crear vista minima
- Crear `Index.vue` simple con `Head`, `AppLayout` y texto tipo "Modulo en construccion" para validar acceso.

5. Registrar en navegacion
- Agregar opcion en `resources/js/routing.ts` con titulo e icono.

Objetivo de esta instruccion: poder entrar al modulo desde menu con control de permisos, sin implementar CRUD ni logica adicional.

## 3) Estructura recomendada de controlador

Para modulos CRUD web, se recomienda:

- `index(Request $request)`
  - filtros (`search`), orden y paginacion
  - retorna `Inertia::render(..., ['items' => $items])`
- `store(Request $request)`
  - valida
  - crea
  - `return back()->with('success', '...')`
- `update(Request $request, $id)`
  - valida
  - actualiza
  - `return back()->with('success', '...')`
- `destroy($id)`
  - elimina (soft delete si aplica)
  - `return back()->with('success', '...')`

Siempre envolver operaciones de riesgo en `try/catch`, usar `report($e)` y retornar errores controlados.

## 4) Estructura recomendada de vista Template (Inertia + Vue)

Basada en `resources/js/Pages/Template.vue`:

- `<script setup lang="ts">`
  - imports: `Head`, `router`, `useForm`, `usePage`, `AppLayout`
  - interfaces `Props` y `Form`
  - `const can = usePage().props.auth.permissions`
  - estado reactivo (`items`, `total`, `loading`, `search`, `options`, `showModal`)
  - metodos `fetchItems`, `create`, `edit`, `save`, `destroy`, `close`
  - para rutas extra fuera de CRUD basico (`store/update/destroy`), usar `axios` con funcion simple `try/catch` y `route('modulo.accion', params)`
  - `watch([options, search], debounce(fetchItems, 400), { deep: true })`

- `<template>`
  - `<Head title="..." />`
  - `<AppLayout>` con slots `header` y `options`
  - `v-data-table-server` para listado
  - `v-dialog` + `v-form` para crear/editar
  - botones condicionados por permisos
  - en columnas de acciones usar botones de icono sin texto

## 5) Convenciones de nombres

- Modelo: singular en PascalCase (`FeedbackCategory` o `Category` dentro de namespace `Feedback`).
- Controlador: `<Modulo>Controller`.
- Vista: `Index.vue` para listado CRUD inicial.
- Ruta nombre: kebab-case (`feedback-categories.index`).
- Tabla: plural y consistente; si hay schema, usar `schema.tabla`.

## 6) Comandos utiles

- Crear modelo + migracion:
  - `php artisan make:model Feedback/Category -m`
- Crear controlador resource web:
  - `php artisan make:controller Web/AdminClub/FeedbackCategoryController --resource`
- Crear controlador API:
  - `php artisan make:controller Api/V1/FeedbackCategoryController`

## 7) Plan de trabajo recomendado para un modulo nuevo

1. Definir alcance funcional y permisos requeridos.
2. Diseñar tablas/relaciones y crear migraciones.
3. Crear modelos con relaciones y casts.
4. Implementar controlador(es) web/API con validaciones.
5. Registrar rutas y nombres de ruta.
6. Construir vista `Index.vue` basada en Template.
7. Configurar permisos y seeders.
8. Integrar menu y reglas de visibilidad por permisos.
9. Ejecutar migracion/seeding y pruebas funcionales.
10. Documentar modulo (rutas, permisos, payloads y casos borde).

## 8) Documento para incorporar en PDF

Se genero una version lista para pegar en el documento funcional en:

- `Docs/Plan_trabajo_ParqueEspaña.md`

Ese contenido esta preparado para insertarse en `Docs/ParqueEspaña.pdf` como seccion "Plan de trabajo para alta de modulos".

## 9) Regla de implementacion: "minimo funcional"

Cuando el usuario pida enfoque simple/minimo, aplicar esta regla estricta:

- Regla general: por defecto, cualquier implementacion debe ser lo mas simple posible.
- Solo optimizar, abstraer o sofisticar la solucion si el usuario lo solicita de forma explicita.

- Implementar solo lo solicitado para que funcione (sin extras).
- Validar unicamente lo necesario para guardar y no romper flujo.
- No agregar validaciones avanzadas, escenarios preventivos ni endurecimientos de seguridad no solicitados.
- No refactorizar ni abstraer en funciones auxiliares si no lo pidieron.
- Preferir codigo explicito y directo (estilo junior), evitando compactaciones innecesarias.
- Mantener el codigo siempre basico y facil de entender para nivel junior.
- En CRUD sencillo, validar solo lo necesario (requerido, tipo, longitud o peso) y luego guardar/actualizar/eliminar sin logica extra.
- Si el requerimiento incluye calculos o reglas de negocio, agregar esa logica, pero sin complejidad innecesaria.
- Para consultas especificas fuera de CRUD basico, usar funciones simples con `axios` y `try/catch` (sin abstracciones extras).
- Evitar `continue`; usar `if/else` claro cuando haya que condicionar.
- No anticipar requerimientos futuros: si hoy solo piden guardar, solo guardar.

### 9.1) Ejemplo practico en store

Si el requerimiento dice "guardar informacion y adjuntos":

1. Validar campos basicos requeridos.
2. Crear registro principal.
3. Guardar adjuntos (si existen) en ruta acordada.
4. Retornar `back()->with('success', ...)`.

No incluir envio real, historial adicional, filtros complejos o logica extra hasta que se pida explicitamente.

### 9.2) Ejemplo practico en consulta auxiliar con axios

Cuando se necesite una ruta auxiliar fuera de `store/update/destroy`, usar formato simple como este:

```ts
const getMembers = async () => {
    try {
        const response = await axios.get(route("email-notifications.members", form));
    } catch (e) {
        console.error(e);
    }
};
```

## 10) Regla de orden en vistas Vue

Aplicar siempre esta regla al crear o editar vistas Vue en `<script setup>`:

- Agrupar por secciones del mismo tipo (no mezclar bloques).
- Mantener juntas: variables/refs, `useForm`, `computed`, funciones, `watch`, y lifecycle (`onMounted`, etc.).
- El orden entre secciones puede variar libremente, pero cada tipo debe quedar junto en su propio bloque.
- Se permite usar comentarios de seccion para identificar claramente cada bloque.

Divisores sugeridos:

- `/* ====================== Props ====================== */`
- `/* ====================== Variables ====================== */`
- `/* ====================== useForm ====================== */`
- `/* ====================== Computed ====================== */`
- `/* ====================== Funciones ====================== */`
- `/* ====================== Watchers ====================== */`
- `/* ====================== Lifecycle ====================== */`
