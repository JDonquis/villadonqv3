<script>
    import BalanceBar from "../../components/BalanceBar.svelte";
    import Search from "../../components/Search.svelte";
    import Table from "../../components/Table.svelte";
    import { page, router } from "@inertiajs/svelte";
    import { displayAlert } from "../../stores/alertStore";
    import { convertUsdToBs, getDolarRateByDate } from "../../utils/dolarApi";
    import { formatBsInput } from "../../utils/formatters";

    export let data = [];
    export let config;
    export let annulments = [];

    const CALENDAR_MONTHS = [
        ["january", "Enero"],
        ["february", "Febrero"],
        ["march", "Marzo"],
        ["april", "Abril"],
        ["may", "Mayo"],
        ["june", "Junio"],
        ["july", "Julio"],
        ["august", "Agosto"],
        ["september", "Septiembre"],
        ["october", "Octubre"],
        ["november", "Noviembre"],
        ["december", "Diciembre"],
    ];

    let showTotalDebt = false;
    let showConfirmModal = false;
    let pendingBalanceId = null;
    let pendingStudentName = "";

    // --- Anulación masiva de meses ---
    const SCHOOL_MONTHS = [
        ["september", "Septiembre"],
        ["october", "Octubre"],
        ["november", "Noviembre"],
        ["december", "Diciembre"],
        ["january", "Enero"],
        ["february", "Febrero"],
        ["march", "Marzo"],
        ["april", "Abril"],
        ["may", "Mayo"],
        ["june", "Junio"],
        ["july", "Julio"],
        ["august", "Agosto"],
    ];

    function defaultUpToMonth() {
        const currentMonth = new Date()
            .toLocaleString("en-US", { month: "long" })
            .toLowerCase();
        const index = SCHOOL_MONTHS.findIndex(([id]) => id === currentMonth);
        const target = index > 0 ? index - 1 : Math.max(0, index);
        return SCHOOL_MONTHS[target]?.[0] ?? "october";
    }

    let showAnnulModal = false;
    let showHistory = false;
    let annulLoading = false;
    let annulPreview = null;
    let annulStudentName = "";
    $: annulmentsList = annulments;
    let annulForm = {
        up_to_month: defaultUpToMonth(),
        include_inscription: false,
        course_id: "",
        section_id: "",
        student_id: null,
    };

    async function annulRequest(url, body) {
        const response = await fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN":
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content") || "",
            },
            body: JSON.stringify(body),
        });
        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload?.message || "Error en la operación");
        }
        return payload;
    }

    function openAnnulModal(student = null) {
        annulPreview = null;
        annulStudentName = student
            ? `${student.name ?? ""} ${student.last_name ?? ""}`.trim()
            : "";
        annulForm = {
            up_to_month: defaultUpToMonth(),
            include_inscription: false,
            course_id: "",
            section_id: "",
            student_id: student?.id ?? null,
        };
        showAnnulModal = true;
    }

    async function previewAnnul() {
        annulLoading = true;
        try {
            annulPreview = await annulRequest(
                "/dashboard/estados-de-cuenta/anular-meses/preview",
                { ...annulForm },
            );
        } catch (error) {
            displayAlert({ type: "error", message: error.message });
        }
        annulLoading = false;
    }

    async function confirmAnnul() {
        annulLoading = true;
        try {
            const result = await annulRequest(
                "/dashboard/estados-de-cuenta/anular-meses",
                { ...annulForm },
            );
            displayAlert({
                type: "success",
                message: `Anulación aplicada: ${result.affected_count} estudiantes ($${result.total_annulled}).`,
            });
            showAnnulModal = false;
            router.reload();
        } catch (error) {
            displayAlert({ type: "error", message: error.message });
        }
        annulLoading = false;
    }

    async function undoAnnulment(id) {
        try {
            const result = await annulRequest(
                `/dashboard/estados-de-cuenta/anulaciones/${id}/deshacer`,
                {},
            );
            displayAlert({ type: "success", message: result.message });
            router.reload();
        } catch (error) {
            displayAlert({ type: "error", message: error.message });
        }
    }
    $: urlParams = new URLSearchParams($page.url.split("?")[1] || "");
    $: tableData = {
        ...data?.students,
        filters: {
            debt_filter: urlParams.get("debt_filter") || "",
            month: urlParams.get("month") || "",
            course_id: urlParams.get("course_id") || "",
        },
    };
    $: selectedMonth = tableData.filters.month || "";
    $: selectedCourseId = tableData.filters.course_id || "";

    function applyStatementParam(name, value) {
        const current = new URLSearchParams($page.url.split("?")[1] || "");
        if (value) {
            current.set(name, value);
        } else {
            current.delete(name);
        }
        current.delete("page");
        router.get(
            $page.url.split("?")[0],
            Object.fromEntries(current.entries()),
            { preserveState: true, replace: true },
        );
    }

    $: console.log({ data }, { tableData });
    $: console.log(config);

    async function copyToClipboard(value, label = "Texto") {
        if (!value) {
            displayAlert({
                type: "error",
                message: `No hay ${label.toLowerCase()} para copiar.`,
            });
            return;
        }

        try {
            await navigator.clipboard.writeText(String(value));
            displayAlert({
                type: "success",
                message: `${label} copiado al portapapeles`,
            });
        } catch (error) {
            displayAlert({
                type: "error",
                message: "No se pudo copiar al portapapeles.",
            });
        }
    }

    const CHARGE_DEFAULT_LABELS = {
        ame: "Seguro de atención primaria (AME)",
        investment_plan: "Plan de inversión",
    };

    function chargeInfo(student, type) {
        const charges = (student.charges || []).filter((c) => c.type === type);

        if (charges.length === 0) {
            return {
                text: "Sin cargo",
                cls: "bg-gray-50 text-gray-400 border-gray-200",
                title: "",
            };
        }

        const conceptName =
            charges[0]?.concept_name || CHARGE_DEFAULT_LABELS[type] || type;
        const unpaid = charges.filter((c) => Number(c.remaining) > 0);

        if (unpaid.length === 0) {
            return {
                text: "Pagado",
                cls: "bg-green/20 text-green-800 border-green-300",
                title: conceptName,
            };
        }

        const total = unpaid.reduce(
            (sum, c) => sum + (Number(c.remaining) || 0),
            0,
        );

        const hasPartial = unpaid.some((c) => (Number(c.paid_amount) || 0) > 0);

        const cls = hasPartial
            ? "bg-yellow/30 text-yellow-900 border-yellow-300"
            : "bg-red/20 text-red-800 border-red-300";

        if (unpaid.length > 1) {
            return {
                text: `Debe $${total.toFixed(2)} (${unpaid.length} períodos)`,
                cls,
                title: `${conceptName} · deuda acumulada de ${unpaid.length} períodos`,
            };
        }

        return {
            text: `Debe $${total.toFixed(2)}`,
            cls,
            title: conceptName,
        };
    }

    const MONTH_LABELS = {
        january: "Enero",
        february: "Febrero",
        march: "Marzo",
        april: "Abril",
        may: "Mayo",
        june: "Junio",
        july: "Julio",
        august: "Agosto",
        september: "Septiembre",
        october: "Octubre",
        november: "Noviembre",
        december: "Diciembre",
    };

    function getOutstandingMonths(student) {
        const balances = Array.isArray(student?.balances)
            ? student.balances
            : [];
        const months = [];

        Object.entries(MONTH_LABELS).forEach(([key, label]) => {
            const hasDebt = balances.some((balance) => {
                const monthData = balance?.months ?? {};
                const status =
                    monthData[`${key}_status`] ?? balance?.[`${key}_status`];
                const amount = Number(monthData[key] ?? balance?.[key] ?? 0);

                return (
                    status === "debt" ||
                    status === "partially_paid" ||
                    (status === "pending" && amount < 0)
                );
            });

            if (hasDebt) {
                months.push(label);
            }
        });

        return months;
    }

    async function sendToWhatsApp(student) {
        let phoneNumber = student.representative.user.phone_number?.replace(
            /[ -]/g,
            "",
        );

        if (!phoneNumber || phoneNumber.length < 9) {
            displayAlert({
                type: "error",
                message:
                    "No hay un número de WhatsApp válido para este representante.",
            });
            return;
        }

        if (!phoneNumber.startsWith("+") && !phoneNumber.startsWith("58")) {
            phoneNumber = "58" + phoneNumber;
        }

        phoneNumber = phoneNumber.replace("+", "");

        const totalUsd = Number(
            student?.total_debt ??
                (Array.isArray(student?.balances)
                    ? student.balances.reduce(
                          (sum, balance) =>
                              sum + (Number(balance?.total_debt) || 0),
                          0,
                      )
                    : 0),
        );

        const monthsText =
            getOutstandingMonths(student).join(", ") || "ningún mes";
        const totalUsdText = `$${Number(totalUsd || 0).toFixed(2)}`;
        const colegio = config?.name || "el colegio";
        const repName =
            `${student.representative?.user?.name ?? ""} ${student.representative?.user?.last_name ?? ""}`.trim();
        const studentName =
            `${student?.name ?? ""} ${student?.last_name ?? ""}`.trim();

        const paymentLink = `${window.location.origin}/dashboard/mis-pagos`;

        const text = `Hola ${repName}:

Le escribimos desde ${colegio} para informarle que su representado ${studentName} tiene una deuda pendiente de ${totalUsdText}.

Ingrese a este link para más información y proceder con el pago: ${paymentLink}`;

        window.open(
            `https://wa.me/${phoneNumber}?text=${encodeURIComponent(text)}`,
            "_blank",
        );

        // Encontrar el balance del lapso activo (status = 1) o el más reciente
        const activeBalance =
            student.balances?.find((b) => b.school_lapse?.status === 1) ??
            student.balances?.[0];

        if (activeBalance) {
            pendingBalanceId = activeBalance.id;
            pendingStudentName = studentName;
            showConfirmModal = true;
        } else {
            displayAlert({
                type: "info",
                message: "Se abrió WhatsApp con el mensaje listo para enviar.",
            });
        }
    }

    async function confirmReminder(sent) {
        if (!sent || !pendingBalanceId) {
            showConfirmModal = false;
            pendingBalanceId = null;
            pendingStudentName = "";
            return;
        }

        try {
            const response = await fetch(
                "/dashboard/estados-de-cuenta/marcar-recordatorio",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN":
                            document
                                .querySelector('meta[name="csrf-token"]')
                                ?.getAttribute("content") || "",
                    },
                    body: JSON.stringify({
                        balance_student_id: pendingBalanceId,
                    }),
                },
            );

            const data = await response.json();

            if (data.success) {
                displayAlert({
                    type: "success",
                    message: `Recordatorio de ${data.month} guardado.`,
                });
            } else {
                displayAlert({
                    type: "error",
                    message: data.message || "Error al guardar recordatorio",
                });
            }
        } catch (error) {
            displayAlert({
                type: "error",
                message: "Error de conexión al guardar recordatorio",
            });
        }

        showConfirmModal = false;
        pendingBalanceId = null;
        pendingStudentName = "";
    }

    // Helper para saber si algún balance tiene recordatorio en el mes actual
    function hasCurrentMonthReminder(student) {
        const monthEn = new Date()
            .toLocaleString("en-US", { month: "long" })
            .toLowerCase(); // english month key
        return (
            student.balances?.some((b) => b.months?.[`${monthEn}_reminded`]) ??
            false
        );
    }
