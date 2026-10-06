# Session Context — VillaDonq V3

**Fecha:** 2026-09-07
**Stack:** Laravel 10 + Inertia.js + Svelte 4 + Vite + Tailwind CSS
**DB:** MySQL

---

## Sesión 2026-09-07 — Planes de Evaluación: modal compartido + nombre autogenerado en servidor

### Objetivo
- Refactorizar `EvaluationPlanCreateModal.svelte` para soportar modo admin (`PlanesEvaluacion`) y profesor (`MisPlanes`) con borradores/envío/edición, reemplazando el formulario propio de `MisPlanes`.
- Eliminar el campo `name` del formulario; el nombre del plan se genera en el servidor.

### `app/Services/EvaluationPlanService.php`
- **Nuevo `buildPlanName(array $data)`**: arma `"{Materia} {Yy}-{Yy} {Curso} {Momento_label} {Secciones | 'Todas las secciones'}"` resolviendo `Matter`, `SchoolLapse` (Carbon start/end → años), `Course`, `Lapse` (`momentLabel`), `Section::whereIn()->pluck('name')->orderBy('id')`. Se aplica en `createPlan` (ramas multi-sección y única) y `updatePlan` (ramas clone y única) — se eliminaron los 4 `'name' => $data['name'],`.
- **Fix** "Column 'id' in field list is ambiguous": la normalización de `$data['section_id'] = 'all'` usaba `pluck('id')` y fallaba (join con `course_sections`); ahora `pluck('sections.id')` en `createPlan` y `updatePlan`.

### `app/Http/Controllers/EvaluationPlanController.php`
- `myPlans`: pasa a `MisPlanes` `data.plans/matters/courses/sections/school_lapses` + `filters`.
- `canEdit` ahora incluye `draft` (antes solo pending/rejected) para editor de borradores.

### `resources/js/components/EvaluationPlanCreateModal.svelte`
- Reescrito: usos admin y teacher; sin campo `name` en `useForm`; `openForEdit(plan)` prefill desde el plan (units→topics, section_id array, rasgos_points, status draft/pending); footer teacher = "Guardar borrador" / "Guardar y enviar"; admin = "Crear".
- **IMPORTANTE**: Svelte 4 expone las funciones a `bind:this` del padre SOLO si llevan `export`. `open()` y `openForEdit()` pasaron a `export function open()/openForEdit()`. Sintoma del bug: `Uncaught TypeError: planModal?.openForEdit is not a function` en consola y el modal de edición nunca se abría (el de crear sí, porque se abría con el trigger propio del componente en admin).

### `resources/js/Pages/Dashboard/MisPlanes.svelte`
- Reemplazado el formulario propio por `<EvaluationPlanCreateModal mode="teacher" renderTriggerButton={false} bind:this={planModal}>`.
- Eliminados: bloque reactivo de auto-nombre, `getAutoPlanName()`, `normalizeSectionSelection()`, `$form.name`. `canEdit` incluye `"draft"`.

### Verificación end-to-end (UI real)
- **Admin**: planes 17 y 18 creados `Biología 25-26 5to Año 1er Momento Todas las secciones` (status=approved, user=3, matter=11, course=1, sections 1 y 2, approved_by=1) vía POST `/dashboard/planes-evaluacion` [302].
- **Profesor (Durán, user 3)**: draft plan 19 `Biología 25-26 5to Año 1er Momento A` POST `/mis-planes`; edit PUT `/mis-planes/19` mantiene draft con items; "Guardar y enviar" PUT → status=pending (items se recrean, id item 37→38, updated_at avanza).
- Build `vite build` OK (warning de chunk >500kB pre-existente).

### Notas
- Botones del toolbar de `Table.svelte` son solo ícono (sin texto) — para clics programáticos buscar `iconify-icon` con `ic:baseline-edit`/`mdi:eye`/`material-symbols:delete-outline`.
- Datos de prueba en BD: planes 12/13 (Arte y Patrimonio), 17/18 (Biología approved), 19 (Biología pending).

---

# Sesión anterior — VillaDonq V2

**Fecha:** 2026-05-16
**Stack:** Laravel 10 + Inertia.js + Svelte 4 + Vite + Tailwind CSS
**DB:** MySQL (SQLite para tests)

---

## Cambios realizados en esta sesión

### 1. Módulo de Pagos — Eliminar y Actualizar

#### `app/Services/BalanceService.php`
- **Fix `revertStudentBalance()`:** Agrupa `BalancePayment` por `balance_student_id`, revierte todos los montos primero, recalcula statuses una sola vez, y hace un solo `save()` por balance.

#### `app/Services/PaymentService.php`
- **`delete()` → soft delete:** Cambia `status = 0`, guarda `deleted_by`, ya NO hace `$payment->delete()` (hard delete)
- **`update($id, $data)` → nuevo método:** Revuelve balances del pago existente (soft delete: `status = 0`, `deleted_by = usuario`), luego crea un nuevo pago con los datos actualizados
- **`getAll()`:** Agregado `->where('status', '!=', 0)` para excluir pagos eliminados

#### `app/Http/Controllers/PaymentController.php`
- **`update()` → nuevo método:** Envuelve en transacción DB con rollback en caso de error

#### `routes/web.php`
- **PUT `/dashboard/pagos/{id}`** → `PaymentController@update`

---

### 2. Módulo de Estados de Cuenta — Backend

#### `app/Services/AccountStatementService.php` (nuevo)
- **`getAll($params)`:** Query de estudiantes activos con balances filtrados
- Calcula `total_debt` (suma de valores absolutos de campos negativos: inscription + 12 meses) y `total_income` (suma de `balance_payments.amount`)
- Filtros: `school_lapse_year`, `start_date`, `end_date`, `section_id`
- **`debt_filter`** (reemplaza `debt_status`): Mapea los valores del frontend:
  - `debtors` → `total_debt > 0`
  - `current_period` → balance en `SchoolLapse::where('status', 1)` con `total_debt > 0`
  - `previous_period` → balance en el `SchoolLapse` anterior cronológicamente al activo con `total_debt > 0`
  - `exempted` → `is_exempt == true`
  - `up_to_date` → `total_debt == 0`
- Ordenamiento: `debt`, `name`, `last_name`, `course`, `section` (asc/desc)
- Paginación manual con `LengthAwarePaginator` (calcula deuda de TODOS los estudiantes primero, luego ordena y pagina — Opción A)

#### `app/Http/Controllers/AccountStatementController.php` (nuevo)
- **`index()`:** Retorna `inertia('Dashboard/EstadosCuenta', ['data' => ...])`

#### `routes/web.php`
- **GET `/dashboard/estados-cuenta`** → `AccountStatementController@index`

---

## Estructura de datos clave

### Payments (soft delete via `status`)
- `status = 1` → activo, `status = 0` → eliminado
- `deleted_by` → usuario que eliminó
- `user_id` → usuario que creó

### BalanceStudent (deuda)
- Campos negativos = deuda: `inscription`, `january`..`december`
- Status fields: `*_status` → `BalanceStudentStatusEnum` (`pending`, `paid`, `debt`, `partially_paid`)
- Un balance por estudiante por periodo escolar (`school_lapse_id`)

### BalancePayment
- Registra qué pago cubrió qué porción de un balance (mes o inscripción)
- `is_inscription` = true/false

### Orden escolar de meses
`september → october → november → december → january → february → march → april → may → june → july → august`

---

## LSP warnings conocidos (no bloqueantes)
- `PaymentService.php` — `withQueryString()` undefined (es método de Laravel Paginator, funciona correctamente)
- `Student.php` — `Storage` y `Str` imports (pre-existent)

---

## Pendientes / Próximos pasos
- Frontend `Pagos.svelte` necesita fix en rutas de delete y edit (no se tocó)
- Tests del módulo de pagos (eliminar/actualizar)
- Tests del módulo de estados de cuenta

---

# Sesión 2026-08-30 — Módulo Horarios (admin)

## Nuevo módulo Horarios (admin-only, `/dashboard/horarios`)
Tablas de horario semanal por periodo + curso + sección, con receso y clases Lun–Vie.

### Migraciones
- `2026_08_30_000001_create_schedules_table.php`: `schedules` (school_lapse_id, course_id, section_id, recess_start 24h `HH:MM`, recess_duration_minutes, UNIQUE combo)
- `2026_08_30_000002_create_schedule_classes_table.php`: `schedule_classes` (schedule_id, day 1-5, start_time/end_time 24h `HH:MM:SS`, matter_id, teacher_id, order)

### Archivos
- `app/Models/Schedule.php`, `app/Models/ScheduleClass.php`
- `app/Services/ScheduleService.php`: `getIndexData`, `get`, `save` (borra y recrea las clases en transacción), `formatPeriod`
- `app/Http/Controllers/ScheduleController.php`: `index` (`Dashboard/Horarios`), `store` (`back()`)
- `routes/web.php`: GET+POST `/dashboard/horarios` en grupo admin
- `resources/js/Pages/Dashboard/Horarios.svelte`
- `resources/js/components/LeftNav.svelte`: link "Horarios" en `adminNavPages`

### Frontend (`Horarios.svelte`)
- Header: select Periodo, select Curso, pills de Sección, y Receso (hora 12h + AM/PM + duración min) que aplica a todos los días.
- 5 columnas (Lun–Vie) con clases. Cada clase: inicio/fin 12h, materia (select), profesor (filtrado por `matter_ids` de la materia).
- Conversión 12h ↔ 24h (helpers `to24String`/`from24String`).

### IMPORTANTE — Selects Svelte (bind:value tipo estricto)
En Svelte 4, un `<select bind:value>` SOLO muestra la opción seleccionada si el tipo del valor enlazado coincide con el `value` de las `<option>`:
- Opciones numéricas (`value={id}`, `value={h}`) → enlazar con NÚMERO.
- Opciones string (`"30"`, `"AM"`) → enlazar con STRING.

En esta página resolvimos: `selectedPeriod`/`selectedCourse`/`recess.hora`/`row.matter_id`/`row.teacher_id`/`row.start.hour`/`row.end.hour` usan números; `recess.minuto`/`ampm` y `row.*.minute` usan strings. Si mezclas tipos el select se ve vacío aunque el estado esté bien.

### Insertar clase entre/antes de otras (feature solicitado)
- `insertClass(day, index)`: inserta una fila vacía en `classes[day]` en el índice dado; si hay clase anterior, el inicio de la nueva = fin de la anterior.
- Botón "+" (hover) antes de cada clase (`opacity-0 group-hover:opacity-100`) que inserta en esa posición, más "Agregar clase" al final (append vía `addClass` = `insertClass(day, length)`).
- `order` en BD refleja la posición final tras guardar.

### Verificado en navegador
- Load de horario guardado repuebla el formulario completo (receso, horas, materia, profesor).
- Insertar antes de una clase existente y guardar persiste el nuevo orden (order=0,1,...).

---

# Sesión 2026-08-30 (parte 2) — Módulo Mi Horario (profesor, solo lectura)

## Nuevo módulo Mi Horario (teacher-only, `/dashboard/mi-horario`)
Versión de SOLO LECTURA del calendario semanal para profesores. No es un formulario:
- Sin filtros de curso/sección (un profesor no imparte en dos secciones a la vez).
- Sin campo de profesor (es el propio usuario autenticado).
- Muestra TODAS las clases que el `teacher_id` imparte en el periodo seleccionado,
  agrupadas por día (Lun–Vie), y cada tarjeta de clase muestra hora inicio/fin (12h),
  materia y su **curso + sección como texto de solo lectura** (ej. "5to Año · Sección A").
- Único filtro: selector de Periodo escolar (por defecto el `status = 1` activo).

### Archivos
- `app/Services/MyScheduleService.php`: `getIndexData(teacherId, lapse?)` — lista los
  `Schedule` del periodo que tengan clases del profesor, recorre sus `schedule_classes`
  (filtradas por `teacher_id`, con `matter`) y las agrupa por día con `course_name`/
  `section_name`. Omite el campo de profesor a propósito.
- `app/Http/Controllers/MyScheduleController.php`: `index` → `Dashboard/MiHorario`; solo
  usa `school_lapse_id`. Devuelve `data.periods / lapse_id / days / filters`.
- `resources/js/Pages/Dashboard/MiHorario.svelte`: calendario semanal read-only.
- `routes/web.php`: GET `/dashboard/mi-horario` en grupo `role:teacher`.
- `resources/js/components/LeftNav.svelte`: link "Mi Horario" en `teacherNavPages`.

### Gotcha
- No eliminar `formatPeriod` privado del service (sin él, `map()` lanza
  "Call to undefined method App\Services\MyScheduleService::formatPeriod").
- El encabezado de la vista de profesor es un layout propio (botón "Volver" + "MI HORARIO"
  + nombre del profesor), ajeno al DashboardLayout de admin.

---

# Sesión 2026-08-30 (parte 3) — Rejilla semanal en Horarios (admin) + fix actualización

## Vista de rejilla (grid) por defecto en `Dashboard/Horarios.svelte`
- `viewMode`: `"grid"` (default) o `"form"`. `showForm()`/`showGrid()` alternan.
- La rejilla se muestra por sección seleccionada: 5 columnas (Lun–Vie, cabecera colorida), cada
  una con las clases posicionadas con **`position: absolute`** según su hora de inicio/fin.
- **Escala de posicionamiento (patrón de plantilla previa):** `PX_PER_HOUR = 48`, `BASE_HOUR = 4`
  (`top = (hour + min/60 - 4) * 48`, `height = top(end) - top(start)`). Sin columna de horas;
  cada caja de clase muestra materia (negrita), profesor y su rango 12h pequeño
  (`formatTimeRange`). Colores pastel por materia (`MATTER_PASTELS`, hash `id % 10`).
- El receso se pinta como banda `position: absolute` (fondo ámbar, texto "Receso") en cada
  columna en su horario; las clases quedan encima (z-10 sobre z-0).
- Altura de columna calculada desde el fin más tardío de la semana (`dayHeight()`), mínimo 240px.
- Empty state: si no hay clases ni receso con duración (`hasGridContent()`).
- Helpers: `topOf`, `heightOf`, `recessEnd`, `classesFor`, `dayHeight`, `hasGridContent`,
  `matterColor`, `formatTimeRange`, `timeLabel`.
- Filtros (periodo/curso/sección) duplicados; cambiar sección recarga vía `reload()`.

## CRÍTICO — Fix: cambiar sección no actualizaba la rejilla
**Síntoma:** desde la rejilla, al hacer clic en otra sección la URL y el botón cambiaban pero la
rejilla seguía mostrando la sección anterior.
**Causa raíz:** `reload()` usaba `preserveState: true`; en este setup (@inertiajs/svelte) el prop
`data` de la página NO se reenvía al componente en visitas con `preserveState`, por lo que la
rejilla (y el form) quedaban con datos stale de la carga inicial.
**Solución:** quitar `preserveState: true` de `reload()` (se mantiene `preserveScroll: true`).
Cada cambio de periodo/curso/sección re-monta el componente con props frescas.
- **Comportamiento asociado:** al cambiar sección/periodo/curso (desde rejilla o form) se vuelve
  a la vista default (grid) y se descartan ediciones sin guardar del form. Documentado a propósito.
- **Verificado en navegador (admin Juandonquis):** grid default → Editar → form con datos reales
  de la DB → Ver vista → grid; A↔B actualiza correctamente (sección B = solo RECREO 8:43).

## Candidato crónico de testing
- Los datos de prueba de `schedules/schedule_classes` se modificaron con guardados de pruebas
  anteriores: sección A hoy tiene clases 7:00-8:00 (Arte, Lun) y 8:00-10:00 (Biología, Lun) y
  Biología 7:00-7:45 (Mar), receso 10:00; sección B sin clases (receso 8:43).

---



## 2026-08-30 - Rejilla horarios en componente reutilizable + lista de horas por materia
- Nuevo componente 
esources/js/Components/ScheduleWeekGrid.svelte: grid semanal de posicionamiento absoluto (5 columnas Lun-Vie, cajas absolute a 70px/hora con base 7am, banda de receso, pastel unico por materia via id, linea meta adaptable con teacher/section/course). Usado en: Horarios (admin), HorarioHijo (representante), MiHorario (profesor). Horarios/MiHorario/HorarioHijo ya no duplican el grid (eliminados helpers duplicados de Horarios.svelte).
- Colores de materia: MATTER_PASTELS ahora se indexa por id-1 (no modulo 10) para que cada materia tenga color unico; ampliado a 14 pasteles. Biologia(id11) y Matematica(id1) ya no colisionan.
- Bonus (Horarios admin, vista grid): bloque reactivo subjectHours calcula las horas semanales por materia (suma de duraciones de schedule.days via 	oMinutes), ordena descendente y muestra tabla Materias y horas semanales bajo el ScheduleWeekGrid (oculta si no hay clases). Formato Xh Ym.
- Verificado en navegador: listado correcto seccion A (Bio 2h45m, Mat 1h15m, Info 1h15m, Ing/Arte/Cast 1h) y oculto en seccion B (sin clases).

