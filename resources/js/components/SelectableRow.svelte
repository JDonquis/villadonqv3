<script>
    import { createEventDispatcher } from 'svelte';
    const dispatch = createEventDispatcher();

    export let rowData = {};
    export let idKey = "id";
    export let selectedRow = { status: false, data: null };
    export let activeClass = "bg-yellow bg-opacity-10 brightness-110";
    export let inactiveClass = "hover:bg-gray-100";
    export let classes = "";

    $: isSelected = selectedRow?.status && selectedRow?.data?.[idKey] === rowData[idKey];

    function handleClick(e) {
        const clickPos = { x: e.clientX, y: e.clientY };
        dispatch('select', {
            status: !isSelected,
            data: isSelected
                ? null
                : { ...rowData, _clickPosition: clickPos },
        });
    }
</script>

<tr
    on:click={handleClick}
    class={`cursor-pointer transition-colors ${isSelected ? activeClass : inactiveClass} ${classes}`}
>
    <slot />
</tr>
