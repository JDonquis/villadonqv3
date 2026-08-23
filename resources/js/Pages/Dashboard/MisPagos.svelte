<script>
    export let students = [];
    import { page } from "@inertiajs/svelte";
    import BalanceBar from "../../components/BalanceBar.svelte";
    import Table from "../../components/Table.svelte";

    export let config = {
        day_of_monthly_payment: 0,
        grace_period: 0,
    };

    export let data;

    function formatCurrency(value) {
        return (
            "$" +
            Number(value || 0).toLocaleString("en-US", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })
        );
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
    {#if students.length === 0}
        <p class="text-gray-400 text-sm">No tienes hijos inscritos.</p>
    {/if}

    {#each students as student}
        <div class="flex flex-col gap-2">
            <div>
                <div class="flex items-center mb-1">
                    <span>
                        {student.name}
                        {student.last_name}
                    </span>
                </div>
                <span
                    class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded border border-gray-200/50 font-mono text-xs"
                >
                    {#if student.document_type}
                        <span class="uppercase">{student.document_type}-</span>
                    {/if}
                    {student.ci}
                </span>

                <!-- Separador opcional o punto -->
                <span class="text-gray-300">•</span>

                <!-- Curso y Sección -->
                <span
                    class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-xs"
                >
                    {student.course}-{student.section}
                </span>
            </div>

            {#if student.balances.length === 0}
                <p class="text-sm text-gray-400">Sin estados de cuenta.</p>
            {/if}

            {#each student.balances as balance}
                <div class="flex flex-col gap-2 mt-2">
                    <BalanceBar
                                        balances={student.balances}
                                        amountToPay={student?.amount_in_dolars}
                                        is_exempt={student.is_exempt
                                            ? student.exemption_percentage
                                            : false}
                                        dayOfPayment={config.day_of_monthly_payment}
                                        gracePeriod={config.grace_period}
                                    />

                    {#if balance.balance_payments.length > 0}
                        <div class="text-sm text-gray-600">
                            <span class="font-medium text-gray-700"
                                >Pagos realizados:</span
                            >
                            <Table
                                allowFilters={false}
                                serverSideData={data?.payments}
                                otherSelectOptions={[
                                    {
                                        label: "Ver detalles",
                                        icon: "mdi:eye",
                                        classes: "bg-blue",
                                        // onClick: fillFormToEdit,
                                    },
                                ]}
                                edit={false}
                                pagination={true}
                            >
                                <thead slot="thead" class="sticky top-0 z-50">
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha de la transacción</th>
                                        <th>Estudiante/s</th>
                                        <th>Total USD$</th>
                                        <th>Total Bs</th>
                                        <th>Método de pago</th>
                                        <th>Referencia</th>
                                        <!-- <th>Representante</th> -->
                                    </tr>
                                </thead>

                                <tbody slot="tbody">
                                    {#each data?.payments?.data as row, i}
                                        <tr
                                            rowData={row}
                                            idKey="id"
                                            activeClass="bg-color2 bg-opacity-10 brightness-110"
                                            classes={`${row.status === 0 ? "bg-red text-gray-400 bg-opacity-10 opacity-70" : ""} `}
                                        >
                                            <td>
                                                <span class="text-xs">
                                                    {row.id}
                                                </span>
                                            </td>
                                            <td>{row.date}</td>
                                            <td class="px-4 py-3 align-top">
                                                <div class="space-y-2">
                                                    {#each row?.students as student, j}
                                                        <div
                                                            class="flex flex-col gap-1 text-sm"
                                                        >
                                                            <!-- Línea Superior: Nombre completo del estudiante -->
                                                            <div
                                                                class="font-semibold text-gray-800 capitalize leading-snug"
                                                            >
                                                                {student.name}
                                                                {student.last_name}
                                                            </div>

                                                            <!-- Línea Inferior: Metadatos organizados en chips/badges -->
                                                            <div
                                                                class="flex items-center gap-1.5 flex-wrap text-xs text-gray-500"
                                                            >
                                                                <!-- Monto individual (si aplica) -->
                                                                {#if student.pivot?.amount_in_dolars}
                                                                    <span
                                                                        class="font-medium bg-green/20 px-1.5 py-0.5 rounded border border-emerald-200/60"
                                                                    >
                                                                        ${student
                                                                            .pivot
                                                                            .amount_in_dolars}
                                                                    </span>
                                                                {/if}

                                                                <!-- Cédula -->
                                                                <span
                                                                    class="bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded border border-gray-200/50 font-mono text-xs"
                                                                >
                                                                    {#if student.document_type}
                                                                        <span
                                                                            class="uppercase"
                                                                            >{student.document_type}-</span
                                                                        >
                                                                    {/if}
                                                                    {student.ci}
                                                                </span>

                                                                <!-- Separador opcional o punto -->
                                                                <span
                                                                    class="text-gray-300"
                                                                    >•</span
                                                                >

                                                                <!-- Curso y Sección -->
                                                                <span
                                                                    class="text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded border border-gray-200/40 text-[11px]"
                                                                >
                                                                    {student
                                                                        .course
                                                                        ?.name} -
                                                                    {student
                                                                        .section
                                                                        ?.name}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    {/each}
                                                </div>
                                            </td>
                                            <!-- <td
                    >{row.representative.user.name}
                    {row.representative.user.last_name}</td
                > -->
                                            <td>${row.total_in_dolars}</td>
                                            <td>{row.total_in_bs} Bs</td>
                                            <td class="">
                                                <!-- <ColorsPayMethods
                        payment_method_id={row.account_payment.method.name}
                        accounts={data.accounts.data}
                    /> -->
                                                <span
                                                    class={`h-5 text-${ColorsPayMethods()[row.account_payment.method.name]}  bg-${ColorsPayMethods()[row.account_payment.method.name]} w-5  left-0 top-0`}
                                                    >|</span
                                                >
                                                {row.account_payment.method
                                                    .name}
                                                {#if row.account_payment.bank}- {row
                                                        .account_payment
                                                        .bank}{/if}
                                                {#if row.account_payment.cash_currency}-
                                                    {row.account_payment
                                                        .cash_currency}{/if}
                                                {#if row.account_payment.username}-
                                                    {row.account_payment
                                                        .username}{/if}
                                            </td>
                                            <td>{row.reference}</td>
                                        </tr>
                                    {/each}
                                </tbody>
                            </Table>
                        </div>
                    {/if}
                </div>
            {/each}
        </div>
    {/each}
</div>
