# Check List de Pruebas QA — VillaDonq V3

Sistema escolar (matrícula, pagos, estados de cuenta, planes de evaluación, notas, horarios, reportes).
Corrida **manual** — marcar cada ítem con `[x]` cuando pase y dejar nota cuando falle.

> **Entorno / corrida:** fecha: `____________` · DB: `____________` · rama/commit: `____________` · tester: `____________`
> **Bug:** usar la plantilla al final del documento.

---

## 0. Prerrequisitos

- [ ] DB de prueba limpia y sembrada (`php artisan migrate --seed`).
- [ ] Usuarios admin disponibles (`juandonquis07@gmail.com` / `12345678`).
- [ ] Creados vía UI: 1 **profesor**, y vía matrícula 1 **estudiante + representante** (con credenciales propias).
- [ ] 2 periodos escolares creados SOLO en una copia de DB (ver §8; nunca correr `lapse:start-next` sobre datos reales).
- [ ] Config registrada antes de empezar: precio inscripción, mensualidad, día de corte, días de gracia, cuentas bancarias.
- [ ] Dataset de referencia anotado:
  - A = al día · B = 2 meses atrasados · C = exento 50% · D = recién inscrito · E = con sobrepago.
- [ ] `yarn run build` OK y app servida; comando disponible: `php artisan balance:recalculate-status`.
- [ ] 3 sesiones/navegadores listos (admin, teacher, representante).

---

## 1. Autenticación y roles

- [ ] Login con credenciales correctas (admin) entra al dashboard.
- [ ] Login con credencial incorrecta muestra error y no entra.
- [ ] Logout cierra sesión; con "atrás" no se reingresa a una página protegida.
- [ ] Profesor autenticado NO puede abrir rutas admin por URL directa (p.ej. `/dashboard/planes-evaluacion`, `/dashboard/pagos`) → redirige/bloquea.
- [ ] Representante NO puede abrir rutas admin ni de teacher.
- [ ] Admin SÍ puede entrar a las páginas compartidas (mis-pagos, perfil, mis-hijos).
- [ ] Menú lateral solo muestra los módulos del rol logueado.
- [ ] Flujo "olvidar contraseña": solicitud enviada y reset completado.
- [ ] Flujo "establecer contraseña" de primer acceso (usuario con setup pendiente) funciona y luego pide login.
- [ ] "Reenviar correo" de setup (Personal/Profesores) llega y permite el primer acceso.

## 2. Dashboard admin

- [ ] Las métricas/gráficos del dashboard cargan sin error.
- [ ] Endpoint de gráfico `graficos/annual-vs-monthly-flow` responde con y sin `schoolLapse` en la URL.

## 3. Configuración (MainConfig + cuentas)

- [ ] Editar precio de inscripción/mensualidad se guarda y persiste al recargar.
- [ ] CRUD de cuentas bancarias (crear, editar, eliminar) funciona.
- [ ] Las cuentas visibles en MisPagos del representante reflejan los cambios.
- [ ] Config de pagos (día de corte, gracia) se guarda.
- [ ] Sección **Cupos**: muestra todos los cursos del periodo activo con cupo asignado / inscritos / disponibles.
- [ ] Sección **Cupos**: cambiar un cupo, guardar y recargar → persiste (la fila de cupo se crea si no existía).
- **Edges:**
- [ ] Guardar precio mensual en 0 o no numérico → validación con mensaje claro.
- [ ] Cambiar la mensualidad NO altera saldos existentes por sí solo; correr `php artisan balance:recalculate-status` recalcula los estados correctamente.
- [ ] En Cupos, escribir un cupo menor a los inscritos actuales → la fila se pinta roja con aviso; se permite guardar.
- [ ] Guardar cupos en 0 o no numérico → validación (entero ≥ 0).
- [ ] Eliminar una cuenta que ya tiene pagos asociados no rompe el historial (o queda bloqueado con mensaje claro).

## 4. Catálogos (Personal, Profesores, Materias, Secciones)