- Fix build en Linux: las importaciones del componente usaban ruta Components/ (mayuscula) pero el directorio git es lowercase 
esources/js/components/ (Windows case-insensitive lo toleraba; Linux no). Corregido a ../../components/ScheduleWeekGrid.svelte en Horarios/HorarioHijo/MiHorario.

## 2026-08-30 - Fix guardar plan de evaluacion
- Al crear/editar un plan de evaluacion, EvaluationPlanService::syncItems escribe en evaluation_plan_items las columnas unit_name, unit_number, assessment_type, points, scheduled_date, description. El modelo y el frontend ya usaban el esquema rico de unidades/temas, pero la tabla real en MySQL solo tenia id/evaluation_plan_id/name/percentage/date/order. Causaba SQLSTATE[42S22] Unknown column unit_name.
- Solucion: migracion 2026_08_30_224015_add_unit_topic_columns_to_evaluation_plan_items_table agrega esas 6 columnas. Ejecutada con php artisan migrate.
- Verificado: crear plan via servicio ahora persiste items con esos campos (plan de prueba creado y borrado).

## 2026-08-30 - MisPlanes: filtros de periodo/momento + quitar columnas
- En resources/js/Pages/Dashboard/MisPlanes.svelte (plan de evaluacion del profesor) se quitaron las columnas "Periodo" y "Momento" de la tabla.
- Se anadieron dos filtros encima de la tabla: "Periodo escolar" (de data.school_lapses, solo UNA seleccion, sin opcion "Todos", por defecto el periodo activo) y "Momento" (de las lapses del periodo seleccionado, con "Todos").
- Backend: EvaluationPlanController@myPlans ahora acepta school_lapse_id y lapse_id via query; EvaluationPlanService@getPlansForTeacher(, ) filtra por ambos y, si no llega school_lapse_id, usa por defecto el periodo activo (status=1).
- Frontend: export let filters = {}; reactive selectedSchoolLapse (cae al activo), momentOptions de sus lapses; applyFilter hace router.get('/dashboard/mis-planes', {school_lapse_id, lapse_id}, {preserveState:true, replace:true}); al cambiar periodo resetea lapse_id.
- Verificado en navegador (profesor Duran, page 7): columna removida, filtro periodo sin "Todos" mostrando 2025-2026, filtro momento recarga lista (?school_lapse_id=1&lapse_id=1).
- FIX: al seleccionar un momento el valor del select se borraba tras el reload (data.plans si se actualizaba, pero el select quedaba en blanco). Causa raiz: preserveState:true en router.get (misma clase de gotcha que Horarios en AGENTS.md) impedia que el value controlado del <select> se re-renderizara con el prop filters actualizado. Solucion: quitar preserveState (re-monta con props frescas) y normalizar a string tanto el value del select como value={String(id)} de las opciones para evitar mismatch numero/string. Verificado en navegador.
- DEFAULT periodico por fecha: en MisPlanes el periodo por defecto ya no usa is_active, sino que resuelve el lapse cuya suma de momentos (start..end de sus lapses) contiene la fecha actual. Frontend: schoolLapseForToday() en MisPlanes.svelte (usa start/end de los momentos del school_lapse). Backend: EvaluationPlanService::currentSchoolLapseId() (private) usado como default cuando no llega school_lapse_id; fallback a status=1 y luego al mas reciente. Nota: el objeto school_lapse por si mismo no expone start/end en el payload, solo sus lapses[] (momentos) los tienen.

## 2026-08-30 - MisPlanes: filtro de Materia + modal Ver plan en unidades/temas
- Nuevo filtro "Materia" en MisPlanes.svelte (por defecto "Todas", opciones = data.matters, las materias que imparte el profesor). Backend: getPlansForTeacher filtra por matter_id; controller filtra de vuelta filters.matter_id en el prop filters. Verificado en navegador (?school_lapse_id=1&matter_id=11 filtra a Biologia).
- Modal "Ver plan" (read-only) de MisPlanes ahora renderiza la estructura unidades -> temas (en vez de la tabla plana de items). Usa plan.units (ya agrupado por formatPlan): por unidad muestra una tabla con Tema / Tipo de prueba / Descripcion / % / Pts / Fecha por tema, y Total items_total% al final. Verificado en navegador.

## 2026-08-30 - MisPlanes: recordatorio de dias de clase al elegir fecha de un tema
- Se revirtio el TopicDatePicker (picker de @svelte-plugins/datepicker con dias restringidos) al input date nativo de antes. Se elimino resources/js/components/TopicDatePicker.svelte y su import en MisPlanes.svelte.
- Se mantiene la misma verificacion backend (GET /dashboard/mis-planes/allowed-days -> EvaluationPlanService::getAllowedWeekdays, {restrict, allowedWeekdays}) pero ahora solo informativa: cuando el profesor elige scheduled_date de un tema y el horario tiene esa materia en la/las seccion(es) elegidas (matter_id + teacher_id), se muestra bajo el input un aviso amber: 'Recuerda: para esta materia en esta seccion das clases los dias lunes y martes.' (masa las secciones -> 'en todas las secciones'). No bloquea ninguna fecha.
- Helpers en MisPlanes.svelte: DAY_NAMES (1=lunes..7=domingo), describeAllowedDays() arma lista 'lunes y martes'/'lunes, martes y miercoles', allowedSectionsPhrase() distingue 'en esta seccion' vs 'en todas las secciones'. Condicion: allowedWeekdays?.length && scheduled_date seteado. Fetch debounced 250ms (allowedTimer) reactivo a showFormModal/submitStatus/form subject + secciones.
- Verificado en navegador (profesor Duran, page 7): Biologia + 5to Anio + seccion A + fecha 2026-08-31 (lun) -> mensaje 'lunes y martes'; sin fecha -> sin mensaje; seccion B (sin Bio en horario) -> sin mensaje. Build vite OK, php -l OK en EvaluationPlanService/Controller/routes.

## 2026-08-30 - MisPlanes: tooltip flotante + input date sin escritura
- El recordatorio de dias de clase dejo de ser una linea inline bajo el input date: ahora es un tooltip flotante amber (bottom-full, sobre el input) que se abre al hacer click en el input de fecha y se cierra al hacer click fuera (window click listener + on:click|stopPropagation en el wrapper; onMount/onDestroy agregan/remueven el listener). Contenido: 'Recuerda: para esta materia en esta seccion das clases los dias lunes y martes.' (solo si allowedWeekdays?.length; sin requerir scheduled_date).
- El input date bloquea teclado: blockDateTyping previene toda key excepto Tab/Enter/Escape (y atajos Ctrl/Cmd/Alt), por lo que no se pueden tipear digitos/letras/Backspace/espacio ni pegar (on:paste preventDefault). La fecha solo se puede elegir desde el popup del calendario (Enter abre el popup). openTooltip se toglea por key unitIndex-topicIndex (toggleTooltip).
- Verificado en navegador (profesor Duran, page 7): click en input -> tooltip visible; click fuera -> se cierra; keydown '5'/'a'/Backspace/' '/digits => defaultPrevented true; Enter => no prevenido; paste => prevenido. Build vite OK.

## 2026-08-30 - MisPlanes: showPicker al hacer click + escritura normal restaurada
- El bloqueo de teclado del input de fecha se elimino (ya no hay blockDateTyping ni on:paste preventDefault): se puede escribir normal.
- El on:click del input ahora ademas del tooltip llama a e.currentTarget.showPicker() envuelto en try/catch: asi el clic sobre los numeros/segmentos abre de todas formas el popup nativo del calendario (en navegadores sin showPicker el comportamiento default persiste). El tooltip flotante amber sigue funcionando (click abre, click fuera cierra).
- Verificado en navegador (profesor Duran, page 7): keydown '5'/'a' y paste ya NO se previenen (escritura normal), tooltip sigue apareciendo al click con 'lunes y martes' para Bio/5to A/A, sin errores de consola nuevos.

## 2026-08-30 - MisPlanes: calendario tambien se activa con focus (tab)
- El on:click del input de fecha compartia el showPicker con el nuevo on:focus via helper openCalendar(el), con guard lastShowPickerAt (300ms) para que focus+click del mismo gesto no abra el calendario dos veces.
- Verificado en navegador (page 7, spy sobre input.showPicker): solo focus => 1 llamada; focus+click juntos => 1 llamada (guard); click solo (ya enfocado) => 1 llamada. El tooltip sigue togleandose por click.

## 2026-08-30 - PlanesEvaluacion (admin): buscador + filtros Periodo/Momento/Anio/Seccion, quitar Materia/Profesor
- Frontend resources/js/Pages/Dashboard/PlanesEvaluacion.svelte: se quitaron los select de Materia y Profesor de la barra de filtros. El buscador ahora es el componente reutilizable Search.svelte (barra fixed top-right, igual que en Pagos) y los select "Periodo escolar" (de data.school_lapses, sin "Todos", default activo por fecha = mismo schoolLapseForToday() que MisPlanes), "Momento" (lapses del periodo seleccionado, con "Todos", se resetea al cambiar periodo), "Anio" (course_id) y "Seccion" (section_id), ambos "Todos".
- Search.svelte gano un prop opcional extraSearchParams (default {}, backward-compatible) que se fusiona en su router.get de busqueda debounced, para que tipear en search NO pierda los selects aplicados. BuildParams en PlanesEvaluacion arma todos los params de los selects (status/school_lapse_id/lapse_id/course_id/section_id + search) y resetea lapse_id al cambiar school_lapse_id. applyFilter(key,value) hace router.get preserveState+replace y conserva filters.search.
- Backend EvaluationPlanController@index: data ahora trae school_lapses (getSchoolLapses), courses (getCourses), sections (getSections); mantiene statuses; removio matters/teachers del payload (el frontend ya no los usa). filters prop incluye search/school_lapse_id/lapse_id/course_id/section_id (+ materia/teacher por retrocompat).
- EvaluationPlanService::getPlansForAdmin ahora filtra por status/school_lapse_id/lapse_id/course_id/section_id/materia/teacher y busca por search (name/description, y orWhereHas en matter/course/section/schoolLapse y CONCAT(name, last_name) en teacher).
- Celda "Plan" de la tabla restylizada como la de MisPlanes: nombre en <b class="text-gray-700"> + descripcion truncada gris debajo.
- Verificado en navegador (admin de prueba creado y eliminado): Search.svelte + filtros. Tipear 'Patrimonio' en Search -> URL ?school_lapse_id=1&search=Patrimonio&page=1 (extraSearchParams conserva el periodo); cambiar Seccion a A -> URL ?search=Patrimonio&school_lapse_id=1&section_id=1 (applyFilter conserva search) y la tabla queda solo con el plan de seccion A. Backend via tinker: search 'Arte'/'patrimonio'=2, course_id=1 =>2, section_id=1 =>1, lapse_id=1 =>2; school_lapses/courses/sections no vacios. Build vite OK (solo warnings pre-existentes), php -l OK en service+controller, phpunit 2/2 OK.

## 2026-08-31 - PlanesEvaluacion (admin): swipe aprobar/rechazar en cadena + cierre en ultimo + animacion de transicion
- **Objetivo:** al aprobar/rechazar un plan pendiente con el filtro Estado en "Pendiente", auto-avanzar al siguiente plan pendiente en el mismo modal, sin click extra; al aprobar el ultimo, cerrar el modal.
- **Backend (EvaluationPlanController):** `approve()`/`reject()` ahora llaman a `redirectBackToPlans($request, $message)` que lee los filtros (status/search/school_lapse_id/lapse_id/course_id/section_id/matter_id/teacher_id), redirige a `/dashboard/planes-evaluacion?{filters}` con `->with('open_plan', $request->input('next_plan'))` (flash one-time). `index()` agrega `filters.open_plan => session()->pull('open_plan')`.
- **Clave (server-driven, NO onMount):** Inertia conserva la instancia del componente a traves del 302 tras `router.post`, por lo que `onMount` NO se re-ejecuta. El avance lo dispara un bloque reactivo `$: if (filters.open_plan && currentPlanId !== Number(filters.open_plan))` que busca el plan en `data.plans` y llama `openPlanFor(target)`.
- **Frontend (PlanesEvaluacion.svelte):** state `pendingQueue/currentPlanId/currentQueueIndex`; `openPlanFor()` reconstruye la cola desde `data.plans.filter(pending)`, setea plan/indice, resetea reject y abre el modal. Tanto el click de fila (`SelectableRow on:select`) como el boton "Ver plan" pasan por `openPlanFor` (bug original: abrir por fila dejaba `pendingQueue` vacia -> `next_plan` iba null y no avanzaba). `nextPlanId(id)` devuelve el proximo pendiente de la cola solo si `effectiveStatus==='pending'`, sino null; el POST de aprobar/rechazar envia `next_plan` y, cuando es null (ultimo), `onSuccess` pone `showModal=false` (el componente Modal se conserva montado pero queda `opacity-0 pointer-events-none`).
- **Contador + boton manual:** "Plan X de Y (pendiente)" (solo si `pendingQueue.length > 1`) y boton "Siguiente >" navega la cola cliente-side (advanceToNextPlan/inline on:click). Verificado que el boton funciona (handler se ejecuta, idx/len correctos).
- **Animacion de transicion:** el contenido del plan (heading via PlanUnitsView + botones de accion) se envolvio en `{#key plan.id}` con `in:fly={{y:10,duration:180}}` / `out:fade={{duration:120}}` (import { fade, fly } from 'svelte/transition'), para que al cambiar de plan se reproduzca un breve slide-in que indica al usuario que paso a OTRO plan. Agregada rama `{:else if plan.status === 'rejected'}` (boton "Aprobar" para re-aprobar) que antes no existia.
- **Verificado en navegador (admin de prueba + planes TEMP-SWIPE 14/15/16):** aprobar en cadena 5->4->3->2->1->0 planes con contador decreciendo y avance automatico; POST aprobar con `next_plan:15` correcto (req body); rechazar plan 14 con `admin_note` + `next_plan:15` tambien funciona y avanza; al aprobar el ultimo, el modal queda `opacity-0 pointer-events-none` (cerrado); boton "Siguiente >" avanza "Plan 1 de 4" -> "Plan 2 de 4". Build vite OK (solo warnings pre-existentes).
- **Limpieza:** eliminados los planes temporales 14/15/16 (sin items) y el usuario admin de prueba `_test_admin@example.com` (id 11, sin referencias pendientes). Quedan solo los planes reales 12 y 13 (status pending, sin notas).

