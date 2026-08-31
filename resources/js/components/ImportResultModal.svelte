<script>
    import Modal from "./Modal.svelte";

    export let show;
    export let summary = { created: 0, errors: [] };

    let showDetails = false;

    const hasErrors = () => Array.isArray(summary?.errors) && summary.errors.length > 0;
</script>

<Modal bind:showModal={show} classes="max-w-lg">
    <div class="flex flex-col gap-4 p-2">
        <div class="flex items-center gap-3">
            <span
                class="inline-flex items-center justify-center w-10 h-10 rounded-full shrink-0 {hasErrors()
                    ? "bg-amber-100 text-amber-600"
                    : "bg-green-100 text-green-600"}"
            >
                <iconify-icon
                    icon={hasErrors()
                        ? "material-symbols:warning-rounded"
                        : "material-symbols:check-circle-rounded"}
                    width="24"
                    height="24"
                />
            </span>
            <div>
                <h3 class="text-lg font-semibold text-gray-800">
                    Resultado de la importación
                </h3>
                <p class="text-sm text-gray-600">
                    {summary.created}
                    registro(s) creado(s){hasErrors()
                        ? ` y ${summary.errors.length} fila(s) con errores`
                        : " correctamente"}.
                </p>
            </div>
        </div>

        {#if hasErrors()}
            <button
                type="button"
                class="toolbar-secondary warning mx-auto"
                on:click={() => (showDetails = !showDetails)}
            >
                <iconify-icon
                    icon={showDetails
                        ? "material-symbols:keyboard-arrow-up"
                        : "material-symbols:keyboard-arrow-down"}
                    width="20"
                    height="20"
                />
                {showDetails ? "Ocultar detalle" : "Ver detalle"}
                <span
                    class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1 rounded-full bg-[#b45309] text-white text-xs font-bold"
                >
                    {summary.errors.length}
                </span>
            </button>

            {#if showDetails}
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                    <div
                        class="bg-gray-100 px-4 py-2 text-xs font-semibold text-gray-600 uppercase"
                    >
                        Filas con errores
                    </div>
                    <ul class="divide-y divide-gray-100 max-h-64 overflow-auto">
                        {#each summary.errors as error}
                            <li class="px-4 py-2 text-sm text-gray-700">
                                <span class="font-semibold text-red-500"
                                    >Fila {error.row}:</span
                                >
                                {error.message}
                            </li>
                        {/each}
                    </ul>
                </div>
            {/if}
        {/if}

        <button
            type="button"
            class="toolbar-secondary mx-auto"
            on:click={() => (show = false)}
        >
            Cerrar
        </button>
    </div>
</Modal>