- [ ] Crear/editar/eliminar personal (administrador) correcto.
- [ ] Crear/editar/eliminar profesor correcto; se le asignan materias.
- [ ] Crear/editar/eliminar materia correcto.
- [ ] Crear y eliminar sección dentro de un curso correcto.
- [ ] Importación con plantilla oficial (descargar → llenar → importar) crea registros.
- [ ] Reenvío de correo de setup (Personal y Profesores).
- **Edges:**
- [ ] Importar plantilla corrupta/vacía o con columnas mal nombradas → error amigable, sin registros a medias.
- [ ] Importar CI repetido (contra BD y contra filas del mismo archivo) → detecta y reporta.
- [ ] Eliminar profesor que tiene planes de evaluación u horarios → regla de integridad (bloqueo con mensaje claro).
- [ ] Eliminar materia en uso por profesores/planes → bloqueo claro.
- [ ] Eliminar curso/sección con estudiantes o cupos → bloqueo claro.
- [ ] Crear sección duplicada (mismo curso + mismo nombre) → validación.

## 5. Matrícula y cupos

- [ ] Alta de estudiante con representante principal (búsqueda por CI) correcta.
- [ ] Alta de estudiante con segundo representante (búsqueda por CI).
- [ ] Estudiante queda en el curso/sección y el "Inscritos" (`accepted`) del curso sube en 1.
- [ ] Los selects de curso muestran la ocupación "{Año} ({inscritos}/{cupo})" y marcan "· lleno" cuando no hay cupos.
- [ ] Subir y borrar documentos del estudiante.
- [ ] Baja (eliminar) estudiante libera el cupo (el "Inscritos" baja) y permite admitir de nuevo hasta el cupo.
- [ ] Reinscripción de estudiante eliminado vía búsqueda de eliminados; cupo se vuelve a consumir.
- [ ] Importar estudiantes con plantilla.
- **Edges:**
- [ ] Curso con cupo lleno bloquea el alta (mensaje claro, tanto en UI como en servidor).
- [ ] Cupo asignado = 0 bloquea todo alta nuevo.
- [ ] Editar a un estudiante SIN cambiar de curso (aunque esté lleno) → permitido.
- [ ] Cambiar a un estudiante a un curso lleno → bloqueado.
- [ ] Re-activar un estudiante eliminado cuyo curso está lleno → bloqueado.
- [ ] Importar una fila hacia un curso lleno → error solo en esa fila del resumen, el resto importa.
- [ ] CI duplicado al dar de alta → validación.
- [ ] Reinscribir a alguien ya activo → evita duplicado.
- [ ] Cambiar de curso/sección a un estudiante actualiza los contadores de cupo de origen y destino.

## 6. Pagos (admin) y saldos — núcleo delicado

- [ ] Registrar pago dirigido a **inscripción**.
- [ ] Registrar pago dirigido a un **mes** puntual.
- [ ] Registrar pago que cubre **varios meses** (reparte correctamente en `BalancePayment`).
- [ ] Registrar pago al **mes actual** y a un **mes futuro**.
- [ ] Registrar pago **parcial** (menor a la mensualidad) → estado `parcialmente pagado`.
- [ ] Registrar **sobrepago** (mayor a la deuda) → saldo sobrante queda positivo (sobrepago).
- [ ] El estado del mes cambia a `pagado` cuando el monto cubre el total.
- [ ] **Actualizar** un pago: revierte saldos del pago viejo, lo deja `status=0` con `deleted_by`, crea un pago nuevo y escribe `PaymentHistory`; montos y estados recalculados.
- [ ] **Eliminar** un pago: la deuda reaparece; el pago desaparece de listas y totales.
- **Edges:**
- [ ] Pago en septiembre cubriendo **diciembre + enero** (cruce de año escolar) se reparte en el mes correcto.
- [ ] Estudiante **exento 50%**: los montos de inscripción/mensualidad aplican descuento proporcional.
- [ ] Montos con decimales/redondeo (p.ej. 0.01) no desbalancean la suma.
- [ ] Pago hecho dentro del período de gracia posterior al día de corte se considera a tiempo.
- [ ] Intentar pagar a un estudiante sin balance activo → manejo correcto.
- [ ] Dos pagos seguidos al mismo estudiante acumulan correctamente sin pisar `BalancePayment`.
- [ ] Un pago eliminado/actualizado NO aparece en estados de cuenta ni historial de ingresos.

## 7. Estados de Cuenta

- [ ] Filtros: deudores, morosos, periodo actual, periodo anterior, exento, al día.
- [ ] Total de deuda e ingresos por periodo coinciden con los datos cargados.
- [ ] Ordenamiento por deuda/nombre/curso y paginación funcionan.
- **Edges:**
- [ ] Eliminar/actualizar un pago y volver a estados de cuenta → refleja el cambio sin recargar cache.
- [ ] Estudiante sin pagos muestra toda la deuda (inscripción + meses).
- [ ] Filtro de periodo anterior devuelve solo balances de ese periodo.

