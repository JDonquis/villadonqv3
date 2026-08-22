<script>
    import { inertia } from "@inertiajs/svelte";
    import { router } from "@inertiajs/svelte";
    import { page } from "@inertiajs/svelte";
    import debounce from "lodash/debounce";
    import { createEventDispatcher } from "svelte";

    const dispatch = createEventDispatcher();
    export let classes = "";

    export let filtersOptions = [];
    export let selectedRow;
    export let serverSideData = {};
    export let pagination = true;
    export let allowFilters = true;
    export let otherSelectOptions = false
    export let edit = true

    let filterClientData = {
        search: new URLSearchParams($page.url.split('?')[1] || '').get('search') || '',
        ...serverSideData.filters,
    };
    
    // Inicializar arrays para multiselect
    $: if (filtersOptions && typeof filtersOptions === 'object') {
        Object.entries(filtersOptions).forEach(([key, option]) => {
            if (option.type === 'multiselect') {
                const urlParams = new URLSearchParams($page.url.split('?')[1] || '');
                if (urlParams.has(key + '[]')) {
                    filterClientData[key] = urlParams.getAll(key + '[]');
                } else if (urlParams.has(key)) {
                    filterClientData[key] = [urlParams.get(key)];
                } else if (!filterClientData[key]) {
                    filterClientData[key] = [];
                }
            }
        });
    }

    let buttonPosition = { top: '-100px', left: 'auto' };

    $: if (selectedRow?.status && selectedRow?.data?._clickPosition) {
        buttonPosition = {
            top: (selectedRow.data._clickPosition.y - 60) + 'px',
            left: (selectedRow.data._clickPosition.x + 20) + 'px'
        };
    }

    let perPageOptions = [1, 5, 10, 15, 25, 50];
    let perPageKey = 'table_per_page_' + $page.component.replace(/\//g, '_');
    let rowsPerPage = typeof localStorage !== 'undefined' 
        ? parseInt(localStorage.getItem(perPageKey)) || 25 
        : 25;

    $: visibleLinks = buildVisibleLinks(serverSideData?.links || [], serverSideData?.current_page || 1);

    function buildVisibleLinks(links, currentPage) {
        if (!links.length) return [];
        
        // Filtrar solo los botones numerados (los que tienen url)
        const numberedLinks = links.filter(l => l.url && !isNaN(parseInt(l.label)));
        if (numberedLinks.length <= 7) return links;
        
        // Mostrar máximo 5 botones de página
        const totalPages = numberedLinks.length;
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + 4);
        
        if (end - start < 4) {
            start = Math.max(1, end - 4);
        }
        
        return links.filter((link, index) => {
            if (!link.url) return true; // Mantener Previous/Next
            const pageNum = parseInt(link.label);
            if (isNaN(pageNum)) return false;
            return pageNum >= start && pageNum <= end || pageNum === 1 || pageNum === totalPages;
        });
    }

    function changePerPage(newPerPage) {
        rowsPerPage = newPerPage;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(perPageKey, newPerPage);
        }
        const params = { ...filterClientData, per_page: newPerPage };
        // Remover arrays del params principal
        Object.keys(params).forEach(key => {
            if (Array.isArray(params[key])) {
                delete params[key];
            }
        });
        // Agregar arrays como key[]
        Object.keys(filterClientData).forEach(key => {
            if (Array.isArray(filterClientData[key])) {
                params[key + '[]'] = filterClientData[key];
            }
        });
        router.get($page.url.split('?')[0], params, { preserveState: true });
    }

    function handleFilters() {
        const params = { ...filterClientData };
        // Procesar arrays: convertir key[] del servidor a arrays
        const urlParams = new URLSearchParams($page.url.split('?')[1] || '');
        
        // Limpiar arrays existentes en params
        Object.keys(params).forEach(key => {
            if (Array.isArray(params[key])) {
                delete params[key];
            }
        });
        
        // Agregar arrays como key[]
        Object.keys(filterClientData).forEach(key => {
            if (Array.isArray(filterClientData[key]) && filterClientData[key].length > 0) {
                params[key + '[]'] = filterClientData[key];
            }
        });
        
        router.get(`${$page.url.split('?')[0]}`, params, { preserveState: true, replace: true });
    }

    const handleSearch = debounce(() => {
        const params = { ...filterClientData };
        // Remover arrays del params principal
        Object.keys(params).forEach(key => {
            if (Array.isArray(params[key])) {
                delete params[key];
            }
        });
        // Agregar arrays como key[]
        Object.keys(filterClientData).forEach(key => {
            if (Array.isArray(filterClientData[key])) {
                params[key + '[]'] = filterClientData[key];
            }
        });
        router.get(`${$page.url.split('?')[0]}`, params, { preserveState: true, replace: true });
    }, 300);
</script>

