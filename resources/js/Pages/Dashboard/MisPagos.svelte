<script>
    import { page } from "@inertiajs/svelte";
    import BalanceBar from "../../components/BalanceBar.svelte";
    import Table from "../../components/Table.svelte";
    import Modal from "../../components/Modal.svelte";
    import Input from "../../components/Input.svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import { useForm } from "@inertiajs/svelte";
    import axios from "axios";
    import { getDolarRateByDate } from "../../utils/dolarApi";
    import debounce from "lodash/debounce";
    import { tick, onMount } from "svelte";
    import { fade } from "svelte/transition";
    import ColorsPayMethods from "../../components/ColorsPayMethods";
    import Search from "../../components/Search.svelte";

    export let config = {
        day_of_monthly_payment: 0,
        grace_period: 0,
    };

    let dolarPrice = 0; // Inicializamos en 0
    let dateOfDolarPrice = "";
    let showFormPay = false;
    let paymentSelectRef;
    const LOCAL_STORAGE_KEY = "misPagos:selectedPaymentAccount";
    async function updateDolarPriceByDate(targetDate) {
        if (!targetDate) return;
        try {
            const { rate, dateFound } = await getDolarRateByDate(targetDate);
            dolarPrice = rate;
            dateOfDolarPrice = dateFound;

            // Recalcular montos con la nueva tasa
            recalculateTotals();

            if (dateOfDolarPrice !== $form.date) {
                displayAlert({
                    type: "info",
                    message: `No se encontró tasa para la fecha seleccionada. Se tomó la tasa del día ${formatFechaCorta(dateOfDolarPrice)}`,
                });
            }
        } catch (error) {
            if (error.name === "NoRate") {
                displayAlert({
                    type: "error",
                    message:
                        "No se encontró registro de tasa BCV en los días previos a la fecha seleccionada.",
                });
                return;
            }

            console.error("Error buscando tasa histórica:", error);
            displayAlert({
                type: "error",
                message:
                    "No se pudo obtener la tasa para la fecha seleccionada. Verifica la conexión.",
            });
        }
    }

    function recalculateTotals() {
        if (dolarPrice <= 0) return;

        // Mapeamos los estudiantes para actualizar sus cálculos con la nueva tasa
        $form.students = $form.students.map((s) => {
            const dolars = parseFloat(s.amount_in_dolars) || 0;
            return {
                ...s,
                amount_in_bs: (dolars * dolarPrice).toFixed(2),
            };
        });

        // Recalcular los acumulados del formulario general
        $form.total_in_dolars = $form.students
            .reduce(
                (total, s) => total + (parseFloat(s.amount_in_dolars) || 0),
                0,
            )
            .toFixed(2);

        $form.total_in_bs = ($form.total_in_dolars * dolarPrice).toFixed(2);
    }

    onMount(() => {
        const saved = localStorage.getItem(LOCAL_STORAGE_KEY);
        if (saved) {
            $form.account_payment_id = +saved;
        }
    });

    $: if ($form.account_payment_id) {
        try {
            localStorage.setItem(LOCAL_STORAGE_KEY, +$form.account_payment_id);
        } catch (e) {
            // ignore
        }
    }

    function computeRepresentativeDebt() {
        if (!data?.students) return 0;
       

        let total = 0;
        for (const student of data.students) {
            for (const balance of student.balances) {
               
                total += balance.total_debt || 0;
            }
        }
        return total;
    }

    // ⚡ REACTIVIDAD DE SVELTE:
    // Cada vez que el usuario mueva la fecha en el Input, esto se ejecutará solo.
    let lastFetchedDate = null;

    $: if ($form.date && $form.date !== lastFetchedDate) {
        lastFetchedDate = $form.date;
        updateDolarPriceByDate($form.date);
    }

    // Modificamos la función de conversión para que use el dolarPrice dinámico
    $: $form.total_in_dolars, exchange();

    function exchange() {
        if (data?.students?.length === 1) return;

        if (dolarPrice > 0 && $form.total_in_dolars) {
            $form.total_in_bs = (
                parseFloat($form.total_in_dolars) * dolarPrice
            ).toFixed(2);
        }
    }

    function formatBsInput(value) {
        const raw = String(value ?? "").replace(/[^\d]/g, "");

        if (!raw) return "";

        const digits = raw.replace(/^0+(?=\d)/, "");
        const integerPart = digits.slice(0, -2) || "0";
        const decimalPart = digits.slice(-2).padStart(2, "0");

        return `${Number(integerPart).toLocaleString("de-DE")},${decimalPart}`;
    }

    function parseBsInput(value) {
        const raw = String(value ?? "").replace(/[^\d]/g, "");

        if (!raw) return 0;

        const integerPart = raw.slice(0, -2) || "0";
        const decimalPart = raw.slice(-2).padStart(2, "0");

        return Number(`${integerPart}.${decimalPart}`);
    }

    function syncSingleStudentTotals(type, value) {
        if (!data?.students || data.students.length !== 1) return;

        const studentIndex = 0;

        if (type === "usd") {
            const rawValue = value ?? "";

            if (rawValue === "") {
                $form.total_in_dolars = "";
                $form.total_in_bs = "";
                $form.students[studentIndex] = {
                    ...($form.students[studentIndex] || {}),
                    amount_in_dolars: "",
                    amount_in_bs: "",
                };
                return;
            }

            const numericValue = parseFloat(rawValue) || 0;
            const usdTotal = String(numericValue);
            const bsTotal =
                dolarPrice > 0
                    ? (numericValue * dolarPrice).toFixed(2)
                    : "0.00";

            $form.total_in_dolars = usdTotal;
            $form.total_in_bs = bsTotal;
            $form.students[studentIndex] = {
                ...($form.students[studentIndex] || {}),
                amount_in_dolars: usdTotal,
                amount_in_bs: bsTotal,
            };
            return;
        }

        const numericValue = parseBsInput(value);
        const bsTotal = numericValue.toFixed(2);
        const usdTotal =
            dolarPrice > 0 ? (numericValue / dolarPrice).toFixed(2) : "0.00";

        $form.total_in_bs = bsTotal;
        $form.total_in_dolars = usdTotal;
        $form.students[studentIndex] = {
            ...($form.students[studentIndex] || {}),
            amount_in_dolars: usdTotal,
            amount_in_bs: bsTotal,
        };
    }

    const currentDate = new Date();
    const currentDateString = currentDate.toISOString().split("T")[0];
    export let data;
    const emptyDataForm = {
        date: currentDateString,
        reported_date: currentDateString,
        students: [],
        account_payment_id: "",
        total_in_dolars: "",
        total_in_bs: "",
        reference: "",
        observations: "",
    };



    let form = useForm({ ...emptyDataForm });

    $: console.log($form.account_payment_id);
    function formatCurrency(value) {
        return (
            "$" +
            Number(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
    }

    const monthLabels = {
        september: "Sep",
        october: "Oct",
        november: "Nov",
        december: "Dic",
        january: "Ene",
        february: "Feb",
        march: "Mar",
        april: "Abr",
        may: "May",
        june: "Jun",
        july: "Jul",
        august: "Ago",
    };

    function statusLabel(status) {
        const map = {
            paid: "Pagado",
            debt: "Deuda",
            pending: "Pendiente",
            partially_paid: "Parcial",
        };
        return map[status] || status;
    }

    function hasMonthValue(balance, key) {
        const value = balance.months?.[key];
        return value !== null && value !== undefined && Number(value) !== 0;
    }

    $: selectedPaymentAccount =
        data?.accounts?.data?.find(
            (account) =>
                account.id == $form.account_payment_id,
        ) || null;

    function getPaymentDetails(account) {
        if (!account) return [];

        return [
            { label: "Banco", value: account.bank },
            { label: "Titular", value: account.person_name },
            { label: "Cédula", value: account.ci },
            { label: "Teléfono", value: account.phone_number },
            { label: "Cuenta", value: account.account_number },
            { label: "Usuario", value: account.username },
        ].filter(
            (field) =>
                field.value !== null &&
                field.value !== undefined &&
                field.value !== "",
        );
    }

    async function copyToClipboard(value, label) {
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
</script>

<svelte:head>
    <title>Mis Pagos</title>
</svelte:head>

<div class="bg-white p-2 md:py-3 md:px-5 rounded-lg flex flex-col gap-2">
    {#if data.students.length === 0}
        <p class="text-gray-400 text-sm">No tienes hijos inscritos.</p>
    {/if}

    <div class="md:grid grid-cols-12 gap-6">
        <div class="col-span-8">
            {#each data.students as student, i}
                {#if data.students.length > 1}
                    <div class="md:flex gap-4 md:gap-5">
                        <div>
                            <div
                                class="md:min-w-[200px] flex items-center mb-1"
                            >
                                <span class="font-semibold text-gray-800">
                                    {student.name}
                                    {student.last_name}
                                </span>
                            </div>
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

                            <!-- Separador opcional o punto -->
                            <span class="text-gray-300">•</span>

                            <!-- Curso y Sección -->
                            <span
                                class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-xs"
                            >
                                {student.course}-{student.section}
                            </span>
                        </div>
                        {#if showFormPay}
                        <div class="mt-3 md:mt-0 flex gap-4">
                            <div class="flex flex-col items-start">
                                <b class="pr-1 text-xs">$. USD</b>
                                <input
                                    id={`amount_in_dolars_${i}`}
                                    type="number"
                                    min="0"
                                    placeholder="Dólares"
                                    step="0.01"
                                    class="w-20 px-1 py-1 md:py-2 md:px-2 border-gray-400 rounded-md border focus:outline-0"
                                    value={$form.students[i]
                                        ?.amount_in_dolars || ""}
                                    on:input={(e) => {
                                        const rawDollarValue =
                                            e.target.value ?? "";
                                        const numericDollarValue =
                                            parseFloat(rawDollarValue) || 0;

                                        $form.students[i] = {
                                            ...$form.students[i],
                                            amount_in_dolars: rawDollarValue,
                                            amount_in_bs:
                                                dolarPrice > 0
                                                    ? (
                                                          numericDollarValue *
                                                          dolarPrice
                                                      ).toFixed(2)
                                                    : "0.00",
                                        };
                                        $form.total_in_dolars = $form.students
                                            .reduce(
                                                (total, s) =>
                                                    total +
                                                    (parseFloat(
                                                        s.amount_in_dolars,
                                                    ) || 0),
                                                0,
                                            )
                                            .toString();
                                        $form.total_in_bs =
                                            dolarPrice > 0
                                                ? (
                                                      parseFloat(
                                                          $form.total_in_dolars,
                                                      ) * dolarPrice
                                                  ).toFixed(2)
                                                : "0.00";
                                    }}
                                />
                            </div>

                            <div class="flex flex-col items-start">
                                <b class="pr-1 text-xs">Bs. VES</b>
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    min="0"
                                    step="0.01"
                                    class="w-28 border px-1 py-1 md:py-2 md:px-2 border-gray-400 rounded-md focus:outline-"
                                    value={formatBsInput(
                                        $form.students[i]?.amount_in_bs || "",
                                    )}
                                    placeholder="Bolívares"
                                    on:input={(e) => {
                                        const numericBs = parseBsInput(
                                            e.target.value,
                                        );
                                        const bsValue = numericBs.toFixed(2);
                                        const usdValue =
                                            dolarPrice > 0
                                                ? (
                                                      numericBs / dolarPrice
                                                  ).toFixed(2)
                                                : "0.00";

                                        $form.students[i] = {
                                            ...$form.students[i],
                                            amount_in_bs: bsValue,
                                            amount_in_dolars: usdValue,
                                        };
                                        $form.total_in_bs = $form.students
                                            .reduce(
                                                (total, s) =>
                                                    total +
                                                    (parseFloat(
                                                        s.amount_in_bs,
                                                    ) || 0),
                                                0,
                                            )
                                            .toFixed(2);
                                        $form.total_in_dolars = (
                                            $form.total_in_bs / dolarPrice
                                        ).toFixed(2);
                                    }}
                                />
                            </div>
                        </div>
                        {/if}
                    </div>
                {:else}
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center mb-1">
                                <span class="font-semibold text-gray-800">
                                    {student.name}
                                    {student.last_name}
                                </span>
                            </div>
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
                                class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-xs"
                            >
                                {student.course}-{student.section}
                            </span>
                        </div>
                    </div>
                {/if}
                {#each student.balances as balance}
                    <div class="flex flex-col gap-2 mt-2 mb-2">
                        <BalanceBar
                            balances={student.balances.map((b) => ({
                                ...b,
                                ...b.months,
                            }))}
                            amountToPay={$form.students[i]?.amount_in_dolars}
                            is_exempt={student.is_exempt
                                ? student.exemption_percentage
                                : false}
                            dayOfPayment={config.day_of_monthly_payment}
                            gracePeriod={config.grace_period}
                            dolarRate={dolarPrice}
                        />
                    </div>
                {/each}
            {/each}
        </div>
        <div class="col-span-4">
        {#if !showFormPay}
            <button
                class="ml-auto animated-button w-fitcontent"
                on:click={async (e) => {
                    // ensure hidden first to replay animation
                    showFormPay = false;
                    await tick();
                    // set amounts
                    const totalDebt = computeRepresentativeDebt();
                    syncSingleStudentTotals("usd", totalDebt)

                    // restore saved selection or focus select
                    const saved = localStorage.getItem(LOCAL_STORAGE_KEY);
                    if (saved) {
                        $form.account_payment_id = +saved;
                    }

                    showFormPay = true;

                        // small delay to ensure element mounted
                        await tick();
                        document.querySelector("#amount_in_dolars_0").focus();
                    
                }}
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="arr-2"
                    viewBox="0 0 24 24"
                >
                    <path
                        d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                    ></path>
                </svg>
                <span class="text">Procesar pago</span>
                <span class="circle"></span>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="arr-1"
                    viewBox="0 0 24 24"
                >
                    <path
                        d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                    ></path>
                </svg>
            </button>
        {/if}
            {#if showFormPay}
            <form class="formPay col-span-4 w grid grid-cols-2 gap-3 md:gap-x-5" in:fade={{duration:180}} out:fade={{duration:120}}>
                <Input
                    bind:this={paymentSelectRef}
                    type="select"
                    label={"Método de pago"}
                    bind:value={$form.account_payment_id}
                    error={$form.errors?.account_payment_id}
                    required={true}
                >
                    {#each data.accounts.data as account}
                        <option
                            value={account.id}
                            class={`border-l-4 mix-blend-difference  }`}
                        >
                            {account.payment_method_name}
                            {#if account.bank}- {account.bank}{/if}
                            {#if account.cash_currency}- {account.cash_currency}{/if}
                            {#if account.username}- {account.username}{/if}
                        </option>
                    {/each}
                </Input>
                <Input
                    type="date"
                    required={true}
                    label={"Fecha del pago"}
                    bind:value={$form.date}
                    error={$form.errors?.date}
                    max={currentDateString}
                />

                {#if selectedPaymentAccount}
                    <div
                        class="col-span-2 mt-2 rounded-lg border border-gray-200 bg-gray-50 p-2 md:p-3"
                    >
                        <div
                            class="mb-2 flex items-center justify-between gap-3"
                        >
                            <span class="text-sm font-semibold text-gray-700">
                                Datos para {selectedPaymentAccount.payment_method_name}
                            </span>
                        </div>

                        <div class="space-y-2">
                            {#each getPaymentDetails(selectedPaymentAccount) as detail}
                                <div
                                    class="flex items-center justify-between gap-2 md:gap-3 rounded-md  bg-white px-2 py-2"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="text-[10px] font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            {detail.label}
                                        </div>
                                        <div
                                            class=" truncate text-xs md:text-sm font-medium text-gray-800"
                                        >
                                            {detail.value}
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-100"
                                        on:click={() =>
                                            copyToClipboard(
                                                detail.value,
                                                detail.label,
                                            )}
                                    >
                                        Copiar
                                    </button>
                                </div>
                            {/each}
                        </div>
                    </div>
                {/if}

                {#if data.students.length > 1}
                    <Input
                        type="hidden"
                        label={"Total en Dólares ($)"}
                        required={true}
                        readonly={true}
                        bind:value={$form.total_in_dolars}
                        error={$form.errors?.total_in_dolars}
                    />

                    <Input
                        type="hidden"
                        label={"Total en Bolívares (Bs)"}
                        readonly={true}
                        bind:value={$form.total_in_bs}
                        error={$form.errors?.total_in_bs}
                    />
                    <p>
                        <span class="block font-medium text-sm">
                            Total en USD:
                        </span>
                        <span class="text-gray-500"> $ </span>
                        <b>{$form.total_in_dolars}</b>
                    </p>
                    <p>
                        <span class="block font-medium text-sm">
                            Total en VES:
                        </span>
                        <span class="text-gray-500"> Bs </span>
                        <b>{formatBsInput($form.total_in_bs)}</b>
                    </p>
                {:else}
                    <Input
                        type="number"
                        label={"Total en Dólares ($)"}
                        required={true}
                        min="0"
                        step="0.01"
                        value={$form.total_in_dolars || ""}
                        error={$form.errors?.total_in_dolars}
                        on:focus={(e) => {
                            if (e.target.value !== "") {
                                e.target.select();
                            }
                        }}
                        on:input={(e) =>
                            syncSingleStudentTotals("usd", e.target.value)}
                    />

                    <Input
                        type="text"
                        label={"Total en Bolívares (Bs)"}
                        min="0"
                        step="0.01"
                        value={formatBsInput($form.total_in_bs)}
                        error={$form.errors?.total_in_bs}
                        on:input={(e) =>
                            syncSingleStudentTotals("bs", e.target.value)}
                        on:focus={(e) => {
                            if (e.target.value !== "") {
                                e.target.select();
                            }
                        }}
                    />
                {/if}
                <button
                    class="animated-button col-span-2"
                    on:click={(e) => {
                        // e.preventDefault();
                        // showModal = true;
                        // searchInputRef.focus();
                        // if (submitStatus === "Solo lectura") {
                        //     $form.reset();
                        //     submitStatus = "Registrar";
                        // }
                    }}
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="arr-2"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                        ></path>
                    </svg>
                    <span class="text-sm md:text-base text"
                        >Confirmar que pagué este monto</span
                    >
                    <span class="circle"></span>
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="arr-1"
                        viewBox="0 0 24 24"
                    >
                        <path
                            d="M16.1716 10.9999L10.8076 5.63589L12.2218 4.22168L20 11.9999L12.2218 19.778L10.8076 18.3638L16.1716 12.9999H4V10.9999H16.1716Z"
                        ></path>
                    </svg>
                </button>
                <button type="button" class="bg-gray-200 rounded-full text-gray-600 hover:bg-gray-400  mt-2 py-2 px-4" on:click={() => { showFormPay = false; }}>Descartar</button>
            </form>
            {/if}
        </div>
    </div>

    <div class="bg-white pt-5 mt-5 rounded-lg text-gray-600">
        <span class="font-medium text-gray-700 px-4">Pagos realizados:</span>
        <Table
            allowFilters={false}
            serverSideData={data?.payments}
            otherSelectOptions={[
                {
                    label: "Ver detalles",
                    icon: "mdi:eye",
                    classes: "bg-blue",
                    // onClick: fillFormToEdit,
                },
            ]}
            edit={false}
            pagination={true}
        >
            <thead slot="thead" class="sticky top-0 z-50">
                <tr>
                    <th>ID</th>
                    <th>Fecha de la transacción</th>
                    <th>Estudiante/s</th>
                    <th>Total USD$</th>
                    <th>Total Bs</th>
                    <th>Método de pago</th>
                    <th>Referencia</th>
                    <!-- <th>Representante</th> -->
                </tr>
            </thead>

            <tbody slot="tbody">
                {#each data?.payments?.data as row, i}
                    <tr
                        rowData={row}
                        idKey="id"
                        activeClass="bg-color2 bg-opacity-10 brightness-110"
                        classes={`${row.status === 0 ? "bg-red text-gray-400 bg-opacity-10 opacity-70" : ""} `}
                    >
                        <td>
                            <span class="text-xs">
                                {row.id}
                            </span>
                        </td>
                        <td>{row.date}</td>
                        <td class="px-4 py-3 align-top">
                            <div class="space-y-2">
                                {#each row?.students as student, j}
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
                                            <!-- Monto individual (si aplica) -->
                                            {#if student.pivot?.amount_in_dolars}
                                                <span
                                                    class="font-medium bg-green/20 px-1.5 py-0.5 rounded border border-emerald-200/60"
                                                >
                                                    ${student.pivot
                                                        .amount_in_dolars}
                                                </span>
                                            {/if}

                                            <!-- Cédula -->
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

                                            <!-- Separador opcional o punto -->
                                            <span class="text-gray-300">•</span>

                                            <!-- Curso y Sección -->
                                            <span
                                                class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-[11px]"
                                            >
                                                {student.course?.name} -
                                                {student.section?.name}
                                            </span>
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </td>
                        <!-- <td
                    >{row.representative.user.name}
                    {row.representative.user.last_name}</td
                > -->
                        <td>${row.total_in_dolars}</td>
                        <td>{row.total_in_bs} Bs</td>
                        <td class="">
                            <!-- <ColorsPayMethods
                        payment_method_id={row.account_payment.method.name}
                        accounts={data.accounts.data}
                    /> -->
                            <span
                                class={`h-5 text-${ColorsPayMethods()[row.account_payment.method.name]}  bg-${ColorsPayMethods()[row.account_payment.method.name]} w-5  left-0 top-0`}
                                >|</span
                            >
                            {row.account_payment.method.name}
                            {#if row.account_payment.bank}- {row.account_payment
                                    .bank}{/if}
                            {#if row.account_payment.cash_currency}-
                                {row.account_payment.cash_currency}{/if}
                            {#if row.account_payment.username}-
                                {row.account_payment.username}{/if}
                        </td>
                        <td>{row.reference}</td>
                    </tr>
                {/each}
            </tbody>
        </Table>
    </div>
</div>
