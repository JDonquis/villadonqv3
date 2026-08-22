<script>
    // let balances = [
    //     {
    //         id: 48,
    //         student_id: 49,
    //         status: "pending",
    //         inscription: 0,
    //         inscription_status: "paid",
    //         // Meses iniciales y mediados del periodo (Pagados/Sin deuda)
    //         september: 0,
    //         september_status: "paid",
    //         october: 0,
    //         october_status: "paid",
    //         november: 0,
    //         november_status: "paid",
    //         december: 0,
    //         december_status: "paid",
    //         january: 0,
    //         january_status: "paid",
    //         february: 0,
    //         february_status: "paid",
    //         march: 0,
    //         march_status: "paid",
    //         april: 0,
    //         april_status: "paid",
    //         may: 0,
    //         may_status: "paid",
    //         // ÚLTIMOS 3 MESES DEL PERIODO (Según orden escolar: Sep -> Ago)
    //         june: -50,
    //         june_status: "debt",
    //         july: -50,
    //         july_status: "debt",
    //         august: -50,
    //         august_status: "debt",
    //         school_lapse_id: 1,
    //         created_at: "2026-05-01T03:44:12.000000Z",
    //         updated_at: "2026-05-01T03:44:12.000000Z",
    //         school_lapse: {
    //             id: 1,
    //             start: "2024-09-01",
    //             end: "2025-08-31",
    //             status: 1,
    //             created_at: "2026-05-01 03:44:08",
    //             updated_at: "2026-05-01 03:44:08",
    //         },
    //     },

    //     {
    //         id: 49,
    //         student_id: 49,
    //         status: "pending",
    //         inscription: -50,
    //         inscription_status: "pending",
    //         january: -50,
    //         january_status: "pending",
    //         february: -50,
    //         february_status: "pending",
    //         march: -50,
    //         march_status: "pending",
    //         april: -50,
    //         april_status: "pending",
    //         may: -50,
    //         may_status: "pending",
    //         june: -50,
    //         june_status: "pending",
    //         july: -50,
    //         july_status: "pending",
    //         august: -50,
    //         august_status: "pending",
    //         september: -50,
    //         september_status: "debt",
    //         october: -50,
    //         october_status: "debt",
    //         november: -50,
    //         november_status: "debt",
    //         december: -50,
    //         december_status: "debt",
    //         school_lapse_id: 1,
    //         created_at: "2026-05-01T03:44:12.000000Z",
    //         updated_at: "2026-05-01T03:44:12.000000Z",
    //         school_lapse: {
    //             id: 2,
    //             start: "2026-09-01",
    //             end: "2027-08-31",
    //             status: 1,
    //             created_at: "2026-05-01 03:44:08",
    //             updated_at: "2026-05-01 03:44:08",
    //         },
    //     },
    // ];

    const months = {
        sep: "september",
        oct: "october",
        nov: "november",
        dic: "december",
        ene: "january",
        feb: "february",
        mar: "march",
        abr: "april",
        may: "may",
        jun: "june",
        jul: "july",
        ago: "august",
    };

    const monthsCalendar = {
        january: 0,
        february: 1,
        march: 2,
        april: 3,
        may: 4,
        june: 5,
        july: 6,
        august: 7,
        september: 8,
        october: 9,
        november: 10,
        december: 11,
    };

    export let balances;
    export let amountToPay = 0;
    export let classes = "";
    export let is_exempt = false;
    export let dayOfPayment = 0;
    export let gracePeriod = 0;
    let tooltipVisible = false;
    let tooltipPayments = [];
    let tooltipStyle = "";
    let tooltipHideTimeout;

    // $: console.log("Balances actualizados:", balances);

    // Obtener el año actual y el día actual para la comparación histórica
    const currentMonth = new Date().getMonth(); // 0-11
    const currentYear = new Date().getFullYear();
    const currentDay = new Date().getDate();

    function checkIfMonthIsExpired(monthName) {
        const monthIndex = monthsCalendar[monthName];
        const expirationDay = dayOfPayment + gracePeriod;

        // Hardcode: Si el mes es Enero (0), usar año pasado
        // Si es cualquier otro mes, usar año actual
        let year = currentYear;
        if (monthIndex > 7) {
            year = currentYear - 1;
        }

        let expirationDate;
        if (expirationDay > 30) {
            expirationDate = new Date(year, monthIndex + 1, expirationDay - 30);
        } else {
            expirationDate = new Date(year, monthIndex, expirationDay);
        }

        const today = new Date();
        expirationDate.setHours(0, 0, 0, 0);
        today.setHours(0, 0, 0, 0);

        return expirationDate < today;
    }

    export let id = "";
    const firstUnpaidMonth =
        balances[0].status != "pending"
            ? Object.entries(months).findIndex(([spanisMonth, monthName]) => {
                  const status = balances[0]?.[`${monthName}_status`];
                  return status === "debt" || status === "partially_paid";
              })
            : Object.entries(months).findIndex(([spanisMonth, monthName]) => {
                  const status = balances[0]?.[`${monthName}_status`];
                  return status == "pending";
              });

    let startPointToPay = {
        school_lapse_index: 0,
        month: firstUnpaidMonth, // Si no hay deudas, cae al primer mes por defecto
    };

    console.log({ firstUnpaidMonth }, balances[0]);

    // $: console.log(firstUnpaidMonth);
    let payingBalances = [{}];

    let endPointToPay = {};

    function getLastPaymentMonth(amountToPay) {
        let lastPaymentMonth = null;
        let endMonthIndex = firstUnpaidMonth;
        let endYearIndex = startPointToPay.school_lapse_index;
        let partialToPay = 0;
        let startMonth = firstUnpaidMonth;
        const arrMonthsEnglish = Object.values(months);
        payingBalances = new Array(balances.length).fill({});

        while (amountToPay > 0) {
            if (endYearIndex > balances.length - 1) {
                break;
            }

            if (
                !payingBalances[endYearIndex]?.balanceInscription &&
                balances[endYearIndex]?.inscription < 0
            ) {
                payingBalances[endYearIndex].balanceInscription = amountToPay;
                amountToPay -= Math.abs(balances[endYearIndex].inscription);
            }
            if (amountToPay <= 0) {
                break;
            }
            const balance = Math.abs(
                balances[endYearIndex][arrMonthsEnglish[endMonthIndex]],
            );

            if (amountToPay < balance) {
                partialToPay = amountToPay;
            }

            amountToPay -= balance;

            payingBalances[endYearIndex] = {
                ...payingBalances[endYearIndex],
                startMonth,
                endMonthIndex,
                endYearIndex,
            };
            if (endMonthIndex == 11) {
                endYearIndex++;
                endMonthIndex = 0;
                startMonth = 0;
            } else {
                endMonthIndex++;
            }
        }
        endPointToPay = { endMonthIndex, endYearIndex, partialToPay };
        return { endMonthIndex, endYearIndex, partialToPay };
    }

    $: console.log({ payingBalances }, { endPointToPay });

    function showBalancePaymentsTooltip(event, payments) {
        if (!payments || payments.length === 0) {
            tooltipVisible = false;
            return;
        }

        clearTimeout(tooltipHideTimeout);
        tooltipPayments = payments;
        // console.log(tooltipPayments)
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

    function hideBalancePaymentsTooltip() {
        tooltipVisible = false;
    }

    // Reactive statement: run getLastPaymentMonth whenever amountToPay changes
    $: endPointToPay = getLastPaymentMonth(amountToPay);
</script>

<div {id} class={`bg-white p-4 rounded-lg ${classes}`}>
    {#each balances as balance, indexYear}
    {@const totalDebt = Math.abs(
        Object.entries(months).reduce((total, [_, month]) => {
            if (
                (balance[month] < 0 && balance[month + "_status"] == "debt") ||
                balance[month + "_status"] == "partially_paid"
            ) {
                total += balance[month];
            }
            return total;
        }, 0)
    ) + Math.abs(balance.inscription)}
        <div class="flex gap-4 items-center mt-2 mb-2">
            <!-- <button>
                <iconify-icon
                    class="rotate-180 relative top-1"
                    icon="grommet-icons:form-next"
                    width="24"
                    height="24"
                ></iconify-icon>
            </button> -->
            <p class="text-xs font-bold">
                                    
                {balance.school_lapse?.start.slice(0, 4)} <span class="text-gray-400">•</span> {balance.school_lapse?.end.slice(
                    0,
                    4,
                )}
            </p>

            <div class="flex items-center gap-1 text-xs">
                <p>Deuda:</p>
                {#if totalDebt > 0}
                    <p class="font-bold text-red">${totalDebt}</p>
                {:else}
                    <p class="">0</p>
                {/if}
                {#if is_exempt}
                    <div
                        class="flex ml-2 items-center gap-2 text-xs mb-2 font-bold bg-purple w-fit px-2 py-1"
                    >
                        <p>Exonerado: {is_exempt}%</p>
                        <iconify-icon icon="mdi:shield-check" class="" />
                    </div>
                {/if}
            </div>
            <!-- <button>
                <iconify-icon
                    class="relative top-1"
                    icon="grommet-icons:form-next"
                    width="24"
                    height="24"
                ></iconify-icon>
            </button> -->
        </div>

        {#if is_exempt < 100}
            <div class="grid p-0 grid-cols-12 rounded-2xl overflow-hidden border-2 border-gray-200">
                <!-- svelte-ignore a11y-no-static-element-interactions -->
                <div
                    class={` hover:brightness-125   relative col-span-1 z-10  text-xs text-gray-700  p-1 capitalize  text-center font-bold
                 ${balance.inscription_status === "pending" || balance.inscription_status === "debt" ? "bg-red/70" : ""}
            ${balance.inscription_status === "paid" ? "bg-green/50" : ""}
            ${balance.inscription_status === "partially_paid" ? "bg-yellow/70" : ""}`}
                    on:mouseenter={(e) =>
                        balance.balance_payments.inscription
                            ? showBalancePaymentsTooltip(
                                  e,
                                  balance.balance_payments.inscription,
                              )
                            : null}
                    on:mouseleave={scheduleTooltipHide}
                >
                    <span> Inscr. </span>

                    <p class="text-black">
                        {Math.abs(balance.inscription) > 0
                            ? "$" + Math.abs(balance.inscription)
                            : ""}
                    </p>

                    <div
                        class={`absolute top-0.5 left-0  h-[95%] z-40 ${payingBalances[indexYear]?.balanceInscription > 0 ? "bg-purple/30 border-y-4 border-black/50 border" : ""}`}
                        style={payingBalances[indexYear]?.balanceInscription > 0
                            ? `max-width: 100%; width: ${(payingBalances[indexYear]?.balanceInscription / Math.abs(balance.inscription)) * 100}%`
                            : ""}
                    ></div>
                </div>
                <div class="col-span-11 grid grid-cols-12">
                    {#each Object.entries(months) as [spanishLabel, month], indexMonth}
                        <!-- svelte-ignore a11y-no-static-element-interactions -->
                        <div
                            class={`group/month hover:brightness-110  hover:border-x border-black/30 relative col-span-1 text-xs capitalize text-center font-bold p-1 text-gray-700
            ${balance[month + "_status"] === "debt" ? "bg-red/70" : ""}
            ${balance[month + "_status"] === "paid" ? "bg-green/50" : ""}
            ${balance[month + "_status"] === "partially_paid" ? (checkIfMonthIsExpired(month) ? "bg-yellow/70" : "bg-blue") : ""}
            ${!balance[month + "_status"] ? "bg-gray-50 " : ""}
        `}
                            title={balance[month + "_status"] == "pending"
                                ? "Pendiente de pago: $" +
                                  Math.abs(balance[month])
                                : ""}
                            on:mouseenter={(e) =>
                                balance.balance_payments?.[month]
                                    ? showBalancePaymentsTooltip(
                                          e,
                                          balance.balance_payments[month],
                                      )
                                    : null}
                            on:mouseleave={scheduleTooltipHide}
                        >
                            <div class="z-40">
                                {spanishLabel}
                            </div>
                            <p class="text-black">
                                {#if balance[month + "_status"] == "debt" || balance[month + "_status"] == "partially_paid"}
                                    ${Math.abs(balance[month])}
                                {/if}
                            </p>

                            <!-- El resto de tu div de progreso (months_to_pay) se queda exactamente igual -->
                            <div
                                class={`text-xs months_to_pay absolute top-0.5 left-0 w-full text-black h-[95%] z-40
            ${indexMonth === startPointToPay.month && startPointToPay.school_lapse_index == +indexYear && amountToPay > Math.abs(balance[month]) ? "border-l-4 border-black/50" : ""}
            ${indexMonth === endPointToPay.endMonthIndex - 1 && endPointToPay.endYearIndex == +indexYear && amountToPay > 0 ? "border-r-4 border-black/50" : ""}
            ${startPointToPay.school_lapse_index <= indexYear && payingBalances[indexYear]?.startMonth <= indexMonth && indexMonth <= payingBalances[indexYear]?.endMonthIndex ? "bg-purple/30 border-y-4 border-black/50" : ""}`}
                                style={((indexMonth ==
                                    endPointToPay.endMonthIndex - 1 &&
                                    endPointToPay.endYearIndex == +indexYear) ||
                                    indexMonth == 11) &&
                                endPointToPay.partialToPay > 0
                                    ? `width: ${(endPointToPay.partialToPay / Math.abs(balance[month])) * 100}%`
                                    : ""}
                            ></div>
                        </div>
                    {/each}
                </div>
            </div>
        {/if}
    {/each}

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
                <div
                    class="flex flex-col gap-0.5 items-center mb-2 p-1 relative"
                >
                    <p class="text-xs">{payment.payment.date}</p>
                    <div class="flex items-center gap-1">
                        <p class="text-sm">
                            Total: ${payment.payment.total_in_dolars}
                        </p>
                        <p class="text-xs">Ref: {payment.payment.reference}</p>
                    </div>
                    <p class="text-sm font-bold">Abonado: ${payment.amount}</p>
                </div>
            {/each}
        </div>
    {/if}
</div>