## 2026-08-31 - Plantilla Excel descargable (Matricula, Profesores, Personal)
- Nueva dependencia: phpoffice/phpspreadsheet (composer).
- Nuevo app/Services/ExcelTemplateService.php con metodos student()/teacher()/user(): generan .xlsx (Xlsx writer) con fila 1 de encabezados estilizados (negrita, fondo #176B6B, auto-width, freezePane A2) y una fila de ejemplo con datos ficticios. Retornan StreamedResponse (response()->streamDownload). Columnas espejan los FormRequest de creacion (estudiante ~37 cols con datos del 2do representante y exoneracion; profesor y personal 7 cols; "Ano escolar"/"Seccion" por nombre, "Materias" separadas por coma, "Es administrador (0/1)").
- Controladores: StudentController@downloadTemplate, TeacherController@downloadTemplate, UserController@downloadTemplate (delegan en el servicio).
- Rutas GET (grupo administrator): /dashboard/matricula/plantilla (ANTES de /matricula/{id}), /dashboard/profesores/plantilla, /dashboard/personal/plantilla.
- Frontend: boton "Descargar plantilla" (anchor estilizado secundario con icono download) en el header de Matricula.svelte, Profesores.svelte y Personal.svelte; descarga nativa del navegador (no usa Inertia).
- Verificado: php artisan route:list muestra las 3 rutas; el servicio genera .xlsx validos (IOFactory lee A1/A2 correctos); vendor/bin/phpunit OK (2 tests); yarn run build OK.

## 2026-08-31 - Botones corregidos + importacion de datos (Matricula, Profesores, Personal)
- FIX layout: nueva clase CSS `.toolbar-secondary` (pill outline #17223B, margin-top:17px igual que .animated-button) para alinear verticalmente los botones secundarios con el boton principal. Personal.svelte: se quito el `flex` del div `mx-auto` que envolvia tambien a la <Table> (causaba 2 columnas); ahora hay una fila de botones propia (justify-end) y la tabla queda debajo. En los 3 modulos el orden es [Importar] [Descargar plantilla] [Nuevo...] alineados a la derecha.
- IMPORT: ExcelTemplateService::readRows($path) lee la 1a hoja, normaliza encabezados (trim+BOM+lowercase) y devuelve [['row'=>n,'data'=>[...]]] omitiendo filas vacias.
- Servicios con import* ($rows): StudentService::importStudents (resuelve curso/seccion por nombre via Course/CourseSection, reutiliza createUser/createRepresentative/createStudent; a createUser se le agrego param bool $notify=true para que el import pase false y NO envie welcome mail; al final dispara event(new StudentCreated) para los side-effects TakeQuota/GenerateInscription/GenerateBalance/GenerateSchoolCharge). TeacherService::importTeachers (materias por nombre separadas por coma; materia inexistente = error de fila). UserService::importUsers (admin, is_admin 0/1). Todos omiten filas invalidas y reportan ['created'=>n,'errors'=>[['row','message']]].
- Controladores @import validan file (required|file|mimes:xlsx|max:20480), parsean y back()->with('import_summary', $result). Rutas POST /dashboard/{matricula|profesores|personal}/importar. HandleInertiaRequests::share() expone prop 'flash.import' (session pull).
- Frontend: nuevo componente ImportResultModal.svelte (resumen creados + errores por fila). Cada pagina tiene input file hidden + boton Importar (useForm con file, post, lee $page.props.flash?.import en onSuccess).
- Verificado: route:list (3 rutas POST), smoke dentro de transaccion rollback (readRows parsea plantillas, import crea 1 en c/u y se revierte; filas malas se omiten y reportan), phpunit OK, yarn build OK.
- FIX import frontend: el handler usaba importForm.clearErrors()/importForm.post() sobre el store devuelto por useForm (metodos inexistentes -> "clearErrors is not a function"). Corregido usando $importForm.* (el store de Svelte se accede con $). Verificado con yarn build.
- FIX import (definitivo): se reemplazo el flujo useForm.post + flash/redirect (que fallaba en navegador) por POST directo con axios + FormData (patron ya usado en Configuracion.svelte). Los controladores @import ahora responden JSON cuando $request->wantsJson() (Accept: application/json): {created, errors} en 200, {error} en 422. Los handlers en las 3 paginas leen la respuesta directamente y abren el ImportResultModal; ya no dependen de session flash ni de Inertia redirect. Se elimino el importForm de useForm. Verificado: build OK, endpoint JSON OK via test HTTP (200 + {created,errors}).
- FIX modal de importacion: el HTML renderizado del usuario mostraba estado inconsistente (icono verde + texto "y 6 filas con errores" + sin boton Ver detalle), lo cual es imposible con un solo render -> DOM obsoleto de un bundle viejo. Se cambio el <ImportResultModal bind:show> persistente por montaje condicional {#if showImportResult}<ImportResultModal .../>{/if} en las 3 paginas: cada vez que se abre se monta fresco con el summary actual, eliminando cualquier estado/DOM residual. Build OK (app-Irldi6Kp.js). Nota: el usuario debe hacer hard refresh (Ctrl+Shift+R) para cargar el bundle nuevo.
- FIX errores SQL crudos en import: los QueryException (ej. Duplicate entry '...' for key 'users_email_unique') llegaban como texto SQL al modal. Ahora los catch por fila en importStudents/importTeachers/importUsers usan ErrorTranslator::translate($e) (traduce 23000/1062 a mensajes en espanol). Ademas StudentService::resolveRepresentative pre-valida que el email del representante no exista en users antes de crearlo (mensaje: "El correo del representante '...' ya esta registrado en el sistema."). ErrorTranslator::duplicateMessage generalizo users_email_unique/users.email a "El correo electronico ya esta registrado en el sistema. Verifique los datos." Verificado: prevalidacion y fallback QueryException devuelven mensajes en espanol; phpunit OK.

## 2026-08-31 - Matricula: numero de estudiantes por grado en el filtro superior
- StudentController@index calcula student_count (activos, status != 0) por course_id con groupBy y lo adjunta a cada curso de data.courses.
- Matricula.svelte: el select de grado/año del filtro superior muestra "{course.name} ({course.student_count})" (ej. "1er Año (25)") y se amplio el contenedor de w-44 a w-56. Solo el filtro superior (no los selects de los modales). Verificado: smoke devuelve conteos correctos, build OK, phpunit OK.

## 2026-08-31 - FIX grace period en estatus de meses (pending vs debt)
- Bug: el mes actual nunca se marcaba como debt cuando day_of_monthly_payment + grace_period > 31 (30+5=35) porque se comparaba now()->day >= umbral (nunca true). 39 estudiantes debian agosto (august_status=debt) pero aparecian como "al dia" (sin deuda real) en Estados de Cuenta.
- Nuevo helper app/Support/PaymentDeadline.php::currentMonthPastDue($day, $grace): calcula por FECHA (Carbon::create(año, mes, day)->addDays(grace)) y maneja el cruce de fin de mes (day 30 + grace 5 -> vence 04-sep).
- Aplicado en los 4 lugares: BalanceService::determineMonthStatus (mes actual -> Debt solo si currentMonthPastDue, sino Pending; +param grace en 3 call-sites), listener ChangeDebtsForStudents::determineMonthStatus, comando balance:recalculate-status, y AccountStatementService::getHasRealDebtSql/checkHasRealDebt ($isPastDay). Ahora respeta grace: dentro de gracia = pending (no deudor), pasado = debt.
- Ejecutado php artisan balance:recalculate-status: 39 balances actualizados (august_status debt->pending). Filtros consistentes: Al dia=40, Deudores=0, Deudores periodo actual=0. phpunit OK. El BalanceBar (frontend) ya manejaba el overflow correctamente y colorea por status guardado, no requirio cambios.

## 2026-09-04 - Puntos de rasgos (0-10) en planes de evaluacion + validacion 100%
- Concepto: rasgos (conducta/puntualidad) valen hasta 10 puntos; cada punto = 5% (escala /20). El plan declara rasgos_points (entero 0-10, default 0). Validacion en creacion/edicion del plan: suma(porcentajes de temas) + rasgos*5 == 100 (±0.01). Antes solo se rechazaba >100.
- Migraciones: add rasgos_points a evaluation_plans; tablas student_plan_rasgos (draft por plan+estudiante) y student_grade_publication_rasgos (snapshot al publicar). Modelos nuevos StudentPlanRasgo y StudentGradePublicationRasgo; relaciones en EvaluationPlan y StudentGradePublication.
- EvaluationPlanService: createPlan/updatePlan guardan rasgos_points; formatPlan expone rasgos_points/rasgos_percentage/total_percentage.
- Requests Store/UpdateEvaluationPlanRequest (+heredado admin): regla rasgos 0-10 y check de 100% en withValidator (reemplaza el >100).
- Frontend plan: MisPlanes y EvaluationPlanCreateModal tienen select rasgos 0-10 + indicador en vivo (suma% + rasgos*5, rojo si !=100); PlanUnitsView muestra rasgos; columna % de lista muestra rasgos.
- Notas: getMatrixData incluye rasgos por estudiante y definitiva = sum(score*%/100) + rasgos del estudiante (null hasta calificar); computeDefinitive(items,scores,?rasgos=0); definitiveForStudent y publishedDefinitiveForStudent suman rasgos; saveGrades persiste rasgos (0..plan.rasgos_points); publishGrades copia rasgos al snapshot; canPublish considera rasgos. ReportService/RepresentativeService ya usan publishedDefinitiveForStudent -> boletas con rasgos.
- MisEstudiantes: columna "Rasgos (max)" por estudiante (0..max), input numerico; envio via $form.rasgos. Si plan.rasgos_points=0 no se muestra.
- Verificado: build OK, phpunit OK, smoke createPlan guarda rasgos_points y items suman 90 (rasgos=2).

## 2026-09-04 - Planes de evaluacion: secciones filtradas por grado/curso
- Bug: los formularios/filtros de Planes de Evaluacion mostraban TODAS las secciones (A-F) sin filtrar por el curso (grado) seleccionado. Las secciones pertenecen a un curso via course_sections (relacion Course::section()).
- Backend: EvaluationPlanService::getCourses() ahora hace Course::with('section') y cada curso trae 'sections' [{id,name}]. Validacion servidor en Store/UpdateEvaluationPlanRequest: cada section_id (si no es 'all') debe existir en course_sections del course_id; si no, error "Alguna(s) seccion(es) no pertenecen al año escolar seleccionado."
- Frontend:
  - MisPlanes (profesor): reactive courseSections desde data.courses; checkboxes de secciones muestran las del curso; al cambiar "Curso" se limpia section_id; normalizeSectionIdsForAllowed (caso 'all') y getSelectedSectionsLabel usan courseSections.
  - EvaluationPlanCreateModal (admin): igual (courseSections, checkboxes, normalizeSectionIds, reset secciones al cambiar Año; se oculta "Todas las secciones" si no hay secciones).
  - PlanesEvaluacion (filtro listado admin): el select "Seccion" muestra las secciones del "Año" seleccionado (filterCourseSections); cambiar "Año" resetea section_id a "".
- Verificado: getCourses devuelve secciones por curso; logica de pertenencia course_sections ok; build OK; phpunit OK.

## 2026-09-04 - Estado "Borrador" (draft) en planes de evaluacion + visibilidad estudiantes
- Nuevo estado draft en EvaluationPlanStatusEnum (label "Borrador"). "Publicado" == "aprobado" (sin flag nuevo): estudiantes/boletas ya filtran por status approved (RepresentativeService, ReportService) -> drafts/pendientes nunca visibles.
- createPlan ya respetaba $data['status'] ?? pending; updatePlan ahora tambien respeta status (draft/pending) en vez de forzar pending (3 lugares).
- Store/UpdateEvaluationPlanRequest: regla status sometimes|in:draft,pending (el profesor solo draft o pending).
- getPlansForTeacher (MisPlanes) incluye draft en el whereIn por defecto para que el profesor vea sus borradores. Admin (PlanesEvaluacion): el filtro "Estado" excluye draft (los borradores no entran a la cola del admin).
- MisPlanes: badge draft gris; form defaults status pending; fillFormToEdit setea status segun plan; dos botones de submit en el pie -> "Guardar borrador" (status draft) y "Enviar a aprobacion"/"Guardar y enviar" (status pending). Mensajes de exito diferenciados. Borradores se editan/eliminan (canEdit permite != approved).
- Nota: se descarto el endpoint/quick action por fila submitPlan (aprobacion posterior se hace desde el form). Verificado: smoke crea plan status=draft label=Borrador y aparece en la lista del profesor; build OK; phpunit OK.

## 2026-09-04 - FIX: filtro "Momento escolar" en MisEstudiantes volvia al 3 (tablas sin migrar)
- Sintoma reportado: al hacer click en otro momento en el select de "Momento escolar" (MisEstudiantes), siempre volvia a "3er Momento" aunque se seleccionase 1er/2do.
- Diagnostico (en navegador, user Dur�n con plan 13 aprobado): el request Inertia a /dashboard/mis-estudiantes?school_lapse_id=1&lapse_id=1&plan_id= recibia un 302 con Location a /dashboard/mis-estudiantes (URL limpia); el controller luego recibia la query VACIA (log temporal: QUERY_STRING=null), aplicaba default-por-fecha (hoy fuera de rango -> ultimo momento = 3), y la UI se resetaba al 3.
- Causa raiz real: 4 migraciones pendientes nunca aplicadas => al cargar un plan con rasgos, StudentGradeService::getMatrixData() hacia with('items','rasgos') y student_plan_rasgos NO existia => SQLSTATE 1146 => App\Exceptions\Handler (render, linea ~59) hacia redirect()->back() = el 302, y back() lleva al referer sin query.
- Fix: php artisan migrate -> se aplicaron: 2026_09_03_160000_create_student_grade_publications_tables, 2026_09_04_120723_add_rasgos_points_to_evaluation_plans_table, 2026_09_04_120724_create_student_plan_rasgos_table, 2026_09_04_120725_create_student_grade_publication_rasgos_table.
- Verificado en navegador (Dur�n): select 1er Momento se queda + carga plan "Arte y Patrimonio � 5to A�o � B" con matriz de los 13 estudiantes; select 2do Momento se queda (sin plan, mensaje "Aun no tienes planes").
- Nota: los profesores de prueba 3 (witexi4040@prorises.com) y 9 (genio@gmail.com) quedaron con password temporal 'test1234' (se dejo para debug del navegador; reinstalar/avisar).
## 2026-09-04 - FIX frontend: MisEstudiantes no actualizaba al PRIMER cambio de momento
- Sintoma: al entrar a Mis Estudiantes (default 3er Momento) y cambiar de momento por PRIMERA vez, la URL cambiaba (lapse_id) pero la pagina seguia igual; habia que cambiar de momento una segunda vez para que reaccionara.
- Causa: el select de momento usaba bind:value={selectedLapseId} + on:change={() => selectMoment(selectedLapseId)} (patron fragil): el handler lee la variable bound, cuyo orden de flush respecto al evento change es indeterminado -> en el primer cambio puede tomar el valor viejo (default) y el router.get no surte efecto visual.
- Fix: cambiar a value={selectedLapseId} + on:change={(e) => selectMoment(e.target.value)} (consistente con los selects de Periodo y Plan que ya usaban e.target.value), y dentro de selectMoment setear selectedLapseId = momentId explicitamente (ya no depende del bind). Se mantiene la logica userSelectedLapse para no pisar la seleccion manual con el default-por-fecha al recibir props del servidor.
- Verificado en navegador (Duran): entrada limpia -> 1er click en 1er Momento -> URL lapse_id=1 + plan Arte y Patrimonio + matriz 13 estudiantes cargados en un solo cambio.

## 2026-09-04 - Representante: nueva pagina "Materias del Estudiante" (desde Mis Hijos)
- Pedido: en MisHijos reemplazar el span "N materias" por un boton "Sus materias" que abre una pagina con filtro de curso (default el actual del hijo) + filtro de momento (lapses, default el momento actual), desglosando las materias del estudiante una por una con sus notas por examen (estilo fila de MisEstudiantes) y un boton "Ver plan" (a la derecha, arriba de las notas) que abre un modal con PlanUnitsView.
- Backend:
  - `RepresentativeService::materiasHijo(User, Student, array $filters = [])`: valida/implica seleccion de curso (default = course del estudiante) y lapse (default = currentLapse() mismo que MisHijos); `courses` = cursos DISTINTOS de los hijos del representante; `moments` = lapses del periodo activo con label; por materia del curso elegido busca el plan aprobado (course_id + periodo + lapse) y arma status/definitive/items con `publishedScoresForStudent` + `publishedDefinitiveForStudent` (solo notas PUBLICADAS, igual que MisHijos). Devuelve { student, courses, moments, filters{school_lapse_id,course_id,lapse_id}, subjects }.
  - `EvaluationPlanService::formatPlan` paso de private a public para reusarlo y poder alimentar PlanUnitsView desde el servicio del representante.
  - Nueva ruta GET `/dashboard/mis-hijos/{student}/materias` -> `RepresentativeController@materiasHijo` (abort_unless 404 si no es hijo del representante, misma validacion que horarioHijo).
- Frontend `resources/js/Pages/Dashboard/MateriasHijo.svelte`: pagina Dashboard/ con select Curso + select Momento (patron value={} + on:change con e.target.value para no caer en el bug de bind:value), reload via router.get preserveState+preserveScroll, tarjetas por materia (nombre + badge estado + boton "Ver plan"), tabla de ficha con columnas por examen (nombre + %) y columna "Definitiva ({momento})", Modal con PlanUnitsView. MisHijos.svelte: span reemplazado por link "Sus materias" (outline, junto a "Su horario").
- Gotcha (IMPORTANTE, como en Horarios): los datos derivados de `data` deben ser reactivos ($:), NO const al init. Primera version usaba `const subjects = data.subjects` y al cambiar de momento SI cambio la URL pero la lista quedo stale (solo reacciono al recargar). Fix: $: student/courses/moments/filters/subjects/activeMomentLabel.
- Verificado en navegador (repre de Pedro Francisco Ugarte Espinoza, msocratis2018@gmail.com / 12345678): boton Sus materias en MisHijos; pagina con default 3er Momento (mismo currentLapse que MisHijos); al elegir 1er Momento (lapse_id=1) Arte y Patrimonio muestra plan con 4 examenes (Tierra 20%, Jupiter 25%, Sol 15%, lejana 40%), notas "—" (no hay publicacion para ese plan) y Definitive "En curso"; "Ver plan" abre PlanUnitsView con unidades/temas/pts/fechas y total 100%; switch reactivo 1er<->2do actualiza URL + contenido sin recarga; modal cierra con Escape.

## 2026-09-04 - FIX: selects de Curso/Momento en MateriasHijo quedaban vacios
- Sintoma: los valores de los inputs (selects) de "Curso" y "Momento" no se actualizaban con la realidad y quedaban vacios aunque los options existieran (devtools mostraba value="" con options correctos).
- Causa raiz (SSR hydration): el componente usaba value={selectedCourseId} en el <select> (patron Svelte que NO actualiza el valor del select durante el hydration inicial / al recibir props nuevas con preserveState). Ademas selectedXxx eran variables derivadas $: que no se reflejaban en el DOM del select.
- Fix (3 partes):
  1) selectedCourseId/selectedLapseId pasan a ser let normales sincronizadas desde el servidor por un bloque $: if (!userTouched) (mismo patron userSelectedLapse de MisEstudiantes) -> al cargar/recibir props se setean, y NO se pisotea la seleccion manual mientras el request esta en vuelo.
  2) En el <select> se quito value={} y se puso selected={String(course.id) === selectedCourseId} en cada <option> (marca el option correcto; funciona en SSR + cliente) manteniendo on:change={(e)=>...} con e.target.value + userTouched = true.
  3) Estilo: value del select (ej. "5to Año", "3er Momento") visible en el DOM/snapshot.
