<script>
    import { useForm } from "@inertiajs/svelte";
    import clickOutside from "../../components/ClickOutside";
    import { inertia, router } from "@inertiajs/svelte";
    import ColorsPayMethods from "../../components/ColorsPayMethods";

    import Alert from "../../components/Alert.svelte";
    import Input from "../../components/Input.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import axios from "axios";
    export let data;

    console.log({ data });

    const institution = useForm({
        name: "Jesús el Nazareno",
        active_students: "400",
        promotions: "33",
        years: "34",
        slogan: "Formando mentes brillantes para un mañana prometedor",
        courses: [1, 2, 3],
    });
    // function resizeInput(event) {
    //     event.target.style.width = event.target.value.length + "ch";
    // }

    const prices = useForm({
        ...data.prices,
    });

    function updatePrices(e) {
        e.preventDefault();

        $prices.regular_inscription_price = $prices.new_inscription_price;

        if (!confirm("¿Está seguro de guardar estos cambios?")) return;

        const formData = {
            regular_inscription_price: $prices.regular_inscription_price,
            new_inscription_price: $prices.new_inscription_price,
            monthly_payment: $prices.monthly_payment,
            day_of_monthly_payment: $prices.day_of_monthly_payment,
            grace_period: $prices.grace_period,
            ame_price: $prices.ame_price,
            investment_plan_price: $prices.investment_plan_price,
        };

        $prices.processing = true;
        $prices.defaults();

        router.put("/dashboard/configuracion/pagos", formData, {
            preserveScroll: true,
            onSuccess: () => {
                $prices.processing = false;
                displayAlert({
                    type: "success",
                    message: "Tarifas actualizadas",
                });
            },
            onError: (errors) => {
                $prices.processing = false;
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
        });
    }

    function deleteAccount(id) {
        router.delete(`/dashboard/configuracion/eliminar-cuenta/${id}`, {
            onBefore: () =>
                confirm("¿Está seguro de eliminar este metodo de pago?"),
            onSuccess: (mensaje) => {
                displayAlert({
                    type: "success",
                    message: "Método de pago eliminado",
                });
            },
            onError: (errors) => {
                if (errors.data) {
                    displayAlert({ type: "error", message: errors.data });
                }
            },
        });
    }

    const initiateNextCourse = async () => {
        const mensaje =
            "⚠️ ¿ESTÁ SEGURO DE INICIAR EL PRÓXIMO PERIODO ESCOLAR?\n\n" +
            "Esta acción NO se puede deshacer. Tenga en cuenta lo siguiente:\n\n" +
            "• El periodo actual quedará finalizado (esto NO borra ninguna información).\n" +
            "• Todas las nuevas inscripciones y movimientos financieros se registrarán bajo este nuevo ciclo.\n\n" +
            "¿Desea continuar?";

        if (!confirm(mensaje)) {
            return;
            // El usuario aceptó, aquí va tu lógica para iniciar el periodo
        }

        try {
            const response = await axios.post(
                "/dashboard/periodo-escolar/iniciar-proximo",
            );
            displayAlert({
                type: "success",
                message:
                    response.data.message || "Próximo periodo escolar iniciado",
            });
        } catch (error) {
            displayAlert({
                type: "error",
                message:
                    error.response?.data?.message ||
                    "Error al iniciar el próximo periodo escolar",
            });
        }
    };

    let showPaymentOptions = false;

    let accordionState = {
        quotas: false,
        lapses: false,
        plans: false,
    };

    let quotaSaving = false;

    const quotasForm = useForm(
        Object.fromEntries(
            (data.quotas || []).map((row) => [
                row.course_id,
                String(row.assigned ?? row.accepted ?? ""),
            ]),
        ),
    );

    function quotaAssigned(row) {
        const value = parseInt($quotasForm[row.course_id], 10);
        return Number.isNaN(value) ? 0 : value;
    }

    $: overCapacity = (data.quotas || []).some(
        (row) => row.accepted > quotaAssigned(row),
    );

    function saveQuotas(e) {
        e.preventDefault();
        if (
            !confirm(
                "¿Está seguro de guardar los cupos del periodo escolar activo?",
            )
        )
            return;
        quotaSaving = true;
        router.put(
            "/dashboard/configuracion/cupos",
            { assigned: $quotasForm.data() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    quotaSaving = false;
                    $quotasForm.defaults();
                    $quotasForm.reset();
                    displayAlert({
                        type: "success",
                        message: "Cupos actualizados correctamente",
                    });
                },
                onError: (errors) => {
                    quotaSaving = false;
                    displayAlert({
                        type: "error",
                        message:
                            errors.assigned?.[0] ||
                            errors.message ||
                            "Error al guardar los cupos",
                    });
                },
            },
        );
    }
    const momentsForm = useForm(
        Object.fromEntries(
            (data.lapses || []).map((lapse) => [
                lapse.id,
                { start: lapse.start, end: lapse.end },
            ]),
        ),
    );

    const planForm = useForm(
        Object.fromEntries(
            (data.courses || []).map((course) => [
                course.id,
                course.plan_de_estudio_code ?? "",
            ]),
        ),
    );
    let planSaving = false;

    $: planGroups = [
        {
            label: "Educación Media General",
            courses: (data.courses || []).filter((c) =>
                c.name.includes("Año"),
            ),
        },
        {
            label: "Educación Primaria",
            courses: (data.courses || []).filter((c) =>
                c.name.includes("Grado"),
            ),
        },
        {
            label: "Educación Inicial / Preescolar",
            courses: (data.courses || []).filter((c) =>
                c.name.includes("Nivel"),
            ),
        },
    ].filter((group) => group.courses.length > 0);

    function saveCoursePlans(e) {
        e.preventDefault();
        if (
            !confirm(
                "¿Está seguro de guardar los códigos de plan de estudio?",
            )
        )
            return;
        planSaving = true;
        router.put(
            "/dashboard/configuracion/planes-de-estudio",
            { codes: $planForm.data() },
            {
                preserveScroll: true,
                onSuccess: () => {
                    planSaving = false;
                    $planForm.defaults();
                    $planForm.reset();
                    displayAlert({
                        type: "success",
                        message:
                            "Códigos de plan de estudio guardados correctamente",
                    });
                },
                onError: (errors) => {
                    planSaving = false;
                    displayAlert({
                        type: "error",
                        message:
                            errors.codes?.[0] ||
                            errors.message ||
                            "Error al guardar los códigos",
                    });
                },
            },
        );
    }

    $: lapsesSorted = (data.lapses || [])
        .slice()
        .sort((a, b) => Number(a.number) - Number(b.number));

    function lapseForToday(sorted) {
        const today = new Date().toISOString().slice(0, 10);
        return (
            (sorted || []).find(
                (lapse) => today >= lapse.start && today <= lapse.end,
            ) || null
        );
    }

    $: currentLapse = lapseForToday(lapsesSorted);
    $: currentIndex = currentLapse
        ? lapsesSorted.findIndex((lapse) => lapse.id === currentLapse.id)
        : -1;
    $: nextLapse = currentIndex >= 0 ? lapsesSorted[currentIndex + 1] : null;

    function setMomentDate(id, key, value) {
        $momentsForm[id] = { ...$momentsForm[id], [key]: value };
    }

    let momentSaving = false;
    let momentClosing = false;

    function saveMomentsDates(e) {
        e.preventDefault();
        if (!confirm("¿Guardar las fechas de los momentos escolares?")) return;
        momentSaving = true;
        const rows = lapsesSorted.map((lapse) => ({
            id: lapse.id,
            start: $momentsForm[lapse.id]?.start ?? "",
            end: $momentsForm[lapse.id]?.end ?? "",
        }));
        router.put(
            "/dashboard/configuracion/momentos",
            { rows },
            {
                preserveScroll: true,
                onSuccess: () => {
                    momentSaving = false;
                    $momentsForm.defaults();
                    $momentsForm.reset();
                    displayAlert({
                        type: "success",
                        message: "Fechas de momentos guardadas correctamente",
                    });
                },
                onError: (errors) => {
                    momentSaving = false;
                    displayAlert({
                        type: "error",
                        message:
                            errors.rows?.[0] ||
                            errors.message ||
                            "Error al guardar las fechas",
                    });
                },
            },
        );
    }

    function closeCurrentMoment() {
        if (!currentLapse || !nextLapse) return;
        if (
            !confirm(
                `¿Cerrar el ${currentLapse.label} y pasar al ${nextLapse.label}? El ${nextLapse.label} quedará vigente desde hoy.`,
            )
        )
            return;
        momentClosing = true;
        router.post(
            "/dashboard/configuracion/momentos/cerrar",
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    momentClosing = false;
                    displayAlert({
                        type: "success",
                        message: "Momento cerrado y siguiente activado",
                    });
                },
                onError: (errors) => {
                    momentClosing = false;
                    displayAlert({
                        type: "error",
                        message:
                            errors.message ||
                            "No se pudo cerrar el momento",
                    });
                },
            },
        );
    }
