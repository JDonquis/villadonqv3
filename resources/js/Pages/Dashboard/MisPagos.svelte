<script>
    export let students = [];

    function formatCurrency(value) {
        return "$" + Number(value || 0).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    const monthLabels = {
        september: "Sep",
        october: "Oct",
        november: "Nov",
        december: "Dic",
        january: "Ene",
        february: "Feb",
        march: "Mar",
        april: "Abr",
        may: "May",
        june: "Jun",
        july: "Jul",
        august: "Ago",
    };

    function statusLabel(status) {
        const map = {
            paid: "Pagado",
            debt: "Deuda",
            pending: "Pendiente",
            partially_paid: "Parcial",
        };
        return map[status] || status;
    }

    function hasMonthValue(balance, key) {
        const value = balance.months?.[key];
        return value !== null && value !== undefined && Number(value) !== 0;
    }
</script>

<svelte:head>
    <title>Mis Pagos</title>
</svelte:head>

<div
    class="w-full bg-white shadow-lg p-6 rounded-md max-w-[1200px] flex flex-col gap-6"
>
    <h3 class="text-lg font-bold text-gray-800 tracking-tight">Mis Pagos</h3>

    {#if students.length === 0}
        <p class="text-gray-400 text-sm">No tienes hijos inscritos.</p>
    {/if}

    {#each students as student}
        <div class="flex flex-col gap-2 border border-gray-200 rounded-md p-4">
            <div
                class="flex flex-wrap items-center justify-between gap-2"
            >
                <span class="font-bold text-gray-800"
                    >{student.name} {student.last_name}</span
                >
                <span class="text-sm text-gray-600"
                    >{student.course} - Sección {student.section}</span
                >
            </div>

            {#if student.balances.length === 0}
                <p class="text-sm text-gray-400">Sin estados de cuenta.</p>
            {/if}

            {#each student.balances as balance}
                <div class="flex flex-col gap-2 mt-2">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="font-medium text-gray-700"
                            >Periodo: {balance.school_lapse}</span
                        >
                        <span class="text-gray-600"
                            >Deuda: <b>{formatCurrency(balance.total_debt)}</b></span
                        >
                        <span class="text-gray-600"
                            >Pagado: <b>{formatCurrency(balance.total_income)}</b></span
                        >
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr
                                    class="border-b border-gray-200 text-left text-gray-500"
                                >
                                    {#each Object.entries(monthLabels) as [key, label]}
                                        <th class="py-1 pr-2 font-medium">{label}</th>
                                    {/each}
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="border-b border-gray-100">
                                    {#each Object.entries(monthLabels) as [key, label]}
                                        <td class="py-1 pr-2">
                                            {#if hasMonthValue(balance, key)}
                                                <span class="text-gray-800"
                                                    >{formatCurrency(balance.months[key])}</span
                                                >
                                                <span
                                                    class="text-gray-400 block"
                                                    >{statusLabel(
                                                        balance.months[key + "_status"],
                                                    )}</span
                                                >
                                            {:else}
                                                <span class="text-gray-300">-</span>
                                            {/if}
                                        </td>
                                    {/each}
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {#if balance.balance_payments.length > 0}
                        <div class="text-sm text-gray-600">
                            <span class="font-medium text-gray-700"
                                >Pagos realizados:</span
                            >
                            <ul class="mt-1 list-disc pl-5">
                                {#each balance.balance_payments as payment}
                                    <li>
                                        {payment.date} - {formatCurrency(payment.amount)}
                                        {#if payment.method}({payment.method}){/if}
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/if}
                </div>
            {/each}
        </div>
    {/each}
</div>