- Verificado en navegador: entrada limpia -> Curso=1 ("5to Año") y Momento=3 ("3er Momento") seleccionados; cambio a 1er Momento (lapse_id=1) actualiza select a "1er Momento" + carga plan Arte con examenes; back/forward restaura el estado filtrado (lapse_id=1) con los selects correctos.

## 2026-09-06 - Copiar planes de evaluacion a otro momento/período (MisPlanes + PlanesEvaluacion)
- Objetivo: reutilizar un plan en un lapso/año siguiente sin recrearlo. Los selectores de Período/Momento ya dejaban ver planes de años anteriores (sin cambios); se agrega la accion "Copiar plan". La copia nace como **borrador** y **sin fechas** (scheduled_date vacio).
- Backend:
  - `CopyEvaluationPlanRequest` (nuevo): valida source_id + school_lapse_id + lapse_id (el momento debe pertenecer al período elegido) + section_id (acepta 'all'; si no, cada seccion debe existir y pertenecer al curso del plan origen) + name nullable + teacher_id nullable (debe ser profesor y dar la materia del plan origen). El contenido NO viaja en el request: se relee del plan origen en BD.
  - `EvaluationPlanService::copyPlan()`: toma `formatPlan($source)['units']`, pone scheduled_date/date en null, conserva matter_id/course_id/description/rasgos_points del origen, usa school_lapse_id/lapse_id/section_id[] del destino, name del request (fallback autogenerado en `buildCopyName()`: materia + período + curso + momento + seccion, o "nombre (copia)"), status=draft, y delega en `createPlan()` (reusa clonacion por varias secciones y registerCourseMatter).
  - `EvaluationPlanController::copy()`: si el usuario es teacher exige ser dueno del plan (source.user_id) y fuerza teacherId=auth, redirigiendo luego a `/dashboard/mis-planes?school_lapse_id=X&lapse_id=Y` para que el borrador quede visible; si es admin permite teacher_id (default el del origen) y hace back(). Errores via ErrorTranslator.
  - Rutas: POST `/dashboard/mis-planes/copiar` (grupo teacher) y POST `/dashboard/planes-evaluacion/copiar` (grupo admin) -> ambas a `copy`.
- Frontend:
  - `EvaluationPlanCopyModal.svelte` (nuevo, reutilizable): resumen del origen; selects Período destino (todas) + Momento destino (del período; default periodo/momento activos por fecha); Profesor solo si isAdmin (default teacher del plan); nombre editable precargado y recalculado al cambiar destino/salvo que el usuario lo haya editado; checkboxes de secciones del curso del plan (default la seccion del plan, opcion "Todas las secciones"); aviso "borrador y sin fechas". Envia router.post al `url` prop; errores via displayAlert.
  - MisPlanes (teacher): boton "Copiar plan" (icono mdi:content-copy) en la barra de acciones flotante de la tabla (`otherSelectOptions`), abierto para cualquier estado; render del modal con url `/dashboard/mis-planes/copiar`.
  - PlanesEvaluacion (admin): boton "Copiar plan" dentro del modal de vista del plan (encima de PlanUnitsView, todos los estados) porque al seleccionar fila se autoabre el modal (z-99999) que tapa la barra flotante (z-100); render del modal anidado con isAdmin url `/dashboard/planes-evaluacion/copiar`.
- Verificado: route:list muestra ambas rutas a EvaluationPlanController@copy; php -l OK; yarn build OK (warnings a11y preexistentes en filtros).

## 2026-09-07 - Admin puede rechazar planes ya APROBADOS (+ visibilidad de rechazados al profesor)
- Pedido: el administrador debe poder rechazar un plan aunque ya este aprobado. Diagnostico: el backend ya lo permitia (EvaluationPlanController@reject / EvaluationPlanService::reject sin restriccion de estado), pero la UI estaba rota: en PlanesEvaluacion el boton "Rechazar" de un plan approved activaba rejectMode y NO existia una rama que mostrara el editor de rechazo (textarea + Cancelar/Confirmar) para ese estado (solo se renderizaba dentro de plan.status === "pending"). Ademas, getPlansForTeacher ocultaba los rejected por defecto, asi que un plan aprobado rechazado desaparecia del profesor para siempre sin poder corregirlo.
- Cambios:
  1. PlanesEvaluacion.svelte: el editor de rechazo (textarea "Motivo del rechazo (opcional)" + Cancelar + Confirmar rechazo) ahora se muestra cuando `rejectMode && (status === "pending" || status === "approved")`. Al rechazar un plan APROBADO se muestra un aviso amber: "Este plan esta aprobado. Al rechazarlo dejara de mostrarse en boletas/notas publicadas; el profesor debera corregirlo y reenviarlo para volver a aprobarse." (se permiten rechazos aun con notas publicadas, por decision). Botones normales intactos: pending = Aprobar/Rechazar, approved = Rechazar (rejectPlanStart), rejected = Aprobar.
  2. EvaluationPlanService::getPlansForTeacher(): el whereIn por defecto ahora incluye Rejected (approved|pending|draft|rejected) para que el profesor vea el plan rechazado, lo corrija (queda pendiente) o lo elimine. (Antes MisPlanes no tenia filtro de estado y los rejected eran inalcanzables para el profesor.)
- Verificado: php -l OK; yarn build OK (warnings a11y preexistentes); prueba manual: admin filtro Estado=Aprobado -> Rechazar un plan aprobado -> editor con aviso -> Confirmar -> desaparece de aprobados; profesor lo ve con badge "Rechazado" y puede editarlo/re-enviarlo. Nota: se limpiaron marcadores de conflicto viejos (18a45eb) que habian quedado commiteados en opencode.md (lineas 291-329).

## 2026-09-07 - Cupos configurables por curso + bloqueo real en matricula
- Contexto previo: los cupos (tabla quotas, por course_id+school_lapse_id) solo se mantenían con listeners (TakeQuota/UpdateTakeQuota), sin UI ni validacion; assigned venia del seeder (500) y se copiaba con lapse:start-next. No existia bloqueo en el alta (remaining podia ir a negativo) ni liberacion al eliminar.
- Decisiones: cupo POR CURSO (grado, sin migracion); fuente de ocupacion = los CONTADORES del periodo activo (accepted/remaining), NO contar students.course_id (porque en la transicion de anio course_id no es por-periodo y daria falsos llenos); pantalla como seccion dentro de Configuracion; Matricula muestra ocupacion y bloquea; reEnroll SI queda limitado por el cupo del grado destino.
- Backend:
  - `app/Services/QuotaService.php` (nuevo): activeSchoolLapseId(); quotaFor(courseId, ?lapse) (default periodo status=1); quotasForPeriod() -> por curso {assigned, accepted, remaining(>=0), has_quota}; assertCapacity(courseId) -> excepcion traducible "El año escolar X alcanzó su cupo (A de M)" si accepted>=assigned (sin fila = se permite, legacy); occupy()/release() (ajustar accepted y recomputar remaining sin negativos); saveAssigned(course=>assigned) con firstOrCreate y remaining=assigned-accepted.
  - MainConfigController: index() agrega data.quotas (quotasForPeriod del periodo activo); nuevo updateQuotas(Request) valida assigned.* integer|min:0 y delega en saveAssigned. Ruta PUT /dashboard/configuracion/cupos.
  - StudentService: assertCapacity al crear (create) y re-activar eliminado (mismo create), al importar (createStudentFromRow), al cambiar de curso en update (solo si cambia), y en reEnroll (curso destino, tras la rama graduate). delete() libera cupo (release del curso del estudiante en el periodo activo) antes de status=0. Se mantienen los listeners TakeQuota/UpdateTakeQuota para ocupar/mover.
  - StudentController@index: adjunta a cada curso quota_assigned/quota_accepted/quota_remaining (ademas de student_count).
- Frontend:
  - Configuracion.svelte: nueva seccion "Cupos (capacidad por año escolar)" con periodo activo en el encabezado, tabla Curso | Cupo asignado (input) | Inscritos | Disponibles, fila roja si accepted > assigned, aviso global si hay sobrecupo y boton Guardar (router.put /dashboard/configuracion/cupos). Nota: Configuracion YA tenia el boton "Iniciar proximo periodo" (axios POST /periodo-escolar/iniciar-proximo) = disparador real de la transicion (mismo efecto que lapse:start-next).
  - Matricula.svelte: helpers courseById/courseFull/courseLabel; selects de curso (crear/editar, reinscribir y filtro superior) muestran "{Año} (inscritos/cupo)" y "· lleno"; guard cliente en handleSubmit (bloquea elegir curso lleno al crear o al mover, permite mantener el curso actual al editar); servidor sigue siendo fuente de verdad.
- Limites/notas: cursos sin fila de cupo se tratan como sin limite hasta configurarlos; la seccion edita el periodo ACTIVO (sin selector historico); en la transicion de anio hay que configurar las capacidades nuevas en Configuracion (accepted se reinicia a 0 con lapse:start-next/iniciar-proximo); los contadores legacy de cursos ya sobrecupo se muestran en rojo.
- Verificado: php -l OK en QuotaService/StudentService/MainConfigController/StudentController/routes; route:list muestra PUT configuracion/cupos; tinker: quotasForPeriod devuelve los 14 cursos con assigned=500 y accepted reales; yarn build OK.

## 2026-09-07 - Control de fechas de momentos (lapsos) en Configuracion + boton "cerrar y pasar al siguiente"
- Pedido: controlar cuando empieza/termina cada lapso desde Configuracion y poder cerrar el lapso vigente para dar el paso al siguiente. Sin cambios de esquema: el momento vigente se deduce por FECHA (lapso cuyo start..end contiene hoy), asi que editar lapses.start/end ya "mueve" el vigente en toda la app (MisPlanes/MisEstudiantes/representantes/reportes usan ese criterio).
- Decisiones (con usuario): cerrar = SOLO mover la ventana de fechas (no bloquea edicion de planes/notas); el boton opera sobre el momento que hoy esta vigente; se editan inicio y fin por momento.
- Backend:
  - `app/Services/LapseService.php` (nuevo): forPeriod(period) -> [{id,number,label,start,end}]; currentByDate(period) (misma regla que getActiveLapseId/currentLapse); saveMoments(id, rows) valida fechas Y-m-d, start<=end, dentro de period.start..end y SIN solaparse (end_n < start_{n+1}); closeAndAdvance(period) -> setea fin del vigente = hoy-1 e inicio del siguiente = hoy (si hay siguiente; si el vigente es el 3ro devuelve mensaje para usar "Iniciar proximo periodo"; casos borde: momento que empezo hoy, siguiente ya terminado).
  - MainConfigController: index() agrega data.lapses (LapseService::forPeriod del periodo activo); nuevos updateMoments() (PUT) y closeCurrentMoment() (POST) con try/catch y withErrors(['message'=>...]). IMPORTANTE: durante la implementacion se detecto que un edit previo habia dejado el controlador con index() duplicado + imports perdidos; se limpio (queda 1 index) y se re-agregaron imports (Request/PaymentMethod/AccountPayment/MainConfigService).
  - Rutas: PUT /dashboard/configuracion/momentos y POST /dashboard/configuracion/momentos/cerrar (grupo administrator).
- Frontend Configuracion.svelte: nueva seccion amplia "Momentos escolares (lapsos)" bajo Cupos: tabla Momento | Inicio | Fin | Estado, fila resaltada "Vigente hoy"; inputs date por momento (momentsForm), boton "Guardar fechas de momentos" (router.put rows [{id,start,end}]); boton "Cerrar {vigente} y pasar al {siguiente}" (router.post, con confirm) que aparece solo si hay siguiente; si el vigente es el 3ro se sugiere "Iniciar proximo periodo"; si hoy no cae en ningun momento se muestra aviso. Errores via displayAlert(errors.message).
- Verificado: php -l OK; route:list muestra PUT y POST; tinker con la BD local (periodo activo 2027-2028, hoy 2026-09-07 fuera de rango) -> forPeriod devuelve los 3 momentos y currentByDate=none (comportamiento correcto); yarn build OK (warnings preexistentes).

## 2026-09-14 — Importación fallida de Matrícula: tabla `failed_imports` + reintento
- Pedido: cuando un import Excel falla, guardar los registros en una tabla para editarlos reintento tras reintento.
- **Migration**: `2026_09_14_000000_create_failed_imports_table` con `row_number`, `data` (JSON), `error_message`.
- **Modelo**: `app/Models/FailedImport.php` con cast `data => 'array'`.
- **`app/Services/StudentService.php`**:
  - Extraído `mapRowData(array $raw): array` del mapping de `STUDENT_IMPORT_MAP` (antes inline en `createStudentFromRow`).
  - Extraído `createStudentFromMappedData(array $data, int $rowNumber)` con toda la lógica de validación/curso/sección/representante/cuota/creación/evento.
  - `createStudentFromRow` ahora es `mapRowData` → `createStudentFromMappedData`.
  - `importStudents()`: en el catch de cada fila, llama a `mapRowData` y crea un `FailedImport`.
  - Nuevo `retryImport(int $failedImportId)`: llama `createStudentFromMappedData` y elimina el `FailedImport` si éxito.
- **`app/Http/Controllers/StudentImportFailedController.php`** (nuevo):
  - `index()`: Inertia page con todos los `FailedImport` ordenados por created_at.
  - `update($id)`: recibe campos editables y actualiza `data` JSON.
  - `retry($id)`: llama `studentService.retryImport`, devuelve JSON success/fail.
  - `destroy($id)`: elimina el registro.
- **Rutas**: GET/PUT/POST DELETE `/dashboard/importaciones-fallidas` (grupo administrator). Import añadido en `web.php`.
- **Frontend**:
   - `resources/js/Pages/Dashboard/ImportacionesFallidas.svelte`: tabla con filas editables inline (nombre, CI, rep, curso/sección, etc.), botones "Editar/Guardar/Cancelar", "Reintentar", "Eliminar". Mensaje "No hay registros con errores" si la lista está vacía.
   - `resources/js/components/ImportResultModal.svelte`: enlace "Ver N registro(s) con error para editar" → `/dashboard/importaciones-fallidas`.
   - **`StudentService::createStudentFromMappedData`**: `rep_email` es campo requerido. Si el CI del estudiante ya existe (cualquier status), se actualiza en lugar de crear (status 0 → reactiva a 1, status 1 → actualiza datos). Si está graduado, lanza error. Esto permite re-importar estudiantes eliminados o actualizar los datos de los existentes.
   - **`StudentService::resolveRepresentative`**: cuando el email ya existe, busca el `Representative` asociado y lo reutiliza en vez de tirar error (permite re-intento correcto cuando un import previo creó el representante pero falló en crear el estudiante).
- **Frontend**: `Matricula.svelte` muestra un botón naranja "N errores" en el toolbar cuando hay `failed_imports` (enlaza a `/dashboard/importaciones-fallidas`). El conteo viene del controller via `data.failedImportsCount`.
- **`ImportacionesFallidas.svelte`**: Página unificada con filtro por tipo (Todos/Estudiantes/Profesores). Detecta el tipo de importación basado en la estructura de los datos (`student_name` → estudiante, `ci` → profesor). Los enlaces de API se adaptan al tipo detectado.
- **`TeacherService`**: Refactored con `mapRowData`, `createTeacherFromMappedData`, `importTeachers` almacena fallos en `FailedImport` (con `import_type = 'teacher'`), `retryImport` reintentando la creación/actualización. Maneja upsert cuando el CI ya existe.
- **`TeacherImportFailedController`**: Nuevo controlador para importaciones fallidas de profesores (index/update/retry/destroy).
- **`Personal.svelte`**: Botón verde "N errores" en toolbar cuando hay importaciones fallidas de profesores (enlaza a `/dashboard/importaciones-fallidas-profesores`).
- **`ImportResultModal.svelte`**: Acepta prop `importType` ('student' o 'teacher') para enlazar a la página correcta.
- **`UserController@index`**: Pasa `failedImportsCount` (profesores) al frontend.
- **Notas**: Tanto estudiantes como profesores comparten la misma tabla `failed_imports` con columna `import_type`.
- **Verificado**: php -l OK en StudentController/StudentService/routes; php artisan migrate OK; table `failed_imports` tiene columnas (id, row_number, data, error_message, timestamps); route:list muestra las 4 rutas; yarn build OK; vendor/bin/phpunit OK (2 tests).

