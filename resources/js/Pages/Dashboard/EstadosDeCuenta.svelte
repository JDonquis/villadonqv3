<script>
    import BalanceBar from "../../components/BalanceBar.svelte";
    import Search from "../../components/Search.svelte";
    import Table from "../../components/Table.svelte";
    import html2canvas from "html2canvas";
    import { page } from "@inertiajs/svelte";
    import { displayAlert } from "../../stores/alertStore";

    export let data = [];
    export let config;

    let showTotalDebt = false;
    $: tableData = {
        ...data?.students,
        filters: {
            debt_filter:
                new URLSearchParams($page.url.split("?")[1] || "").get(
                    "debt_filter",
                ) || "",
        },
    };

    $: console.log({ data }, { tableData });
    $: console.log(config);
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

<Search placeholder="Buscar estudiante..." class="mb-4" />

{#if data.total_debt}
    <div class="w-max mb-5 flex flex-wrap items-center gap-2">
        <span class="font-semibold">Deuda:</span>
        <b
            class={`text-sm ${showTotalDebt ? "opacity-100" : "opacity-0 blur-sm"} text-red transition-all duration-200`}
        >
            {showTotalDebt ? `$${data.total_debt}` : "•••"}
        </b>
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
    <thead slot="thead">
        <tr>
            <th>Estudiante</th>
            <th>Balance</th>
            <th>Rep Legal</th>
        </tr>
    </thead>
    <tbody slot="tbody">
        {#each tableData.data as student}
            <tr>
                <td class=" space-y-2">
                    <div class="flex items-center gap-2">

                        <div class="flex flex-col gap-1 text-sm">
                                <!-- Línea Superior: Nombre completo del estudiante -->
                                <div
                                    class="font-semibold text-gray-800 capitalize leading-snug"
                                >
                                    {student.name}
                                    {student.last_name}
                                </div>

                                <!-- Línea Inferior: Metadatos organizados en chips/badges -->
                                <div
                                    class="flex items-center gap-1.5 flex-wrap text-xs text-gray-500"
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

                                    <span class="text-gray-300">•</span>

                                    <span
                                        class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-[11px]"
                                    >
                                        {student.course?.name} - {student
                                            .section?.name}
                                    </span>
                                </div>
                            </div>

                 
                    </div>
                </td>
                <td>
                    <BalanceBar
                        id={`balance-bar-${student.id}`}
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