<section class={`w-full ${classes}`}>
    <div class=" md:flex md:items-center md:justify-between">

        {#if allowFilters}
        
        <div class="flex gap-2 rounded-lg overflow-hidden   md:gap-7">
            <div
                class={`inline-flex overflow-hidden  ${allowFilters ? "  divide-x divide-gray-100" : ""}  rtl:flex-row-reverse" : ""}`}
            >
                <!-- <button
                    on:click={(e) => {
                        filterClientData["status"] = "";
                        handleFilters();
                    }}
                    class="px-5 py-2 text-xs font-medium text-gray-600 transition-colors duration-200 sm:text-sm bg-gray-200 hover:bg-gray-100"
                    class:bg-gray-200={filterClientData["status"] == "" ||
                        !filterClientData["status"]}
                >
                    Todos
                </button> -->
                {#each Object.entries(filtersOptions) as [filterKey, filterOption]}
                    {#each filterOption as filter, i}
                        <button
                            on:click={(e) => {
                                filterClientData[filterKey] = filter.id;
                                handleFilters();
                            }}
                            class="px-5 font-semibold py-2 text-xs bg-white text-gray-600 transition-colors duration-200 sm:text-sm hover:bg-gray-100"
                            class:bg-yellow={serverSideData.filters[filterKey] ==
                                filter.id ||
                                (i == 0 && !filterClientData[filterKey])}
                        >
                            {filter.name}
                        </button>
                    {/each}
                {/each}
            </div>
            <slot name="filterBox"></slot>
        </div>
        {/if}
        <div class="flex  gap-10">
       
        {#if selectedRow?.status}
            <div 
                class="fixed  z-[100] flex fadeIn gap-2"
                style="top: {buttonPosition.top}; left: {buttonPosition.left};"
            >

                {#if edit}
                <button
                    on:click={() => dispatch("fillFormToEdit")}
                    class="bg-yellow cursor-pointer text-2xl hover:-translate-x-0.5 hover:-translate-y-0.5 hover:medium-shadow border-3 border-black small-shadow px-4 py-1"
                    title="Editar"
                >
                   <iconify-icon icon="ic:baseline-edit" class="relative top-1" width="24" height="24"></iconify-icon>
                </button>
                {/if}

                <button
                    on:click={() => dispatch("clickDeleteIcon")}
                    class="small-shadow border-3 hover:-translate-x-0.5 hover:-translate-y-0.5 hover:medium-shadow border-black bg-red font-bold px-4 py-1 text-2xl"
                    title="Eliminar"
                >
                    <iconify-icon
                        class="relative -bottom-1"
                        icon="material-symbols:delete-outline"
                    ></iconify-icon>
                </button>
                {#if otherSelectOptions }
                {#each otherSelectOptions as selectedButton}
                    <button
                    on:click={selectedButton.onClick}
                    class={` cursor-pointer text-2xl hover:-translate-x-0.5 hover:-translate-y-0.5 hover:medium-shadow border-3 border-black small-shadow px-4 py-1 ${selectedButton.classes}` }
                    title="{selectedButton.label}"
                >
                   <iconify-icon icon={selectedButton.icon} class="relative top-1" width="24" height="24"></iconify-icon>
                </button>
                {/each}
                {/if}
            </div>
        {/if}
        </div>
    </div>

    <div class="flex flex-col mt-4">
        <div
            class="-mx-4 -my-2  sm:-mx-6 lg:-mx-8"
        >
            <div class="inline-block w-full py-2 align-middle md:px-6 lg:px-8">
                <div
                    class=" overflow-x-auto  scroll-table border rounded-lg shadow-2xl bg-white border-gray-200"
                >
                    <table
                        class="table   w-full divide-y divide-gray-200"
                    >
                        <slot name="thead"></slot>

                        <slot
                            name="tbody"
                            class="bg-white divide-y divide-gray-200  "
                        ></slot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {#if pagination}
        <!-- Pagination ---------------------------------------------------------------------------------------------- -->
        <div class="mt-2 px-3 py-1 rounded-xl bg-white sm:flex sm:items-center sm:justify-between flex-wrap gap-2">
            <div class="text-sm text-gray-500 flex items-center gap-2">
                <span>Mostrando</span>
                <span class="font-medium text-gray-700">{serverSideData.from || 0}</span>
                <span>a</span>
                <span class="font-medium text-gray-700">{serverSideData.to || 0}</span>
                <span>de</span>
                <span class="font-medium text-gray-700">{serverSideData.total || 0}</span>
                <span>entradas</span>
                <select 
                    class="ml-2 px-2 py-1  rounded text-sm"
                    bind:value={rowsPerPage}
                    on:change={(e) => changePerPage(parseInt(e.target.value))}
                >
                    {#each perPageOptions as option}
                        <option value={option}>{option}</option>
                    {/each}
                </select>
                <span>por página</span>
            </div>

            <!-- pagination buttons -->
            <div class="flex items-center gap-1 flex-wrap">
                {#each visibleLinks as link, i}
                    {#if link.url === null}
                        <span class="px-3 py-1 text-gray-400 cursor-not-allowed">{link.label.replace(/&laquo;/g, '<').replace(/&raquo;/g, '>')}</span>
                    {:else if link.active}
                        <span class="px-3 py-1 bg-yellow/70 font-bold border rounded-xl">{link.label}</span>
                    {:else}
                        <a
                            use:inertia
                            href={link.url}
                            class="px-3 py-1 border hover:bg-gray-100"
                        >
                            {link.label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»')}
                        </a>
                    {/if}
                {/each}
            </div>
        </div>
    {/if}
</section>

<style>
    /* normal css */
    .scroll-table::-webkit-scrollbar {
        /* width: 17px;   */
        height: 13px;
    }

 

    .scroll-table::-webkit-scrollbar-thumb {
        background-color: #d1deec; /* color of the scroll thumb */
        border-radius: 6px; /* roundness of the scroll thumb */
    }
    .scroll-table::-webkit-scrollbar-thumb:hover {
        background-color: rgb(184, 206, 231);
    }
    .scroll-table::-webkit-scrollbar-corner {
        /* background: rgba(0, 0, 0, 0.5); */
    }
    tr {
        border-bottom: 1px solid black;
    }
</style>