---

## Sesión 2026-09-16 — FABs móviles y unificación de títulos de página

### Objetivo
- Botones flotantes (FAB) con ícono `mdi:plus` (sin texto) en esquina inferior derecha, solo visibles en móvil (<640px), en las páginas del Dashboard.
- Unificar todos los títulos de página al estilo `<h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden">` (visibles solo en móvil; en escritorio el header del layout ya muestra el nombre de página).

### FABs (sesión anterior, ya verificado)
- Aplicados a Matricula, Pagos, Personal, Profesores, Materias, EvaluationPlanCreateModal (trigger) y MisPlanes. Patrón: botón escritorio dentro de `<div class="hidden sm:flex">` (o `sm:block` en Pagos) + FAB `class="fixed-bottom-mobile fab sm:hidden bg-color1 text-white"` fuera del wrapper (helpers `fixed-bottom-mobile`/`.fab` en `resources/css/app.css:506-546`).

### Títulos aplicados en esta sesión
- **Unificados a `text-xl md:text-2xl font-bold text-color1 sm:hidden`**: `Materias.svelte`, `MisPlanes.svelte`, `PlanesEvaluacion.svelte`, `MisEstudiantes.svelte`, `MiHorario.svelte`, `HorarioHijo.svelte`, `MateriasHijo.svelte` (h3→h2), `MisHijos.svelte` (h3→h2), `Perfil.svelte` (h3→h2), `ImportacionesFallidas.svelte` (h1→h2).
- **Agregados donde no existían** (mismo estilo + `mb-3`): `Index.svelte` ("Panel de control"), `Matricula.svelte`, `Pagos.svelte`, `EstadosDeCuenta.svelte`, `Personal.svelte`, `Profesores.svelte`, `MisPagos.svelte`, `Configuracion.svelte`, `Horarios.svelte`.
- **Alineación escritorio**: con el h2 oculto en desktop, los botones de acción quedaban a la izquierda; se añadió `sm:ml-auto` al wrapper del botón en `Materias` y `MisPlanes`, y al wrapper del trigger en `EvaluationPlanCreateModal.svelte` (afecta solo a `PlanesEvaluacion`, que es el único con trigger visible; `MisPlanes` usa `renderTriggerButton={false}`).
- **No tocados (a propósito)**: `addPaymentMethod.svelte` (vacío), `MetodosDePago/Crear|Editar.svelte` (formas standalone con su propio h2 interno), `DetalleEstudiante.svelte` (parámetro dinámico, no es página de módulo), `Matricula2.svelte` (componente alterno no renderizado), `Pagos.svelte` slots `<h2>` de modales (no títulos).
- **Verificación UI real**: build `corepack yarn run build` OK (revertir `package.json` tras build, corepack añade `packageManager`). Comprobado en navegador a 390px y 1280px: admin PlanesEvaluacion/Index/Horarios y profesor MisPlanes → h2 block/none, FAB flex/none, wrapper hidden none/flex + `ml` auto ✓.
- **Responsive fila de temas en `EvaluationPlanCreateModal.svelte`**: la fila de tema pasó de `md:grid grid-cols-[5px_1.2fr_1.2fr_1fr_70px_63px_140px_32px]` (solo grid desde md) a `grid grid-cols-2 ... md:grid-cols-[...igual]` + `w-full md:w-auto` en el `<input type="date">`. Debajo de md cada tema se muestra en 2 columnas ("1. Tema" lado a lado, Tipo/Descripción, %/Pts, Fecha/Quitar); en md+ las 8 columnas quedan exactamente igual (verificado: `5px 103px 177px 86px 70px 63px 140px 32px` a 1280px, 2×~100.8px a 320px).

---

## Sesión 2026-09-19 — Tarjetas móviles en Pagos (agrupadas por fecha)

### Objetivo
- En la página **Pagos**, reemplazar la tabla por **tarjetas agrupadas por fecha de transacción** en móvil (<640px), manteniendo la tabla original en escritorio. Misma paginación server-side, estado "No hay datos", fecha legible para humanos, alcance solo Pagos (carrito más adelante), tarjeta sin expandir.

### Cambios
- **`resources/js/components/PaymentCard.svelte`** (NUEVO): tarjeta colapsada reutilizable con badge de concepto, método con punto de color, totales USD/Bs, referencia copiable, contador de estudiantes, botón "Eliminar pago" (solo admin), estilo atenuado + badge para `status === 0` (eliminado), accesible (Enter/Space abre detalles). Emite `onSelect(payment)` (click/keyboard) y `onDelete(payment)`.
- **`resources/js/Pages/Dashboard/Pagos.svelte`**:
  - Import `PaymentCard`; `formatFechaHumana(raw)` → `Intl.DateTimeFormat('es-VE')` ("12 de septiembre de 2026").
  - `paymentsByDate`: agrupa pagos de la página actual por `p.raw_date || p.date`, orden descendente; cada grupo muestra fecha + contador ("1 pago"/"N pagos") y "No hay datos" si vacío.
  - Sección móvil `<div class="sm:hidden space-y-4">` con las tarjetas (~L1226-1253); la `<Table>` pasó a `classes="hidden sm:block"`.
  - `fillFormToEdit(payment = null)`: ahora acepta el pago directamente (antes leía solo `selectedRow.data`, que las tarjetas nunca setean → el modal de solo lectura abría vacío). Con argumento usa el pago; sin argumento (click en la tabla) sigue usando `selectedRow.data`.

### Gotchas (verificados en UI real)
- **Svelte 4 NO mezcla `class="..."` en el root de un componente cuyo root usa `class={dinámico}`** (el `<section class={`w-full ${classes}`}>` de `Table.svelte` solo recibió `w-full s-...`, se perdió `hidden sm:block`, y la tabla era visible a 390px). Solución: usar la prop dedicada `classes` que ya concatena al root (`w-full hidden sm:block`).
- `stores/alertStore` NO exporta `copyToClipboard` (solo `displayAlert`); `PaymentCard` define su propio `copyToClipboard` con `navigator.clipboard` (patrón ya usado en Pagos:420 / MisPagos:289 / EstadosDeCuenta:26).
- El overlay de los `<dialog>` de `Modal.svelte` (opacity-0 pero en DOM) bloquea los clics de la herramienta sobre elementos de abajo → clics programáticos con `dispatchEvent` sobre el elemento.

### Verificación (build `corepack yarn run build` OK + revertir `package.json`)
- **390px**: `section.w-full` → `w-full hidden sm:block`, `display:none`; sección móvil `display:block` con grupos ["12 de septiembre de 2026","8 de septiembre de 2026","31 de agosto de 2026"] y 3 tarjetas; búsqueda "zzznadaexistente" → "No hay datos"; click en tarjeta → modal REGISTRO DE PAGO en solo lectura poblado (Fecha 2026-09-12, Ref 555001, $5.00, método Efectivo - Dolares disabled); botón "Eliminar pago" → confirm nativo (dismiss, sin mutación).
- **1422px**: tabla `display:block` con 3 filas; sección móvil `display:none`.

### Ajuste posterior (misma sesión): eliminar pago desde el modal
- **`PaymentCard.svelte`**: eliminado el botón "Eliminar pago" de la tarjeta (y las props `onDelete`/`isAdmin`).
- **`Pagos.svelte`**: botón rojo "Eliminar pago" (icono `material-symbols:delete-outline` + `bg-red text-white`, `justify-end`) al pie del modal de solo lectura, visible solo con `submitStatus === "Solo lectura" && $page.props.auth.is_admin` (~L985-1003). Llama `handleDelete(currentPayment?.id || $form.id, currentPayment)`.
- `let currentPayment = null`: se setea en `fillFormToEdit(selectedData)` y se resetea en `openRegistrarPago` (evita que el botón rojo aparezca al registrar un pago nuevo) y tras eliminar.
- `handleDelete(id, payment = null)`: la validación "ya eliminado" usa `(payment || selectedRow.data)?.status`; `onSuccess` ahora además cierra el modal (`showModal = false`).
- Verificado a 390px: tarjeta sin botón de eliminar; al abrir el modal de solo lectura aparece el botón rojo; click → confirm nativo (dismiss sin mutación, 3 pagos intactos). A 1422px: tabla intacta (3 filas), sección móvil oculta.

---

## Sesión 2026-09-19 — Matrícula: búsqueda global explícita y columna Año-Sección

### Objetivo
Al buscar en Matrícula, el backend devolvía estudiantes de **cualquier año/sección** (bug de precedencia SQL) pero los filtros de año/sección seguían visibles y seleccionados → confusión. Se pidió: durante una búsqueda, ocultar los filtros de año y sección, mostrar un indicador de "búsqueda en todos", y agregar una columna "Año-Sección" con el curso real de cada resultado. Decisión del usuario: búsqueda global **solo activos** (`status != 0`, sin graduados); columna **tras "Edad"**.

### Causa raíz (backend)
- `StudentService::getStudentsPerCourse()` (`app/Services/StudentService.php`): las `orWhere` de la búsqueda estaban **fuera de un closure**, así que por precedencia SQL (`AND` > `OR`) los `course_id`/`section_id` solo acotaban la primera rama (`search LIKE`); el resto (ci/name/last_name/CONCAT/representante) escapaba y traía estudiantes de todos los años.

### Cambios
- **`app/Services/StudentService.php` — `getStudentsPerCourse()`**: reescrito en 3 modos explícitos:
  1. Con `search` → global: `where('status','!=',0)` + todas las ramas LIKE (incl. representante) **agrupadas en `where(function($q){...})`**; ignora `course_id`/`section_id`/`graduate`.
  2. `graduate` → `graduate=1, status=0` (como antes).
  3. Sin search → `status, course_id, section_id` (como antes).
- **`resources/js/Pages/Dashboard/Matricula.svelte`**:
  - `$: isSearching = !!(data?.filters?.search && data.filters.search.trim());`
  - Filtro de año (`:1044-1076`): si `isSearching` se reemplaza por chip ámbar "Buscando en todos los años y secciones" (icono `mdi:magnify`); si no, `<select>` de años normal.
  - Filtro de sección: `filtersOptions={isSearching || data.filters.graduate ? {} : { section_id: sectionsOfThisYear }}` → desaparece en desktop y móvil durante la búsqueda.
  - Columna "Año-Sección" (solo `isSearching`): `<th>` tras "Edad" + `<td>{row.course_name} - {row.section_name}</td>` (los datos ya venían en `StudentResource`).
  - `extraSearchParams` se mantiene → al limpiar la búsqueda los filtros vuelven al estado previo (URL conserva `course_id`/`section_id`).

### Notas / gotchas
- El componente `Input.svelte` **ignora la prop `id`** (usa `id={label}` y `for="nombre"` hardcodeado). El selector `#filterYear` **no existe** en el DOM real.
- Con `graduate=1` + búsqueda, el backend ahora ignora `graduate` y devuelve activos globales (coherente con "solo activos").

### Verificación (build `corepack yarn run build` OK + revertir `package.json`, `php -l` OK)
- **Sin búsqueda** (1280px): `<select>` de años con options (quotas), botones de sección A/B/C, headers sin Año-Sección.
- **`?search=Perez`**: URL conserva `course_id=1&section_id=1&graduate=false` pero 6 resultados de **distintos** cursos (5to Año A/B, 2do Grado A, 3er Grado A...); select de años oculto, botones de sección desaparecen, chip "Buscando en todos los años y secciones" visible, columna Año-Sección con "5to Año - A" etc.
- **Limpiar búsqueda**: URL `search=` → `<select>` de años vuelve, secciones A/B/C vuelven, columna desaparece, 1 fila (5to Año A).
- **Móvil (~390px)**: igual — select de años oculto, chip visible, columna presente.

---

## Sesión 2026-09-19 — Pagos: modo "un estudiante" con totales editables (estilo MisPagos)

### Objetivo
En el modal de Pagos, replicar el patrón de MisPagos en el bloque móvil `<div class="md:hidden">` (y tabla desktop): con **un solo estudiante** ocultar los inputs USD/Bs por persona y dejar **solo los totales, editables**; con **varios estudiantes** mantener los inputs por persona y los totales de solo lectura, y si el usuario hace click en un total (readonly), **enfocar el input individual correspondiente que esté vacío**.

### Cambios
- **`resources/js/components/Input.svelte`**: agregado `on:click` al forwarding del `<input>` (antes solo `on:change`/`on:input`/`on:focus`) — el resto de usos no se ve afectado.
- **`resources/js/Pages/Dashboard/Pagos.svelte`**:
  - Reactivos: `showPerStudentAmounts = $form.students.length > 1 || submitStatus === "Solo lectura"` y `totalReadonly = $form.students.length > 1 || submitStatus === "Solo lectura"` (en el modal de solo lectura se **siguen** mostrando los inputs por estudiante, readonly, para no perder detalle; solo el flujo de edición los oculta).
  - `syncSingleStudentTotals(type, value)` (clon de MisPagos): con exactamente 1 estudiante, editar el total USD escribe el monto del estudiante 0 y recalcula Bs (y viceversa); vaciar el campo limpia todo.
  - `focusEmptyStudentAmount(type)`: con `length > 1` y no solo lectura, busca `input[data-student-amount="usd|bs"]` **visible** (`offsetParent !== null`) y vacío, y le da focus.
  - Bloque móvil: grid por estudiante envuelto en `{#if showPerStudentAmounts}`; los inputs USD/Bs (móvil **y** desktop) llevan `data-student-amount="usd|bs"`.
  - Tabla desktop `#selected_student`: las 2 celdas de monto envueltas en `{#if showPerStudentAmounts}` (en modo 1 estudiante la fila muestra nombre+remove+BalanceBar sin inputs).
  - Totales: `bind:value` reemplazado por `value={...}` + `on:input` → `syncSingleStudentTotals`; `readonly={totalReadonly}`; `on:focus` select-all cuando hay texto; `on:click` → `focusEmptyStudentAmount("usd"|"bs")`.

### Notas
- `data-student-amount` usado en vez de `id` (los mismos inputs se renderizan en móvil y desktop → los `id` se duplicarían); el helper filtra por visibilidad con `offsetParent`.
- `TextField`/`Input.svelte` NO es un `<dialog open>` real: `Modal.svelte` mantiene el `<dialog>` en DOM con clase opacity-0 (`.open` falso) — las consultas deben usar `getClientRects()`/`offsetParent`, no `dialog.open`.
- El botón "Registrar pago" del navbar desktop y el FAB móvil (`aria-label="Registrar pago"`, `fixed-bottom-mobile fab sm:hidden`, `offsetParent === null` por position:fixed) son dos elementos distintos.

### Verificación (build `corepack yarn run build` OK + revertir `package.json`)
- **Desktop 1280px | 1 estudiante (María Rodríguez)**: `input[data-student-amount]` = 0 en el DOM (ocultos); totales `readonly=false`; escribir USD 33,5 → Bs auto "28426.28" (tasa 848,55); escribir "170000" en Bs → Bs 1.700,00 → USD 2,00 (sync correcto, `parseBsInput`). Al pasar a 2 estudiantes los valores se conservan (10 → 8.485,46).
- **Desktop | 2 estudiantes (María+Juanito)**: 2 inputs USD + 2 Bs visibles y editables; totales `readonly=true`; click en total USD → focus en input USD vacío; click en total Bs → focus en input Bs vacío.
- **Móvil 390px | 1 estudiante**: sin grid por estudiante; totales editables; escribir total USD 10 → Bs 8485.46.
- **Móvil | 2 estudiantes**: grid por estudiante visible (4 inputs: María "10"/"8.485,46", Juanito vacíos); totales `readonly`; click total USD/Bs → enfoca el input individual vacío.

### Pruebas manuales para dejar pendientes
- Registrar pago real con 1 estudiante (total editable) y con 2 estudiantes (montos por persona) para confirmar el POST del formulario con los nuevos `value`+`on:input` (antes `bind:value`).

### Ajuste posterior (misma sesión): Concepto de pago antes que el buscador en móvil
- En móvil el orden del modal era: buscador → concepto. Se pidió que en teléfonos el **Concepto de pago** esté **antes** del buscador.
- El `<div>` del concepto vivía dentro del bloque `col-span-4` (columna derecha), que en DOM iba después del bloque de búsqueda (`col-span-8`). Se **extrajo del `col-span-4`** y se colocó como **primer hijo del `<form>`**; en md se fuerza la posición original con `md:col-span-4 md:col-start-9 md:row-start-1` (concepto), `md:col-start-1 md:row-start-1` (buscador) y `md:col-start-9 md:row-start-2` (resto de campos, conservan `grid grid-cols-2`).
- **Gotcha**: el grid auto-placement (sparse) coloca los items en orden de DOM; al mover el concepto primero, el buscador (sin `col-start`) saltaba a **row2** pese a quedar hueco en row1 (cursor no retrocede). Solución: `md:row-start-*` explícitos.
- Verificado: móvil 390px → Concepto (y178) antes que Buscar (y281); desktop 1280px → buscador izq (y78), concepto der arriba (y78, ancho 360), fechas der abajo (y192) — sin cambios visuales respecto al comportamiento previo.

