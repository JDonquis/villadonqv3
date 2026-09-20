<script context="module">
    // 1. Static memory optimization: Defined once for all component instances
    const MONTH_KEYS = [
        { key: "sep", name: "september", label: "Se" },
        { key: "oct", name: "october", label: "Oc" },
        { key: "nov", name: "november", label: "No" },
        { key: "dic", name: "december", label: "Di" },
        { key: "ene", name: "january", label: "En" },
        { key: "feb", name: "february", label: "Fe" },
        { key: "mar", name: "march", label: "Ma" },
        { key: "abr", name: "april", label: "Ab" },
        { key: "may", name: "may", label: "My" },
        { key: "jun", name: "june", label: "Jn" },
        { key: "jul", name: "july", label: "Jl" },
        { key: "ago", name: "august", label: "Ag" },
    ];

    function hasMonthPayment(balance, month) {
        const payments = balance?.balance_payments;
        if (!payments) return false;
        if (Array.isArray(payments)) {
            return payments.some((payment) => payment.month === month);
        }
        return Array.isArray(payments[month]) && payments[month].length > 0;
    }
</script>

<script>
    import { convertUsdToBs } from "../utils/dolarApi";
    import { formatBsInput } from "../utils/formatters";

    // Component Props
    export let balances = [];
    export let amountToPay = 0;
    export let classes = "";
    export let is_exempt = false;
    export let dolarRate = 0;
    export let id = "";

    // Tooltip State
    let tooltipVisible = false;
    let tooltipPayments = [];
    let tooltipStyle = "";
    let tooltipHideTimeout;

    // 2. Reactively compute firstUnpaidMonth only when balances change
    $: firstUnpaidMonth = (() => {
        if (!balances?.[0]) return 0;
        const isPending = balances[0].status === "pending";
        const foundIndex = MONTH_KEYS.findIndex(({ name }) => {
            const status = balances[0]?.[`${name}_status`];
            return isPending
                ? status === "pending"
                : status === "debt" || status === "partially_paid";
        });
        return foundIndex < 0 ? 0 : foundIndex;
    })();

    $: startPointToPay = {
        school_lapse_index: 0,
        month: firstUnpaidMonth,
    };

    let payingBalances = [];

    // 3. Optimized payment calculation function with zero-amount early exit
    function calculatePaymentDistribution(amount) {
        if (!balances?.length || amount <= 0) {
            payingBalances = [];
            return { endMonthIndex: 0, endYearIndex: 0, partialToPay: 0 };
        }

        let remaining = amount;
        let endMonthIndex = startPointToPay.month;
        let endYearIndex = startPointToPay.school_lapse_index;
        let partialToPay = 0;
        let startMonth = startPointToPay.month;

        const localPayingBalances = new Array(balances.length);

        while (remaining > 0 && endYearIndex < balances.length) {
            const currentBalanceObj = balances[endYearIndex];
            if (!currentBalanceObj) break;

            if (
                !localPayingBalances[endYearIndex]?.balanceInscription &&
                currentBalanceObj.inscription < 0
            ) {
                localPayingBalances[endYearIndex] = {
                    ...localPayingBalances[endYearIndex],
                    balanceInscription: remaining,
                };
                remaining -= Math.abs(currentBalanceObj.inscription);
            }

            if (remaining <= 0) break;

            const monthProp = MONTH_KEYS[endMonthIndex].name;
            const monthBalance = Math.abs(currentBalanceObj[monthProp] || 0);

            if (remaining < monthBalance) {
                partialToPay = remaining;
            }

            remaining -= monthBalance;

            localPayingBalances[endYearIndex] = {
                ...localPayingBalances[endYearIndex],
                startMonth,
                endMonthIndex,
                endYearIndex,
            };

            if (endMonthIndex === 11) {
                endYearIndex++;
                endMonthIndex = 0;
                startMonth = 0;
            } else {
                endMonthIndex++;
            }
        }

        payingBalances = localPayingBalances;
        return { endMonthIndex, endYearIndex, partialToPay };
    }

    $: endPointToPay = amountToPay > 0 
  ? calculatePaymentDistribution(amountToPay) 
  : { endMonthIndex: 0, endYearIndex: 0, partialToPay: 0 };

    // Tooltip event handlers
    function showBalancePaymentsTooltip(event, payments) {
        if (!payments || payments.length === 0) {
            tooltipVisible = false;
            return;
        }
        clearTimeout(tooltipHideTimeout);
        tooltipPayments = payments;
        const rect = event.currentTarget.getBoundingClientRect();
        tooltipStyle = `position: fixed; top: ${rect.bottom}px; left: ${rect.left + rect.width / 2}px; transform: translateX(-50%); z-index: 9999;`;
        tooltipVisible = true;
    }

    function scheduleTooltipHide() {
        clearTimeout(tooltipHideTimeout);
        tooltipHideTimeout = setTimeout(() => {
            tooltipVisible = false;
        }, 150);
    }
