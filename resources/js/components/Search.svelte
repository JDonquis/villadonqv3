<script>
    import { page, router } from "@inertiajs/svelte";
    import debounce from "lodash/debounce";
    import Modal from "./Modal.svelte";
    import FilterControls from "./FilterControls.svelte";
    import { filter } from "lodash";

    const parseUrlFilters = () => {
        const params = new URLSearchParams($page.url.split("?")[1] || "");
        const filters = {};

        for (const [key, value] of params.entries()) {
            if (key.endsWith("[]")) {
                const baseKey = key.replace(/\[\]$/, "");
                if (!filters[baseKey]) {
                    filters[baseKey] = [];
                }
                filters[baseKey].push(value);
            } else {
                filters[key] = value;
            }
        }

        return filters;
    };

    let initialUrlFilters = parseUrlFilters();
    let search = $page.props.filters?.search || initialUrlFilters.search || "";
    export let extraSearchParams = {};

    const handleSearch = debounce((event) => {
        router.get(
            `${$page.url.split("?")[0]}`,
            { ...extraSearchParams, search, page: "1" },
            { preserveState: true },
        );
    }, 300);

    let showModal = false;
    export let filtersOptions = false;
    export let allowSearch = true;
    export let inlineFilters = false;
    let isFilterAply = false;
    let firstTime = true;
    let filterClientData = { ...initialUrlFilters, ...$page.props.filters };
    let isFilterDataInitialized = false;

    $: if (!isFilterDataInitialized) {
        filterClientData = { ...initialUrlFilters, ...$page.props.filters };
        isFilterDataInitialized = true;
    }

    $: isFilterAply = Object.keys(filterClientData).some((value) => {
        console.log(filterClientData[value]);
        return (
            value != "search" &&
            value != "page" &&
            filterClientData[value] != "todos"
        );
    });

    const changeDateFilter = (args) => {
        filterClientData = {
            ...filterClientData,
            start_date: +args.detail.startDate,
            end_date: +args.detail.endDate,
        };
        handleFilters();
    };

    const handleFilters = () => {
        firstTime = false;
        router.get(`${$page.url.split("?")[0]}`, filterClientData, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    console.log(filterClientData);</script>

<div
    class="fixed top-3 z-50 lg right-20 md:right-64 flex items-center rounded-xl bg-gray-50 border border-gray-200"
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
        placeholder={$$props.placeholder || "Buscar"}
        bind:value={search}
        on:input={() => {
            handleSearch();
        }}
        class={`block w-full rounded-xl py-1.5 pr-5 text-gray-700 -full ${filtersOptions ? "-r-none" : ""}  md:w-56  placeholder-gray-400/70 pl-11 rtl:pr-11 rtl:pl-5 focus:border-blue-400 focus:ring-blue-300 focus:outline-none focus:ring focus:ring-opacity-40`}
        style={$$props.style}
    />
    {#if filtersOptions && !inlineFilters}
        <div class="md:right-64 top-3 z-50">
            <button
                class=" relative flex gap-2 hover:bg-gray-300 -full -l-none p-2 px-3"
                class:bg-gray-300={isFilterAply}
                title="Busqueda de filtros"
                on:click={(e) => {
                    e.preventDefault();

                    showModal = true;
                }}
            >
                {#if isFilterAply}
                    <div
                        class="absolute bg-color1 h-2 w-2 -full right-1 top-0"
                    ></div>
                {/if}
                <span> Filtros </span>
                <iconify-icon icon="mage:filter" width="24" height="24"
                ></iconify-icon>
            </button>
        </div>
    {/if}
</div>

{#if inlineFilters && filtersOptions}
    <div
        class="border border-gray-200 bg-gray-50 rounded-xl p-4"
    >
        <FilterControls
            {filtersOptions}
            {filterClientData}
            {handleFilters}
            {changeDateFilter}
        />
    </div>
{/if}

{#if !inlineFilters}
<Modal bind:showModal classes={"max-w-[960px] h-full"} showCancelButton={false}>
    <p slot="header" class="opacity-60">Filtros de busqueda</p>
    <FilterControls
        {filtersOptions}
        {filterClientData}
        {handleFilters}
        {changeDateFilter}
    />
</Modal>
{/if}
