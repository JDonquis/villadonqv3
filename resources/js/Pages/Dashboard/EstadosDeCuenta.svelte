<script>
    import BalanceBar from "../../components/BalanceBar.svelte";
    import Search from "../../components/Search.svelte";
    import Table from "../../components/Table.svelte";
    import html2canvas from "html2canvas";
    import { page, router } from "@inertiajs/svelte";
    import { displayAlert } from "../../stores/alertStore";

    export let data = [];
    export let config;

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

        const hasPartial = unpaid.some(
            (c) => (Number(c.paid_amount) || 0) > 0,
        );

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

    async function sendToWhatsApp(student) {
        const element = document.getElementById(`balance-bar-${student.id}`);

        if (!element) return;

        try {
            const canvas = await html2canvas(element, {
                scale: 2,
                backgroundColor: "#ffffff",
                logging: false,
                useCORS: true,
            });

            // Convert canvas to blob
            const blob = await new Promise((resolve) =>
                canvas.toBlob(resolve, "image/png"),
            );

            // Copy image FIRST
            const item = new ClipboardItem({ "image/png": blob });
            await navigator.clipboard.write([item]);

            let phoneNumber = student.representative.user.phone_number.replace(
                /[ -]/g,
                "",
            );

            if (!phoneNumber || phoneNumber.length < 9) return;

            if (!phoneNumber.startsWith("+") && !phoneNumber.startsWith("58")) {
                phoneNumber = "58" + phoneNumber;
            }

            phoneNumber = phoneNumber.replace("+", "");

            const text = `🔹 *Recordatorio de Pago* 🔹

Hola ${student.representative.user.name} ${student.representative.user.last_name}, esperamos que se encuentre muy bien.

Le contactamos de la administración para informarle que la mensualidad de su representado se encuentra vencida:

👤 *Estudiante:* ${student.name} ${student.last_name}

Le agradeceríamos ponerse al día a la brevedad posible para actualizar el estatus de su cuenta y recordando que la institución debe cumplir compromisos administrativos.

ℹ️ *Nota importante sobre la imagen adjunta:*
Por favor, *haga clic sobre la imagen para abrirla en pantalla completa*. Así podrá visualizar correctamente todo el calendario escolar, los meses solventes (en verde) y el desglose exacto de los montos pendientes (en rojo).

Si ya realizó el pago, por favor ignore este mensaje o envíenos el comprobante. ¡Gracias por su apoyo continuo! ✨`;

            // OPEN WHATSAPP AFTER clipboard succeeds
            window.open(
                `https://wa.me/${phoneNumber}?text=${encodeURIComponent(text)}`,
                "_blank",
            );

            // alert(
            //     "Imagen copiada al portapapeles. Solo pega la imagen en WhatsApp.",
            // );
            displayAlert({
                type: "info",
                message:
                    "Imagen copiada al portapapeles. Solo pega la imagen en WhatsApp.",
            });
        } catch (err) {
            console.error("Error al copiar al portapapeles:", err);

            // Fallback download
            const canvas = await html2canvas(element);

            const link = document.createElement("a");
            link.download = `balance-${student.name}-${student.last_name}.png`;
            link.href = canvas.toDataURL();
            link.click();

            // displayInfoAlert(
            //     "No se pudo copiar al portapapeles. Se ha descargado la imagen, por favor envíala manualmente por WhatsApp.",
            // );
            displayAlert({
                type: "info",
                message:
                    "No se pudo copiar al portapapeles. Se ha descargado la imagen, por favor envíala manualmente por WhatsApp.",
            });
        }
    }
</script>

<svelte:head>
    <title>Estados de Cuenta</title>
</svelte:head>

<h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-3">
    Estados de Cuenta
</h2>

<Search placeholder="Buscar estudiante..." class="mb-4" />

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
    <div slot="filterBox" class="flex items-center gap-2 md:gap-2">
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
    </div>
    <thead slot="thead">
        <tr>
            <th>Estudiante</th>
            <th class="hidden md:table-cell">Balance</th>
            <th>Rep Legal</th>
        </tr>
    </thead>
    <tbody slot="tbody">
        {#each tableData.data as student}
            <tr style="content-visibility: auto; contain-intrinsic-size: 0 350px;">
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
                                            <iconify-icon
                                                icon="mdi:content-copy"
                                            ></iconify-icon>
                                        </button>
                                    </div>

                                    <span class="text-gray-300">•</span>

                                    <span
                                        class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-[11px]"
                                    >
                                        {student.course?.name} - {student
                                            .section?.name}
                                    </span>

                                    {#if Number(config?.ame_price) > 0}
                                        {@const ameInfo = chargeInfo(student, "ame")}
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
                </td>
                <!-- Desktop BalanceBar: shown in dedicated column -->
                <td class="hidden md:table-cell min-w-[500px]">
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
                </td>
                <td class="group"
                    >{student.representative.user.name}
                    {student.representative.user.last_name}

                    <button
                        title="Enviar por WhatsApp"
                        on:click={() => sendToWhatsApp(student)}
                        class="text-green cursor-pointer p-1 hover:bg-gray-100 hidden group-hover:inline-flex"
                    >
                        <iconify-icon
                            icon="ic:baseline-whatsapp"
                            width="14"
                            height="14"
                        ></iconify-icon>
                    </button>
                </td>
            </tr>
        {/each}
    </tbody></Table
>