### Ajuste posterior 2 (misma sesión): "+ Crear concepto" a la derecha del label
- Se pidió el botón "+ Crear concepto" en la **fila del label** "Concepto de pago", alineado a la derecha (extremo opuesto).
- Solución contenida en `Pagos.svelte` (sin tocar `Input.svelte`): el wrapper del concepto pasó a `relative` y el botón usa `absolute right-0 top-3 md:top-5` (coincide con el `mt-3 md:mt-5` del label de `Input`), manteniendo `{#if submitStatus !== "Solo lectura"}`. Se quitó el `mt-1` original y se ajustó a `px-1.5 py-0.5`.
- Verificado: móvil 390px → label (x20, top149) y botón (x262, top155, right=370, extremo derecho); desktop 1280px → label (top159-176) y botón (top156-176, right=1335= borde del wrapper de 404px de ancho), select justo debajo (top180) sin solapamiento.

### Ajuste posterior 3 (misma sesión): abreviaturas de meses en BalanceBar para pantallas <500px
- Pedido del usuario: en `BalanceBar.svelte`, en pantallas menores a 500px usar abreviaturas `En Fe Ma Ab My Jn Jl Ag Se Oc No Di` sin afectar funcionalidad.
- Implementado 100% en el componente (sin prop extra): mapa `shortLabels` (`sep:"Se"`, `oct:"Oc"`, `nov:"No"`, `dic:"Di"`, `ene:"En"`, `feb:"Fe"`, `mar:"Ma"`, `abr:"Ab"`, `may:"My"`, `jun:"Jn"`, `jul:"Jl"`, `ago:"Ag"`) + `isNarrow` con `window.matchMedia("(max-width: 500px)")` escuchando `change` (reactivo). El label del mes usa `{isNarrow ? shortLabels[spanishLabel] : spanishLabel}`.
- Verificado: móvil 390px → la grilla muestra `Se Oc No Di En Fe Ma Ab My Jn Jl Ag`; desktop 1280px → sin cambios (muestra las claves `sep`/`oct`… capitalizadas vía CSS, como antes). `getLastPaymentMonth`/tooltips/colores intactos.

### Ajuste posterior 4 (misma sesión): inputs de dinero en Pagos con formato de MisPagos (Bs con puntos/comas, escritura derecha→izquierda)
- Pedido: unificar los inputs de dinero de `Pagos.svelte` con los de `MisPagos` (sobre todo Bolívares: separador de miles `.` y decimales `,`, y que los dígitos se vayan ubicando de derecha a izquierda).
- Los inputs por estudiante (móvil y tabla) ya usaban `formatBsInput`/`parseBsInput`. Faltaba el **Total en Bolívares** (1 estudiante): era `type="number"` con valor crudo. Cambiado a `type="text"` + `value={formatBsInput($form.total_in_bs)}` (idéntico a `MisPagos.svelte:731-748`), manteniendo `on:input→syncSingleStudentTotals("bs")`, `on:focus` select-all y `on:click→focusEmptyStudentAmount`. El Total en Dólares sigue `type="number"` como en MisPagos.
- Verificado en el navegador (registro de pago, 1 estudiante): USD "5" → Bs "4.242,73" (punto+comma ✓); Bs "170000" → parsea 1700,00; display inicial "0,00". El reactive `$: $form.total_in_dolars, exchange()` (`Pagos.svelte:379`, igual en `MisPagos.svelte:124`) recalcula `total_in_bs = usd×tasa`, por lo que al teclear Bs el valor mostrado converge al redondeo en USD (p.ej. teclear "170000" → muestra "1.697,09" = 2,00×848,5458) — comportamiento compartido con MisPagos (USD es la fuente canónica), se dejó en paridad.

### Ajuste posterior 5 (misma sesión): totales de Pagos = estructura completa de MisPagos (multi-estudiante y solo lectura)
- Tras el ajuste 4 el usuario pidió copiar la **estructura de bloques de totales** de `MisPagos.svelte` tal cual: `{#if showPerStudentAmounts}` → `<Input type="hidden">` para `total_in_dolars`/`total_in_bs` + dos `<div class="col-span-1">` de solo lectura con `<span class="block font-medium text-sm">Total en USD:</span>` (valor `$ {formatBsInput(total_usd)}`) y `Total en VES:` (valor `Bs {formatBsInput(total_bs)}`); `{:else}` → USD `type="number"` editable + Bs `type="text" value={formatBsInput($form.total_in_bs)}` editable (sin los párrafos).
- En `Pagos.svelte` `showPerStudentAmounts = $form.students.length > 1 || submitStatus === "Solo lectura"`. Se **eliminaron** el reactive `totalReadonly` y la función `focusEmptyStudentAmount` (quedaron sin uso tras el cambio; el `on:click` de los inputs sin valor se cubre con select-all del `on:focus`). `syncSingleStudentTotals` sigue igual.
- Verificado en el navegador: **1 estudiante** → USD/Bs editables (Bs texto con "0,00"); **2 estudiantes** (María + Juanito, ambos con balances) → desaparecen los 2 inputs editables, aparecen los párrafos `Total en USD: $ 0.00` y `Total en VES: Bs 0,00` (`formatBsInput` aplicado), y quedan visibles los 4 inputs por estudiante `[data-student-amount]`. Build OK.
- **Gotcha detectado (bug pre-existente, ajeno a este cambio)**: al agregar como 2º estudiante uno con `balances: []` (p.ej. Fioriela antonieta, que no tiene state de cuenta) el clic en la fila NO lo añade y la UI se queda en 1 estudiante — el render del `BalanceBar` con `balances` vacío hace `balances[0].status` → TypeError y Svelte revierte la actualización del componente (en la UI de búsqueda el `#students-search-table` puede quedar con `hidden` aun teniendo filas). No se toca aquí; en pruebas de multi-estudiante usar siempre estudiantes con balances.

### Ajuste posterior 6 (misma sesión): hueco enorme entre "Concepto de pago" y el resto del bloque derecho (desktop)
- Pedido del usuario: en desktop, al seleccionar un estudiante en el modal de registro quedaba un espacio muy grande entre el select "Concepto de pago" y los inputs de abajo (F. de la transacción), sin afectar el layout móvil. Medido antes: select terminaba en y141 y F. de la transacción arrancaba en y461 (**~320px de hueco**).
- Causa: el `<form>` es `md:grid grid-cols-12`; el buscador vive en un bloque `md:col-start-1 md:row-start-1` y dentro de ÉL estaba la tabla desktop `#selected_student` (con el `BalanceBar`, ~330px de alto). Como la altura de una fila de grid es compartida por todas las columnas, la fila 1 se estiraba al alto de la tabla y el `md:row-start-2` del bloque derecho (Concepto está en `row-start-1`, resto de campos en `row-start-2`) quedaba empujado muy abajo.
- Solución en `Pagos.svelte` (solo desktop): se cerró el bloque del buscador justo después de las tarjetas móviles (`md:hidden`) y la tabla `#selected_student` se envolvió en un div propio **direct child del form** con `hidden md:block md:col-span-8 md:col-start-1 md:row-start-2 w-full` (la tabla conserva sus clases `hidden`/`md:block` según `$form.students.length` y `mt-5`). Con esto la fila 1 queda corta (buscador+concepto) y la fila 2 es la tabla (izq) en paralelo con el bloque derecho de campos; si la tabla es más alta, el sobrante cae DEBAJO de Observaciones, nunca entre concepto y F. de la transacción.
- **Móvil intacto**: fuera de `md:` el form es bloque y el nuevo wrapper tiene `hidden`, por lo que el flujo Concepto → Buscar → tarjetas → campos no cambia.
- Verificado: desktop 1280px → hueco concepto→F. de la transacción **23px** (antes 320px; la tabla está en fila 2 izq); móvil 390px → Concepto y89 → Buscar y143 → tarjeta María y193 → F. transacción y322, wrapper desktop `display:none`. Build OK.
- **Nota**: durante la edición un `</div>` de más rompió el parse de Svelte (`attempted to close an element that was not open`) — el cierre original del bloque de búsqueda (tras `</table>`) pasaba a ser el del nuevo wrapper, así que hubo que quitar el extra.

### Ajuste posterior 7 (misma sesión): abrir el modal de crear/registrar con la tecla N + tooltip en botones
- Pedido del usuario: en todas las páginas que tienen un botón de crear/registrar, la tecla **N** debe abrir el modal, y los botones (desktop + FAB móvil) deben mostrar `title="Aprieta la tecla N"` al hacer hover (texto acordado). Alcance: Pagos, Personal, Profesores, Materias, Matricula, Matricula2 y MisPlanes (no los modales de solo lectura tipo MisHijos/PlanesEvaluacion).
- **Diseño centralizado** (pedido del usuario, en vez de un handler por página): toda la lógica vive en `resources/js/components/Modal.svelte`. Props nuevas `keyShortcut` (ej. `"n"`) y `onKeyShortcut` (callback opcional). En `handleKeydown` (ya escuchaba keydown en `document` para Escape): si coincide la tecla (case-insensitive), sin `alt/ctrl/meta`, `event.target` NO es `INPUT/TEXTAREA/SELECT/contentEditable` y `!showModal` → `event.preventDefault()` y llama `onKeyShortcut()` o si no hay callback `showModal = true`.
- Las páginas solo pasan props al `<Modal>` correspondiente: `keyShortcut="n"` + `onKeyShortcut={openX}` (función de apertura existente, que hace su lógica previa: reset de form, pre-fill de curso/sección desde filtros, chequeo de permisos, foco al buscador del modal, etc.). No quedan `handleKeyN` ni `<svelte:window>` en ninguna página.
- `MisPlanes` no usa `<Modal>` directo: usa `EvaluationPlanCreateModal.svelte` (`bind:this={planModal}`, método `open()`). Ese componente ganó `export let keyShortcut = null;` y lo encadena a su `<Modal>` interno con `onKeyShortcut={open}`; la página solo pasa `keyShortcut="n"`.
- **Bug real reportado y corregido**: en Pagos, al disparar con "n" el modal abría y `openRegistrarPago()` enfoca el input de búsqueda del modal, pero como el handler del atajo NO hacía `preventDefault()`, el mismo keydown insertaba la "n" en ese input recién enfocado (el usuario lo veía al soltar la tecla). Solución: `event.preventDefault()` en Modal.svelte antes de invocar el atajo.
- Verificado en navegador (Pagos, 1280px y teclado físico): "N" desde body abre `REGISTRO DE PAGO` y enfoca el buscador del modal **sin teclear "n"**; repetir "N" con modal abierto no lo resetea; Escape sigue cerrando; con foco en un INPUT real la tecla se escribe y el modal NO abre. Build OK. (Un test con `dispatchEvent` sintético no sirve: `event.target` pasa a ser `document`, no el elemento enfocado.)

### 2026-09-27: MisEstudiantes — búsqueda por voz (B+C), resaltado de fila, y extracción de Asistencia a componente

**Contexto**: página de notas del profesor (`MisEstudiantes.svelte`). El 404 que se veía en DevTools sobre `/dashboard/mis-estudiantes` era **route cache obsoleto** (`php artisan route:clear` lo resolvió); las rutas siempre estuvieron registradas.

#### 1. Búsqueda por voz: robustez de `findVoiceStudent()`
- El indicador ▲/▼/↕ de las columnas no cambiaba al reordenar. **Causa raíz real (no era solo la mutación)**: dos cosas a la vez —
  1. `toggleSort` mutaba `sortState` en sitio (`sortState.direction = "desc"`), y **Svelte no dispara reactividad por mutación de propiedades de objeto**. Fix: reasignar (`sortState = { ...sortState, direction: ... }`).
  2. La plantilla llamaba `getSortIndicator("student")`, y esa función lee `sortState` **por dentro**. El compilador de Svelte solo registra como dependencia las variables referidas **sintácticamente** en la expresión del template, así que `sortState` nunca quedaba registrado y el indicador jamás se re-renderizaba (pasara o no la reasignación). Fix: `getSortIndicator(key, state)` recibiendo `sortState` **como argumento**, y los 3 call sites actualizados (`"student"`, `` `item_${item.id}` `` y `"definitive"`).
  - Verificado: Estudiante ▲→▼→▲ con el orden de filas invirtiéndose en cada clic, y al cambiar a un tema/Definitiva el indicador de Estudiante pasa a ↕ mientras el nuevo queda en ▲.
- B+C (peso de coincidencia exacta ×2 y umbral de ambigüedad 0.95 → 0.99) **no bastaba** para "faviana acosta": el micrófono transcribe "Faviana" como "Fabiana", así que la coincidencia exacta se perdía y el apelido pasaba a depender del fuzzy, empatando con muchos estudiantes. Fix: bonos por la parte más identificadora del nombre hispano, en `findVoiceStudent()`:
  - `nameComboBonus = 3` si coinciden nombre de pila + primer apellido,
  - `firstLastNameBonus = 2` si coincide el primer apellido (paterno),
  - `secondLastNameBonus = 1` si coincide el segundo apellido (materno),
  - `score = weightedExactScore + nameComboBonus + firstLastNameBonus + secondLastNameBonus + fuzzyScore`.
- Resuelto: "faviana acosta" → **Faviana Sofia Acosta Molina** (no ambiguo). Los nombres intermedios ya entran como tokens en `normalizeVoiceText`; no hizo falta una fase extra.
- El `<td>` del nombre del estudiante obtiene `bg-color4/10` cuando `pendingVoiceStudentId === student.id` (resaltado del estudiante enfocado por el dictado); el resto de clases del sticky/hover se conservan con template literal.

#### 2. Asistencia movida a `resources/js/components/AsistenciaMatrix.svelte`
- Todo lo de asistencia salió de `MisEstudiantes.svelte` (≈240 líneas de script + ≈190 de markup) al nuevo componente, que se invoca así:
  `<AsistenciaMatrix planId={data.matrix.plan.id} students={sortedStudents} />`. Props: `planId` (para los endpoints) y `students` (**el orden de sort del padre se inyecta**, para que la tabla de asistencia herede el sort de la tabla de notas). El estado (`attendanceSessions`, `attendanceData`, `newSessionDate`, `attendanceSaving`, `attendanceDirty`, `lastLoadedPlanId`) y todas las funciones (`loadAttendanceMatrix`, `createSession`, `deleteSession`, `toggleAttendance`, `toggleAllInSession`, `isAllPresent`, `formatDate`, `formatDayOfWeek`, `getAttendanceClass`, `saveAttendance`) viven ahora en el componente. Se eliminó del padre el import de `axios` (quedó sin uso) y el estado/bloques reactivos de asistencia.
- **Carga única por montaje**: al vivir en un `{#if}` el componente se monta/desmonta al cambiar de tab, así que el bloque reactivo `$: if (planId && planId !== lastLoadedPlanId)` carga **1 sola vez** al abrir el tab y también al cambiar de plan. Esto eliminó la doble petición `GET /dashboard/mis-estudiantes/asistencia/{planId}` que existía antes (el `on:click` del tab y el bloque reactivo disparaban ambos).
- **Dos trampas de reactividad de Svelte 4 encontradas al extraer** (el componente no re-renderizaba aunque el estado cambiara):
  - `{@const status = getSessionStatus(session.id, student.id)}` y `checked={isAllPresent(session.id)}` **esconden `attendanceData`**: el compilador solo registra dependencias **sintácticas**, no lee dentro de las funciones. Fix: el status se lee inline en el template (`{@const status = attendanceData[session.id]?.[student.id] || "absent"}`) y el "marcar todos" usa un mapa reactivo `$: allPresentBySession = buildAllPresentMap(attendanceData, attendanceSessions, students)` pasando `attendanceData` **como argumento** para que sí sea dependencia.
