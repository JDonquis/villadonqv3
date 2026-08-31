<script>
    import { DatePicker } from "@svelte-plugins/datepicker";
    import { format } from "date-fns";
    import { createEventDispatcher } from "svelte";
    const dispatch = createEventDispatcher();

    const today = new Date();

    const MILLISECONDS_IN_DAY = 24 * 60 * 60 * 1000;

    const getDateFromToday = (days) => {
        return Date.now() - days * MILLISECONDS_IN_DAY;
    };

    export let startDate = "";
    export let endDate = today;
    let dateFormat = "d MMM, yyyy";
    let isOpen = false;

    // Convertir timestamps a fechas si es necesario
    $: if (startDate && !isNaN(Number(startDate))) {
        startDate = new Date(Number(startDate));
    }
    $: if (endDate && !isNaN(Number(endDate))) {
        endDate = new Date(Number(endDate));
    }

    let formattedStartDate = "";
    let formattedEndDate = "";

    $: {
        formattedStartDate = formatDate(startDate);
        formattedEndDate = formatDate(endDate);
    }

    const formatDate = (date) => {
        if (!date) return "";
        try {
            const d = date instanceof Date ? date : new Date(date);
            if (isNaN(d.getTime())) return "";
            return format(d, dateFormat);
        } catch (e) {
            return "";
        }
    };

    const onClearDates = () => {
        startDate = "";
        endDate = "";
        dispatch("changeDateFilter", {
            startDate: "",
            endDate: "",
        });
    };

    $: hasDateSelection = Boolean(formattedStartDate || formattedEndDate);

    const toggleDatePicker = () => (isOpen = !isOpen);

    const onDateChange = (args) => {
        dispatch("changeDateFilter", {
            startDate: args.startDate,
            endDate: args.endDate,
        });
    };


    // Reactive statement to detect changes in startDate or endDate
</script>

<div class="date-filter">
    <DatePicker
        {onDateChange}
        presetLabels={[
            "Hoy",
            "Últ. 7 Días",
            "Últ. 30 Días",
            "Últ. 60 Días",
            "Últ. 90 Días",
            "Últ. Año",
        ]}
        startOfWeek="1"
        dowLabels={["dom", "lun", "mar", "mié", "jue", "vie", "sáb"]}
        monthLabels={[
            "Enero",
            "Febrero",
            "Marzo",
            "Abril",
            "Mayo",
            "Junio",
            "Julio",
            "Agosto",
            "Septiembre",
            "Octubre",
            "Noviembre",
            "Diciembre",
        ]}
        bind:isOpen
        bind:startDate
        bind:endDate
        isRange
        showPresets
    >
        <div
            class="date-field border-2 rounded-md p-1.5"
            on:click={toggleDatePicker}
            class:open={isOpen}
        >
            <i class="icon-calendar" />
            <div class="date">
                {#if formattedStartDate && formattedEndDate}
                    {formattedStartDate} - {formattedEndDate}
                {:else if formattedStartDate}
                    {formattedStartDate}
                {:else}
                    Todas las fechas
                {/if}
            </div>
            {#if hasDateSelection}
                <button
                    type="button"
                    class="clear-date-button"
                    on:click|stopPropagation={onClearDates}
                    aria-label="Limpiar filtro de fechas"
                    title="Limpiar filtro de fechas"
                >
                    Limpiar
                </button>
            {/if}
        </div>
    </DatePicker>
</div>

<style>
    .date-field {
        align-items: center;
        /* background-color: #fff; */
        /* border-bottom: 1px solid #e8e9ea; */
        display: inline-flex;
        gap: 8px;
        font-size: 15px;
        min-width: 100px;
        width: 100%;
    }

    .date-field.open {
        border-bottom: 1px solid #0087ff;
    }

    .date-field .date {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .clear-date-button {
        border: 1px solid #d1d5db;
        border-radius: 9999px;
        background: #f3f4f6;
        color: #374151;
        font-size: 11px;
        line-height: 1;
        padding: 4px 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .clear-date-button:hover {
        background: #e5e7eb;
    }

    .date-field .icon-calendar {
        background: url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAEMSURBVHgB7ZcPzcIwEMUfXz4BSCgKwAGgACRMAg6YBBxsOMABOAAHFAXgAK5Z2Y6lHbfQ8SfpL3lZaY/1rb01N+BHUKSMNBfEJjZWISA56Uo6C2KvVpkgFn9oRx9vICFtUT1JKO3tvRtZdjBxXQs+YY+1FenIfuesPUGVVLzfRWKvmrSzbbN19wS+kAb2+sCEuUxrYzkbe4YvCVM2Vr5NPAkVa+van7Wn38U95uTpN5TJ/A8ZKemAakmbmJJGpI0gVmwA0huieFItjG19DgTHtwIZhCfZq3ztCuzQYh+FKBSvusjAGs8PnLYkLgMf34JoIBqKBaIAb0Kw9RlhMCTbzzPWAqYq7LsuPaGDUsYmznaOk5zChUJTNQ4TFVMkrOL4HPsoNn26PxROHCggAAAAASUVORK5CYII=")
            no-repeat center center;
        background-size: 14px 14px;
        height: 14px;
        width: 14px;
        flex-shrink: 0;
    }
</style>
