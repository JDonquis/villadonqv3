<script>
    import DateRange from "./DateRange.svelte";

    export let filtersOptions;
    export let filterClientData;
    export let handleFilters;
    export let changeDateFilter;

    let isDropdownOpen = false;
</script>

<div class="flex gap-5 md:gap-10">
    {#each Object.entries(filtersOptions) as [filterKey, filterOption] (filterKey)}
        <article class="md:flex  mt-3">
            <h4
                class="capitalize w-fit  text-xs md:text-sm font-medium px-2 flex items-center  lg:mb-1.5"
            >
                {filterOption.label}
            </h4>
            {#if filterOption.type === "search"}
                <input
                    value={filterClientData?.[filterKey] || ""}
                    class="h-auto border-gray-400 border p-2 py-1"
                    placeholder={"🔍 " + filterOption.label}
                    type="search"
                    name=""
                    id=""
                    on:input={(e) => {
                        const inputValue = e.target.value;
                        if (/^\d*$/.test(inputValue)) {
                            filterClientData[filterKey] = inputValue;
                            handleFilters();
                        } else {
                            e.target.value =
                                filterClientData[filterKey] || "";
                        }
                    }}
                />
            {:else if filterOption.type === "select" && filterOption.multiple}
                <div class="relative">
                    <!-- Multiselect Trigger -->
                    <!-- svelte-ignore a11y-click-events-have-key-events -->
                    <!-- svelte-ignore a11y-no-noninteractive-tabindex -->
                    <!-- svelte-ignore a11y-no-static-element-interactions -->
                    <div
                        class="flex z-50 items-center justify-between w-full px-3 py-2 text-sm border border-gray-300 rounded-md cursor-pointer hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        on:click={() => (isDropdownOpen = !isDropdownOpen)}
                        on:blur={() =>
                            setTimeout(() => (isDropdownOpen = false), 200)}
                        tabindex="0"
                    >
                        <div class="flex flex-wrap gap-1 flex-1">
                            {#if filterClientData[filterKey]?.length > 0}
                                {#each filterClientData[filterKey] as selectedId (selectedId)}
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-blue-100 text-blue-800 rounded"
                                    >
                                        {#each filterOption.options as option (option.id)}
                                            {#if String(option.id) === selectedId}
                                                {option.name}
                                            {/if}
                                        {/each}
                                        <button
                                            type="button"
                                            class="hover:text-blue-600"
                                            on:click|stopPropagation={() => {
                                                filterClientData[
                                                    filterKey
                                                ] = filterClientData[
                                                    filterKey
                                                ].filter(
                                                    (v) => v !== selectedId,
                                                );
                                                if (
                                                    filterClientData[
                                                        filterKey
                                                    ].length === 0
                                                ) {
                                                    delete filterClientData[
                                                        filterKey
                                                    ];
                                                }
                                                handleFilters();
                                            }}
                                        >
                                            <iconify-icon
                                                icon="mdi:close"
                                                width="14"
                                                height="14"
                                            />
                                        </button>
                                    </span>
                                {/each}
                            {:else}
                                <span class="text-gray-400 text-sm">Todos</span>
                            {/if}
                        </div>
                        <iconify-icon
                            icon={isDropdownOpen
                                ? "mdi:chevron-up"
                                : "mdi:chevron-down"}
                            width="20"
                            height="20"
                            class="text-gray-400 ml-2 flex-shrink-0"
                        />
                    </div>

                    <!-- Dropdown -->
                    {#if isDropdownOpen}
                        <div
                            class="absolute z-50 w-full min-w-[300px] mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-56 overflow-y-auto"
                        >
                            <!-- Select All option -->
                            <label
                                class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 border-b border-gray-200"
                            >
                                <input
                                    type="checkbox"
                                    checked={filterClientData[filterKey]
                                        ?.length ===
                                        filterOption.options.length}
                                    on:change={(e) => {
                                        if (e.target.checked) {
                                            filterClientData[filterKey] =
                                                filterOption.options.map(
                                                    (o) => String(o.id),
                                                );
                                        } else {
                                            delete filterClientData[
                                                filterKey
                                            ];
                                        }
                                        handleFilters();
                                    }}
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                />
                                <span class="font-medium text-gray-700"
                                    >Seleccionar todos</span
                                >
                            </label>

                            <!-- Options -->
                            {#each filterOption.options as option (option.id)}
                                <label
                                    class="flex items-center gap-2 px-3 py-2 text-sm cursor-pointer hover:bg-gray-50 transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        checked={filterClientData[
                                            filterKey
                                        ]?.includes(String(option.id))}
                                        on:change={(e) => {
                                            if (
                                                !filterClientData[filterKey]
                                            ) {
                                                filterClientData[
                                                    filterKey
                                                ] = [];
                                            }
                                            if (e.target.checked) {
                                                filterClientData[
                                                    filterKey
                                                ] = [
                                                    ...filterClientData[
                                                        filterKey
                                                    ],
                                                    String(option.id),
                                                ];
                                            } else {
                                                filterClientData[
                                                    filterKey
                                                ] = filterClientData[
                                                    filterKey
                                                ].filter(
                                                    (v) =>
                                                        v !==
                                                        String(option.id),
                                                );
                                            }
                                            if (
                                                filterClientData[filterKey]
                                                    .length === 0
                                            ) {
                                                delete filterClientData[
                                                    filterKey
                                                ];
                                            }
                                            handleFilters();
                                        }}
                                        class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    />
                                    <span class="flex items-center gap-2">
                                        {#if option.color}
                                            <span
                                                class={`inline-block w-3 h-3 rounded-full bg-${option.color} text-${option.color} `}
                                            ></span>
                                        {/if}
                                        {option.name}
                                    </span>
                                </label>
                            {/each}

                            <!-- Clear selection -->
                            {#if filterClientData[filterKey]?.length > 0}
                                <div
                                    class="sticky bottom-0 bg-white p-2 border-t border-gray-200"
                                >
                                    <button
                                        type="button"
                                        class="w-full px-3 py-1.5 text-xs text-red-600 hover:bg-red-50 rounded transition-colors"
                                        on:click={() => {
                                            delete filterClientData[
                                                filterKey
                                            ];
                                            handleFilters();
                                        }}
                                    >
                                        Limpiar selección
                                    </button>
                                </div>
                            {/if}
                        </div>
                    {/if}
                </div>
            {:else if filterOption.type === "select"}
                <select
                    bind:value={filterClientData[filterKey]}
                    on:change={(e) => {
                        const selectedValue = e.target.value;
                        if (selectedValue == "todos") {
                            delete filterClientData[filterKey];
                        }
                        handleFilters();
                    }}
                    name={filterOption.label}
                    id=""
                    class="rounded p-1 py-2"
                >
                    <option value="todos">Todos</option>
                    {#each filterOption.options as filter, i (filter.id)}
                        <option
                            selected={String(
                                filterClientData?.[filterKey],
                            ) === String(filter.id)}
                            value={String(filter.id)}>{filter.name}</option
                        >
                    {/each}
                </select>
            {:else if filterOption.type === "date"}
                <DateRange
                    startDate={Number(filterClientData?.start_date)}
                    endDate={Number(filterClientData?.end_date)}
                    on:changeDateFilter={changeDateFilter}
                />
            {:else}
                {#each filterOption.options as filter, i (filter.id)}
                    <button
                        class="text-left filter_button px-2 py-1 my-1 text-xs font-medium hover:text-dark -full text-gray-700 block transition-colors duration-75 sm:text-sm hover:bg-gray-200"
                        class:bg-gray-200={filterClientData?.[filterKey] ==
                            filter.id}
                        on:click={(e) => {
                            if (filterClientData[filterKey] == filter.id) {
                                delete filterClientData[filterKey];
                            } else {
                                filterClientData[filterKey] = filter.id;
                            }

                            handleFilters();
                        }}
                    >
                        {filter.name}
                        {#if filterClientData?.[filterKey] == filter.id}
                            <iconify-icon
                                icon="line-md:close"
                                class="relative top-1"
                            ></iconify-icon>
                        {/if}
                    </button>
                {/each}
            {/if}
        </article>
    {/each}
</div>
