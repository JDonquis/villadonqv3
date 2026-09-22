<script>
    import { useForm } from "@inertiajs/svelte";
    import clickOutside from "../../components/ClickOutside";
    import { inertia, router } from "@inertiajs/svelte";
    import ColorsPayMethods from "../../components/ColorsPayMethods";

    import Alert from "../../components/Alert.svelte";
    import Input from "../../components/Input.svelte";
    import GoogleMapsPicker from "../../components/GoogleMapsPicker.svelte";
    import { displayAlert } from "../../stores/alertStore";
    import axios from "axios";
    export let data;

    console.log({ data });

    const institution = useForm({
        name: data.institution?.name ?? "",
        code: data.institution?.code ?? "",
        municipality: data.institution?.municipality ?? "",
        federal_entity: data.institution?.federal_entity ?? "",
        cdcee: data.institution?.cdcee ?? "",
        director_name: data.institution?.director_name ?? "",
        director_ci: data.institution?.director_ci ?? "",
        phone_number: data.institution?.phone_number ?? "",
        address: data.institution?.address ?? "",
        latitude: data.institution?.latitude ?? "",
        longitude: data.institution?.longitude ?? "",
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
            preescolar_inscription_price: $prices.preescolar_inscription_price,
            primaria_inscription_price: $prices.primaria_inscription_price,
            secundaria_inscription_price: $prices.secundaria_inscription_price,
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

    function saveInstitution(e) {
        e.preventDefault();

        if (!confirm("¿Está seguro de guardar los datos del plantel?")) return;

        const formData = {
            name: $institution.name,
            code: $institution.code,
            municipality: $institution.municipality,
            federal_entity: $institution.federal_entity,
            cdcee: $institution.cdcee,
            director_name: $institution.director_name,
            director_ci: $institution.director_ci,
            phone_number: $institution.phone_number,
            address: $institution.address,
            latitude: $institution.latitude,
            longitude: $institution.longitude,
        };

        $institution.processing = true;
        $institution.defaults();

        router.put("/dashboard/configuracion/institucion", formData, {
            preserveScroll: true,
            onSuccess: () => {
                $institution.processing = false;
                displayAlert({
                    type: "success",
                    message: "Datos del plantel guardados",
                });
            },
            onError: (errors) => {
                $institution.processing = false;
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
            "• Los estudiantes activos se promoverán automáticamente al siguiente grado y quedarán reinscritos en el nuevo periodo.\n" +
            "• Los estudiantes de 5to año pasarán a graduados.\n" +
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
        institution: true,
        payments: true,
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
    <h2 class="text-xl md:text-2xl font-bold text-color1 sm:hidden mb-3">
        Configuración
    </h2>
    <div class="py-5"></div>

    <!-- Acerca del Plantel Educativo -->
    <div class="my-10 w-full md:px-2">
        <button
            type="button"
            class="accordion-trigger w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-color1"
            on:click={() => (accordionState.institution = !accordionState.institution)}
        >
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold text-xl">Acerca del Plantel Educativo</h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Información general, ubicación y datos del director
                    </p>
                </div>
                <iconify-icon
                    icon={accordionState.institution ? "mdi:chevron-up" : "mdi:chevron-down"}
                    class="text-2xl text-gray-600"
                ></iconify-icon>
            </div>
        </button>

        {#if accordionState.institution}
            <div class="mt-4">
                <form class="bg-white rounded-lg shadow-sm p-6" on:submit={saveInstitution}>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
                        <Input
                            label="Código del plantel"
                            type="text"
                            bind:value={$institution.code}
                            maxlength="20"
                            placeholder="Ej: 12345"
                        />
                        <Input
                            label="CDCEE"
                            type="text"
                            bind:value={$institution.cdcee}
                            maxlength="20"
                            placeholder="Código CDCEE"
                        />
                        <Input
                            label="Nombre del plantel"
                            type="text"
                            bind:value={$institution.name}
                            maxlength="100"
                            class="md:col-span-2 lg:col-span-3"
                            placeholder="Nombre oficial del plantel"
                        />
                        <Input
                            label="Director(a)"
                            type="text"
                            bind:value={$institution.director_name}
                            maxlength="100"
                            placeholder="Nombre completo del director/a"
                        />
                        <Input
                            label="Cédula del Director(a)"
                            type="text"
                            bind:value={$institution.director_ci}
                            maxlength="20"
                            placeholder="Cédula de identidad"
                        />
                        <Input
                            label="Teléfono"
                            type="tel"
                            bind:value={$institution.phone_number}
                            maxlength="11"
                            placeholder="Ej: 02125551234"
                        />
                        <Input
                            label="Municipio"
                            type="text"
                            bind:value={$institution.municipality}
                            maxlength="100"
                            placeholder="Ej: Libertador"
                        />
                        <Input
                            label="Entidad Federal"
                            type="text"
                            bind:value={$institution.federal_entity}
                            maxlength="100"
                            placeholder="Ej: Distrito Capital"
                        />
                        <Input
                            label="Dirección"
                            type="text"
                            bind:value={$institution.address}
                            maxlength="255"
                            class="md:col-span-2 lg:col-span-3"
                            placeholder="Dirección completa del plantel"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Ubicación en el mapa (clic para seleccionar coordenadas)
                        </label>
                        <GoogleMapsPicker
                            latitude={$institution.latitude}
                            longitude={$institution.longitude}
                            onSelect={(lat, lng) => {
                                $institution.latitude = lat;
                                $institution.longitude = lng;
                            }}
                        />
                        <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
                            <div class="bg-gray-50 rounded-lg p-3">
                                <span class="text-gray-500">Latitud:</span>
                                <span class="font-mono text-gray-800 ml-2">{$institution.latitude || "—"}</span>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-3">
                                <span class="text-gray-500">Longitud:</span>
                                <span class="font-mono text-gray-800 ml-2">{$institution.longitude || "—"}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        {#if $institution.isDirty}
                            <button
                                type="submit"
                                class="animated-button flex items-center justify-center gap-3"
                                disabled={$institution.processing}
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
                                {#if $institution.processing}
                                    <span class="text">Cargando...</span>
                                {:else}
                                    <iconify-icon
                                        icon="material-symbols:save"
                                        class="text"
                                        width="20"
                                        height="20"
                                    />
                                    <span class="text">Guardar datos del plantel</span>
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
                        {/if}
                    </div>
                </form>
            </div>
        {/if}
    </div>

    <div class="md:flex gap-10">
        <div>
            <form
                class="Configuracion_tarifas my-10 mb-4 py-3 min-w-[310px] max-w-[330px]"
                id="pricesForm"
                on:submit={updatePrices}
            >
                <h2 class="font-bold text-xl mb-4">Tarifas</h2>

                <div class="w-full gap-10 pl-1">
                    <Input
                        label="Inscripción Preescolar ($)"
                        type="number"
                        required={true}
                        bind:value={$prices.preescolar_inscription_price}
                    />
                    <Input
                        label="Inscripción Primaria ($)"
                        type="number"
                        required={true}
                        bind:value={$prices.primaria_inscription_price}
                    />
                    <Input
                        label="Inscripción Secundaria ($)"
                        type="number"
                        required={true}
                        bind:value={$prices.secundaria_inscription_price}
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
                            class="animated-button flex items-center justify-center gap-3 mb-2 mt-7 w-full"
                            type="submit"
                            form={"pricesForm"}
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
                                icon="material-symbols:save"
                                class="text"
                                width="22"
                                height="22"
                            ></iconify-icon>
                            <span class="text">Guardar tarifas</span>
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

        <div class="my-10 w-full md:px-2">
            <button
                type="button"
                class="accordion-trigger w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-left shadow-sm transition hover:border-color1"
                on:click={() => (accordionState.payments = !accordionState.payments)}
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-xl">Métodos de pago</h2>
                    </div>
                    <iconify-icon
                        icon={accordionState.payments ? "mdi:chevron-up" : "mdi:chevron-down"}
                        class="text-2xl text-gray-600"
                    ></iconify-icon>
                </div>
            </button>

            {#if accordionState.payments}
                <div class="mt-4">
                    <header class="md:flex justify-between items-center mb-6">
                        <div></div>
                        <div class="relative z-30">
                            <button
                                on:click={() =>
                                    (showPaymentOptions = !showPaymentOptions)}
                                class="animated-button flex items-center justify-center gap-2"
                                use:clickOutside={() => {
                                    showPaymentOptions = false;
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
                                <iconify-icon icon="line-md:plus" class="text"></iconify-icon>
                                <span class="text">Nuevo Método</span>
                                <span class="circle"></span>
                                <iconify-icon icon="mingcute:down-line" class="text"></iconify-icon>
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
                                    </ul>
                                </div>
                            {/if}
                        </div>
                    </header>
                    <div class="flex flex-wrap gap-4">
                        {#each data.accounts.data as payMethod}
                            <article
                                id={`account-${payMethod.id}`}
                                class={`border-3 rounded-md group duration-200 relative medium-shadow bg-white w-fit pb-3 md:pb-5 pt-3  px-4 md:px-8 pl-6 md:pl-9`}
                            >
                                <div
                                    class={`h-full bg-${ColorsPayMethods()[payMethod.payment_method_name]} w-3 md:w-5 absolute left-0 top-0`}
                                ></div>
                                <header class="flex justify-between gap-2">
                                    <h3 class={` font-semibold text-lg md:text-xl mt-0`}>
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
                                    class="md:flex text-black justify-items-start gap-4 md:gap-6 py-2"
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
                </div>
            {/if}
        </div>
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
                        class="animated-button mt-4 flex items-center justify-center gap-3"
                        disabled={quotaSaving}
                        on:click={saveQuotas}
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
                        {#if quotaSaving}
                            <span class="text">Cargando...</span>
                        {:else}
                            <iconify-icon
                                icon="material-symbols:save"
                                class="text"
                                width="20"
                                height="20"
                            />
                            <span class="text">Guardar cupos</span>
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
                                class="animated-button flex items-center justify-center gap-3"
                                disabled={momentSaving}
                                on:click={saveMomentsDates}
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
                                {#if momentSaving}
                                    <span class="text">Cargando...</span>
                                {:else}
                                    <iconify-icon
                                        icon="material-symbols:save"
                                        class="text"
                                        width="20"
                                        height="20"
                                    />
                                    <span class="text">Guardar fechas de momentos</span>
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
                        {/if}

                        {#if currentLapse && nextLapse}
                            <button
                                type="button"
                                class="animated-button flex items-center justify-center gap-3"
                                disabled={momentClosing}
                                on:click={closeCurrentMoment}
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
                                {#if momentClosing}
                                    <span class="text">Cargando...</span>
                                {:else}
                                    <iconify-icon
                                        icon="carbon:next-outline"
                                        class="text"
                                        width="20"
                                        height="20"
                                    />
                                    <span class="text">
                                        Cerrar {currentLapse.label} y pasar al
                                        {nextLapse.label}
                                    </span>
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
                        class="animated-button mt-2 flex items-center justify-center gap-3"
                        disabled={planSaving}
                        on:click={saveCoursePlans}
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
                        {#if planSaving}
                            <span class="text">Cargando...</span>
                        {:else}
                            <iconify-icon
                                icon="material-symbols:save"
                                class="text"
                                width="20"
                                height="20"
                            />
                            <span class="text">Guardar códigos de plan de estudio</span>
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
