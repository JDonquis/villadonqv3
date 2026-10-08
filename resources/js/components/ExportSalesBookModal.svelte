<script>
    import { createEventDispatcher, onMount } from "svelte";
    import { fade, scale } from "svelte/transition";

    export let isOpen = false;
    export let totalRecords = 0;

    const dispatch = createEventDispatcher();
    const STORAGE_KEY = "salesbook_export_profiles_v1";

    // Perfil activo ('perfil_1' o 'perfil_2')
    let activeProfile = "perfil_1";
    let selectedFormat = "xlsx"; // 'xlsx' | 'csv' | 'pdf'

    const REQUIRED_COLUMNS = ["student_name", "amount_bs"];

    const DEFAULT_PROFILES = {
        perfil_1: {
            title: "Perfil Fiscal / SENIAT",
            desc: "Declaración mensual básica en Bolívares",
            checkedCols: [
                "student_name",
                "student_grade",
                "student_section",
                "amount_bs",
                "payment_method",
                "concept",
                "reference",
            ],
        },
        perfil_2: {
            title: "Auditoría Integral",
            desc: "Conciliación bimoneda con tasas, bancos y operadores",
            checkedCols: [
                "student_name",
                "student_ci",
                "student_grade",
                "student_section",
                "legal_rep",
                "amount_bs",
                "payment_method",
                "amount_usd",
                "bcv_rate",
                "receiving_bank",
                "concept",
                "reference",
                "observations",
                "correlative_id",
                "operator_user",
            ],
        },
    };

    // Estado independiente con persistencia reactiva para ambos perfiles
    let profiles = JSON.parse(JSON.stringify(DEFAULT_PROFILES));

    // Catálogo modular de columnas
    const catalog = {
        student: {
            title: "Estudiante",
            icon: "solar:user-rounded-linear",
            items: [
                {
                    id: "student_name",
                    label: "Nombre y Apellido",
                    required: true,
                },
                {
                    id: "student_ci",
                    label: "Cédula de Identidad",
                    required: false,
                },
                { id: "student_grade", label: "Año / Grado", required: false },
                { id: "student_section", label: "Sección", required: false },
                {
                    id: "legal_rep",
                    label: "Representante Legal",
                    required: false,
                },
            ],
        },
        financial: {
            title: "Financiero",
            icon: "solar:dollar-linear",
            items: [
                { id: "amount_bs", label: "Monto Bs.", required: true },
                {
                    id: "payment_method",
                    label: "Método de Pago",
                    required: false,
                },
                { id: "amount_usd", label: "Monto USD ($)", required: false },
                { id: "bcv_rate", label: "Tasa de Cambio", required: false },
                {
                    id: "receiving_bank",
                    label: "Banco Receptor",
                    required: false,
                },
            ],
        },
        audit: {
            title: "Auditoría",
            icon: "solar:document-text-linear",
            items: [
                {
                    id: "report_date",
                    label: "Fecha de Reporte",
                    required: false,
                },
                { id: "concept", label: "Concepto Contable", required: false },
                { id: "reference", label: "N° de Referencia", required: false },
                { id: "observations", label: "Observación", required: false },
                {
                    id: "correlative_id",
                    label: "ID Correlativo",
                    required: false,
                },
                {
                    id: "operator_user",
                    label: "Usuario Operador",
                    required: false,
                },
            ],
        },
    };

    let hydrated = false;

    function withRequired(cols = []) {
        return Array.from(new Set([...REQUIRED_COLUMNS, ...cols]));
    }

    onMount(() => {
        try {
            const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) || "{}");
            if (saved.profiles) {
                profiles = {
                    perfil_1: {
                        ...profiles.perfil_1,
                        ...saved.profiles.perfil_1,
                        checkedCols: withRequired(
                            saved.profiles.perfil_1?.checkedCols,
                        ),
                    },
                    perfil_2: {
                        ...profiles.perfil_2,
                        ...saved.profiles.perfil_2,
                        checkedCols: withRequired(
                            saved.profiles.perfil_2?.checkedCols,
                        ),
                    },
                };
            }
            if (saved.activeProfile) activeProfile = saved.activeProfile;
            if (saved.format) selectedFormat = saved.format;
        } catch (e) {
            /* ignorar almacenamiento corrupto */
        }
        hydrated = true;
    });

    $: if (hydrated) persist(profiles, activeProfile, selectedFormat);

    function persist(p, a, f) {
        try {
            localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({ profiles: p, activeProfile: a, format: f }),
            );
        } catch (e) {
            /* almacenamiento no disponible */
        }
    }

    $: currentChecked = profiles[activeProfile].checkedCols;
    $: isChecked = (id) => currentChecked.includes(id);

    function toggleColumn(id, required) {
        if (required) return;
        const list = profiles[activeProfile].checkedCols;
        if (list.includes(id)) {
            profiles[activeProfile].checkedCols = list.filter(
                (item) => item !== id,
            );
        } else {
            profiles[activeProfile].checkedCols = [...list, id];
        }
        profiles = { ...profiles };
    }

    function selectAll(val) {
        const all = [
            ...catalog.student.items.map((i) => i.id),
            ...catalog.financial.items.map((i) => i.id),
            ...catalog.audit.items.map((i) => i.id),
        ];
        profiles[activeProfile].checkedCols = val ? all : [...REQUIRED_COLUMNS]; // deja sólo los obligatorios
        profiles = { ...profiles };
    }

    function handleExport() {
        dispatch("export", {
            profile: activeProfile,
            profileTitle: profiles[activeProfile].title,
            format: selectedFormat,
            columns: profiles[activeProfile].checkedCols,
        });
    }
