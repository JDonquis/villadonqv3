<script>
    import { displayAlert } from "../stores/alertStore";
    import { createEventDispatcher, onDestroy } from "svelte";

    /**
     * Drawer de observaciones pedagógicas de un estudiante dentro de un plan.
     * El alcance (profesor dueño del plan o administración con el módulo "notas")
     * lo resuelve el backend con App\Support\GradeAccess: aquí solo se refleja
     * lo que devuelve `can_modify` en cada observación.
     */

    export let show = false;
    export let plan = null;
    export let student = null;

    const ENDPOINT = "/dashboard/mis-estudiantes/observaciones";
    const MAX_LENGTH = 1000;

    const TYPES = [
        {
            value: "positive",
            label: "Positiva",
            icon: "mdi:arrow-up-bold-circle",
            active: "bg-green text-color1 border-green",
        },
        {
            value: "neutral",
            label: "Neutra",
            icon: "mdi:minus-circle",
            active: "bg-blue text-color1 border-blue",
        },
        {
            value: "negative",
            label: "Negativa",
            icon: "mdi:arrow-down-bold-circle",
            active: "bg-red text-white border-red",
        },
    ];

    const FILTERS = [
        { value: "all", label: "Todas" },
        { value: "positive", label: "Positivas" },
        { value: "neutral", label: "Neutras" },
        { value: "negative", label: "Negativas" },
    ];

    const typeClasses = {
        positive: "bg-green/20 text-green border-green/30",
        neutral: "bg-blue/20 text-color2 border-blue/40",
        negative: "bg-red/20 text-red border-red/30",
    };

    const accentClasses = {
        positive: "border-l-green",
        neutral: "border-l-blue",
        negative: "border-l-red",
    };

    const dispatch = createEventDispatcher();

    let observations = [];
    let counts = { positive: 0, neutral: 0, negative: 0 };
    let loading = true;
    let saving = false;
    let filter = "all";
    let editingId = null;
    let openMenuId = null;

    let form = { type: "positive", body: "", shared: false };

    let voiceListening = false;
    let voiceSupported = false;
    let voiceInterim = "";
    let voiceRecognition = null;

    let loadedFor = null;
    let textarea;

    $: total = counts.positive + counts.neutral + counts.negative;
    $: visible = filter === "all"
        ? observations
        : observations.filter((observation) => observation.type === filter);
    $: initials = student
        ? `${(student.name || "")[0] || ""}${(
              student.last_name || ""
          )[0] || ""}`.toUpperCase()
        : "";
    $: isEditing = editingId !== null;

    // Se recarga cada vez que se abre para un estudiante distinto. `loadedFor` se
    // limpia al cerrar para que la próxima apertura vuelva a pedir el historial.
    $: if (show && student && loadedFor !== student.id) {
        loadedFor = student.id;
        resetForm();
        load();
    }

    $: if (!show) {
        loadedFor = null;
        openMenuId = null;
        stopDictation();
    }

    function csrfToken() {
        return (
            document.querySelector('meta[name="csrf-token"]')?.content || ""
        );
    }

    function requestHeaders() {
        return {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": csrfToken(),
        };
    }

    function firstError(payload) {
        const errors = payload?.errors || {};
        const first = Object.values(errors)[0];
        return Array.isArray(first) ? first[0] : first || null;
    }

    async function load() {
        if (!plan?.id || !student?.id) return;

        loading = true;

        try {
            const query = new URLSearchParams({
                plan_id: String(plan.id),
                student_id: String(student.id),
            });
            const response = await fetch(`${ENDPOINT}?${query.toString()}`, {
                headers: requestHeaders(),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(
                    result?.message || "No se pudo cargar el historial.",
                );
            }

            observations = result.data.observations || [];
            counts = result.data.counts || counts;
        } catch (error) {
            observations = [];
            displayAlert({
                type: "error",
                title: "Observaciones",
                message: error.message,
            });
        } finally {
            loading = false;
        }
    }

    function resetForm() {
        form = { type: "positive", body: "", shared: false };
        editingId = null;
    }

    function startEdit(observation) {
        editingId = observation.id;
        form = {
            type: observation.type,
            body: observation.body,
            shared: observation.shared,
        };
        openMenuId = null;
        stopDictation();

        setTimeout(() => textarea?.focus(), 60);
    }

    async function save() {
        if (!plan?.id || !student?.id) return;

        const body = form.body.trim();

        if (body.length < 3) {
            displayAlert({
                type: "error",
                title: "Observación",
                message: "Escribe al menos 3 caracteres antes de guardar.",
            });
            return;
        }

        saving = true;
        stopDictation();

        const payload = {
            evaluation_plan_id: plan.id,
            student_id: student.id,
            type: form.type,
            body,
            shared_with_representative: form.shared,
        };

        const isUpdate = isEditing;
        const url = isUpdate ? `${ENDPOINT}/${editingId}` : ENDPOINT;

        try {
            const response = await fetch(url, {
                method: isUpdate ? "PUT" : "POST",
                headers: requestHeaders(),
                body: JSON.stringify(payload),
            });
            const result = await response.json();

            if (!response.ok) {
                throw new Error(
                    firstError(result) ||
                        result?.message ||
                        "No se pudo guardar la observación.",
                );
            }

            observations = result.data.observations || [];
            counts = result.data.counts || counts;
            resetForm();

            // El contador del botón en la matriz vive fuera del drawer: le avisamos
            // el total nuevo para que no quede desactualizado hasta recargar Inertia.
            if (!isUpdate) {
                dispatch("changed", {
                    studentId: student.id,
                    total: observations.length,
                });
            }

            displayAlert({
                type: "success",
                title: "Observación",
                message: result.message,
            });
        } catch (error) {
            displayAlert({
                type: "error",
                title: "Observación",
                message: error.message,
            });
        } finally {
            saving = false;
        }
    }

    async function remove(observation) {
        openMenuId = null;
        saving = true;

        try {
            const response = await fetch(
                `${ENDPOINT}/${observation.id}`,
                {
                    method: "DELETE",
                    headers: requestHeaders(),
                },
            );
            const result = await response.json();

            if (!response.ok) {
                throw new Error(
                    result?.message || "No se pudo eliminar la observación.",
                );
            }

            observations = result.data.observations || [];
            counts = result.data.counts || counts;

            if (editingId === observation.id) resetForm();

            dispatch("changed", {
                studentId: student.id,
                total: observations.length,
            });

            displayAlert({
                type: "success",
                title: "Observación",
                message: result.message,
            });
        } catch (error) {
            displayAlert({
                type: "error",
                title: "Observación",
                message: error.message,
            });
        } finally {
            saving = false;
        }
    }

    function toggleDictation() {
        if (voiceListening) {
            stopDictation();
            return;
        }

        const SpeechRecognition =
            window.SpeechRecognition || window.webkitSpeechRecognition;

        if (!SpeechRecognition) {
            displayAlert({
                type: "error",
                title: "Dictado por voz",
                message:
                    "Este navegador no admite dictado por voz. Prueba con Chrome o Edge.",
            });
            return;
        }

        const recognition = new SpeechRecognition();
        recognition.lang = "es-VE";
        recognition.continuous = true;
        recognition.interimResults = true;

        voiceRecognition = recognition;
        voiceListening = true;
        voiceInterim = "";

        recognition.onresult = (event) => {
            let finalText = "";

            for (
                let index = event.resultIndex;
                index < event.results.length;
                index += 1
            ) {
                const transcript = event.results[index][0].transcript;
                if (event.results[index].isFinal) {
                    finalText += transcript;
                } else {
                    voiceInterim = transcript;
                }
            }

            if (finalText.trim()) {
                form = {
                    ...form,
                    body: (form.body + " " + finalText.trim())
                        .replace(/\s+/g, " ")
                        .trim()
                        .slice(0, MAX_LENGTH),
                };
                voiceInterim = "";
            }
        };

        recognition.onerror = (event) => {
            voiceListening = false;
            voiceRecognition = null;
            voiceInterim = "";
            displayAlert({
                type: "error",
                title: "Dictado por voz",
                message:
                    event.error === "not-allowed"
                        ? "Permite el acceso al micrófono en el navegador para dictar."
                        : `Error de dictado: ${event.error}.`,
            });
        };

        recognition.onend = () => {
            if (voiceRecognition === recognition) {
                voiceRecognition = null;
                voiceListening = false;
                voiceInterim = "";
            }
        };

        try {
            recognition.start();
        } catch (error) {
            voiceRecognition = null;
            voiceListening = false;
            voiceInterim = "";
        }
    }

    function stopDictation() {
        const recognition = voiceRecognition;
        voiceRecognition = null;
        voiceListening = false;
        voiceInterim = "";

        if (recognition) {
            try {
                recognition.stop();
            } catch (error) {
                // El navegador ya pudo detener el reconocimiento.
            }
        }
    }

    function close() {
        stopDictation();
        openMenuId = null;
        show = false;
    }

    function onKeydown(event) {
        if (show && event.key === "Escape") close();
    }

    onDestroy(stopDictation);
</script>

<svelte:window on:keydown={onKeydown} />

<!-- Backdrop -->
<!-- svelte-ignore a11y-click-events-have-key-events a11y-no-noninteractive-element-interactions -->
<div
    class="fixed inset-0 z-[99999] bg-black bg-opacity-40 backdrop-blur-sm transition-opacity duration-200 {show
        ? 'opacity-100'
        : 'opacity-0 pointer-events-none'}"
    on:click={close}
    role="presentation"
