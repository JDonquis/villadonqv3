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
    import { onMount, onDestroy, tick } from "svelte";
    import { page } from "@inertiajs/svelte";
    import PaymentCard from "../../components/PaymentCard.svelte";

    export let data = { students: { data: [] }, accounts: { data: [] } };
    export let config = {
        day_of_monthly_payment: 0,
        grace_period: 0,
    };

    export let searched_students = [];
    let isSearchTableOpen = false;
    let searchInputRef;
    let searchTableRef;
    let showMobileFilters = false;
    let isMobileView = false;
    const currentDate = new Date();
    let dolarPrice = 0; // Inicializamos en 0

    const currentDateString = currentDate.toISOString().split("T")[0];

    const emptyDataForm = {
        date: currentDateString,
        reported_date: currentDateString,
        students: [],
        account_payment_id: "",
        payment_concept_id: "",
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
    let currentPayment = null;
    let submitStatus = "Registrar";

    function applyLastPaymentMethod() {
        const savedPaymentMethod = localStorage.getItem("lastPaymentMethod");
        if (savedPaymentMethod) {
            $form.account_payment_id = +savedPaymentMethod;
        }
    }

    function openRegistrarPago() {
        showModal = true;
        searchInputRef.focus();
        currentPayment = null;
        if (submitStatus === "Solo lectura") {
            $form.reset();
            submitStatus = "Registrar";
        }
        applyLastPaymentMethod();
    }

    function savePaymentMethod(methodId) {
        localStorage.setItem("lastPaymentMethod", methodId);
    }

    let concepts = [...(data?.concepts ?? [])];
    let showConceptModal = false;
    let editingConceptId = null;
    let conceptForm = useForm({
        name: "",
        description: "",
        price: "",
    });

    $: isConceptPayment = !!$form.payment_concept_id;

    function applyConceptToStudents() {
        const concept = concepts.find(
            (c) => String(c.id) === String($form.payment_concept_id),
        );
        if (!concept) return;
        const price = parseFloat(concept.price);
        if (!(price > 0)) return;
        $form.students = $form.students.map((s) => ({
            ...s,
            amount_in_dolars: price.toFixed(2),
            amount_in_bs: (price * dolarPrice).toFixed(2),
        }));
        $form.total_in_dolars = $form.students
            .reduce(
                (total, s) => total + (parseFloat(s.amount_in_dolars) || 0),
                0,
            )
            .toFixed(2);
        $form.total_in_bs = ($form.total_in_dolars * dolarPrice).toFixed(2);
    }

    function getStudentDebtInDollars(student) {
        const explicitDebt = Number(student?.total_debt ?? 0);
        if (Number.isFinite(explicitDebt) && explicitDebt > 0) {
            return explicitDebt.toFixed(2);
        }

        const balances = Array.isArray(student?.balances)
            ? student.balances
            : [];
        const totalDebt = balances.reduce((sum, balance) => {
            const value = Number(
                balance?.total_debt ?? balance?.current_debt ?? 0,
            );
            return sum + (Number.isFinite(value) ? value : 0);
        }, 0);

        return totalDebt > 0 ? totalDebt.toFixed(2) : "0.00";
    }

    async function focusBolivaresTarget() {
        await tick();

        const totalInput = document.getElementById("payment-total-bs");
        if ($form.students.length === 1) {
            if (totalInput) {
                totalInput.focus();
                totalInput.select();
            }
            return;
        }

        const lastStudent = $form.students[$form.students.length - 1];
        const targetId = lastStudent
            ? `student-bs-${lastStudent.id ?? $form.students.length - 1}`
            : "";
        const studentInput = targetId
            ? document.getElementById(targetId)
            : null;

        if (studentInput) {
            studentInput.focus();
            studentInput.select();
        }
    }

    $: showPerStudentAmounts =
        $form.students.length > 1 || submitStatus === "Solo lectura";

    function syncSingleStudentTotals(type, value) {
        console.log($form.students);
        if ($form.students.length > 1) return;

        const studentIndex = 0;
        const student = $form.students[studentIndex];

        if (type === "usd") {
            if (value === "" || value == null) {
                $form.total_in_dolars = "";
                $form.total_in_bs = "";
                $form.students[studentIndex] = {
                    ...student,
                    amount_in_dolars: "",
                    amount_in_bs: "",
                };
                return;
            }

            const numericValue = parseFloat(value) || 0;
            const usdTotal = String(numericValue);
            const bsTotal =
                dolarPrice > 0
                    ? (numericValue * dolarPrice).toFixed(2)
                    : "0.00";

            $form.total_in_dolars = usdTotal;
            $form.total_in_bs = bsTotal;
            $form.students[studentIndex] = {
                ...student,
                amount_in_dolars: usdTotal,
                amount_in_bs: bsTotal,
            };
            return;
        }

        if (type === "bs") {
            console.log("syncSingleStudentTotals called with:", {
                type,
                value,
                dolarPrice,
            });

            const numericValue = parseBsInput(value);
            const bsTotal = numericValue.toFixed(2);
            const usdTotal =
                dolarPrice > 0
                    ? (numericValue / dolarPrice).toFixed(2)
                    : "0.00";

            console.log("Calculated totals:", { bsTotal, usdTotal });

            $form.total_in_bs = bsTotal;
            $form.total_in_dolars = usdTotal;
            $form.students[studentIndex] = {
                ...student,
                amount_in_dolars: usdTotal,
                amount_in_bs: bsTotal,
            };
        }
    }

    const openCreateConcept = () => {
        $conceptForm.reset();
        editingConceptId = null;
        showConceptModal = true;
    };

    const openEditConcept = (concept) => {
        $conceptForm.reset();
        $conceptForm.name = concept.name;
        $conceptForm.description = concept.description || "";
        $conceptForm.price = concept.price;
        editingConceptId = concept.id;
        showConceptModal = true;
    };

    const saveConcept = async () => {
        if (!$conceptForm.name.trim()) {
            displayAlert({
                type: "error",
                message: "El nombre del concepto es obligatorio",
            });
            return;
        }
        if ($conceptForm.price === "" || $conceptForm.price == null) {
            $conceptForm.price = 0;
        }
        try {
            if (editingConceptId) {
                const { data } = await axios.put(
                    `/dashboard/pagos/conceptos/${editingConceptId}`,
                    {
                        name: $conceptForm.name,
                        description: $conceptForm.description,
                        price: $conceptForm.price,
                    },
                );
                const updated = data.concept;
                concepts = concepts.map((c) =>
                    c.id === updated.id ? updated : c,
                );
                displayAlert({
                    type: "success",
                    message: "Concepto actualizado correctamente",
                });
            } else {
                const { data } = await axios.post(
                    "/dashboard/pagos/conceptos",
                    {
                        name: $conceptForm.name,
                        description: $conceptForm.description,
                        price: $conceptForm.price,
                    },
                );
                const created = data.concept;
                concepts = [...concepts, created];
                $form.payment_concept_id = created.id;
                applyConceptToStudents();
                displayAlert({
                    type: "success",
                    message: "Concepto creado correctamente",
                });
            }
        } catch (error) {
            displayAlert({
                type: "error",
                message:
                    error?.response?.data?.message ||
                    error?.response?.data?.errors?.name?.[0] ||
                    "Error al guardar el concepto",
            });
        }
        showConceptModal = false;
    };

    const deleteConcept = async (id) => {
        if (!confirm("¿Está seguro de eliminar este concepto?")) return;
        try {
            await axios.delete(`/dashboard/pagos/conceptos/${id}`);
            concepts = concepts.filter((c) => c.id !== id);
            if (String($form.payment_concept_id) === String(id)) {
                $form.payment_concept_id = "";
            }
            displayAlert({
                type: "success",
                message: "Concepto eliminado correctamente",
            });
        } catch (error) {
            displayAlert({
                type: "error",
                message:
                    error?.response?.data?.message ||
                    "Error al eliminar el concepto",
            });
        }
    };

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

    function formatFechaHumana(dateString) {
        if (!dateString) return "";
        const date = new Date(`${dateString}T00:00:00`);
        return new Intl.DateTimeFormat("es-VE", {
            day: "numeric",
            month: "long",
            year: "numeric",
        }).format(date); // "21 de agosto de 2025"
    }

    // Agrupación reactiva de pagos por fecha (respeta paginación server-side)
    $: paymentsByDate = Object.entries(
        (data?.payments?.data || []).reduce((acc, p) => {
            const key = p.raw_date || p.date;
            acc[key] = acc[key] || [];
            acc[key].push(p);
            return acc;
        }, {}),
    ).sort(([a], [b]) => b.localeCompare(a)); // más recientes primero
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
    // $: $form.total_in_dolars, exchange();

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

        console.log({ value, raw, digits, integerPart, decimalPart });
        console.log(
            "Formatted BS:",
            `${Number(integerPart).toLocaleString("de-DE")},${decimalPart}`,
        );
        return `${Number(integerPart).toLocaleString("de-DE")},${decimalPart}`;
    }

    function parseBsInput(value) {
        const raw = String(value ?? "").replace(/[^\d]/g, "");

        if (!raw) return 0;

        const integerPart = raw.slice(0, -2) || "0";
        const decimalPart = raw.slice(-2).padStart(2, "0");

        return Number(`${integerPart}.${decimalPart}`);
    }

    function restoreBsCaret(el, formattedValue, digitsBeforeCaret, wasAtEnd) {
        let pos = formattedValue.length;
        if (!wasAtEnd) {
            pos = 0;
            let count = 0;
            while (pos < formattedValue.length && count < digitsBeforeCaret) {
                if (/\d/.test(formattedValue[pos])) count++;
                pos++;
            }
        }
        tick().then(() => {
            if (document.activeElement === el) {
                el.setSelectionRange(pos, pos);
            }
        });
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

    function updateViewportState() {
        isMobileView = window.innerWidth < 768;
        if (!isMobileView) {
            showMobileFilters = true;
        }
    }

    // Agregar y remover el event listener
    onMount(() => {
        updateViewportState();
        window.addEventListener("resize", updateViewportState);
        document.addEventListener("mousedown", handleClickOutside);
    });
    onDestroy(() => {
        window.removeEventListener("resize", updateViewportState);
        document.removeEventListener("mousedown", handleClickOutside);
    });

    function handleDelete(id, payment = null) {
        if ((payment || selectedRow.data)?.status == 0) {
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
                currentPayment = null;
                showModal = false;
            },
        });
    }

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

    async function fillFormToEdit(payment = null) {
        showModal = true;
        submitStatus = "Solo lectura";
        const selectedData = payment || selectedRow.data;
        currentPayment = selectedData;

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
        $form.payment_concept_id = selectedData.payment_concept_id || "";
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

    // $: console.log($form);
</script>

<svelte:head>
    <title>Pagos</title>
</svelte:head>

<Alert />

<h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-3">Pagos</h2>

<Modal
    bind:showModal
    keyShortcut="n"
    onKeyShortcut={openRegistrarPago}
    classes="w-full h-full md:h-auto md:w-11/12"
>
    <h2 slot="header" class="text-sm text-center">REGISTRO DE PAGO</h2>

    <form
        id="a-form"
        on:submit={handleSubmit}
        action=""
        class="w-full md:grid md:grid-cols-12 md:gap-x-5 lg:gap-x-10 px-0 md:px-3 md:pl-2"
    >
        <div
            class="relative w-full md:col-span-4 md:col-start-9 md:row-start-"
        >
            <Input
                type="select"
                label={"Concepto de pago"}
                bind:value={$form.payment_concept_id}
                error={$form.errors?.payment_concept_id}
                readonly={submitStatus === "Solo lectura"}
                on:change={applyConceptToStudents}
            >
                <option value="">Mensualidad / Inscripciones</option>
                {#each concepts as concept}
                    <option value={concept.id}>
                        {concept.name}
                        {#if concept.price != null && Number(concept.price) > 0}
                            - ${concept.price}
                        {/if}
                    </option>
                {/each}
            </Input>
            {#if submitStatus !== "Solo lectura"}
                <button
                    type="button"
                    class="absolute right-0 top-0 md:top-5 text-xs font-semibold text-color2 bg-gray-200 hover:text-color1 hover:shadow-md px-1.5 py-0.5 rounded-md"
                    on:click={openCreateConcept}
                >
                    + Crear concepto
                </button>
            {/if}
        </div>
        <div
            class="col-span-8 md:col-start-1 md:row-start- md:-top-12 relative mx-auto md:mx-0 text-left w-full"
        >
            <!-- <Input
                type="text"
                required={true}
                label={"Nombre"}
                bind:value={$form.name}
                error={$form.errors?.name}
            /> -->
            <div
                class="w-fit mx-auto md:mx-0 mt-4 md:mt-0 z-50 lg right-20 md:right-64 flex items-center rounded-xl bg-gray-50 border border-gray-400"
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
                    placeholder="Buscar Estudiante / representante"
                    class={`block  w-full rounded-xl py-1.5 pr-5 text-gray-700 -full   md:w-56 lg:w-96 placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40`}
                    bind:this={searchInputRef}
                    on:input={(e) => {
                        search_student(e.target.value);
                    }}
                    on:focus={(e) => {
                        e.target.select();
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
                class={`${isSearchTableOpen ? "block bg-gray-200 z-50" : "hidden"} p-2 md:p-6 w-full absolute font-semibold rounded-md top-12 max-h-[370px] min-h-[300px] overflow-y-scroll z-50 shadow-xl [&_*]:px-4 [&_*]:py-2 [&_*]:text-left  text-sm  mt-5`}
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
                            on:click={async () => {
                                // Verificar si el estudiante ya está en el arreglo
                                if (
                                    !$form.students.some(
                                        (s) => s.id === student.id,
                                    )
                                ) {
                                    const defaultDebt =
                                        getStudentDebtInDollars(student);
                                    const defaultBs =
                                        dolarPrice > 0
                                            ? (
                                                  Number(defaultDebt) *
                                                  dolarPrice
                                              ).toFixed(2)
                                            : "0.00";

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
                                            total_debt: defaultDebt,
                                            amount_in_dolars: defaultDebt,
                                            amount_in_bs: defaultBs,
                                        },
                                    ];

                                    $form.total_in_dolars = $form.students
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
                                        Number($form.total_in_dolars) *
                                        dolarPrice
                                    ).toFixed(2);

                                    applyConceptToStudents();
                                }
                                isSearchTableOpen = false;
                                await focusBolivaresTarget();
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

            <div class="mt-2 md:mt-4 space-y-3">
                {#each $form.students as student, i}
                    <div class="neumorphism md:p-3 lg:mx-2 rounded-lg">
                        <div
                            class="flex justify-between items-center mb-1 mt-3"
                        >
                            <span class="flex flex-col md:flex-row">
                                <span>
                                    {student.name}
                                    {student.last_name}

                                </span>

                                <div>

                                    <span
                                        class="bg-gray-200 md:ml-2 text-gray-700 px-1.5 py-0.5 rounded border border-gray-200/50 font-mono text-xs"
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
                                        class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border-gray-200/40 text-xs"
                                    >
                                        {student.course_name}-{student.section_name}
                                    </span>
                                </div>
                            </span>

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
                        </div>

                        {#if !isConceptPayment && submitStatus !== "Solo lectura"}
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

                        {#if showPerStudentAmounts}
                            <div class="grid grid-cols-2 gap-3 mt-3 mb-2">
                                <div
                                    class="flex flex-col items-start col-span-1"
                                >
                                    <b class="pr-1 text-xs">$. USD</b>
                                    <input
                                        type="number"
                                        min="0"
                                        placeholder="Dólares"
                                        step="0.01"
                                        class="w-full py-1 px-1 md:py-2 md:px-2 border-gray-300 rounded-md border focus:outline-0"
                                        data-student-amount="usd"
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
                                <div class="flex flex-col items-start">
                                    <b class="pr-1 text-xs">Bs. VES</b>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        min="0"
                                        step="0.01"
                                        class="w-full border py-1 px-1 md:py-2 md:px-2 border-gray-300 rounded-md focus:outline-"
                                        data-student-amount="bs"
                                        value={formatBsInput(
                                            student.amount_in_bs || "",
                                        )}
                                        placeholder="Bolívares"
                                        readonly={submitStatus ===
                                            "Solo lectura"}
                                        on:focus={(e) => {
                                            if (e.target.value !== "") {
                                                e.target.select();
                                            }
                                        }}
                                        id={`student-bs-${student.id ?? i}`}
                                        on:input={(e) => {
                                            const el = e.target;
                                            const rawValue = el.value;
                                            const start = el.selectionStart;
                                            const end = el.selectionEnd;
                                            const wasAtEnd =
                                                start === end &&
                                                start === rawValue.length;
                                            const digitsBeforeCaret = (
                                                rawValue
                                                    .slice(0, start)
                                                    .match(/\d/g) || []
                                            ).length;

                                            const numericBs =
                                                parseBsInput(rawValue);
                                            const bsValue =
                                                numericBs.toFixed(2);
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

                                            const formattedValue =
                                                formatBsInput(bsValue);
                                            restoreBsCaret(
                                                el,
                                                formattedValue,
                                                digitsBeforeCaret,
                                                wasAtEnd,
                                            );
                                        }}
                                    />
                                </div>
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>
        </div>
        <!-- <div
            class="hidden md:block md:col-span-8 md:col-start-1 md:row-start-2 w-full"
        >
            <table
                id="selected_student"
                class={`${$form.students.length > 0 ? "md:block" : "hidden"} hidden  w-full font-semibold relative    text-sm  mt-1 p-2`}
            >
                <thead
                    class="[&_*]:px-2 md:[&_*]:px-4 [&_*]:py-2 [&_*]:text-left"
                >
                    <tr>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="neumorphism p-2 rounded-lg m-2">
                    {#each $form.students as student, i}
                        <tr
                            class={` w-full [&_td]:px-2 [&_td*]:py-2 text-sm cursor-pointer  border-gray-500`}
                        >
                            <td class="md:min-w-[300px]">
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

                                <span class="text-gray-300">•</span>

                                <span
                                    class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-xs"
                                >
                                    {student.course_name}-{student.section_name}
                                </span>
                            </td>
                            {#if showPerStudentAmounts}
                                <td>
                                    <div class="flex flex-col items-start">
                                        <b class="pr-1 text-xs">$. USD</b>
                                        <input
                                            type="number"
                                            min="0"
                                            placeholder="Dólares"
                                            step="0.01"
                                            class="w-20 py-2 px-2 border-gray-400 rounded-md border focus:outline-0"
                                            data-student-amount="usd"
                                            value={student.amount_in_dolars ||
                                                ""}
                                            readonly={submitStatus ===
                                                "Solo lectura"}
                                            on:input={(e) => {
                                                $form.students[i] = {
                                                    ...$form.students[i],
                                                    amount_in_dolars:
                                                        e.target.value,
                                                    amount_in_bs: (
                                                        e.target.value *
                                                        dolarPrice
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
                                            id={`student-bs-${student.id ?? i}`}
                                            class="w-24 border py-2 px-2 border-gray-400 rounded-md focus:outline-"
                                            data-student-amount="bs"
                                            value={formatBsInput(
                                                student.amount_in_bs || "",
                                            )}
                                            placeholder="Bolívares"
                                            readonly={submitStatus ===
                                                "Solo lectura"}
                                            on:focus={(e) => {
                                                if (e.target.value !== "") {
                                                    e.target.select();
                                                }
                                            }}
                                            on:input={(e) => {
                                                const el = e.target;
                                                const rawValue = el.value;
                                                const start = el.selectionStart;
                                                const end = el.selectionEnd;
                                                const wasAtEnd =
                                                    start === end &&
                                                    start === rawValue.length;
                                                const digitsBeforeCaret = (
                                                    rawValue
                                                        .slice(0, start)
                                                        .match(/\d/g) || []
                                                ).length;

                                                const numericBs =
                                                    parseBsInput(rawValue);
                                                const bsValue =
                                                    numericBs.toFixed(2);
                                                const usdValue =
                                                    dolarPrice > 0
                                                        ? (
                                                              numericBs /
                                                              dolarPrice
                                                          ).toFixed(2)
                                                        : "0.00";

                                                $form.students[i] = {
                                                    ...$form.students[i],
                                                    amount_in_bs: bsValue,
                                                    amount_in_dolars: usdValue,
                                                };
                                                $form.total_in_bs =
                                                    $form.students
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
                                                    $form.total_in_bs /
                                                    dolarPrice
                                                ).toFixed(2);

                                                const formattedValue =
                                                    formatBsInput(bsValue);
                                                restoreBsCaret(
                                                    el,
                                                    formattedValue,
                                                    digitsBeforeCaret,
                                                    wasAtEnd,
                                                );
                                            }}
                                        />
                                    </div>
                                </td>
                            {/if}

                            <td class="max-w-[70px] bg-gray-200">
                                <button
                                    type="button"
                                    class="h-full hover:bg-paper ml-1"
                                    on:click={() => {
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
                            <td
                                colspan="7"
                                class="md:px-3 pb-10 max-w-[350px] md:max-w-[900px]"
                            >
                                {#if !isConceptPayment && submitStatus !== "Solo lectura"}
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
        </div> -->

        <div
            class={`w-full md:col-span-4 md:col-start-9 md:row-start-0 grid grid-cols-2 gap-x-3 md:gap-x-5 ${$form.students.length > 0 ? "block" : "hidden"} md:grid`}
        >
            <Input
                type="date"
                required={true}
                label={"F. de la transacción"}
                bind:value={$form.date}
                error={$form.errors?.date}
                max={currentDateString}
                readonly={submitStatus === "Solo lectura"}
                classes={"col-span-1"}
            />
            <Input
                type="date"
                required={true}
                label={"F. de reporte"}
                bind:value={$form.reported_date}
                error={$form.errors?.reported_date}
                max={currentDateString}
                readonly={submitStatus === "Solo lectura"}
                classes={"col-span-1"}
            />
            <Input
                type="select"
                label={"Método de pago"}
                bind:value={$form.account_payment_id}
                error={$form.errors?.account_payment_id}
                required={true}
                readonly={submitStatus === "Solo lectura"}
                classes={"col-span-2 "}
                on:change={(e) => savePaymentMethod(e.target.value)}
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

            {#if showPerStudentAmounts}
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
                <div class="col-span-1">
                    <span class="block font-medium text-sm">
                        Total en USD:
                    </span>
                    <span class="text-gray-500"> $ </span>
                    <b>{$form.total_in_dolars}</b>
                </div>
                <div class="col-span-1">
                    <span class="block font-medium text-sm">
                        Total en VES:
                    </span>
                    <span class="text-gray-500"> Bs </span>
                    <b>{formatBsInput($form.total_in_bs)}</b>
                </div>
            {:else if $form.students.length > 0}
                <Input
                    type="number"
                    label={"Total en Dólares ($)"}
                    required={true}
                    min="0"
                    step="0.01"
                    value={$form.total_in_dolars || ""}
                    error={$form.errors?.total_in_dolars}
                    classes={"col-span-1"}
                    on:focus={(e) => {
                        if (e.target.value !== "") {
                            e.target.select();
                        }
                    }}
                    on:input={(e) =>
                        syncSingleStudentTotals("usd", e.target.value)}
                />

                <Input
                    id="payment-total-bs"
                    type="text"
                    label={"Total en Bolívares (Bs)"}
                    min="0"
                    step="0.01"
                    value={formatBsInput($form.total_in_bs)}
                    error={$form.errors?.total_in_bs}
                    classes={"col-span-1"}
                    on:focus={(e) => {
                        if (e.target.value !== "") {
                            e.target.select();
                        }
                    }}
                    on:input={(e) => {
                        const el = e.target;
                        const rawValue = el.value;
                        const start = el.selectionStart;
                        const end = el.selectionEnd;
                        const wasAtEnd =
                            start === end && start === rawValue.length;
                        const digitsBeforeCaret = (
                            rawValue.slice(0, start).match(/\d/g) || []
                        ).length;

                        syncSingleStudentTotals("bs", rawValue);

                        const formattedValue = formatBsInput($form.total_in_bs);
                        restoreBsCaret(
                            el,
                            formattedValue,
                            digitsBeforeCaret,
                            wasAtEnd,
                        );
                    }}
                />
            {/if}
            <Input
                type="number"
                label={"Referencia"}
                required={true}
                bind:value={$form.reference}
                error={$form.errors?.reference}
                readonly={submitStatus === "Solo lectura"}
                classes={"col-span-2"}
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
                    class={` max-w-[430px] mt-7  items-center justify-center gap-3 ${!$form.students.length > 0 ? "hidden  " : "flex animated-button"} `}
                    disabled={$form.processing}
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
        {/if}

        {#if submitStatus === "Solo lectura" && $page.props.auth.is_admin}
            <div class="flex justify-end col-span-12 mt-7">
                <button
                    type="button"
                    class="flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-red text-white text-sm font-semibold hover:bg-red/90 transition-colors"
                    on:click={() =>
                        handleDelete(
                            currentPayment?.id || $form.id,
                            currentPayment,
                        )}
                >
                    <iconify-icon
                        icon="material-symbols:delete-outline"
                        width="20"
                        height="20"
                    ></iconify-icon>
                    Eliminar pago
                </button>
            </div>
        {/if}
    </form>
</Modal>

<Modal bind:showModal={showConceptModal} classes="w-fitcontent">
    <h2 slot="header" class="text-sm text-center">
        GESTIÓN DE CONCEPTOS DE PAGO
    </h2>

    <div class="px-4">
        {#if concepts.length > 0}
            <div
                class="max-h-44 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100"
            >
                {#each concepts as concept}
                    <div
                        class="flex items-center justify-between gap-3 px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p
                                class="font-semibold text-sm text-gray-800 truncate"
                            >
                                {concept.name}
                                {#if concept.price != null && Number(concept.price) > 0}
                                    <span
                                        class="font-mono text-xs text-gray-500"
                                    >
                                        (${concept.price})
                                    </span>
                                {/if}
                            </p>
                            {#if concept.description}
                                <p class="text-xs text-gray-500 truncate">
                                    {concept.description}
                                </p>
                            {/if}
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                type="button"
                                class="text-xs font-semibold text-blue-600 hover:text-blue-800"
                                on:click={() => openEditConcept(concept)}
                            >
                                Editar
                            </button>
                            <button
                                type="button"
                                class="text-xs font-semibold text-red-600 hover:text-red-800"
                                on:click={() => deleteConcept(concept.id)}
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                {/each}
            </div>
        {:else}
            <p class="text-sm text-gray-400 text-center py-4">
                Aún no hay conceptos. Crea uno para poder registrar pagos por
                concepto.
            </p>
        {/if}

        <div class="border-t border-gray-100 mt-4 pt-4">
            <h3 class="text-xs font-semibold text-gray-700 mb-1">
                {editingConceptId ? "Editar concepto" : "Nuevo concepto"}
            </h3>
            <Input
                type="text"
                label={"Nombre"}
                required={true}
                bind:value={$conceptForm.name}
                error={$conceptForm.errors?.name}
            />
            <Input
                type="text"
                label={"Descripción"}
                bind:value={$conceptForm.description}
            />
            <Input
                type="number"
                label={"Precio ($)"}
                min="0"
                step="0.01"
                bind:value={$conceptForm.price}
                error={$conceptForm.errors?.price}
            />
            <div class="flex justify-end gap-5 mt-4">
                <button
                    type="button"
                    class="animated-button w-full mt-2 flex items-center justify-center gap-3 text-xs"
                    on:click={saveConcept}
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
                    <iconify-icon
                        class="text"
                        icon="material-symbols:save-sharp"
                        width="18"
                        height="18"
                    />
                    <span class="text">
                        {editingConceptId
                            ? "Guardar cambios"
                            : "Guardar concepto"}
                    </span>
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
    </div>
</Modal>

<div class=" items-start justify-between gap-5 mt-1">
    <div class="flex justify-between items-end gap-3 w-full">
        {#if data.total_income}
            <div class=" flex items-center max-w-fit gap-2">
                <span class="font-semibold text-xs md:text-base"
                    >Total ingresos:</span
                >
                {#if showTotalIncome}
                    <b
                        class={`text-sm bg-white shadow-sm px-2 text-green transition-all duration-200`}
                    >
                        ${data.total_income}
                    </b>
                {/if}

                <button
                    type="button"
                    class="inline-flex items-center justify-center bg-white/10 p-2 text-gray-700 transition hover:bg-green/10 focus:outline-none"
                    on:click={() => {
                        showTotalIncome = !showTotalIncome;
                    }}
                    aria-label={showTotalIncome
                        ? "Ocultar total"
                        : "Mostrar total"}
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

        <div class=" items-center gap-5 ml-auto mb-3">
            <p class="text-sm text-gray-500">
                1$ <span class="hidden md:inline"
                    >el {formatFechaCorta(dateOfDolarPrice)}</span
                >
                = {#if dolarPrice}{dolarPrice}{:else}<iconify-icon
                        icon="line-md:loading-loop"
                        width="24"
                        height="24"
                    ></iconify-icon>{/if} Bs
            </p>
            <div class="hidden sm:block">
                <button
                    class="animated-button ml-auto w-fitcontent"
                    title="Aprieta la tecla N"
                    on:click={openRegistrarPago}
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
            <button
                type="button"
                class={` fixed-bottom-mobile fab sm:hidden bg-color1 text-white`}
                title="Aprieta la tecla N"
                on:click={openRegistrarPago}
                aria-label="Registrar pago"
            >
                <iconify-icon icon="mdi:plus" width="26" height="26"
                ></iconify-icon>
            </button>
        </div>
    </div>

    <div class="flex-1 min-w-0">
        <Search
            bind:showModal={showMobileFilters}
            inlineFilters={!isMobileView}
            filtersOptions={{
                payment_concept_id: {
                    type: "select",
                    multiple: true,
                    label: "Concepto de pago",
                    options: [
                        {
                            id: "regular",
                            name: "Mensualidad / Inscripciones",
                            color: "color1",
                        },
                        ...concepts.map((c) => ({
                            id: String(c.id),
                            name: c.name,
                        })),
                    ],
                },
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
    </div>
</div>

<!-- MÓVIL (< 640px): Cards agrupadas por fecha -->
<div class="sm:hidden space-y-4 mb-10">
    {#if paymentsByDate.length === 0}
        <div class="text-center py-8 text-gray-500 bg-white rounded-xl">
            No hay datos
        </div>
    {:else}
        {#each paymentsByDate as [date, payments]}
            <section class=" rounded-xl shadow-sm overflow-hidden">
                <header class="px-4 py-3 flex items-center justify-between">
                    <span class="font-semibold text-color1 text-sm"
                        >{formatFechaHumana(date)}</span
                    >
                    <span class="text-sm text-gray-500"
                        >{payments.length} pago{payments.length > 1
                            ? "s"
                            : ""}</span
                    >
                </header>
                <div class="space-y-3">
                    {#each payments as payment}
                        <PaymentCard
                            {payment}
                            onSelect={() => fillFormToEdit(payment)}
                            {dolarPrice}
                        />
                    {/each}
                </div>
            </section>
        {/each}
    {/if}
</div>

<!-- ESCRITORIO (≥ 640px): Tabla existente -->
<Table
    classes="hidden sm:block"
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
    <thead slot="thead" class="sticky top-0 z-40">
        <tr>
            <th>ID</th>
            <th>Fecha de la transacción</th>
            <th>Estudiante/s</th>
            <th>Total USD$</th>
            <th>Total Bs</th>
            <th>Método de pago</th>
            <th>Concepto</th>
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
                classes={`${row.status === 0 ? "bg-red text-gray-400 bg-opacity-20 opacity-70" : ""} `}
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
                                    class="flex items-center gap-1.5 text-xs text-gray-500"
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
                <td>
                    {#if row.payment_concept}
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200"
                        >
                            {row.payment_concept.name}
                        </span>
                    {:else}
                        <span class="text-gray-400 text-xs">
                            Mensualidad / Inscripciones
                        </span>
                    {/if}
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
