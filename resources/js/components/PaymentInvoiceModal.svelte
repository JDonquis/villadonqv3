<script>
    import { createEventDispatcher } from "svelte";
    import { fade, scale } from "svelte/transition";

    export let isOpen = false;
    export let payment = null;

    const dispatch = createEventDispatcher();

    let selected = null;
    let lastPaymentId = null;

    function buildOptions(payment) {
        const out = [];
        const seen = new Set();

        for (const student of payment?.students ?? []) {
            const rep = student?.representative;
            const user = rep?.user;
            const studentName = `${student?.name ?? ""} ${student?.last_name ?? ""}`.trim();

            if (user) {
                const name = `${user.name ?? ""} ${user.last_name ?? ""}`.trim();
                const dedupe = `L|${name}|${user.ci ?? ""}`;
                if (!seen.has(dedupe)) {
                    seen.add(dedupe);
                    out.push({
                        key: `legal:${student.id}`,
                        type: "legal",
                        studentId: student.id,
                        label: name || "Representante legal",
                        document: user.ci ? `${rep?.document_type ? rep.document_type + "-" : ""}${user.ci}` : "",
                        badge: "Legal",
                        studentName,
                    });
                }
            }

            if (rep?.second_representative_name) {
                const name = `${rep.second_representative_name} ${rep.second_representative_last_name ?? ""}`.trim();
                const dedupe = `S|${name}|${rep.second_representative_ci ?? ""}`;
                if (!seen.has(dedupe)) {
                    seen.add(dedupe);
                    out.push({
                        key: `second:${student.id}`,
                        type: "second",
                        studentId: student.id,
                        label: name || "Representante",
                        document: rep.second_representative_ci
                            ? `${rep.second_document_type ? rep.second_document_type + "-" : ""}${rep.second_representative_ci}`
                            : "",
                        badge: "No legal",
                        studentName,
                    });
                }
            }
        }

        return out;
    }

    $: options = buildOptions(payment);

    $: if (isOpen && payment && payment.id !== lastPaymentId) {
        lastPaymentId = payment.id;
        selected =
            options.find((option) => option.type === "legal")?.key ??
            options[0]?.key ??
            null;
    }

    function generate() {
        const option = options.find((item) => item.key === selected);
        if (!option || !payment) return;

        const params = new URLSearchParams({
            rep_student_id: option.studentId,
            rep_type: option.type,
        });

        const link = document.createElement("a");
        link.href = `/dashboard/pagos/${payment.id}/factura?${params.toString()}`;
        link.rel = "noopener";
        document.body.appendChild(link);
        link.click();
        link.remove();

        dispatch("close");
    }
</script>

{#if isOpen}
    <div
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
        transition:fade={{ duration: 150 }}
        on:click|self={() => dispatch("close")}
    >
        <div
            class="bg-white w-full max-w-lg rounded-3xl shadow-2xl border border-slate-100 overflow-hidden flex flex-col max-h-[92vh]"
            transition:scale={{ start: 0.98, duration: 200 }}
        >
            <div class="p-6 space-y-4 overflow-y-auto">
                <div>
                    <span
                        class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400"
                    >
                        Factura de Pago
                    </span>
                    <h3 class="text-lg font-bold text-slate-800 mt-1">
                        N° {String(payment?.id ?? "").padStart(6, "0")}
                    </h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Seleccione el representante que aparecerá en la factura. Por
                        defecto se muestra el representante legal.
                    </p>
                </div>

                {#if options.length === 0}
                    <div
                        class="text-sm text-slate-500 bg-slate-50 border border-slate-200 rounded-xl p-4"
                    >
                        Este pago no tiene representantes asociados.
                    </div>
                {:else}
                    <div class="space-y-2">
                        {#each options as option}
                            <button
                                type="button"
                                on:click={() => (selected = option.key)}
                                class="w-full p-3 rounded-2xl border-2 text-left transition flex items-center gap-3 {selected ===
                                option.key
                                    ? 'border-emerald-500 bg-emerald-50/20'
                                    : 'border-slate-200/80 hover:bg-slate-50'}"
                            >
                                <span
                                    class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 {selected ===
                                    option.key
                                        ? 'border-emerald-500'
                                        : 'border-slate-300'}"
                                >
                                    {#if selected === option.key}
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"
                                        ></span>
                                    {/if}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-sm font-semibold text-slate-800"
                                            >{option.label}</span
                                        >
                                        <span
                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full border {option.type ===
                                            'legal'
                                                ? 'text-emerald-700 border-emerald-200 bg-emerald-50'
                                                : 'text-orange border-orange bg-yellow/10'}"
                                        >
                                            {option.badge}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate">
                                        {#if option.document}{option.document} · {/if}Representante
                                        de {option.studentName}
                                    </div>
                                </div>
                            </button>
                        {/each}
                    </div>
                {/if}
            </div>

            <div
                class="px-6 py-4 border-t border-slate-100 bg-slate-50/60 flex items-center justify-end gap-2.5 shrink-0"
            >
                <button
                    type="button"
                    class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:bg-slate-200/60 transition"
                    on:click={() => dispatch("close")}
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    disabled={options.length === 0 || !selected}
                    class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold flex items-center gap-2 shadow-sm transition"
                    on:click={generate}
                >
                    <iconify-icon
                        icon="fa6-solid:file-invoice"
                        class="text-base"
                    ></iconify-icon>
                    <span>Generar factura</span>
                </button>
            </div>
        </div>
    </div>
{/if}