>
    <!-- svelte-ignore a11y-no-noninteractive-element-interactions -->
    <aside
        class="absolute right-0 top-0 h-full w-full sm:max-w-[560px] bg-white shadow-2xl flex flex-col transition-transform duration-300 {show
            ? 'translate-x-0'
            : 'translate-x-full'}"
        on:click|stopPropagation
        aria-label="Observaciones del estudiante"
    >
        <!-- Header -->
        <header class="flex-none border-b border-grayBlue/30 px-5 py-4">
            <div class="flex items-start gap-3">
                <div
                    class="flex-none w-11 h-11 rounded-full bg-color1 text-white flex items-center justify-center font-bold text-sm uppercase"
                >
                    {initials}
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="font-bold text-color1 text-base leading-tight truncate">
                        {student ? `${student.last_name}, ${student.name}` : ""}
                    </h3>
                    <p class="text-[11px] text-gray-500 mt-0.5 truncate">
                        C.I. {student?.ci ?? ""} · {plan?.course_name ?? ""} ·
                        {plan?.section_name ?? ""}
                    </p>
                </div>

                <button
                    type="button"
                    on:click={close}
                    class="flex-none text-gray-400 hover:text-color1 transition-colors"
                    title="Cerrar"
                    aria-label="Cerrar"
                >
                    <iconify-icon
                        icon="line-md:close"
                        width="24"
                        height="24"
                    ></iconify-icon>
                </button>
            </div>

            <!-- Contadores por tipo -->
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <span class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold">
                    {total} {total === 1 ? "observación" : "observaciones"}
                </span>
                {#each TYPES as type (type.value)}
                    <span
                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[11px] font-semibold {typeClasses[
                            type.value
                        ]}"
                        title={type.label}
                    >
                        <iconify-icon icon={type.icon}></iconify-icon>
                        {counts[type.value] || 0}
                    </span>
                {/each}
            </div>
        </header>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
            <!-- Formulario -->
            <section class="border border-grayBlue/40 rounded-lg p-4 space-y-3">
                <h4 class="text-sm font-bold text-color1 flex items-center gap-1.5">
                    <iconify-icon icon="mdi:pencil-outline"></iconify-icon>
                    {isEditing ? "Editar observación" : "Nueva observación"}
                </h4>

                <!-- Tipo -->
                <div class="flex gap-2">
                    {#each TYPES as type (type.value)}
                        <button
                            type="button"
                            on:click={() => (form = { ...form, type: type.value })}
                            class="flex-1 inline-flex items-center justify-center gap-1 px-2 py-1.5 rounded-md border text-xs font-semibold transition-colors {form.type ===
                            type.value
                                ? type.active
                                : 'bg-white text-gray-500 border-grayBlue/40 hover:bg-slate-50'}"
                        >
                            <iconify-icon icon={type.icon}></iconify-icon>
                            {type.label}
                        </button>
                    {/each}
                </div>

                <!-- Texto + dictado -->
                <div class="relative">
                    <div class="relative">
                        <textarea
                            bind:this={textarea}
                            bind:value={form.body}
                            maxlength={MAX_LENGTH}
                            rows="4"
                            placeholder="Describe el desempeño, la participación o una conducta a destacar…"
                            class="w-full border border-grayBlue/40 rounded-md px-3 py-2 pr-11 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-color4/40 resize-none"
                        ></textarea>

                        <button
                            type="button"
                            on:click={toggleDictation}
                            class="absolute right-2 top-2 w-8 h-8 rounded-full flex items-center justify-center transition-colors {voiceListening
                                ? 'bg-red text-white'
                                : 'bg-slate-100 text-gray-500 hover:bg-slate-200'}"
                            title={voiceListening
                                ? "Detener dictado"
                                : "Dictar por voz"}
                            aria-label={voiceListening
                                ? "Detener dictado"
                                : "Dictar por voz"}
                        >
                            <iconify-icon
                                icon={voiceListening
                                    ? "mdi:microphone"
                                    : "mdi:microphone-outline"}
                            ></iconify-icon>
                        </button>
                    </div>

                    <div class="absolute bottom-3 w-full flex items-center justify-between mt-1 pr-3">
                        <span
                            class="text-[11px] {voiceListening
                                ? 'text-red font-semibold'
                                : 'text-gray-400'}"
                        >
                            {#if voiceListening}
                                {voiceInterim
                                    ? `Escuchando: ${voiceInterim}`
                                    : "Escuchando… dicta la observación"}
                            {/if}
                        </span>
                        <span
                            class="text-[11px] font-mono {form.body.length >=
                            MAX_LENGTH - 100
                                ? 'text-red'
                                : 'text-gray-400'}"
                        >
                            {form.body.length}/{MAX_LENGTH}
                        </span>
                    </div>
                </div>

                <!-- Compartir con el representante -->
                <label
                    class="flex items-center justify-between gap-3 bg-slate-50/70 rounded-md px-3 py-2 cursor-pointer"
                >
                    <span class="flex items-center gap-2 min-w-0">
                        <iconify-icon
                            icon="mdi:account-supervisor-circle-outline"
                            class="text-color2 flex-none"
                        ></iconify-icon>
                        <span class="min-w-0">
                            <span class="block text-xs font-semibold text-color1">
                                Compartir con el representante
                            </span>
                            <span class="block text-[11px] text-gray-500">
                                Visible en “Mis hijos”. Si lo desmarcas, será
                                privada.
                            </span>
                        </span>
                    </span>

                    <span class="relative flex items-center flex-none">
                        <input
                            id="observation-shared"
                            name="shared_with_representative"
                            type="checkbox"
                            bind:checked={form.shared}
                            class="sr-only peer"
                        />
                        <span
                            class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-color4"
                        ></span>
                    </span>
                </label>

                <p class="text-[11px] text-gray-400">
                    Materia: <span class="font-semibold text-gray-600"
                        >{plan?.matter_name ?? "—"}</span
                    > · Docente: <span class="font-semibold text-gray-600"
                        >{plan?.teacher_name ?? "—"}</span
                    >
                </p>

                <div class="flex items-center gap-2 pt-1">
                    <button
                        type="button"
                        on:click={save}
                        disabled={saving}
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md bg-color1 text-white text-sm font-semibold hover:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <iconify-icon icon="mdi:content-save-outline"
                        ></iconify-icon>
                        {isEditing ? "Guardar cambios" : "Guardar observación"}
                    </button>

                    {#if isEditing}
                        <button
                            type="button"
                            on:click={resetForm}
                            disabled={saving}
                            class="px-3 py-2 rounded-md border border-grayBlue/50 text-sm font-semibold text-gray-600 hover:bg-slate-50 transition disabled:opacity-50"
                        >
                            Cancelar
                        </button>
                    {/if}
                </div>
            </section>

            <!-- Filtros -->
            <div class="flex flex-wrap items-center gap-2">
                {#each FILTERS as item (item.value)}
                    <button
                        type="button"
                        on:click={() => (filter = item.value)}
                        class="px-2.5 py-1 rounded-full text-xs font-semibold transition-colors {filter ===
                        item.value
                            ? 'bg-color1 text-white'
                            : 'bg-slate-100 text-gray-600 hover:bg-slate-200'}"
                    >
                        {item.label}
                    </button>
                {/each}
            </div>

            <!-- Historial -->
            {#if loading}
                <div class="flex items-center justify-center py-8 text-gray-400">
                    <iconify-icon
                        icon="mdi:loading"
                        class="animate-spin"
                    ></iconify-icon>
                    <span class="ml-2 text-sm">Cargando historial…</span>
                </div>
            {:else if visible.length === 0}
                <div
                    class="text-center text-gray-400 text-sm py-8 border border-dashed border-grayBlue/50 rounded-lg"
                >
                    {#if observations.length === 0}
                        Todavía no hay observaciones para este estudiante.
                    {:else}
                        No hay observaciones del tipo seleccionado.
                    {/if}
                </div>
            {:else}
                <ul class="space-y-2.5">
                    {#each visible as observation, index (observation.id)}
                        <li
                            class="relative border-l-4 rounded-r-md bg-slate-50/70 px-3 py-2.5 {accentClasses[
                                observation.type
                            ] ?? accentClasses.neutral}"
                        >
                            <div class="flex items-center gap-2 mb-1">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[11px] font-semibold {typeClasses[
                                        observation.type
                                    ] ?? typeClasses.neutral}"
                                >
                                    <iconify-icon
                                        icon={observation.type_icon}
                                    ></iconify-icon>
                                    {observation.type_label}
                                </span>

                                <span class="text-[11px] text-gray-400">
                                    {observation.created_at_label}
                                </span>

                                {#if observation.can_modify}
                                    <div class="relative ml-auto">
                                        <button
                                            type="button"
                                            on:click={() =>
                                                (openMenuId =
                                                    openMenuId ===
                                                    observation.id
                                                        ? null
                                                        : observation.id)}
                                            class="w-6 h-6 rounded flex items-center justify-center text-gray-400 hover:bg-slate-200 hover:text-color1 transition"
                                            title="Opciones"
                                            aria-label="Opciones"
                                        >
                                            <iconify-icon
                                                icon="mdi:dots-vertical"
                                            ></iconify-icon>
                                        </button>

                                        {#if openMenuId === observation.id}
                                            <!-- En la última tarjeta el menú se abre hacia
                                                 arriba: si no, el `overflow-y-auto` del
                                                 cuerpo lo recortaría. -->
                                            <div
                                                class="absolute right-0 z-10 w-36 bg-white border border-grayBlue/40 rounded-md shadow-lg overflow-hidden {index ===
                                                visible.length - 1
                                                    ? 'bottom-7'
                                                    : 'top-7'}"
                                            >
                                                <button
                                                    type="button"
                                                    on:click={() =>
                                                        startEdit(
                                                            observation,
                                                        )}
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-slate-100"
                                                >
                                                    <iconify-icon
                                                        icon="mdi:pencil-outline"
                                                    ></iconify-icon>
                                                    Editar
                                                </button>
                                                <button
                                                    type="button"
                                                    on:click={() =>
                                                        remove(observation)}
                                                    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-red hover:bg-red/10"
                                                >
                                                    <iconify-icon
                                                        icon="mdi:trash-can-outline"
                                                    ></iconify-icon>
                                                    Eliminar
                                                </button>
                                            </div>
                                        {/if}
                                    </div>
                                {/if}
                            </div>

                            <p class="text-sm text-gray-700 leading-snug whitespace-pre-line">
                                {observation.body}
                            </p>

                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2"
                            >
                                <span class="text-[11px] text-gray-500">
                                    {observation.author_name}
                                </span>
                               
                                <span
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold {observation.shared
                                        ? 'text-color2'
                                        : 'text-gray-400'}"
                                    title={observation.shared
                                        ? "Compartida con el representante"
                                        : "Privada: solo la ven el profesor y la administración"}
                                >
                                    <iconify-icon
                                        icon={observation.shared
                                            ? "mdi:send-check-outline"
                                            : "mdi:lock-outline"}
                                    ></iconify-icon>
                                    {observation.shared
                                        ? "Enviado a Repr."
                                        : "Nota privada"}
                                </span>
                            </div>
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>
    </aside>
</div>
