<script>
    export let label = "";
    export let value = "";
    export let icon = "";
    export let color = "blue";
    export let trend = null;

    const colorClasses = {
        blue: "bg-blue-50 text-blue-700 border-blue-200",
        green: "bg-green-50 text-green-700 border-green-200",
        red: "bg-red-50 text-red-700 border-red-200",
        orange: "bg-orange-50 text-orange-700 border-orange-200",
        purple: "bg-purple-50 text-purple-700 border-purple-200",
        teal: "bg-teal-50 text-teal-700 border-teal-200",
        amber: "bg-amber-50 text-amber-700 border-amber-200",
    };

    $: baseClasses = "bg-white rounded-xl  p-5 shadow-sm hover:shadow-md transition-shadow neumorphism";
    $: colorClass = colorClasses[color] || colorClasses.blue;
    $: cardClasses = `${baseClasses} ${colorClass}`;
    $: trendColorClass = trend ? (trend.up ? "text-green-600" : "text-red-600") : "";
    $: trendIcon = trend ? (trend.up ? "mdi:trending-up" : "mdi:trending-down") : "";
</script>

<div class={cardClasses} role="article">
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-600 truncate">{label}</p>
            <p class="text-2xl md:text-3xl font-bold mt-1 truncate">{value}</p>
            {#if trend}
                <p class="text-xs mt-1 flex items-center gap-1 {trendColorClass}">
                    <iconify-icon icon={trendIcon} width="12" height="12"></iconify-icon>
                    {trend.value}%
                </p>
            {/if}
        </div>
        <div class="flex-shrink-0 ml-4">
            <iconify-icon icon={icon} width="32" height="32" class="opacity-70"></iconify-icon>
        </div>
    </div>
</div>