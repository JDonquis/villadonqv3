<script>
    import Table from "../../components/Table.svelte";
    import Modal from "../../components/Modal.svelte";
    import Input from "../../components/Input.svelte";
    import Alert from "../../components/Alert.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import { useForm } from "@inertiajs/svelte";
    import axios from "axios";
    import { getDolarRateByDate } from "../../utils/dolarApi";
    import debounce from "lodash/debounce";
    import ColorsPayMethods from "../../components/ColorsPayMethods";
    import BalanceBar from "../../components/BalanceBar.svelte";
    import Search from "../../components/Search.svelte";
    import SelectableRow from "../../components/SelectableRow.svelte";
    import { onMount, onDestroy } from "svelte";
    import { page } from "@inertiajs/svelte";

    export let data = { students: { data: [] }, accounts: { data: [] } };
    export let config = {
        day_of_monthly_payment: 0,
        grace_period: 0,
    };

    export let searched_students = [];
    let isSearchTableOpen = false;
    let searchInputRef;
    let searchTableRef;
    const currentDate = new Date();
    let dolarPrice = 0; // Inicializamos en 0

    const currentDateString = currentDate.toISOString().split("T")[0];

    const emptyDataForm = {
        date: currentDateString,
        reported_date: currentDateString,
        students: [],
        account_payment_id: "",
        total_in_dolars: "1",
        total_in_bs: "",
        reference: "",
        observations: "",
    };

    let form = useForm({ ...emptyDataForm });
    let formEdit = useForm({ ...emptyDataForm });

    let showModal = false;
    let showTotalIncome = false;
    $: showModalFormEdit = false;
    let selectedRow = { status: false, data: null };
    let submitStatus = "Registrar";

    function formatFechaCorta(dateString) {
        if (!dateString) return "";

        // Agregamos 'T00:00:00' para evitar desfases por zona horaria UTC
        const date = new Date(`${dateString}T00:00:00`);

        return new Intl.DateTimeFormat("es-VE", {
            weekday: "short", // 'vie.'
            day: "numeric", // '21'
            month: "short", // 'ago.'
        }).format(date);
    }
    // ==========================================
    // 🌐 NUEVA FUNCIÓN PARA BUSCAR TASA POR FECHA
    // ==========================================

    let dateOfDolarPrice = "";
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

    document.addEventListener("keydown", ({ key }) => {
        if (key === "Escape") {
            selectedRow = { status: false, data: null };
        }
    });
    function handleSubmit(event) {
        if (submitStatus === "Solo lectura") {
            return;
        }
        event.preventDefault();
        $form.clearErrors();

        $form.post("/dashboard/pagos", {
            onError: (errors) => {
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
            onSuccess: (mensaje) => {
                $form.reset();
                displayAlert({
                    type: "success",
                    message: "Ok todo salió bien",
                });
                showModal = false;
            },
        });
    }

    const search_student = debounce(async (search_text) => {
        isSearchTableOpen = search_text.length > 0;
        try {
            const response = await axios.get(
                "/dashboard/pagos/search-student?",
                {
                    params: { search: search_text },
                },
            );
            searched_students = response.data;
            // Aquí puedes actualizar el estado con los resultados de la búsqueda
        } catch (error) {
            console.error("Error al buscar estudiantes:", error);
        }
    }, 300);

    // Ocultar tabla al hacer click fuera
    function handleClickOutside(event) {
        if (
            isSearchTableOpen &&
            !searchTableRef?.contains(event.target) &&
            !searchInputRef?.contains(event.target)
        ) {
            isSearchTableOpen = false;
            searched_students = [];
        }
    }

    // Agregar y remover el event listener
    onMount(() => {
        document.addEventListener("mousedown", handleClickOutside);
    });
    onDestroy(() => {
        document.removeEventListener("mousedown", handleClickOutside);
    });

    function handleDelete(id) {
        if (selectedRow.data?.status == 0) {
            displayAlert({
                type: "error",
                message: "Este pago ya ha sido eliminado",
            });
            return;
        }
        $form.delete(`/dashboard/pagos/${id}`, {
            onBefore: () => confirm(`¿Está seguro de eliminar este pago?`),
            onError: (errors) => {
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
            onSuccess: (mensaje) => {
                displayAlert({
                    type: "success",
                    message: "Pago eliminado correctamente",
                });
                selectedRow = { status: false, data: null };
            },
        });
    }

    async function fillFormToEdit() {
        showModal = true;
        submitStatus = "Solo lectura";
        const selectedData = selectedRow.data;
        console.log({ selectedData });

        // const studentsWithBalances = await Promise.all(
        //     selectedData.students.map(async (s) => {
        //         const response_student = await getBalanceByStudentId(s.id);
        //         const studentData = Array.isArray(response_student)
        //             ? response_student[0]
        //             : response_student;

        //         return {
        //             ...s,
        //             balances:
        //                 studentData?.balances?.length > 0
        //                     ? studentData.balances
        //                     : s.balances || [],
        //         };
        //     }),
        // );

        $form.id = selectedData.id;
        // console.log({ studentsWithBalances });
        $form.students = selectedData.students.map((s) => ({
            id: s.id,
            name: s.name,
            last_name: s.last_name,
            ci: s.ci,
            course_name: s.course?.name || "",
            section_name: s.section?.name || "",
            legal_rep_name:
                s.representative?.user?.name +
                " " +
                s.representative?.user?.last_name,
            // balances: s.balances || [],
            amount_in_dolars: s.pivot?.amount_in_dolars,
            amount_in_bs: s.pivot?.amount_in_bs,
        }));
        console.log(selectedData.date);
        $form.date = selectedData.raw_date;
        // $form.reported_date = new Date(selectedData?.reported_date)?.toISOString().split("T")[0] || null;
        $form.account_payment_id = selectedData.account_payment_id;
        $form.total_in_dolars = selectedData.total_in_dolars;
        $form.reference = selectedData.reference;
        $form.observations = selectedData.observations;
    }

    const getBalanceByStudentId = async (studentId) => {
        try {
            const response = await axios.get(
                `/dashboard/pagos/search-student`,
                {
                    params: { id: studentId },
                },
            );
            console.log(response.data);
            return response.data;
        } catch (error) {
            console.log(error);
            return [];
        }
    };

    $: console.log($form);
</script>

<svelte:head>
    <title>Pagos</title>
</svelte:head>

<Alert />

<Modal bind:showModal classes="w-11/12">
    <h2 slot="header" class="text-sm text-center">REGISTRO DE PAGO</h2>

    <form
        id="a-form"
        on:submit={handleSubmit}
        action=""
        class="w-full grid md:grid-cols-12 md:gap-x-5 px-3 pl-2"
    >
        <div class="col-span-8 relative mx-auto text-left w-full">
            <!-- <Input
                type="text"
                required={true}
                label={"Nombre"}
                bind:value={$form.name}
                error={$form.errors?.name}
            /> -->
            <div
                class="w-fit z-50 lg right-20 md:right-64 flex items-center rounded-xl bg-gray-50 border border-gray-400"
            >
                <span class="absolute">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="w-5 h-5 mx-3 text-gray-400"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"
                        />
                    </svg>
                </span>
                <input
                    type="search"
                    placeholder="Buscar Estudiante"
                    class={`block w-full rounded-xl py-1.5 pr-5 text-gray-700 -full   md:w-56  placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40`}
                    bind:this={searchInputRef}
                    on:input={(e) => {
                        search_student(e.target.value);
                    }}
                    on:click={(e) => {
                        e.stopPropagation();
                        isSearchTableOpen = true;
                    }}
                />
            </div>
            <table
                id="students-search-table"
                bind:this={searchTableRef}
                class={`${isSearchTableOpen ? "block bg-gray-200 z-50" : "hidden"} p-6 w-full absolute font-semibold rounded-md top-12 max-h-[370px] min-h-[300px] overflow-y-scroll z-50 shadow-xl [&_*]:px-4 [&_*]:py-2 [&_*]:text-left  text-sm  mt-5`}
            >
                <thead class="">
                    <tr>
                        <th>Estudiante</th>
                        <th>C.I</th>
                        <th>Grado/Año</th>
                        <th>Rep Legal</th>
                    </tr>
                </thead>
                <tbody>
                    {#each searched_students as student}
                        <tr
                            class={`text-xs rounded-xl overflow-hidden py-1 hover:bg-black/10  [&_*]:px-4 [&_*]:py-2 cursor-pointer bg-white bg-opacity-10 border-gray-500`}
                            on:click={() => {
                                // Verificar si el estudiante ya está en el arreglo
                                if (
                                    !$form.students.some(
                                        (s) => s.id === student.id,
                                    )
                                ) {
                                    $form.students = [
                                        ...$form.students,
                                        {
                                            id: student.id,
                                            name: student.name,
                                            balances: student.balances || [],
                                            last_name: student.last_name,
                                            ci: student.ci,
                                            document_type:
                                                student.document_type,
                                            course_name: student.course.name,
                                            section_name: student.section.name,
                                            legal_rep_name:
                                                student.representative.user
                                                    .name +
                                                " " +
                                                student.representative.user
                                                    .last_name,
                                            balances: student.balances,
                                            is_exempt: student.is_exempt,
                                        },
                                    ];
                                }
                                isSearchTableOpen = false;
                            }}
                        >
                            <td class="rounded-l-lg"
                                >{student.name} {student.last_name}</td
                            >
                            <td>
                                {#if student.document_type}
                                    <span style=" padding: 0 "
                                        >{student.document_type}-</span
                                    >
                                {/if}{student.ci}</td
                            >
                            <td>
                                {student.course.name}
                                {student.section.name}
                            </td>

                            <td class="rounded-r-lg"
                                >{student.representative.user.name}
                                {student.representative.user.last_name}</td
                            >
                        </tr>
                    {/each}
                </tbody>
            </table>

            <table
                id="selected_student"
                class={`${$form.students.length > 0 ? "block" : "hidden"}   w-full font-semibold relative    text-sm overflow-hidden mt-5`}
            >
                <thead class="[&_*]:px-4 [&_*]:py-2 [&_*]:text-left">
                    <tr>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {#each $form.students as student, i}
                        <tr
                            class={` w-full [&_td]:px-2 [&_td*]:py-2 text-sm cursor-pointer  border-gray-500`}
                        >
                         <td class="min-w-[300px]">
                                <div class="flex items-center mb-1">
                                    <span>
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
                                    {student.course_name}-{student.section_name}
                                </span>
                            </td>
                            <td>
                                <div class="flex flex-col items-start">
                                    <b class="pr-1 text-xs">$. USD</b>
                                    <input
                                        type="number"
                                        min="0"
                                        placeholder="Dólares"
                                        step="0.01"
                                        class="w-20 py-2 px-2 border-gray-400 rounded-md border focus:outline-0"
                                        value={student.amount_in_dolars || ""}
                                        readonly={submitStatus ===
                                            "Solo lectura"}
                                        on:input={(e) => {
                                            $form.students[i] = {
                                                ...$form.students[i],
                                                amount_in_dolars:
                                                    e.target.value,
                                                amount_in_bs: (
                                                    e.target.value * dolarPrice
                                                ).toFixed(2),
                                            };
                                            $form.total_in_dolars =
                                                $form.students
                                                    .reduce(
                                                        (total, s) =>
                                                            total +
                                                            (parseFloat(
                                                                s.amount_in_dolars,
                                                            ) || 0),
                                                        0,
                                                    )
                                                    .toFixed(2);
                                            $form.total_in_bs = (
                                                $form.total_in_dolars *
                                                dolarPrice
                                            ).toFixed(2);
                                        }}
                                    />
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-col items-start">
                                    <b class="pr-1 text-xs">Bs. VES</b>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        min="0"
                                        step="0.01"
                                        class="w-24 border py-2 px-2 border-gray-400 rounded-md focus:outline-"
                                        value={formatBsInput(student.amount_in_bs || "")}
                                        placeholder="Bolívares"
                                        readonly={submitStatus ===
                                            "Solo lectura"}
                                        on:focus={(e) => {
                                            if (e.target.value !== "") {
                                                e.target.select();
                                            }
                                        }}
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
                            </td>

                           

                            <td class="max-w-[70px]">
                                <button
                                    type="button"
                                    class="h-full hover:bg-paper ml-1"
                                    on:click={() => {
                                        // Eliminar el estudiante del arreglo
                                        $form.students = $form.students.filter(
                                            (s) => s.id !== student.id,
                                        );
                                    }}
                                >
                                    <iconify-icon icon="line-md:close"
                                    ></iconify-icon>
                                </button>
                            </td>
                        </tr>
                        <tr class=" ">
                            <td colspan="7" class="px-3 pb-10">
                                {#if submitStatus !== "Solo lectura"}
                                    <BalanceBar
                                        balances={student.balances.map((b) => ({
                                            ...b,
                                            ...b.months,
                                        }))}
                                        amountToPay={student.amount_in_dolars}
                                        is_exempt={student.is_exempt
                                            ? student.exemption_percentage
                                            : false}
                                        dayOfPayment={config.day_of_monthly_payment}
                                        gracePeriod={config.grace_period}
                                        dolarRate={dolarPrice}
                                    />
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>

        <div class="col-span-4 w-full grid md:grid-cols-2 md:gap-x-5">
            <Input
                type="date"
                required={true}
                label={"Fecha de la transacción"}
                bind:value={$form.date}
                error={$form.errors?.date}
                max={currentDateString}
                readonly={submitStatus === "Solo lectura"}
            />
            <Input
                type="date"
                required={true}
                label={"Fecha de reporte"}
                bind:value={$form.reported_date}
                error={$form.errors?.reported_date}
                max={currentDateString}
                readonly={submitStatus === "Solo lectura"}
            />
            <Input
                type="select"
                label={"Método de pago"}
                bind:value={$form.account_payment_id}
                error={$form.errors?.account_payment_id}
                required={true}
                readonly={submitStatus === "Solo lectura"}
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
                type="number"
                label={"Total en Dólares ($)"}
                required={true}
                readonly={true}
                bind:value={$form.total_in_dolars}
                error={$form.errors?.total_in_dolars}
            />
            <Input
                type="number"
                label={"Total en Bolívares (Bs)"}
                readonly={true}
                bind:value={$form.total_in_bs}
                error={$form.errors?.total_in_bs}
            />
            <Input
                type="number"
                label={"Referencia"}
                required={true}
                bind:value={$form.reference}
                error={$form.errors?.reference}
                readonly={submitStatus === "Solo lectura"}
            />
            <Input
                type="textarea"
                label={"Observaciones"}
                classes={"col-span-2"}
                bind:value={$form.observations}
                error={$form.errors?.observations}
                readonly={submitStatus === "Solo lectura"}
            />
        </div>

        {#if submitStatus !== "Solo lectura"}
            <div class="flex justify-end col-span-12">
                <button
                    type="submit"
                    class="animated-button max-w-[430px] mt-7 flex items-center justify-center gap-3"
                    disabled={$form.processing}
                >
                    <iconify-icon
                        class="text"
                        icon="material-symbols:save-sharp"
                        width="24"
                        height="24"
                    />
                    {#if $form.processing}
                        <span class="text"> Cargando...</span>
                    {:else}
                        <span class="text">{submitStatus}</span>
                    {/if}
                    <span class="circle"></span>
                </button>
            </div>
        {/if}
    </form>
</Modal>

<Search
    filtersOptions={{
        date: {
            type: "date",
            label: "Fecha de la transacción",
        },
        account_payment_id: {
            type: "select",
            multiple: true,
            label: "Método de pago",
            options: data.accounts.data.map((account) => ({
                id: account.id,
                name: [
                    account.payment_method_name,
                    account?.bank || "",
                    account?.cash_currency || "",
                    account?.username || "",
                ]
                    .filter(Boolean)
                    .join(" "),
                color: ColorsPayMethods()[account.payment_method_name],
            })),
        },
    }}
/>

<div class="flex justify-between items-center gap-10 mt-1">
    {#if data.total_income}
        <div class="w-max mb-1 flex flex-wrap items-center gap-2">
            <span class="font-semibold">Total ingresos:</span>
            <b
                class={`text-sm bg-white shadow-sm px-2 ${showTotalIncome ? "opacity-100" : "opacity-0 blur-sm"} text-green transition-all duration-200`}
            >
                {showTotalIncome ? `$${data.total_income}` : "•••"}
            </b>
            <button
                type="button"
                class="inline-flex items-center justify-center bg-white/10 p-2 text-gray-700 transition hover:bg-green/10 focus:outline-none"
                on:click={() => {
                    showTotalIncome = !showTotalIncome;
                }}
                aria-label={showTotalIncome ? "Ocultar total" : "Mostrar total"}
            >
                <iconify-icon
                    icon={showTotalIncome
                        ? "formkit:eyeclosed"
                        : "mdi:eye-outline"}
                    width="24"
                    height="24"
                ></iconify-icon>
            </button>
        </div>
    {/if}

    <div class="flex items-center gap-5">
        <p class="text-sm text-gray-500 mt-4">
            1$ el {formatFechaCorta(dateOfDolarPrice)} = {#if dolarPrice}{dolarPrice}{:else}<iconify-icon
                    icon="line-md:loading-loop"
                    width="24"
                    height="24"
                ></iconify-icon>{/if} Bs
        </p>
        <button
            class="animated-button w-fitcontent"
            on:click={(e) => {
                e.preventDefault();
                showModal = true;
                searchInputRef.focus();
                if (submitStatus === "Solo lectura") {
                    $form.reset();
                    submitStatus = "Registrar";
                }
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
            <span class="text">Registrar pago</span>
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
    </div>
</div>

<Table
    {selectedRow}
    allowFilters={false}
    serverSideData={data?.payments}
    on:clickDeleteIcon={() => {
        if (!$page.props.auth.is_admin) {
            displayAlert({
                type: "error",
                message: "No tienes permisos para eliminar pagos",
            });
            return;
        }
        handleDelete(selectedRow.data?.id);
    }}
    otherSelectOptions={[
        {
            label: "Ver detalles",
            icon: "mdi:eye",
            classes: "bg-blue",
            onClick: fillFormToEdit,
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
            <SelectableRow
                rowData={row}
                idKey="id"
                {selectedRow}
                activeClass="bg-color2 bg-opacity-10 brightness-110"
                on:select={(e) => {
                    selectedRow = e.detail;
                    $formEdit.defaults(
                        e.detail.data ? { ...row } : { ...emptyDataForm },
                    );
                }}
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
                                            ${student.pivot.amount_in_dolars}
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
                                        {student.course?.name} - {student
                                            .section?.name}
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
                    {#if row.account_payment.cash_currency}- {row
                            .account_payment.cash_currency}{/if}
                    {#if row.account_payment.username}- {row.account_payment
                            .username}{/if}
                </td>
                <td>{row.reference}</td>
            </SelectableRow>
        {/each}
    </tbody>
</Table>

<style>
    .grid-container > div:first-child .months_to_pay {
        border-left: 3px solid white;
    }

    /* Selecciona el último DIV que es hijo directo del contenedor del grid */
    .grid-container > div:last-child .months_to_pay {
        border-right: 3px solid white;
    }
</style>