## 8. Transición de periodo escolar (hacer en COPIA de DB)

- [ ] Ejecutar `php artisan lapse:start-next`.
- [ ] El periodo anterior queda `status=0` y el nuevo queda activo.
- [ ] El nuevo periodo tiene sus 3 momentos (`SeedLapses`).
- [ ] Cupos renovados para el nuevo periodo copiando `assigned` del anterior (accepted=0).
- [ ] Planes de evaluación y notas del periodo anterior siguen existiendo asociados a ese periodo.
- [ ] MisPlanes por defecto muestra el periodo nuevo (vacío) y con el filtro se ve el anterior.
- [ ] Pagos/balances del periodo anterior siguen consultables en estados de cuenta (filtro periodo anterior).

## 9. Planes de evaluación (profesor + admin) — incluye copiado

### Creación (profesor)
- [ ] Crear plan para **una sección**; unidades → temas con % y puntos.
- [ ] Crear plan con **"Todas las secciones"** → genera un plan por sección (N clones).
- [ ] Suma evaluaciones + rasgos = **100%**; si no, bloquea en UI **y** servidor (tolerancia 0.01).
- [ ] Guardar como **borrador** vs **enviar a aprobación** generan estados `borrador`/`pendiente`.
- [ ] El tooltip de "días permitidos" coincide con el horario real de esa materia/profesor/sección.
- [ ] Elegir fecha sin restricción funciona; la fecha se guarda en `scheduled_date`.

### Editar / eliminar (profesor)
- [ ] Plan `pendiente` se puede editar y al guardar vuelve a quedar en revisión (borra nota admin).
- [ ] Plan `rechazado` se puede editar (queda `pendiente` al enviar).
- [ ] Plan `aprobado` NO se puede editar ni eliminar (mensaje claro).
- [ ] Eliminar `pendiente`/`borrador` funciona y desaparece de la lista.

### Admin (PlanesEvaluacion)
- [ ] Aprobar un plan → estado `aprobado` con aprobador/fecha.
- [ ] Rechazar con nota → estado `rechazado` + nota visible.
- [ ] En filtro "Pendiente", al aprobar/rechazar el modal **auto-avanza al siguiente pendiente**.
- [ ] Botón "Siguiente ›" recorre la cola de pendientes.
- [ ] Aprobar un plan `rechazado` funciona; rechazar un `aprobado` funciona.
- [ ] Filtros Período/Momento/Año/Sección/Búsqueda se mantienen entre sí (escribir en search conserva selects y viceversa).
- [ ] Se pueden ver planes de **años anteriores** cambiando el período.
- [ ] Los borradores NO aparecen en la cola/listado del admin.

### Copiar plan (nuevo)
- [ ] **Profesor:** filtrar a un momento anterior → acción "Copiar plan" → destino otro momento → queda como **borrador**, con **fechas vacías**, y se redirige a MisPlanes del destino donde se ve/edita.
- [ ] **Profesor:** copiar un plan **aprobado** de un año anterior al momento actual del año en curso.
- [ ] **Profesor:** intentar copiar plan ajeno → error de permisos.
- [ ] **Profesor:** copiar al **mismo** momento (duplicado permitido) funciona.
- [ ] **Profesor:** con "Todas las secciones" genera N copias.
- [ ] **Admin:** copiar un plan de un profesor a otro momento.
- [ ] **Admin:** reasignar a otro profesor que NO da esa materia → error validado; con un profesor válido → OK.
- [ ] **Admin:** dejar el nombre vacío → se autogenera.
- [ ] Copiar con destino momento de otro período distinto al del momento elegido → error "el momento no pertenece al período".
- [ ] La copia NUNCA arrastra `scheduled_date` ni estado `aprobado`; el plan original queda intacto.

## 10. Notas (MisEstudiantes) y publicación