- **Bug pre-existente de colores (corregido)**: `getAttendanceClass()` usaba `bg-green-50`/`text-green-600`/`bg-orange-50`/`text-orange-600`, clases que **no existen** en este proyecto — `tailwind.config.cjs` sobreescribe `green` (`#88D498`) y `orange` (`#FFA552`) como colores planos, sin escala `-50`/`-600`. Resultado: celdas "presente"/"justificado" salían sin fondo ni color (transparentes) y solo se veía el icono. Fix: `bg-green/20 text-green` / `bg-orange/20 text-orange` / `bg-gray-50 text-gray-300` (`bg-gray-50` sí existía).
- **`attendanceDirty` al borrar sesión**: `deleteSession()` marcaba `dirty = true` aunque no quedara ninguna sesión, mostrando el botón "Guardar asistencia" sin nada que guardar. Fix: `attendanceDirty = attendanceSessions.length > 0`.
- Backend sin cambios: `AttendanceService` + `StudentGradeController` (`attendanceMatrix`, `createAttendanceSession`, `deleteAttendanceSession`, `saveAttendance`) y las rutas `asistencia/{planId}`, `asistencia/session`, `asistencia/session/{id}`, `asistencia/save`.
- Verificado en navegador (plan 1, 35 estudiantes): abrir tab = **1 sola** petición; crear sesión 200; marcar todos → 35 celdas verdes con check y checkbox marcado; ciclo ausente→presente→justificado→ausente con colores correctos; "Guardar asistencia" → `POST asistencia/save` **302** + recarga Inertia y **los datos persisten** tras recargar (34 presentes + 1 ausente); borrar sesión 200 → estado vacío. El 404 previo se resolvió con `php artisan route:clear`.
- Lección reutilizable: al extraer estado a un componente en Svelte, las reactividades derivadas deben referencias el estado **sintácticamente** (inline en el template o pasándolo como argumento a un `$:`), nunca solo a través de funciones auxiliares. Este mismo error existía ya en `MisEstudiantes.svelte` con `getSortIndicator` y se mantuvo en el componente nuevo hasta detectarlo con `getComputedStyle`/DOM en vez de a ojo.

### Limpieza: scripts de depuración fuera del repo
- Eliminados `check_user.php` y `check_admin.php` de la raíz. Eran scripts de depuración arrojados a mano (boot manual de Laravel + `echo` de datos de usuarios) que se habían colado en un commit (`d6f74fe "ni"`); `check_user.php` además tenía hardcodeada una cuenta real (`sales43581@bullbaby.com`). No forman parte de la app.

### 2026-09-28 — Banner de progreso de dictado: fix de conteo off-by-one + sonido de completado

#### Objetivo
- Corregir el banner de progreso que contaba mal las notas corregidas (siempre retrasado una actualización) y habilitar el sonido de fanfarria al completar el 100%.

#### Root cause del off-by-one
- `corregidoInfo` era una declaración `$:` reactiva en Svelte. Cuando `updateGrade()` mutaba `editable` y reasignaba `editable = { ...editable }`, el `$:` recomputaba **en un microtask posterior** al final del bloque síncrono actual. `showVoiceProgressBanner()` se invocaba **inmediatamente** después de `updateGrade()`, pero leía `corregidoInfo` que aún tenía los valores **anteriores** a la última corrección.
- Resultado: al corregir al primer estudiante de un tema, `corregidoInfo.counts[itemId]` era aún `0` → `if (corrected === 0) return` → banner no aparecía. Al corregir al segundo, `corregidoInfo` ya había procesado la primera → mostraba `1/35`. El caso "completo" nunca se alcanzaba porque siempre iba retrasado una corrección.

#### Fix
1. **Extraer `getCorregidoInfo()` como función regular** (no `$:`) en `MisEstudiantes.svelte`. Se llama directamente desde `showVoiceProgressBanner()` para obtener el conteo fresco en el mismo tick síncrono. El `$:` reactivo para el template (badges) sigue existiendo pero delega en esta misma función.
2. **Añadir `playSound("completo")`** dentro de `showVoiceProgressBanner()` cuando `corrected === total`. Antes el sonido estaba definido en `playSound()` pero nunca se invocaba.
3. **Añadir `playSound("casi")`** para el tipo `voiceProgressType === "casi"` (nota intermedia, >= 65%).

#### `playSound("casi")`
- Onda `triangle` con frecuencias 600→750 Hz, duración 0.2s, volumen 0.12.

#### Verificado
- Build sin errores (`corepack yarn run build`).
- Corregido 1 de 35 → banner muestra `corregido 1/35` inmediatamente.
- Corregido todos → banner muestra `¡Todos corregidos!` + suena fanfarria.
- Microfono se detiene automaticamente al corregir todos los estudiantes de un tema.

### 2026-09-28 — Formato de teléfonos en Matrícula para compatibilidad con wa.me

#### Objetivo
- Los campos de teléfono en `Matricula.svelte` ahora muestran el número con guiones (`XXX-XXX-XXXX`) para mejor legibilidad.
- Al generar enlaces `wa.me`, el código existente en `EstadosDeCuenta.svelte` ya stripa espacios/guiones (`/[ -]/g, ""`) y agrega el código de país `58` si no existe, produciendo el formato `58XXXXXXXXXX`.

#### Cambios
- `Input.svelte`: añadidos `inputmode` y `pattern` como props exportables (pasados al `<input>`).
- `Matricula.svelte`:
  - Nueva `formatPhoneNumber(value)`: limpia no-dígitos, formatea como `XXX-XXX-XXXX` (o menos si hay menos dígitos).
  - Nueva `formatPhone(fieldName)`: aplica `formatPhoneNumber` al campo del formulario.
  - Los 5 campos de teléfono (`student_phone_number`, `rep_phone_number`, `rep_phone_number2`, `second_rep_phone_number`, `second_rep_phone_number2`) tienen `inputmode="numeric"` y `on:input={() => formatPhone("campo")}`.
  - Se removió `pattern="[0-9]*"` porque el valor formateado contiene guiones.

#### Compatibilidad `wa.me`
- `EstadosDeCuenta.svelte` ya tiene la lógica de conversión:
  ```js
  let phoneNumber = student.representative.user.phone_number.replace(/[ -]/g, "");
  if (!phoneNumber || phoneNumber.length < 9) return;
  if (!phoneNumber.startsWith("+") && !phoneNumber.startsWith("58")) {
      phoneNumber = "58" + phoneNumber;
  }
  phoneNumber = phoneNumber.replace("+", "");
  ```
- `041-234-5678` → strip → `0412345678` → prepend `58` → `580412345678` → URL: `https://wa.me/580412345678?text=...` ✓

### 2026-09-28 — El personal administrador también puede registrar notas

#### Objetivo
Abrir la matriz de calificaciones (`/dashboard/mis-estudiantes`) al personal de administración, que hasta ahora solo podía usarla el profesor dueño de cada plan de evaluación.

#### Hallazgo clave
- **No existe tabla `teachers`.** Un profesor es un `users` con `type_user_id = 3` y la propiedad de un plan es `evaluation_plans.user_id`.
- La autorización estaba **duplicada en 6 lugares** con el patrón `where('user_id', auth()->id())` (5 en `StudentGradeController`, 1 en `StudentGradeService::publishGrades`). No hay policies, `Gate` ni `$this->authorize()` en el proyecto.
- El estado real de "publicado" **nunca** fue `evaluation_plan_items.published_at`: ese campo se escribía pero no lo leía ningún frontend. La publicación real vive en `student_grade_publications` (`version`, `published_by`, `published_at`), que solo escribe el botón "Publicar".

#### Cambios

##### Autorización centralizada (nuevo)
- `app/Support/GradeAccess.php` · nueva clase con `canManage()`, `canManagePlan()` y `managesAllPlans()`. Es la única fuente de verdad.
  - Profesor → solo planes propios (`plan.user_id === user.id`).
  - Administrador → cualquier plan, si es `is_admin` **o** tiene el módulo `notas` (`MODULE` = `'notas'`).
  - Representante / otros → siempre `false`.
- `StudentGradeController::authorizePlan()` · helper privado que resuelve el plan y hace `abort_unless(..., 404)`. Se usa **404** (no 403) para no revelar la existencia de planes ajenos. Reemplaza los 5 chequeos duplicados.

##### Rutas
- Las 7 rutas de `mis-estudiantes` pasaron de `role:teacher` a `role:administrator,teacher` en `routes/web.php`. `mis-planes` y `mi-horario` siguen siendo solo de profesor.
- **No se usó `module.access`**: ese middleware corta a los profesores (no son `is_admin` → busca en `modules()` → 403) y habría que romperlos. El módulo se resuelve en `GradeAccess`, donde ya se distingue profesor vs. administrador.

##### Planes de lectura
- `StudentGradeController::index` ramifica: el profesor usa `getPlansForTeacher()` (sin cambios) y la administración usa `getPlansForAdmin()`, que **ya existía** y ya soporta `school_lapse_id` / `lapse_id` / `status`.
- Se añadió `teacher` al eager load de `getPlansForTeacher` para que `formatPlan()` devuelva `teacher_name` también en la vista del profesor (antes salía `null`).

##### Auto-publicación eliminada (pedido explícito del usuario)
- `StudentGradeService::syncPublishedAt()` **eliminada**, junto con su llamada en `saveGrades()` y con la key `items[].published_at` del payload (que nadie leía).
- Ahora `saveGrades()` **nunca** publica, aunque la matriz quede completa. Publicar solo ocurre con el botón (o sea, `publishGrades()`).
- `publishGrades(int $planId, int $actorId)` ya no filtra por `where('user_id', $actorId)`; el chequeo quedó en el controller vía `GradeAccess`.

##### Auditoría
- Migración `2026_09_28_120000_add_graded_by_to_student_grades_tables` · añade `graded_by` (FK `users`, nullable, `nullOnDelete`) a `student_grades` **y** a `student_plan_rasgos` (para no dejar el rasgo sin autor mientras la nota sí lo tiene).
- `saveGrades()` recibe un `$actorId` explícito (4º parámetro, default 0) en vez de leer `auth()` dentro del service, y lo escribe en ambos `updateOrCreate`.
- Modelos `StudentGrade` y `StudentPlanRasgo`: `graded_by` en `$fillable` + relación `gradedBy()`.
- `getMatrixData()` hace `with('gradedBy')` / `with('rasgos.gradedBy')` y expone `student.graders[itemId]`, `student.rasgo_grader` y `plan.teacher_name`.

##### Permisos
- `ModuleSeeder::MODULES` · nuevo slug `['slug' => 'notas', 'name' => 'Notas', 'icon' => 'mdi:clipboard-check-outline', 'order' => 10]`. Usa `updateOrCreate`, así que es idempotente.
- `Personal.svelte` **no se tocó**: ya dibuja los checkboxes desde el prop `modules` (línea 639), así que "Notas" aparece solo. Correr `php artisan db:seed --class=ModuleSeeder` para los existentes.

##### Frontend
- `LeftNav.svelte`: "Notas" agregado a `adminNavPages` con `slug: 'notas'`. El filtro existente (líneas 131-137) ya hace lo correcto sin lógica nueva: `is_admin` lo ve siempre, un admin con `is_admin = 0` solo si tiene `notas`, y en otro caso queda oculto. No colisiona con el dedup por `href` porque los profesores salen antes en la rama `isTeacher`.
- `MisEstudiantes.svelte`:
  - Reactivos `managesAll` y `planTeacherName`.
  - El `<option>` del selector de plan incluye el nombre del profesor cuando `managesAll` (si no, dos planes idénticos serían indistinguibles).
  - Banner naranja "Estás calificando en nombre del colegio..." solo para la administración.
  - Cada input de nota lleva `title="Registrado por: {nombre}"` cuando existe autor.

#### Verificado
- Migración aplicada; módulo `notas` creado (`Module::pluck` lo devuelve).
- `php -l` OK en los 9 archivos PHP tocados.
- Build OK (`corepack yarn run build`, `Done in 35.12s`); los 3 warnings de a11y de `MisEstudiantes` (líneas 1323/1338/1353) son preexistentes, de los `<label>` del filtro.
- Matriz de autorización (script de comprobación con bootstrap de Laravel, luego borrado):
  ```
  admin total -> cualquier plan             PERMITIDO
  admin limitado (sin notas) -> plan        DENEGADO
  admin limitado CON notas -> plan          PERMITIDO
  representante -> cualquier plan            DENEGADO
  usuario nulo -> cualquier plan            DENEGADO
  profesor dueño -> su plan                 PERMITIDO
  ```