</script>

<Alert />
<svelte:head>
    <title>Configuración</title>
</svelte:head>
<section class="bg-background">
    <div class="py-5"></div>

    <!-- <h2 class="font-bold text-xl">Configuración del perfil</h2>

    <form
        class="bg-background px-1 mx-4 md:py-9 md:pb-12 md:grid justify-between grid-flow-col md:gap-x-10 lg:gap-x-24 items-center relative"
    >
        <div class="md:min-w-[600px] max-w-[690px]">
            <span class="md:text-5xl text-color1 font-bold">
                Colegio
                <br />
                <input
                    class="md:text-5xl bg-transparent"
                    type="text"
                    bind:value={$institution.name}
                    style={`width:${$institution.name.length - 3}ch`}
                />
            </span>
            <textarea
                class="block w-full bg-transparent md:text-xl"
                type="text"
                bind:value={$institution.slogan}
            />

            <div class="flex justify-between mt-4 md:mt-14 text-color1">
                <div>
                    <label
                        class="flex items-center gap-2 mb-2 lg:mb-3 cursor-pointer"
                    >
                        <input
                            type="checkbox"
                            bind:group={$institution.courses}
                            value={1}
                            class="hidden"
                        />

                        {#if $institution.courses.includes(1)}
                            <div
                                class="bg-color1 w-6 md:w-8 aspect-square -full overflow-hidden flex items-center justify-center"
                            >
                                <iconify-icon
                                    class="text-color4 text-4xl"
                                    icon="pajamas:check-xs"
                                ></iconify-icon>
                            </div>
                            <b>Prescolar</b>
                        {:else}
                            <div
                                class="bg-gray-400 w-6 md:w-8 aspect-square -full overflow-hidden flex items-center justify-center"
                            >
                                <iconify-icon
                                    icon="octicon:no-entry-16"
                                    class="text-gray-300"
                                ></iconify-icon>
                            </div>
                            <b class="text-gray-400">Prescolar</b>
                        {/if}
                    </label>
                    <ul class="grid grid-cols-2 gap-x-3">
                        <li>1er nivel</li>
                        <li>2do nivel</li>
                        <li>3er nivel</li>
                    </ul>
                </div>
                <div>
                    <label
                        class="flex items-center gap-2 mb-2 lg:mb-3 cursor-pointer"
                    >
                        <input
                            type="checkbox"
                            bind:group={$institution.courses}
                            value={2}
                            class="hidden"
                        />
                        {#if $institution.courses.includes(2)}
                            <div
                                class="bg-color1 w-6 md:w-8 aspect-square -full overflow-hidden flex items-center justify-center"
                            >
                                <iconify-icon
                                    class="text-color4 text-4xl"
                                    icon="pajamas:check-xs"
                                ></iconify-icon>
                            </div>
                            <b>Primaria</b>
                        {:else}
                            <div
                                class="bg-gray-400 w-6 md:w-8 aspect-square -full overflow-hidden flex items-center justify-center"
                            >
                                <iconify-icon
                                    icon="octicon:no-entry-16"
                                    class="text-gray-300"
                                ></iconify-icon>
                            </div>
                            <b class="text-gray-400">Primaria</b>
                        {/if}
                    </label>
                    <ul class="grid grid-cols-2 gap-x-3">
                        <li>1er grado</li>
                        <li>2do grado</li>
                        <li>3er grado</li>
                        <li>4to grado</li>
                        <li>5to grado</li>
                        <li>6to grado</li>
                    </ul>
                </div>
                <div>
                    <label
                        class="flex items-center gap-2 mb-2 lg:mb-3 cursor-pointer"
                    >
                        <input
                            type="checkbox"
                            bind:group={$institution.courses}
                            value={3}
                            class="hidden"
                        />

                        {#if $institution.courses.includes(3)}
                            <div
                                class="bg-color1 w-6 md:w-8 aspect-square -full overflow-hidden flex items-center justify-center"
                            >
                                <iconify-icon
                                    class="text-color4 text-4xl"
                                    icon="pajamas:check-xs"
                                ></iconify-icon>
                            </div>
                            <b>Secundaria</b>
                        {:else}
                            <div
                                class="bg-gray-400 w-6 md:w-8 aspect-square -full overflow-hidden flex items-center justify-center"
                            >
                                <iconify-icon
                                    icon="octicon:no-entry-16"
                                    class="text-gray-300"
                                ></iconify-icon>
                            </div>
                            <b class="text-gray-400">Secundaria</b>
                        {/if}
                    </label>
                    <ul class="grid grid-cols-2 gap-x-3">
                        <li>1er año</li>
                        <li>2do año</li>
                        <li>3er año</li>
                        <li>4to año</li>
                        <li>5to año</li>
                    </ul>
                </div>
            </div>

            <div
                class="flex justify-between w-full mt-4 md:mt-16 md:gap-10 text-color1"
            >
                <div class="flex divide-x divide-dark">
                    <input
                        class="px-1 text-4xl bg-transparent"
                        bind:value={$institution.years}
                        style={`width:${$institution.years.length}ch`}
                    />
                    <p class="pl-3 col-span-2 leading-5 font-semibold">
                        AÑOS DE
                        <br />
                        FORMACIÓN
                    </p>
                </div>

                <div class="flex divide-x divide-dark">
                    <input
                        class="px-1 text-4xl bg-transparent"
                        bind:value={$institution.promotions}
                        style={`width:${$institution.promotions.length + 0.5}ch`}
                    />
                    <p class="pl-3 col-span-2 leading-5 font-semibold">
                        PROMOCIONES
                        <br />
                        GRADUADAS
                    </p>
                </div>

                <div class="flex divide-x divide-dark">
                    <input
                        class="px-1 text-4xl bg-transparent"
                        bind:value={$institution.active_students}
                        style={`width:${$institution.active_students.length + 0.5}ch`}
                    />
                    <p class="pl-3 col-span-2 leading-5 font-semibold">
                        ESTUDIANTES
                        <br />
                        ACTIVOS
                    </p>
                </div>
            </div>
        </div>

        <label
            class="pl-5 relative pr-2 max-w-[500px] flex items-center justify-center -full big_picture_label cursor-pointer"
        >
            <img
                class="absolute w-full"
                src="https://cdn.discordapp.com/attachments/1238903237218930802/1244452251028688906/Iconos.png?ex=6655d2b9&is=66548139&hm=13ffaaa80051f10b14f4ac464ba1edc1a2b82a9546f069c85de0dfde2da6309a&"
                alt=""
            />

            <img
                class="-full aspect-square border-4 object-cover border-color1 bg-blend-overlay hover:bg-blend-darken"
                src="http://127.0.0.1:8000/storage/institution/institution.jpeg"
                alt=""
            />

            <iconify-icon
                icon="line-md:edit"
                class="text-dark text-6xl bg-white bg-opacity-40 p-20 md:p-32 xl:p-48 hidden absolute -full mx-auto"
            ></iconify-icon>
            <input type="file" name="" id="" class="hidden" />
        </label>
    </form>
    {#if $institution.isDirty}
        <button
            class="shadow-xl slideIn flex items-center justify-center mb-3 ml-auto py-4 w-64 bg-color1 gap-3 text-color4"
        >
            <span> GUARDAR PERFIL </span>
            <iconify-icon icon="material-symbols:save" class="text-3xl"
            ></iconify-icon>
        </button>
    {/if} -->

    <!-- <hr class=" border-gray-300" /> -->
    <div class="flex gap-10">
        <div>
            <form
                class="Configuracion_tarifas my-10 mb-4 py-3 min-w-[310px] max-w-[330px]"
                id="pricesForm"
                on:submit={updatePrices}
            >
                <h2 class="font-bold text-xl mb-4">Tarifas</h2>

                <div class="w-full gap-10 pl-1">
                    <Input
                        label="Inscripción ($)"
                        type="number"
                        required={true}
                        bind:value={$prices.new_inscription_price}
                    />
                    <Input
                        label="Mensualidad ($)"
                        type="number"
                        required={true}
                        bind:value={$prices.monthly_payment}
                    />
                    <div class="flex gap-2">
                        <Input
                            label="Mensualidad vence el "
                            type="number"
                            required={true}
                            bind:value={$prices.day_of_monthly_payment}
                            min={1}
                            max={31}
                        />
                        <Input
                            label="Prórroga de pago"
                            type="number"
                            bind:value={$prices.grace_period}
                            min={0}
                        />
                    </div>

                    <Input
                        label="Seguro de atención primaria (AME) ($)"
                        type="number"
                        required={true}
                        bind:value={$prices.ame_price}
                    />
                    <div class="relative flex items-center">
                        <Input
                            label="Plan de inversión ($)"
                            type="number"
                            required={true}
                            bind:value={$prices.investment_plan_price}
                        />
                        <div class="absolute right-0 top-6 group">
                            <button
                                type="button"
                                tabindex="-1"
                                class="ml-2 cursor-pointer relative"
                            >
                                <iconify-icon
                                    icon="mdi:help-circle-outline"
                                    class="text-lg text-gray-500 hover:text-color1"
                                />
                                <span
                                    class="absolute left-1/2 -translate-x-1/2 mt-2 w-64 p-2 rounded bg-black text-white text-xs opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity z-50 whitespace-pre-line"
                                >
                                    Este cobro se realiza en los meses de:
                                    noviembre, marzo y junio.
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- <Input
                            label="Inscripción de regulares ($)"
                            type="number"
                            required={true}
                            bind:value={$prices.regular_inscription_price}
                        /> -->
                    {#if $prices.isDirty}
                        <button
                                class="animated-button flex items-center gap-3 mb-2 mt-7 w-full"
                                type="submit"
                                form={"pricesForm"}
                            >
                            <iconify-icon
                                icon="material-symbols:save"
                                class="text-3xl"
                            ></iconify-icon>
                            <span> GUARDAR TARIFAS </span>
                        </button>
                    {/if}
                </div>
            </form>

            <form class="periodo my-10 mb-4 py-3 min-w-[310px] max-w-[330px]">
                <h2 class="font-bold text-xl mb-4">Periodo Escolar</h2>

                <div class="w-full gap-10 pl-1">
                    <p>{data.schoolLapse.start} / {data.schoolLapse.end}</p>

                    <!-- <Input
                            label="Inscripción de regulares ($)"
                            type="number"
                            required={true}
                            bind:value={$prices.regular_inscription_price}
                        /> -->
                        <div class="bg-white overflow-hidden rounded-md mb-2 hover:shadow-lg mt-4">
                            
                            <button
                                class="bg-green/50 flex items-center justify-between hover:bg-green hover:text-black  gap-3 py-3 px-4 shadow-sm font-semibold w-full"
                                type="button"
                                on:click={initiateNextCourse}
                            >
                                <span> Iniciar próximo periodo </span>
                                <iconify-icon icon="carbon:next-outline" class="text-xl"
                                ></iconify-icon>
                            </button>
                        </div>
                </div>
            </form>
        </div>

        <section class="my-10">
            <header class="flex justify-between items-center mb-6">
                <h2 class="font-bold text-xl ">Métodos de pago</h2>
                <div class="relative z-30">
                    <button
                        on:click={() =>
                            (showPaymentOptions = !showPaymentOptions)}
                        class="animated-button"
                        use:clickOutside={() => {
                            showPaymentOptions = false;
                        }}
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="arr-2"
                            viewBox="0 0 24 24"
                        >
                        </svg>
                        <span class="text">Nuevo Método</span>
                        <span class="circle"></span>

                        <iconify-icon icon="line-md:plus"></iconify-icon>
                        <iconify-icon icon="mingcute:down-line"></iconify-icon>
                    </button>
                    {#if showPaymentOptions}
                        <div
                            class="payment_options slideIn absolute top-14 w-full bg-gray-100 text-dark shadow-xl p-1"
                        >
                            <ul class="flex flex-col gap-1">
                                {#each data.methods as method}
                                    <li>
                                        <a
                                            class={`hover:bg-${ColorsPayMethods()[method.name]} hover:font-bold hover:text-gray-100 duration-100  border-l-4 border-${ColorsPayMethods()[method.name]} `}
                                            use:inertia
                                            href={`/dashboard/configuracion/crear-cuenta/${method.id}`}
                                        >
                                            {method.name}</a
                                        >
                                    </li>
                                {/each}
                                <!-- <li>
                                <a
                                    class={` hover:bg-binance hover:font-bold hover:text-gray-100 duration-100  border-l-4 border-${ColorsPayMethods()[method.name]} `}
                                    use:inertia
                                    href={`/dashboard/configuracion/crear-cuenta/${method.id}`}
                                >
                                    {method.name}</a
                                >
                            </li> -->
                            </ul>
                        </div>
                    {/if}
                </div>
            </header>
            <div class="flex flex-wrap gap-4">
                {#each data.accounts.data as payMethod}
                    <article
                        id={`account-${payMethod.id}`}
                        class={`border-3 rounded-md group duration-200 relative medium-shadow bg-white w-fit pb-5 pt-3 md:px-8 pl-9`}
                    >
                        <div
                            class={`h-full bg-${ColorsPayMethods()[payMethod.payment_method_name]} w-5 absolute left-0 top-0`}
                        ></div>
                        <header class="flex justify-between gap-2">
                            <h3 class={` font-semibold text-xl`}>
                                {payMethod.payment_method_name}
                            </h3>
                            <div
                                class="butons group-hover:flex hidden gap-1 text-gray-500"
                            >
                                <a
                                    href={`/dashboard/configuracion/editar-cuenta/${payMethod.id}`}
                                    class="hover:bg-yellow cursor-pointer text-xl hover:border-2 border-black hover:text-black hover:small-shadow px-4 py-1"
                                    title="Editar"
                                    use:inertia
                                >
                                    <iconify-icon
                                        class="relative -bottom-1"
                                        icon="ic:outline-edit"
                                    ></iconify-icon>
                                </a>

                                <button
                                    on:click={() => deleteAccount(payMethod.id)}
                                    class="hover:bg-red bg-opacity-10 cursor-pointer text-xl hover:border-2 border-black hover:text-black hover:small-shadow px-4 py-1"
                                    title="Eliminar"
                                >
                                    <iconify-icon
                                        class="text-xl relative top-1"
                                        icon="ph:trash"
                                    ></iconify-icon>
                                </button>
                            </div>
                        </header>
                        <div
                            class="flex text-black justify-items-start gap-4 md:gap-6 py-2"
                        >
                            {#if payMethod?.cash_currency}
                                <div>
                                    <h4 class="text-gray-500">
                                        Tipo de moneda:
                                    </h4>
                                    <p>{payMethod.cash_currency}</p>
                                </div>
                            {/if}
                            {#if payMethod?.bank}
                                <div>
                                    <h4 class="text-gray-500">Banco:</h4>
                                    <p>{payMethod.bank}</p>
                                </div>
                            {/if}
                            {#if payMethod?.phone_number}
                                <div>
                                    <h4 class="text-gray-500">Teléfono:</h4>
                                    <p>{payMethod.phone_number}</p>
                                </div>
                            {/if}
                            {#if payMethod?.ci}
                                <div>
                                    <h4 class="text-gray-500">Cédula:</h4>
                                    <p>{payMethod.ci}</p>
                                </div>
                            {/if}
                            {#if payMethod?.person_name}
                                <div>
                                    <h4 class="text-gray-500">Titular:</h4>
                                    <p>{payMethod.person_name}</p>
                                </div>
                            {/if}
                            {#if payMethod?.account_number}
                                <div>
                                    <h4 class="text-gray-500">N° de cuenta:</h4>
                                    <p>{payMethod.account_number}</p>
                                </div>
                            {/if}
                            {#if payMethod?.email}
                                <div>
                                    <h4 class="text-gray-500">Correo:</h4>
                                    <p>{payMethod.email}</p>
                                </div>
                            {/if}
                            {#if payMethod?.username}
                                <div>
                                    <h4 class="text-gray-500">
                                        Nombre de usuario:
                                    </h4>
                                    <p>{payMethod.username}</p>
                                </div>
                            {/if}
                            {#if payMethod?.comision}
                                <div>
                                    <h4 class="text-gray-500">Comisión:</h4>
                                    <p>{payMethod.comision} %</p>
                                </div>
                            {/if}
                        </div>
                    </article>
                {/each}
            </div>
        </section>
    </div>

    <div class="my-10 w-full md:px-2">
        <button
            type="button"
            class="accordion-trigger w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-color1"
            on:click={() => (accordionState.quotas = !accordionState.quotas)}
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-xl">Cupos (capacidad por año escolar)</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Periodo escolar activo:
                        {#if data.schoolLapse}
                            {data.schoolLapse.start} / {data.schoolLapse.end}
                        {/if}
                    </p>
                </div>
                <iconify-icon
                    icon={accordionState.quotas ? "mdi:chevron-up" : "mdi:chevron-down"}
                    class="text-2xl text-gray-600"
                ></iconify-icon>
            </div>
        </button>

        {#if accordionState.quotas}
            <div class="mt-4">
                <div class="bg-white rounded-lg shadow-sm overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-color1 text-white text-left">
                                <th class="px-4 py-2 font-semibold">Año escolar</th>
                                <th class="px-4 py-2 font-semibold">
                                    Cupo asignado
                                </th>
                                <th class="px-4 py-2 font-semibold">Inscritos</th>
                                <th class="px-4 py-2 font-semibold">Disponibles</th>
                            </tr>
                        </thead>
                        <tbody>
                            {#each data.quotas || [] as row}
                                <tr
                                    class="border-b border-gray-100 {row.accepted > quotaAssigned(row) ? 'bg-red-50' : ''}"
                                >
                                    <td class="px-4 py-2 font-semibold text-gray-800">
                                        {row.name}
                                    </td>
                                    <td class="px-4 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            value={$quotasForm[row.course_id] ?? ""}
                                            on:input={(e) =>
                                                ($quotasForm[row.course_id] =
                                                    e.target.value)}
                                            class="w-28 rounded-md border border-gray-300 px-2 py-1"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        {row.accepted}
                                    </td>
                                    <td class="px-4 py-2 text-gray-700">
                                        {row.has_quota
                                            ? Math.max(0, quotaAssigned(row) - row.accepted)
                                            : "—"}
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>

                {#if overCapacity}
                    <p
                        class="mt-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700"
                    >
                        Hay cursos con más inscritos que el cupo asignado. Se guarda
                        igualmente, pero no se podrán admitir más estudiantes en ellos
                        hasta subir el cupo.
                    </p>
                {/if}

                {#if $quotasForm.isDirty}
                    <button
                        type="button"
                        class="animated-button mt-4 flex items-center gap-2"
                        disabled={quotaSaving}
                        on:click={saveQuotas}
                    >
                        {#if quotaSaving}
                            Cargando...
                        {:else}
                            <iconify-icon
                                icon="material-symbols:save"
                                class="text-xl"
                            />
                            Guardar cupos
                        {/if}
                    </button>
                {/if}
            </div>
        {/if}
    </div>

    <div class="my-10 w-full md:px-2">
        <button
            type="button"
            class="accordion-trigger w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-color1"
            on:click={() => (accordionState.lapses = !accordionState.lapses)}
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-xl">Momentos escolares (lapsos)</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Periodo escolar activo:
                        {#if data.schoolLapse}
                            {data.schoolLapse.start} / {data.schoolLapse.end}
                        {/if}
                    </p>
                </div>
                <iconify-icon
                    icon={accordionState.lapses ? "mdi:chevron-up" : "mdi:chevron-down"}
                    class="text-2xl text-gray-600"
                ></iconify-icon>
            </div>
        </button>

        {#if accordionState.lapses}
            <div class="mt-4">
                {#if lapsesSorted.length}
                    <div class="bg-white rounded-lg shadow-sm overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-color1 text-white text-left">
                                    <th class="px-4 py-2 font-semibold">Momento</th>
                                    <th class="px-4 py-2 font-semibold">Inicio</th>
                                    <th class="px-4 py-2 font-semibold">Fin</th>
                                    <th class="px-4 py-2 font-semibold">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                {#each lapsesSorted as lapse}
                                    <tr
                                        class="border-b border-gray-100 {currentLapse?.id === lapse.id ? 'bg-green/10' : ''}"
                                    >
                                        <td
                                            class="px-4 py-2 font-semibold text-gray-800"
                                        >
                                            {lapse.label}
                                        </td>
                                        <td class="px-4 py-2">
                                            <input
                                                type="date"
                                                value={$momentsForm[lapse.id]?.start ??
                                                    lapse.start}
                                                on:input={(e) =>
                                                    setMomentDate(
                                                        lapse.id,
                                                        "start",
                                                        e.target.value,
                                                    )}
                                                class="rounded-md border border-gray-300 px-2 py-1"
                                            />
                                        </td>
                                        <td class="px-4 py-2">
                                            <input
                                                type="date"
                                                value={$momentsForm[lapse.id]?.end ??
                                                    lapse.end}
                                                on:input={(e) =>
                                                    setMomentDate(
                                                        lapse.id,
                                                        "end",
                                                        e.target.value,
                                                    )}
                                                class="rounded-md border border-gray-300 px-2 py-1"
                                            />
                                        </td>
                                        <td class="px-4 py-2">
                                            {#if currentLapse?.id === lapse.id}
                                                <span
                                                    class="rounded-full bg-color1 px-2 py-0.5 text-xs font-semibold text-white"
                                                >
                                                    Vigente hoy
                                                </span>
                                            {/if}
                                        </td>
                                    </tr>
                                {/each}
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        {#if $momentsForm.isDirty}
                            <button
                                type="button"
                                class="animated-button flex items-center gap-2"
                                disabled={momentSaving}
                                on:click={saveMomentsDates}
                            >
                                {#if momentSaving}
                                    Cargando...
                                {:else}
                                    <iconify-icon
                                        icon="material-symbols:save"
                                        class="text-xl"
                                    />
                                    Guardar fechas de momentos
                                {/if}
                            </button>
                        {/if}

                        {#if currentLapse && nextLapse}
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-md bg-color1 px-4 py-2 text-sm font-semibold text-white hover:bg-green hover:text-black disabled:opacity-50"
                                disabled={momentClosing}
                                on:click={closeCurrentMoment}
                            >
                                {#if momentClosing}
                                    Cargando...
                                {:else}
                                    <iconify-icon
                                        icon="carbon:next-outline"
                                        class="text-lg"
                                    />
                                    Cerrar {currentLapse.label} y pasar al
                                    {nextLapse.label}
                                {/if}
                            </button>
                        {:else if currentLapse}
                            <p class="text-sm text-gray-600">
                                El {currentLapse.label} es el último del periodo. Usa
                                "Iniciar próximo periodo" para pasar al siguiente ciclo.
                            </p>
                        {:else}
                            <p class="text-sm text-gray-500">
                                Hoy no corresponde a ningún momento. Ajusta las fechas
                                para que el sistema reconozca el momento vigente.
                            </p>
                        {/if}
                    </div>
                {:else}
                    <p class="text-sm text-gray-500">
                        No hay momentos configurados para el periodo activo.
                    </p>
                {/if}
            </div>
        {/if}
    </div>

    <div class="my-10 w-full md:px-2">
        <button
            type="button"
            class="accordion-trigger w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-color1"
            on:click={() => (accordionState.plans = !accordionState.plans)}
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-xl">
                        Plan de Estudio (Resolución / Pensum)
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Código oficial de 5 dígitos que aparece en boletines y
                        certificados.
                    </p>
                </div>
                <iconify-icon
                    icon={accordionState.plans ? "mdi:chevron-up" : "mdi:chevron-down"}
                    class="text-2xl text-gray-600"
                ></iconify-icon>
            </div>
        </button>

        {#if accordionState.plans}
            <div class="mt-4">
                {#each planGroups as group}
                    <div class="mb-6">
                        <h3
                            class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500"
                        >
                            {group.label}
                        </h3>
                        <div class="bg-white rounded-lg shadow-sm overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-color1 text-white text-left">
                                        <th class="px-4 py-2 font-semibold">
                                            Año / Grado / Nivel
                                        </th>
                                        <th class="px-4 py-2 font-semibold">
                                            Código de plan de estudio
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {#each group.courses as course}
                                        <tr class="border-b border-gray-100">
                                            <td
                                                class="px-4 py-2 font-semibold text-gray-800"
                                            >
                                                {course.name}
                                            </td>
                                            <td class="px-4 py-2">
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    maxlength="5"
                                                    value={$planForm[course.id] ?? ""}
                                                    on:input={(e) =>
                                                        ($planForm[course.id] = e.target.value
                                                            .replace(/\D/g, "")
                                                            .slice(0, 5))}
                                                    placeholder="Ej: 31011"
                                                    class="w-32 rounded-md border border-gray-300 px-2 py-1"
                                                />
                                            </td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </table>
                        </div>
                    </div>
                {/each}

                {#if $planForm.isDirty}
                    <button
                        type="button"
                        class="animated-button mt-2 flex items-center gap-2"
                        disabled={planSaving}
                        on:click={saveCoursePlans}
                    >
                        {#if planSaving}
                            Cargando...
                        {:else}
                            <iconify-icon icon="material-symbols:save" class="text-xl" />
                            Guardar códigos de plan de estudio
                        {/if}
                    </button>
                {/if}
            </div>
        {/if}
    </div>

    <hr class=" border-gray-300" />
</section>

<style>
    * {
        box-sizing: border-box;
    }
    textarea {
        resize: none;
    }
    .big_picture_label:hover iconify-icon {
        display: block;
    }
    .payment_options ul a {
        width: 100%;
        padding: 5px 10px;
        display: inline-block;
        /* background: red; */
    }
    .slideIn {
        animation-duration: 0.2s;
        animation-fill-mode: forwards;
        animation-name: slideIn;
    }
    @keyframes slideIn {
        0% {
            transform: translateY(-20px);
            opacity: 0;
        }
        100% {
            transform: translateY(0);
            opacity: 1;
        }
    }
</style>