</script>

<div {id} class="bg-white rounded-lg {classes}">
    {#each balances as balance, indexYear}
        <div class="flex gap-4 items-center mt-2 mb-2">
            {#if !balance.school_lapse?.status || balances.length > 1}
                <p class="text-xs font-bold text-gray-500">
                    {balance.school_lapse?.start.slice(0, 4)}
                    <span class="text-gray-400">•</span>
                    {balance.school_lapse?.end.slice(0, 4)}
                </p>
            {/if}

            <div class="flex items-center gap-1 text-xs">
                {#if balance.total_debt > 0}
                    <p class="font-semibold">DEUDA:</p>
                    <p class="font-bold bg-red/20 text-black px-1 rounded-sm">
                        <span class="font-bold text-black/80">$</span>
                        {balance.total_debt}
                    </p>
                    {#if dolarRate > 0}
                        <p class="font-semibold ml-2">ó</p>
                        <p class="font-bold bg-red/10 text-black px-1 rounded-sm">
                            <span class="font-bold text-gray-600">Bs</span>
                            {formatBsInput(convertUsdToBs(balance.total_debt, dolarRate))}
                        </p>
                    {/if}
                {/if}
                {#if is_exempt}
                    <div class="flex ml-2 items-center gap-2 text-xs mb-2 font-bold bg-purple w-fit px-2 py-1">
                        <p>Exonerado: {is_exempt}%</p>
                        <iconify-icon icon="mdi:shield-check" />
                    </div>
                {/if}
            </div>
        </div>

        {#if is_exempt < 100}
            <div class="grid p-0 grid-cols-12 rounded-2xl overflow-hidden border-2 border-gray-200">
                <!-- Inscription Cell -->
                <!-- svelte-ignore a11y-no-static-element-interactions -->
                <div
                    class="hover:brightness-125 relative col-span-1 z-10 text-[9px] md:text-xs text-gray-700 p-1 capitalize text-center font-bold
                    {balance.inscription_status === 'pending' || balance.inscription_status === 'debt' ? 'bg-red/70' : ''}
                    {balance.inscription_status === 'paid' ? 'bg-green/50' : ''}
                    {balance.inscription_status === 'partially_paid' ? 'bg-yellow/70' : ''}"
                    on:mouseenter={(e) => balance.balance_payments?.inscription && showBalancePaymentsTooltip(e, balance.balance_payments.inscription)}
                    on:mouseleave={scheduleTooltipHide}
                >
                    <span class="hidden md:block">Inscr.</span>
                    <span class="md:hidden">Ins.</span>

                    <p class="text-black">
                        {#if Math.abs(balance.inscription) > 0}
                            <span class="hidden md:inline">$</span>{Math.abs(balance.inscription)}
                        {/if}
                    </p>

                    <!-- Render payment overlay only if amountToPay is positive -->
                    {#if amountToPay > 0 && payingBalances[indexYear]?.balanceInscription > 0}
                        <div
                            class="absolute top-0.5 left-0 h-[95%] z-40 bg-purple/30 border-y-4 border-black/50 border"
                            style="max-width: 100%; width: {(payingBalances[indexYear].balanceInscription / Math.abs(balance.inscription)) * 100}%"
                        ></div>
                    {/if}
                </div>

                <!-- Months Grid -->
                <div class="col-span-11 grid grid-cols-12">
                    {#if amountToPay > 0}
                        {#each MONTH_KEYS as { key, name, label }, indexMonth}
                            <!-- svelte-ignore a11y-no-static-element-interactions -->
                            <div
                                class="group/month hover:brightness-110 hover:shadow-2xl hover:border-x border-black/10 relative col-span-1 text-xs capitalize text-center font-bold p-1 text-gray-700
                                {balance[name + '_status'] === 'debt' ? 'bg-red/70' : ''}
                                {balance[name + '_status'] === 'paid' ? 'bg-green/50' : ''}
                                {balance[name + '_status'] === 'partially_paid' ? 'bg-yellow/70' : ''}
                                {balance[name + '_status'] === 'pending' && hasMonthPayment(balance, name) ? 'bg-blue/60' : ''}
                                {!balance[name + '_status'] ? 'bg-gray-50' : ''}"
                                title={balance[name + '_status'] === 'pending' ? 'Pendiente de pago: $' + Math.abs(balance[name]) : ''}
                                on:mouseenter={(e) => balance.balance_payments?.[name] && showBalancePaymentsTooltip(e, balance.balance_payments[name])}
                                on:mouseleave={scheduleTooltipHide}
                            >
                                <!-- 4. CSS Media Queries取代 window.matchMedia JavaScript event listener -->
                                <div class="z-40 text-[9px] md:text-xs">
                                    <span class="hidden sm:inline">{key}</span>
                                    <span class="sm:hidden">{label}</span>
                                </div>

                                <p class="text-black text-[9px] md:text-xs">
                                    {#if balance[name + '_status'] === 'debt' || balance[name + '_status'] === 'partially_paid'}
                                        ${Math.abs(balance[name])}
                                    {/if}
                                </p>

                                <!-- 5. Conditioned DOM Node: Completely unmounted on student list / read-only views -->
                                <div
                                    class="text-xs months_to_pay absolute top-0.5 left-0 w-full text-black h-[95%] z-40
                                    {indexMonth === startPointToPay.month && startPointToPay.school_lapse_index === indexYear && amountToPay > Math.abs(balance[name]) ? 'border-l-4 border-black/50' : ''}
                                    {indexMonth === endPointToPay.endMonthIndex - 1 && endPointToPay.endYearIndex === indexYear && amountToPay > 0 ? 'border-r-4 border-black/50' : ''}
                                    {startPointToPay.school_lapse_index <= indexYear && payingBalances[indexYear]?.startMonth <= indexMonth && indexMonth <= payingBalances[indexYear]?.endMonthIndex ? 'bg-purple/30 border-y-4 border-black/50' : ''}"
                                    style={((indexMonth === endPointToPay.endMonthIndex - 1 && endPointToPay.endYearIndex === indexYear) || indexMonth === 11) && endPointToPay.partialToPay > 0
                                        ? `width: ${(endPointToPay.partialToPay / Math.abs(balance[name])) * 100}%`
                                        : ''}
                                ></div>
                            </div>
                        {/each}
                    {:else}
                        {#each MONTH_KEYS as { key, name, label }, indexMonth}
                            <!-- svelte-ignore a11y-no-static-element-interactions -->
                            <div
                                class="group/month hover:brightness-110 hover:shadow-2xl hover:border-x border-black/10 relative col-span-1 text-xs capitalize text-center font-bold p-1 text-gray-700
                                {balance[name + '_status'] === 'debt' ? 'bg-red/70' : ''}
                                {balance[name + '_status'] === 'paid' ? 'bg-green/50' : ''}
                                {balance[name + '_status'] === 'partially_paid' ? 'bg-yellow/70' : ''}
                                {balance[name + '_status'] === 'pending' && hasMonthPayment(balance, name) ? 'bg-blue/60' : ''}
                                {!balance[name + '_status'] ? 'bg-gray-50' : ''}"
                                title={balance[name + '_status'] === 'pending' ? 'Pendiente de pago: $' + Math.abs(balance[name]) : ''}
                                on:mouseenter={(e) => balance.balance_payments?.[name] && showBalancePaymentsTooltip(e, balance.balance_payments[name])}
                                on:mouseleave={scheduleTooltipHide}
                            >
                                <!-- 4. CSS Media Queries取代 window.matchMedia JavaScript event listener -->
                                <div class="z-40 text-[9px] md:text-xs">
                                    <span class="hidden sm:inline">{key}</span>
                                    <span class="sm:hidden">{label}</span>
                                </div>

                                <p class="text-black text-[9px] md:text-xs">
                                    {#if balance[name + '_status'] === 'debt' || balance[name + '_status'] === 'partially_paid'}
                                        ${Math.abs(balance[name])}
                                    {/if}
                                </p>
                            </div>
                        {/each}
                    {/if}
                </div>
            </div>
        {/if}
    {/each}

    <!-- Shared Tooltip Portal -->
    {#if tooltipVisible}
        <!-- svelte-ignore a11y-no-static-element-interactions -->
        <div
            class="min-h-[100px] w-fit bg-white text-dark border border-gray-200 p-2 shadow-lg rounded-md z-20"
            style={tooltipStyle}
            on:mouseenter={() => clearTimeout(tooltipHideTimeout)}
            on:mouseleave={scheduleTooltipHide}
        >
            <iconify-icon
                icon="teenyicons:up-solid"
                width="14"
                height="14"
                class="text-dark absolute -top-2 z-10 inset-x-0 mx-auto w-max"
            />
            {#each tooltipPayments as payment}
                <div class="flex flex-col gap-0.5 items-center mb-2 p-1 relative">
                    <p class="text-xs">{payment.payment.date}</p>
                    <div class="flex items-center gap-1">
                        <p class="text-sm">Total: ${payment.payment.total_in_dolars}</p>
                        <p class="text-xs">Ref: {payment.payment.reference}</p>
                    </div>
                    <p class="text-sm font-bold">Abonado: ${payment.amount}</p>
                </div>
            {/each}
        </div>
    {/if}
</div>