</script>

{#if isOpen}
    <!-- Fondo oscuro con desenfoque suave -->
    <!-- svelte-ignore a11y-no-static-element-interactions -->
    <!-- svelte-ignore a11y-click-events-have-key-events -->
    <div
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
        transition:fade={{ duration: 150 }}
        on:click|self={() => dispatch("close")}
    >
        <!-- Contenedor del Modal -->
        <div
            class="bg-white w-full max-w-3xl rounded-3xl shadow-2xl border border-slate-100 overflow-hidden flex flex-col max-h-[92vh]"
            transition:scale={{ start: 0.98, duration: 200 }}
        >
            <!-- CONTENIDO PRINCIPAL -->
            <div class="p-7 overflow-y-auto space-y-6">
                <!-- 1. PERFILES LADO A LADO -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400"
                        >
                            Perfil de Exportación
                        </span>
                        <span
                            class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1"
                        >
                            <span
                                class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"
                            ></span>
                            Autoguardado activo
                        </span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        {#each ["perfil_1", "perfil_2"] as key}
                            <button
                                type="button"
                                on:click={() => (activeProfile = key)}
                                class="p-4 rounded-2xl border-2 text-left transition-all relative flex flex-col justify-between {activeProfile ===
                                key
                                    ? 'border-emerald-500 bg-white shadow-sm'
                                    : 'border-slate-200/80 bg-slate-50/50 hover:bg-white hover:border-slate-300'}"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="w-2 h-2 rounded-full {activeProfile ===
                                                key
                                                    ? 'bg-emerald-500'
                                                    : 'bg-slate-300'}"
                                            ></span>
                                            <h4
                                                class="text-sm font-bold text-slate-800"
                                            >
                                                {profiles[key].title}
                                            </h4>
                                        </div>
                                        <p
                                            class="text-xs text-slate-400 mt-1 leading-snug pl-4"
                                        >
                                            {profiles[key].desc}
                                        </p>
                                    </div>
                                    <span
                                        class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 text-xs {activeProfile ===
                                        key
                                            ? 'bg-emerald-500 text-white'
                                            : 'border border-slate-300 text-transparent'}"
                                    >
                                        <iconify-icon icon="mingcute:check-line"
                                        ></iconify-icon>
                                    </span>
                                </div>
                                <div
                                    class="mt-3.5 pt-2.5 border-t border-slate-100 flex items-center justify-between text-xs"
                                >
                                    <span class="font-bold text-slate-600">
                                        {profiles[key].checkedCols.length} columnas
                                        activas
                                    </span>
                                    <span
                                        class="text-[11px] font-bold {activeProfile ===
                                        key
                                            ? 'text-emerald-600'
                                            : 'text-slate-400'} uppercase tracking-wider"
                                    >
                                        {activeProfile === key
                                            ? "Activo"
                                            : "Cambiar"}
                                    </span>
                                </div>
                            </button>
                        {/each}
                    </div>
                </div>

                <!-- 2. CHECKBOXES POR GRUPO -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div>
                            <span
                                class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 block"
                            >
                                Columnas a Incluir
                            </span>
                         
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <button
                                type="button"
                                class="font-semibold text-slate-600 hover:text-emerald-600 transition"
                                on:click={() => selectAll(true)}
                            >
                                Todas
                            </button>
                            <span class="text-slate-300">•</span>
                            <button
                                type="button"
                                class="font-semibold text-slate-400 hover:text-slate-600 transition"
                                on:click={() => selectAll(false)}
                            >
                                Restablecer
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        {#each Object.entries(catalog) as [groupKey, group]}
                            {@const activeInGroup = group.items.filter((i) =>
                                isChecked(i.id),
                            ).length}
                            <div
                                class="p-3.5 rounded-2xl bg-slate-50/70 border border-slate-200/70 space-y-2"
                            >
                                <div
                                    class="flex items-center justify-between pb-1.5 border-b border-slate-200/50"
                                >
                                    <span
                                        class="text-xs font-bold text-slate-700 flex items-center gap-1.5"
                                    >
                                        <iconify-icon
                                            icon={group.icon}
                                            class="text-slate-400 text-sm"
                                        ></iconify-icon>
                                        {group.title}
                                    </span>
                                    <span
                                        class="text-[11px] font-mono font-bold {activeInGroup >
                                        0
                                            ? 'text-slate-500'
                                            : 'text-gray-300'}"
                                    >
                                        {activeInGroup}/{group.items.length}
                                    </span>
                                </div>
                                <div class="space-y-1">
                                    {#each group.items as item}
                                        <label
                                            class="flex items-center gap-2 p-1 rounded-lg hover:bg-white transition cursor-pointer select-none"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={isChecked(item.id)}
                                                disabled={item.required}
                                                on:change={() =>
                                                    toggleColumn(
                                                        item.id,
                                                        item.required,
                                                    )}
                                                class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500/20 border-slate-300"
                                            />
                                            <span
                                                class="text-xs {isChecked(
                                                    item.id,
                                                )
                                                    ? 'font-medium text-slate-700'
                                                    : 'text-slate-400'} flex-1 leading-tight"
                                            >
                                                {item.label}
                                                {#if item.required}
                                                    <span
                                                        class="text-[10px] font-bold text-orange ml-0.5"
                                                        >(Fijo)</span
                                                    >
                                                {/if}
                                            </span>
                                        </label>
                                    {/each}
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>

                <!-- 3. FORMATO DE SALIDA -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400"
                        >
                            Formato de Salida
                        </span>
                        <span class="text-xs text-slate-400"
                            >Seleccione el archivo preferido</span
                        >
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {#each [{ id: "xlsx", title: "Excel (.xlsx)", desc: "Con fórmulas y totales", icon: "vscode-icons:file-type-excel", wrap: "bg-emerald-100 text-emerald-700" }, { id: "csv", title: "CSV (UTF-8)", desc: "Texto separado por comas", icon: "solar:document-text-linear", wrap: "bg-slate-100 text-slate-600" }, { id: "pdf", title: "PDF Ejecutivo", desc: "Listo para impresión", icon: "vscode-icons:file-type-pdf2", wrap: "bg-red/10 text-red" }] as fmt}
                            <button
                                type="button"
                                on:click={() => (selectedFormat = fmt.id)}
                                class="p-3 rounded-2xl border-2 flex items-center gap-3 transition text-left {selectedFormat ===
                                fmt.id
                                    ? 'border-emerald-500 bg-emerald-50/20 shadow-sm'
                                    : 'border-slate-200/80 hover:bg-slate-50'}"
                            >
                                <div
                                    class="w-9 h-9 rounded-xl {fmt.wrap} flex items-center justify-center shrink-0"
                                >
                                    <iconify-icon
                                        icon={fmt.icon}
                                        class="text-xl"
                                    ></iconify-icon>
                                </div>
                                <div>
                                    <span
                                        class="text-xs font-bold text-slate-800 block"
                                        >{fmt.title}</span
                                    >
                                    <span
                                        class="text-[10px] text-slate-400 block leading-tight"
                                        >{fmt.desc}</span
                                    >
                                </div>
                            </button>
                        {/each}
                    </div>
                </div>
            </div>

            <!-- PIE DE ACCIONES -->
            <div
                class="px-7 py-4 border-t border-slate-100 bg-slate-50/60 flex items-center justify-between shrink-0"
            >
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span
                        >Alcance de exportación: <b>{totalRecords} registros</b>
                        (Filtros aplicados)</span
                    >
                </div>
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-200/60 transition"
                        on:click={() => dispatch("close")}
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold flex items-center gap-2 shadow-sm transition"
                        on:click={handleExport}
                    >
                        <iconify-icon
                            icon="solar:download-linear"
                            class="text-base"
                        ></iconify-icon>
                        <span>Descargar Reporte (.{selectedFormat})</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
{/if}