- [ ] Aparecen los estudiantes de las secciones donde el profesor dicta (según horario).
- [ ] Cargar notas por tema dentro de 0..puntos.
- [ ] Cargar rasgos dentro de 0..máx del plan; si el plan tiene `rasgos_points=0` la columna rasgos NO se muestra.
- [ ] Definitiva = Σ(nota × %)/100 + rasgos del estudiante (verificación manual).
- [ ] Guardar notas funciona y persiste.
- [ ] **Publicar** notas crea la publicación (versión) y deja de permitir cambios sin nueva versión.
- [ ] Tras editar y **republicar**, la versión incrementa y los lectores ven la nueva.
- [ ] Un plan `borrador` o `pendiente` NO es visible a representante/boleta.
- **Edges:**
- [ ] Nota negativa o mayor a los puntos del tema → rechazada.
- [ ] Publicar con estudiantes sin notas → decide y documenta el comportamiento (permitido o bloqueado; debe ser consistente).
- [ ] Publicar un plan sin ítems no rompe la boleta.
- [ ] Intentar editar/copiar un plan aprobado con notas publicadas se comporta según regla de negocio (aprobado no editable).

## 11. Horarios (admin)

- [ ] Vista matriz semanal por sección carga.
- [ ] Vista formulario permite crear/editar bloques (día 1-5, hora 24h) y guarda.
- [ ] Banda de receso se muestra en todos los días y respeta `recess_start`/`recess_duration_minutes`.
- [ ] Conteo de ocupación refleja los bloques creados.
- [ ] Cambiar periodo/curso/sección re-monta la grilla con datos frescos (sin estado obsoleto).
- [ ] Materia repite color consistente por índice (no módulo) al recargar.
- [ ] Estado vacío se muestra cuando la sección no tiene clases.
- **Edges:**
- [ ] Crear bloque que **solapa** otro del mismo día/hora/profesor/sección → rechazado con mensaje.
- [ ] Hora final ≤ hora inicial → validación.
- [ ] Clase que cruza el receso se maneja sin romper la grilla.
- [ ] Un mismo profesor en dos secciones a la misma hora (conflicto real) queda marcado/evitado.

## 12. MiHorario (teacher) y HorarioHijo (representante)

- [ ] El profesor ve su semana completa (read-only) agrupada por día con curso/sección.
- [ ] `?school_lapse_id=X` cambia el periodo del horario del profesor.
- [ ] El representante ve el horario de cada hijo.
- [ ] Estado vacío sin clases asignadas (no rompe la vista).

## 13. Representante

- [ ] "Mis hijos" lista solo estudiantes activos/inscritos del representante.
- [ ] Mis pagos muestra deudas por mes con estados correctos.
- [ ] Registrar un pago desde el representante con cuenta bancaria → se refleja en el saldo.
- [ ] Boletas/estados del hijo solo muestran notas **publicadas** de planes aprobados.
- **Edges:**
- [ ] Representante sin hijos muestra estado vacío.
- [ ] Hijo sin balance no rompe la pantalla.
- [ ] Sobrepago hecho desde la vista del representante se muestra coherente.

## 14. Perfil y contraseña

- [ ] Actualizar datos propios (nombre/teléfono/etc.) se guarda.
- [ ] Cambiar contraseña con clave actual **incorrecta** → error claro.
- [ ] Cambiar contraseña con clave actual correcta → OK y permite relogin con la nueva.

## 15. Reportes

- [ ] Boleta (PDF) de un estudiante con notas publicadas + rasgos en el periodo actual.
- [ ] Boleta del periodo anterior (filtro periodo).
- [ ] Certificado (PDF) se genera correctamente.
- **Edges:**
- [ ] Estudiante sin notas publicadas → boleta coherente (sin nota o con aviso).
- [ ] Estudiante recién inscrito y/o exento no rompe el reporte.

## 16. Consistencia de datos (tinker/consulta)

- [ ] En `balance_students`: negativos = deuda, positivos = sobrepago; inscripción + enero..diciembre suman la deuda esperada.
- [ ] Suma de `balance_payments.amount` activos = ingresos aplicados al balance.
- [ ] Todo pago activo (`status=1`) tiene sus filas `balance_payment` (mes o inscripción).
- [ ] Todo create/update/delete de pago dejó su `payment_histories`.
- [ ] Pagos `status=0` (eliminados) NO suman en ningún total/listado.
- [ ] Estados por mes coinciden con la regla (día de corte + gracia) según `determineMonthStatus`.
- [ ] Tras correr `balance:recalculate-status`, los estados pendientes/deuda/pagado quedan correctos.

---

## Plantilla de reporte de bug

```
Módulo:
Severidad (Baja/Media/Alta/Crítica):
Pasos para reproducir:
  1.
  2.
  3.
Esperado:
Obtenido:
Entorno (navegador/rol/fecha):
Captura/log (adjuntar o pegar):
```