</script>

{#if showAnnulModal}
    <!-- svelte-ignore a11y-click-events-have-key-events -->
    <!-- svelte-ignore a11y-no-static-element-interactions -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        role="presentation"
        on:click|self={() => (showAnnulModal = false)}
    >
        <div
            class="bg-white rounded-2xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 space-y-4"
        >
            <h3 class="text-lg font-bold text-color1">Anular meses pasados</h3>
            <p class="text-xs text-gray-500 leading-snug">
                Marca como <b>"No aplica"</b> los meses hasta el seleccionado. Los
                meses con pagos registrados se omiten.
            </p>
            {#if annulForm.student_id}
                <p class="text-xs font-semibold text-color2">
                    Solo para el estudiante: {annulStudentName}
                </p>
            {/if}

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="text-xs font-semibold text-gray-600">
                    Anular hasta
                    <select
                        bind:value={annulForm.up_to_month}
                        on:change={(e) => {
                            if (e.target.value === "none") {
                                annulForm.include_inscription = true;
                            }
                        }}
                        class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
                    >
                        <option value="none">Solo inscripción (sin meses)</option>
                        {#each SCHOOL_MONTHS as [id, label]}
                            <option value={id}>{label}</option>
                        {/each}
                    </select>
                </label>

                <label
                    class="flex items-center gap-2 text-xs font-semibold text-gray-600 mt-5"
                >
                    <input
                        type="checkbox"
                        bind:checked={annulForm.include_inscription}
                        class="rounded border-gray-300"
                    />
                    Incluir inscripción
                </label>

                {#if !annulForm.student_id}
                    <label class="text-xs font-semibold text-gray-600">
                        Curso (opcional)
                        <select
                            bind:value={annulForm.course_id}
                            class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option value="">Todos</option>
                            {#each data?.courses || [] as course}
                                <option value={course.id}>{course.name}</option>
                            {/each}
                        </select>
                    </label>

                    <label class="text-xs font-semibold text-gray-600">
                        Sección (opcional)
                        <select
                            bind:value={annulForm.section_id}
                            class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm"
                        >
                            <option value="">Todas</option>
                            {#each data?.sections || [] as section}
                                <option value={section.id}>{section.name}</option>
                            {/each}
                        </select>
                    </label>
                {/if}
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    on:click={previewAnnul}
                    disabled={annulLoading}
                    class="px-4 py-2 rounded-lg border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-50"
                >
                    Previsualizar
                </button>
            </div>

            {#if annulPreview}
                <div class="rounded-xl border border-gray-200 bg-slate-50 p-3 text-xs">
                    <div class="flex flex-wrap gap-4">
                        <span
                            >Afectados:
                            <b>{annulPreview.affected_count}</b></span
                        >
                        <span
                            >Omitidos (con pagos):
                            <b>{annulPreview.skipped_count}</b></span
                        >
                        <span
                            >Total a anular:
                            <b>${annulPreview.total_annulled}</b></span
                        >
                    </div>
                    {#if annulPreview.balances?.length}
                        <ul class="mt-2 max-h-40 overflow-y-auto space-y-0.5 text-gray-500">
                            {#each annulPreview.balances.slice(0, 50) as b}
                                <li>
                                    {b.student} — {b.months.join(", ")}{b.inscription
                                        ? " + inscripción"
                                        : ""}
                                </li>
                            {/each}
                            {#if annulPreview.balances.length > 50}
                                <li>… y {annulPreview.balances.length - 50} más</li>
                            {/if}
                        </ul>
                    {/if}
                </div>
            {/if}

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    on:click={() => (showAnnulModal = false)}
                    class="px-4 py-2 rounded-lg text-xs font-semibold text-gray-600 hover:bg-gray-100"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    on:click={confirmAnnul}
                    disabled={annulLoading || !annulPreview || annulPreview.affected_count === 0}
                    class="px-5 py-2 rounded-lg bg-red text-white text-xs font-bold hover:opacity-90 disabled:opacity-50"
                >
                    Anular meses
                </button>
            </div>
        </div>
    </div>
{/if}

{#if showHistory}
    <div class="mb-4 rounded-2xl border border-gray-200 bg-white p-4">
        <div class="flex items-center justify-between mb-2">
            <h4 class="text-sm font-bold text-color1">Anulaciones</h4>
            <button
                type="button"
                class="text-xs text-gray-400 hover:text-gray-600"
                on:click={() => (showHistory = false)}>Cerrar</button
            >
        </div>
        {#if annulmentsList.length === 0}
            <p class="text-xs text-gray-400">Sin anulaciones registradas.</p>
        {:else}
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-400 text-left">
                            <th class="py-1 pr-3">Fecha</th>
                            <th class="py-1 pr-3">Hasta</th>
                            <th class="py-1 pr-3">Afectados</th>
                            <th class="py-1 pr-3">Monto</th>
                            <th class="py-1 pr-3">Autor</th>
                            <th class="py-1 pr-3">Estado</th>
                            <th class="py-1"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {#each annulmentsList as a}
                            <tr class="border-t border-gray-100">
                                <td class="py-1.5 pr-3">{a.created_at}</td>
                                <td class="py-1.5 pr-3">
                                    {a.up_to_month_label}{a.include_inscription
                                        ? " + insc."
                                        : ""}
                                </td>
                                <td class="py-1.5 pr-3">{a.affected_count}</td>
                                <td class="py-1.5 pr-3">${a.total_annulled}</td>
                                <td class="py-1.5 pr-3">{a.created_by}</td>
                                <td class="py-1.5 pr-3">
                                    {a.undone ? "Deshecha" : "Aplicada"}
                                </td>
                                <td class="py-1.5 text-right">
                                    {#if !a.undone}
                                        <button
                                            type="button"
                                            class="text-[11px] font-semibold text-color2 hover:text-color3"
                                            on:click={() => undoAnnulment(a.id)}
                                        >
                                            Deshacer
                                        </button>
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
{/if}

{#if showConfirmModal}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-color1 mb-3">
                Confirmar envío
            </h3>
            <p class="text-gray-600 mb-4">
                ¿Se envió el mensaje al representante de <strong
                    >{pendingStudentName}</strong
                >?
            </p>
            <div class="flex gap-3 justify-end">
                <button
                    on:click={() => confirmReminder(false)}
                    class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition"
                >
                    No / Cancelar
                </button>
                <button
                    on:click={() => confirmReminder(true)}
                    class="px-4 py-2 bg-emerald-600 text-white hover:bg-emerald-700 rounded-lg transition"
                >
                    Sí, se envió
                </button>
            </div>
        </div>
    </div>
{/if}

<svelte:head>
    <title>Estados de Cuenta</title>
</svelte:head>

<h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-3">
    Estados de Cuenta
</h2>

<div class="flex flex-wrap items-center gap-2 mb-4">
    <Search placeholder="Buscar estudiante..." class="flex-1" />
    {#if $page.props.auth.is_admin}
        <button
            type="button"
            on:click={openAnnulModal}
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border  bg-white/70 text-xs font-semibold text-gray-600 hover:bg-gray-100"
            title="Anular meses pasados para todos"
        >
            <iconify-icon icon="mdi:calendar-remove-outline" width="16" height="16"
            ></iconify-icon>
            Anular meses
        </button>
        <button
            type="button"
            on:click={() => (showHistory = !showHistory)}
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border  bg-white/70 text-xs font-semibold text-gray-600 hover:bg-gray-100"
            title="Historial de anulaciones"
        >
            <iconify-icon icon="mdi:history" width="16" height="16"></iconify-icon>
            Anulaciones
        </button>
    {/if}
</div>

{#if data.total_debt}
    <div class="w-max mb-5 flex flex-wrap items-center gap-2">
        <span class="font-semibold">Deuda:</span>
        {#if showTotalDebt}
            <b class="text-sm text-red transition-all duration-200">
                ${data.total_debt}
            </b>
        {/if}

        <button
            type="button"
            class="inline-flex items-center justify-center bg-white/10 p-2 text-gray-700 transition hover:bg-red/10 focus:outline-none"
            on:click={() => {
                showTotalDebt = !showTotalDebt;
            }}
            aria-label={showTotalDebt ? "Ocultar total" : "Mostrar total"}
        >
            <iconify-icon
                icon={showTotalDebt ? "formkit:eyeclosed" : "mdi:eye-outline"}
                width="24"
                height="24"
            ></iconify-icon>
        </button>
    </div>
{/if}
<!-- svelte-ignore missing-declaration -->
<Table
    serverSideData={tableData}
    pagination={true}
    filtersOptions={{
        debt_filter: [
            { id: "", name: "Todos" },
            { id: "debtors", name: "Deudores" },
            { id: "current_period", name: "Deudores del periodo actual" },
            { id: "previous_period", name: "Deudores del periodo anterior" },
            { id: "exempted", name: "Solo exonerados" },
            { id: "up_to_date", name: "Al día" },
            { id: "graduated_with_debts", name: "Graduados con deudas" },
        ],
    }}
>
    <!-- <div slot="filterBox" class="flex items-center gap-2 md:gap-2">
        <select
            id="month-filter"
            name="month"
            bind:value={selectedMonth}
            on:change={(e) => applyStatementParam("month", e.target.value)}
            class="px-2 py-1.5 text-xs font-semibold text-gray-700 bg-white border-0 focus:outline-none focus:ring-0"
            title="Filtrar deudores por mes"
        >
            <option value="">Mes: Todos</option>
            {#each CALENDAR_MONTHS as [id, label]}
                <option value={id}>Mes: {label}</option>
            {/each}
        </select>
        <select
            id="course-filter"
            name="course_id"
            bind:value={selectedCourseId}
            on:change={(e) =>
                applyStatementParam("course_id", e.target.value)}
            class="px-2 py-1.5 text-xs font-semibold text-gray-700 bg-white border-0 focus:outline-none focus:ring-0"
            title="Filtrar por grado"
        >
            <option value="">Grado: Todos</option>
            {#each data?.courses || [] as course}
                <option value={course.id}>Grado: {course.name}</option>
            {/each}
        </select>
    </div> -->
    <thead slot="thead">
        <tr>
            <th>Estudiante</th>
            <th class="hidden md:table-cell">Balance</th>
            <th>Rep Legal</th>
        </tr>
    </thead>
    <tbody slot="tbody">
        {#each tableData.data as student}
            <tr
                style="content-visibility: auto; contain-intrinsic-size: 0 350px;"
            >
                <td class=" space-y-2">
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col gap-1 text-sm">
                            <!-- Línea Superior: Nombre completo del estudiante -->
                            <div
                                class="font-semibold text-gray-800 capitalize leading-snug max-w-[220px] sm:max-w-[260px] md:max-w-[320px] truncate"
                            >
                                {student.name}
                                {student.last_name}
                            </div>

                            <!-- Línea Inferior: Metadatos organizados en chips/badges -->
                            <div
                                class="flex items-center gap-1.5 flex-wrap text-xs text-gray-500"
                            >
                                <div
                                    class="group relative inline-flex items-center"
                                >
                                    <span
                                        class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded border border-gray-200/50 font-mono text-xs"
                                    >
                                        {#if student.document_type}
                                            <span class="uppercase"
                                                >{student.document_type}-</span
                                            >
                                        {/if}
                                        {student.ci}
                                    </span>
                                    <button
                                        type="button"
                                        class="absolute -right-1 top-1/2 -translate-y-1/2 rounded border border-gray-200 bg-white p-1 text-[10px] text-gray-500 opacity-0 shadow-sm transition-all duration-200 group-hover:opacity-100 hover:text-color1"
                                        aria-label="Copiar cédula"
                                        title="Copiar cédula"
                                        on:click|stopPropagation={() =>
                                            copyToClipboard(
                                                `${student.document_type ? `${student.document_type}-` : ""}${student.ci}`,
                                                "Cédula",
                                            )}
                                    >
                                        <iconify-icon icon="mdi:content-copy"
                                        ></iconify-icon>
                                    </button>
                                </div>

                                <span class="text-gray-300">•</span>

                                <span
                                    class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-[11px]"
                                >
                                    {student.course?.name} - {student.section
                                        ?.name}
                                </span>

                                {#if Number(config?.ame_price) > 0}
                                    {@const ameInfo = chargeInfo(
                                        student,
                                        "ame",
                                    )}
                                    <span
                                        class="px-1.5 py-0.5 rounded border text-[11px] font-semibold {ameInfo.cls}"
                                        title={ameInfo.title}
                                    >
                                        AME: {ameInfo.text}
                                    </span>
                                {/if}

                                {#if Number(config?.investment_plan_price) > 0}
                                    {@const planInfo = chargeInfo(
                                        student,
                                        "investment_plan",
                                    )}
                                    <span
                                        class="px-1.5 py-0.5 rounded border text-[11px] font-semibold {planInfo.cls}"
                                        title={planInfo.title}
                                    >
                                        Plan: {planInfo.text}
                                    </span>
                                {/if}
                            </div>
                        </div>
                    </div>
                    <!-- Mobile BalanceBar: shown under student info -->
                    <div class="mt-2 md:hidden min-w-[300px]">
                        <div class="relative group/balance">
                            {#if $page.props.auth.is_admin}
                                <button
                                    type="button"
                                    title="Anular meses / inscripción de este estudiante"
                                    on:click|stopPropagation={() =>
                                        openAnnulModal(student)}
                                    class="absolute -top-2 right-0 z-20 inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-600 shadow-sm hover:bg-red/10 hover:text-red"
                                >
                                    <iconify-icon
                                        icon="mdi:calendar-remove-outline"
                                        width="12"
                                        height="12"
                                    ></iconify-icon>
                                    Anular
                                </button>
                            {/if}
                            <BalanceBar
                                id={`balance-bar-${student.id}-mobile`}
                                balances={student.balances.map((b) => ({
                                    ...b,
                                    ...b.months,
                                }))}
                                classes="py-0 px-0"
                                is_exempt={student.is_exempt
                                    ? student.exemption_percentage
                                    : false}
                                dayOfPayment={config.day_of_monthly_payment}
                                gracePeriod={config.grace_period}
                            />
                        </div>
                    </div>
                </td>
                <!-- Desktop BalanceBar: shown in dedicated column -->
                <td class="hidden md:table-cell min-w-[500px]">
                    <div class="relative group/balance">
                        {#if $page.props.auth.is_admin}
                            <button
                                type="button"
                                title="Anular meses / inscripción de este estudiante"
                                on:click|stopPropagation={() =>
                                    openAnnulModal(student)}
                                class="absolute -top-2 right-0 z-20 inline-flex items-center gap-1 rounded-full border border-gray-300 bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-600 shadow-sm opacity-0 group-hover/balance:opacity-100 hover:bg-red/10 hover:text-red transition-opacity"
                            >
                                <iconify-icon
                                    icon="mdi:calendar-remove-outline"
                                    width="12"
                                    height="12"
                                ></iconify-icon>
                                Anular
                            </button>
                        {/if}
                        <BalanceBar
                            id={`balance-bar-${student.id}-desktop`}
                            balances={student.balances.map((b) => ({
                                ...b,
                                ...b.months,
                            }))}
                            classes="py-0 px-0"
                            is_exempt={student.is_exempt
                                ? student.exemption_percentage
                                : false}
                            dayOfPayment={config.day_of_monthly_payment}
                            gracePeriod={config.grace_period}
                        />
                    </div>
                </td>
                <td class="group flex"
                    >{student.representative.user.name}
                    {student.representative.user.last_name}

                    <div class="relative flex items-center gap-2 mt-1 ">
                        <button
                            title="Enviar por WhatsApp"
                            on:click={() => sendToWhatsApp(student)}
                            class="text-green cursor-pointer flex  hover:bg-gray-100 group-hover:inline-flex
                           }"
                        >
                            <iconify-icon
                                icon="ic:baseline-whatsapp"
                                width="14"
                                height="14"
                               class=" ml-2
                            {hasCurrentMonthReminder(student)
                                ? 'text-emerald-700 rounded-full p-0.5 '
                                : ''}"
                            ></iconify-icon>
                            {#if hasCurrentMonthReminder(student)}
                              
                                <span
                                    class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 px-2 py-1 bg-gray-800 text-white text-[10px] rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity"
                                >
                                    Ya recordado este mes
                                </span>
                            {/if}
                        </button>
                    </div>
                </td>
            </tr>
        {/each}
    </tbody></Table
>