- Regresión de la auto-publicación (plan #1, 35 estudiantes × 5 temas = 175 notas):
  ```
  1) profesor guarda la matriz COMPLETA (todas > 0)
     con graded_by: 175   publicaciones creadas: 0   items con published_at: 0
  2) admin guarda el plan ajeno
     notas con graded_by=admin: 175   publicaciones creadas: 0
  3) admin pulsa Publicar
     publicaciones ahora: 1   published_by: 1 (admin)   version: 1
     can_publish antes: true  ->  después: false
  ```

#### Pendiente / notas
- En la prueba se **borraron** las notas y publicaciones previas del plan #1 y se rellenaron con 15/20 (`graded_by` = admin 1), más una publicación. Se puede dejar como demo o limpiar para que el profesor cargue las reales.
- Al admin con `is_admin = 0` se le concedió el módulo `notas` durante la prueba (para validar el caso positivo). Revocar desde Personal si no se quiere.
- No se pudo probar "profesor → plan ajeno" porque la BD solo tiene 1 plan; la lógica es `plan.user_id === user.id`.
- Gotcha de Powershell: `Add-Content` con here-string rompió los acentos (salieron entidades `<C3><A9>`). Para escribir texto con acentos en archivos, usar la herramienta de edición, no `Add-Content`.

---

## Sesión 2026-09-28 — Observaciones pedagógicas por estudiante (drawer en MisEstudiantes)

### Objetivo
Observaciones por estudiante dentro de la página de notas: botón junto al nombre, drawer lateral (diseño Stitch) y backend completo (persistencia, auditoría, historial, borrado lógico y visibilidad para el representante).

Decisiones acordadas: la observación **es por materia** (`evaluation_plan_id` obligatorio), el toggle **Compartir con Representante** guarda el flag **y además se muestra en `MisHijos`**, hay **editar + soft delete**, y la autorización **reutiliza `App\Support\GradeAccess`** (profesor dueño del plan o admin con `is_admin`/módulo `notas`).

### Base de datos
- `database/migrations/2026_09_28_150000_create_student_observations_table.php` → `student_observations`: `evaluation_plan_id` (restrict), `student_id` (restrict), `created_by` (nullOnDelete), `type` (string 20, default `neutral`), `body` (text), `shared_with_representative` (bool), `shared_at` (timestamp null), timestamps + `softDeletes`. Índices compuestos `(evaluation_plan_id, student_id)` y `(student_id, shared_with_representative)`.
- **Materia, curso, sección y profesor NO se duplican**: se derivan del plan vía relaciones. Igual el autor (`created_by`).
- **Patrón nuevo en el proyecto**: es el **primer y único modelo con `SoftDeletes`**. Antes los pagos usaban `status`. Elegido porque el soft delete fue requisito explícito; si se prefiere no abrir ese patrón, cambiar a `status` + unscoped.
- Migración ya aplicada. `php artisan migrate` si una BD nueva falla.

### Backend
- `app/Enums/StudentObservationTypeEnum.php`: `Positive|Neutral|Negative` (backed string) con `label()` e `icon()`. **No** lleva clases Tailwind: los colores se mapean en el Svelte.
- `app/Models/StudentObservation.php`: `HasFactory, SoftDeletes`; cast `type => StudentObservationTypeEnum`, `shared_with_representative => bool`, `shared_at => datetime`; relaciones `plan()`, `student()`, `author()`.
- `app/Http/Requests/ObservationRequest.php` (**abstracto**) + `StoreObservationRequest` + `UpdateObservationRequest`:
  - `plan()` abstracto: cada subclase lo resuelve (input `evaluation_plan_id` en store, `$observation->plan` en update).
  - `authorize()` devuelve `true` si el plan es `null` para que la falta de parámetros sea **422 de validación** y no un 403 que insinúe permisos.
  - Regla de `student_id` con closure que llama a `StudentObservationService::planContainsStudent()` → 422 si el alumno no es del curso/sección del plan (mismo criterio que la matriz: `course_id` + `section_id` + `status != 0`).
  - `UpdateObservationRequest` reemplaza la regla de `student_id` por una que **impide mover la observación a otro estudiante** (422).
  - **Gotcha Laravel 10**: se usa `Rule::in(StudentObservationTypeEnum::values())` y **no** `Rule::enum`, porque `Enum::message()` tiene prioridad sobre `messages()` y el texto queda en inglés fijo. Por eso la clave del mensaje es `type.in`.
- `app/Services/StudentObservationService.php`:
  - `planContainsStudent(EvaluationPlan, int): bool` estático — lo comparten el request y el controller (fuente única).
  - `listForStudent()` devuelve `{observations, counts}` con `counts` = `{positive, neutral, negative}` para el header del drawer.
  - `create/update/delete`, `sharedByStudentGroupedByPlan()` (una sola consulta agrupada por plan, evita el N+1 de `formatSubjects`), `canModify()` y `formatObservation()`.
  - `shared_at` se fija al compartir, **no se reinicia** al editar y se limpia al descompartir. `created_by` (autor) **no** se reescribe al editar.
  - `canModify()`: solo el autor o la administración (un profesor que no es autor no puede editar).
- `app/Http/Controllers/StudentObservationController.php`: `index` (GET con `plan_id`+`student_id`), `store`, `update`, `destroy`. `authorizePlan()` replica el `404` de `StudentGradeController` (no revela planes ajenos); `authorizeObservation()` da `403`. Todas las mutaciones devuelven el `listForStudent()` actualizado para que el drawer no recargue.
- `routes/web.php`: 4 rutas nuevas dentro del grupo `role:administrator,teacher` (`observaciones`, `observaciones/{observation}` PUT/DELETE).
- `RepresentativeService`: `formatSubjectPlan()` ahora recibe `$observations` y los cuelga en `plan.observations`; `formatSubjects()` resuelve **una** consulta con `sharedByStudentGroupedByPlan()`. `materiasHijo`/`MateriasHijo.svelte` **no** se tocaron (arman sus propios subjects) → queda como follow-up.
- `resources/views/app.blade.php`: se agregó `<meta name="csrf-token">`. El proyecto **no lo tenía** y es obligatorio para las mutaciones por `fetch` (sin él salía 419).

### Frontend
- `resources/js/components/StudentObservationsDrawer.svelte` (nuevo, ~700 líneas). **No existía ningún drawer/slide-over en el proyecto** y `Modal.svelte` es centrado, así que se construyó desde cero: backdrop `fixed inset-0 z-[99999]` + panel `absolute right-0 h-full w-full sm:max-w-[560px]`, con `translate-x-full` para el slide (patrón siempre-montado de `Modal`, como `PlanesEvaluacion`).
  - Estado del formulario/editor en un solo bloque (`editingId === null` → crear). Guardar hace POST o PUT; borrar hace DELETE; las tres devuelven la lista fresca.
  - `fetch` + `displayAlert` de `alertStore`, sin recargar Inertia. Carga perezosa al abrir.
  - Recarga con guardas reactivas: `$: if (show && student && loadedFor !== student.id)` carga, `$: if (!show) loadedFor = null` permite recargar al reabrir.
  - **Dictado por voz propio**: Web Speech (`es-VE`, `continuous`, `interimResults`) que anexa el texto reconocido al textarea. Es una versión simplificada de `toggleVoiceDictation()` de `MisEstudiantes` (sin nombres ni notas). `MisEstudiantes.openObservations()` llama `stopVoiceDictation()` + `resetVoiceSelection()` para no dejar dos sesiones de micrófono.
  - Menú contextual `⋮` (Editar/Eliminar) solo si `can_modify`. **En la última tarjeta abre hacia arriba** (`bottom-7`) porque el `overflow-y-auto` del cuerpo lo recortaría.
  - Colores remapeados al paleta del proyecto: `sky-*`→`color3`/`blue`, `rose-*`→`red`, `amber-*`→`yellow`/`orange`, `#17223b`→`color1`. `emerald/slate/indigo` sí existen.
- `resources/js/Pages/Dashboard/MisEstudiantes.svelte`: import del drawer, `observationsStudent`/`showObservations`, función `openObservations()`, botón `mdi:comment-text-outline` en el `<td>` del nombre (el `<div class="min-w-0 pr-1">` pasa a `flex items-center gap-1.5` con los `<p>` en un sub-div `min-w-0 flex-1` para que el nombre siga truncando) y el drawer al final de la plantilla.
- `resources/js/Pages/Dashboard/MisHijos.svelte`: sección "Observaciones del profesor" dentro del `<Modal>` de la materia (debajo de la tabla de notas), solo con `plan.observations` (el backend ya filtró las privadas).

### Verificado
- `php artisan migrate` OK; `php artisan route:list --path=mis-estudiantes` muestra las 4 rutas.
- Sondas PHP con bootstrap de Laravel (luego borradas) sobre service, autorización y payload de representante:
  - Service: normalización de `body` (trim + colapsa espacios), `shared_at` (fija/no reinicia/limpia), contadores, autor conservado al editar, soft delete real (`withTrashed`=1 / sin trashed=0), `sharedByStudentGroupedByPlan` solo devuelve compartidas.
  - HTTP (acting-as, 15 casos): GET 200 · POST 201 · body corto 422 · `type` inválido 422 (mensaje en español) · estudiante ajeno 422 · sin parámetros 422 · PUT autor 200 · PUT moviendo de estudiante 422 · DELETE 200 · DELETE repetido 404 · plan inexistente 422 · POST del admin en plan ajeno 201 · profesor editando observación del admin 403 · representante 403 por middleware.
  - Representante (`misHijos`): llegan **2** compartidas; la privada y la de otro estudiante **no**.
- Harness Svelte temporal con Vite dev server (luego borrado) validando por estilos computados: drawer `560px` en desktop y `390px` full-width sin overflow horizontal en móvil; backdrop `opacity 0` + `pointer-events none` al cerrar y `translate-x(560px)`; Escape cierra; backdrop click cierra; los 19 iconos renderizan `<path>`; toggle cambia de `slate-200` a `blue` y desplaza la perilla; menú contextual abre abajo en la primera tarjeta y arriba en la última, sin recortes; modo edición precarga textarea/tipo/toggle y renombra el botón a "Guardar cambios".
- `corepack yarn run build` OK (`Done in 39.25s`), **sin warnings del componente nuevo**. Los de a11y restantes son preexistentes (`MisEstudiantes` 1339/1354/1369, `DateRange`, `PaymentCard`, `ForgotPassword`).
- Datos de prueba borrados: la tabla `student_observations` quedó vacía.

### Contador de observaciones en el botón (2026-09-29)
Petición: mostrar un indicador pequeño en el botón de observaciones junto al nombre del estudiante con cuántas tiene. Decisiones: badge sobrepuesto en la esquina (no ocupa ancho), invisible cuando son 0 (solo cambia el color del icono a `color2`), y solo el total.

- `StudentObservationService::countsByStudentForPlan(int $planId, array $studentIds): array` → `[student_id => total]` en **una** consulta `groupBy('student_id')` que usa el índice `(evaluation_plan_id, student_id)`. No filtra por `shared_with_representative`: el contador es del profesor y debe incluir las privadas, igual que el drawer. Las soft-deleted quedan fuera por el scope de `SoftDeletes`, así que el número siempre coincide con el historial.
- **Gotcha Laravel**: hay que terminar en `->get()->mapWithKeys(...)`. Un `->pluck('total', 'student_id')` sobre el builder **reemplaza** las columnas del `selectRaw` (ver `onceWithColumns` en `Query\Builder::pluck`) y revienta el `COUNT(*)`.
- `StudentGradeService::getMatrixData()` resuelve los conteos con `app(StudentObservationService::class)` (mismo namespace, sin import) y agrega `observations_count` a cada elemento de `students`. El plan se carga una sola vez por render de Inertia, así que el contador llega correcto sin peticiones extra.
- `StudentObservationsDrawer.svelte`: `createEventDispatcher()` + `dispatch("changed", { studentId, total: observations.length })` en `save()` (solo si no es update) y en `remove()`. `observations` ya viene refrescado de la respuesta, así que `.length` es el total real. Editar no dispara nada porque no cambia el total.
- `MisEstudiantes.svelte`: mapa local `observationCounts` que pisa al `observations_count` del payload — mismo patrón que `editable` y `rasgosEditable`. El reset va en una **guarda reactiva** sobre `data.matrix?.plan?.id`, NO dentro de `selectPlan()`: ese handler usa `router.get` con `preserveState: true` (y los filtros de período/momento también recargan), así que el componente sobrevive y un reset ahí se escaparía al cambiar de plan por otro control.
- Badge: `<span aria-hidden="true">` con `absolute -top-1 -right-1 min-w-[15px] h-[15px]`, `bg-color2 text-white text-[9px]`, y tope de `99+`. El `title`/`aria-label` pasan a singular/plural ("3 observaciones de X, Y"); con 0 se queda el texto original. `aria-hidden` porque el `aria-label` del botón ya lleva la cuenta.
- Verificado por estilos computados contra la celda real (`w-[125px] min-w-[120px] max-w-[140px] px-2.5` en móvil): badge de 15px con 1 dígito y 23px con `99+`, **nunca desborda** la celda (6px de margen en móvil, 16px en desktop), el nombre sigue truncando (`scrollWidth > clientWidth`) y el ancho de la celda no cambia → cero shift de layout. Icono gris `rgb(209,213,219)` sin observaciones y `rgb(31,66,135)` = `color2` con alguna. En `md` el botón pasa a 28px y `px-5`. `build` OK (65.57s), sin warnings nuevos (los 3 de a11y de `MisEstudiantes` son preexistentes, ahora en 1375/1390/1405).
- **⚠ Datos reales en la tabla**: al sondear aparecieron observaciones que escribió el usuario (`created_by=315`, p. ej. "le pego a manuelito bien duro"). **Nunca usar `StudentObservation::forceDelete()` sin filtro para limpiar datos de prueba** — en la sesión anterior la tabla estaba vacía y salió limpio por suerte, pero hoy habría borrado trabajo real. Toda sonda debe capturar un baseline, sembrar con ids propios y borrar solo esos ids, y al final comparar que la tabla quedó idéntica al estado previo. Las sondas de esta sesión lo hicieron así.

### Pendiente / notas
- `MateriasHijo.svelte` (la vista profunda de "Sus materias") **no** muestra observaciones; solo `MisHijos`. Para agregarlo hay que extender el `subjects` de `materiasHijo` (`RepresentativeService` líneas ~283-296) con las compartidas.
- No se verificó en navegador autenticado real (sin credenciales); toda la validación fue por HTTP acting-as + estilos computados.
- No se pudo probar "profesor → plan ajeno" porque la BD solo tiene 1 plan.
- **Este archivo no tiene acentos corruptos**: al leerlo con `Get-Content` en PowerShell los acentos se ven como `?`/`�` porque la consola usa otra codificación, pero el archivo es UTF-8 válido (se confirmó releyéndolo con la herramienta de edición). Para anexar texto, esta sesión usó PHP con `file_put_contents(..., FILE_APPEND)`; el `edit` normal también funciona siempre que el `oldString` copie el texto EXACTO — un fallo anterior fue por escribir "rompe" donde el archivo dice "rompió".
- Corregido de paso: `MisEstudiantes.svelte` usaba `bg-green/15`, clase **inexistente** (15 no está en la escala de opacidad de Tailwind). Ahora `/20`. Ese bug-era preexistente en la barra de "Publicar notas". Ojo al escribir clases nuevas: revisar que el modificador de opacidad exista (10/20/25/30/40/50… sí; 15/35/45 no).
- Limpio: se eliminaron `resources/_obs_test.html`, `resources/js/_obs_test.js` y el `public/_asistencia_test.html` que llevaba tiempo desde la sesión de asistencia.

---

## Sesión 2026-09-30 — Rediseño del Dashboard (admin) + dashboard del representante + comunicados/eventos

### Fase 1 — Correcciones
- Eliminado el KPI "Pagos Pendientes": `payments.status` es entero (1 activo / 0 borrado) y no existe estado `pending`, así que `where('status','pending')` contaba pagos borrados.
- `ChartService::debtByCourse` ya no usa `abs()` del neto: suma inscripción (<0) y meses con status `debt`/`partially_paid` por curso.
- `ChartService::topDebtors` corregido (`currentDebt()` ya devuelve positivo; antes `if ($debt >= 0) return null` descartaba todo).
- `DashboardService` reescrito sin N+1 (carga balances sin relación `student`; un solo mapa de multiplicadores de exención).
- `Index.svelte` tenía doble `onMount`/`onDestroy` de ECharts (init y listener duplicados); queda uno.
- Charts `DebtByCourse`/`TopDebtors` ahora reaccionan al período (`$:` + `setOption`) en vez de solo `onMount`.
- Logo de `LeftNav` usa `homeHref` según rol (antes `/dashboard` daba 403 al representante).

### Fase 2 — KPIs y gráficos admin
- KPIs en `DashboardService::getKpiData($lapse)`: Total Estudiantes (variación vs período anterior vía balances), Ingresos del mes vs meta (`monthly_payment × alumnos con exención` + inscripciones en septiembre), Deuda total (% del facturado = cobrado + deuda), Tasa de cobranza (% representantes al día).
- Métodos nuevos en `ChartService`: `aging()` (al día / 1-30 / 31-60 / +60 usando `day_of_monthly_payment + grace_period`), `collectionByChannel()` (join `account_payments`+`payment_methods`, USD/Bs), `attendanceSummary()` (activos/retirados/graduados + % asistencia del día). `collectionRateTrend` compara contra 12 mensualidades esperadas.
- Endpoints nuevos en `AppController`: `/dashboard/metricas/{lapse?}`, `/dashboard/graficos/aging|collection-by-channel|attendance-summary/{lapse?}`.
- Frontend: `Index.svelte` rehecha (filtro de período que alimenta KPIs y todos los charts) + `AgingChart`, `CollectionByChannelChart`, `AttendanceSummaryCard`, `AcademicEvolutionChart`. `KpiCard` acepta `hint`.

### Fase 3 — Dashboard del representante
- Nueva ruta `/dashboard/inicio` (`role:administrator,representative`) → `RepresentativeDashboardController` → `Dashboard/Inicio.svelte`. `HomeRoute` del representante apunta ahí; ítem "Inicio" en `repNavPages`.
- `RepresentativeDashboardService::getDashboardData($user)` (scoped a sus alumnos): estado de cuenta + próxima cuota, promedio académico (solo publicado y planes `approved`, promedio de definitivas por materia), asistencia acumulada (`AttendanceService::getAccumulatedForStudents`), próximas cuotas/conceptos, próximas evaluaciones (`scheduled_date`), comunicados y eventos.
- "Reportar Pago" enlaza a `/dashboard/mis-pagos?student_id=...`; `MisPagos` lee `student_id` en `onMount` y activa la pestaña de pago. El submit real del pago regular sigue sin cablear (preexistente).
- **Regla de dinero (2026-09-30):** sólo la administración total ve información financiera. `canSeeMoney()` = `is_admin` (NO alcanza con `type_user_id = 1`; un admin limitado con `is_admin = 0` y módulos acotados no debe ver dinero).
  - `DashboardService::getKpiData($lapse, bool $includeMoney)`: si `$includeMoney = false` sólo devuelve `school_lapse_id`, `total_students`, `total_representatives`, `enrollment` (sin ingresos/deuda/cobranza).
  - `AppController@dashboard` pasa `canSeeMoney` y los KPIs filtrados; `metrics()` respeta el flag. Los endpoints de dinero (`annual-vs-monthly-flow`, `debt-by-course`, `collection-rate-trend`, `top-debtors`, `aging`, `collection-by-channel`) hacen `abort_unless(auth()->user()->is_admin, 403)`. `attendance-summary` sí se permite (matrícula/asistencia, sin dinero).
  - `Index.svelte` recibe `canSeeMoney`: si es `false` solo muestra "Total Estudiantes", "Representantes legales" y el resumen de matrícula/asistencia; oculta KPIs y gráficos de dinero y no inicializa ni consulta sus endpoints.
  - `RepresentativeDashboardService::canSeeMoney()` también usa `is_admin`/type administrador; para el representante devuelve `false`, `account` va `null` y `upcoming.payments` vacío (`Inicio.svelte` oculta "Estado de Cuenta" y "Próximos Pagos").
  - `/dashboard/mis-pagos` está en `role:administrator,representative` (los profesores no entran a pagos).

### Fase 4 — Comunicados y eventos (módulos nuevos)
- Migraciones `2026_09_30_000001_create_announcements_table` y `..._000002_create_school_events_table` (aplicadas). Modelos `Announcement` (audiencia all/course/section/student, scope `published`) y `SchoolEvent` (tipo general/exam/meeting/holiday).
- `AnnouncementController` (index/store/update/destroy) y `SchoolEventController` (store/update/destroy); rutas admin `/dashboard/comunicados` y `/dashboard/eventos`. Página `Dashboard/Comunicados.svelte` con dos pestañas. Módulo `comunicados` en `ModuleSeeder` + `EnsureModuleAccess` (`eventos`→`comunicados`) + `LeftNav`.

### Verificado
- `php -l` en todos los PHP nuevos/tocados; `vendor/bin/phpunit` OK (6 tests; `HomeRouteTest` actualizado a `/dashboard/inicio`). `yarn run build` OK.
- Sonda de humo temporal (luego borrada, 7 casos, `DatabaseTransactions`): admin `/dashboard` 200, `/dashboard/metricas` JSON, charts JSON, `/dashboard/comunicados` 200, rep `/dashboard/inicio` 200, rep `/dashboard` 403, comunicado creado por admin visible para el representante.
- Se corrieron `php artisan migrate --force` y `db:seed --class=ModuleSeeder`; no se dejaron datos de prueba.
