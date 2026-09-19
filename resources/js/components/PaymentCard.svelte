<script>
    import ColorsPayMethods from "./ColorsPayMethods";
    import { displayAlert } from "../stores/alertStore";

    export let payment;
    export let onSelect;
    export let dolarPrice = 0;

    async function copyToClipboard(value, label = "Texto") {
        if (!value) {
            displayAlert({ type: "error", message: `No hay ${label.toLowerCase()} para copiar.` });
            return;
        }
        try {
            await navigator.clipboard.writeText(String(value));
            displayAlert({ type: "success", message: `${label} copiado al portapapeles` });
        } catch (error) {
            displayAlert({ type: "error", message: "No se pudo copiar al portapapeles." });
        }
    }

    function formatFechaHumana(dateString) {
        if (!dateString) return '';
        const d = new Date(`${dateString}T00:00:00`);
        return new Intl.DateTimeFormat('es-VE', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }).format(d);
    }

    function formatFechaCorta(dateString) {
        if (!dateString) return '';
        const d = new Date(`${dateString}T00:00:00`);
        return new Intl.DateTimeFormat('es-VE', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        }).format(d);
    }

    function formatBs(value) {
        if (!value) return '0,00';
        const num = parseFloat(value);
        return num.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const isDeleted = payment.status === 0;
    const conceptName = payment.payment_concept?.name || 'Mensualidad / Inscripciones';
    const methodName = payment.account_payment?.method?.name || '';
    const methodColor = methodName ? ColorsPayMethods()[methodName] : 'gray';
    const studentsCount = payment.students?.length || 0;

    function handleCopyReference() {
        if (payment.reference) {
            copyToClipboard(payment.reference, 'Referencia');
        }
    }

    function handleClick(e) {
        e.stopPropagation();
        onSelect?.(payment);
    }
</script>

<div
    class={` rounded-xl shadow-sm  overflow-hidden transition-all duration-200 hover:shadow-md ${
        isDeleted ? 'bg-red  opacity-70 bg-opacity-20' : 'bg-white'
    }`}
    on:click={handleClick}
    role="button"
    tabindex="0"
    on:keydown={(e) => { if (e.key === 'Enter' || e.key === ' ') handleClick(e); }}
>
    <!-- Fila principal: Concepto, Método, Totales -->
    <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div class="flex-1 min-w-0  flex-wrap items-center gap-2">
            <!-- Concepto badge -->
            <span class="inline-flex items-center px-2 mb-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200 truncate max-w-[200px]">
                {conceptName} 
            </span>

            <!-- Método de pago con punto de color -->
            <div class="flex items-center gap-1.5 text-sm text-gray-700">
                <span
                    class={`w-2.5 h-2.5 rounded-full flex-shrink-0 bg-${methodColor}`}
                ></span>
                <span class="font-medium">{methodName}</span>
                {#if payment.account_payment?.bank}
                    <span class="text-gray-400">- {payment.account_payment.bank}</span>
                {/if}
                {#if payment.account_payment?.cash_currency}
                    <span class="text-gray-400">- {payment.account_payment.cash_currency}</span>
                {/if}
                {#if payment.account_payment?.username}
                    <span class="text-gray-400">- {payment.account_payment.username}</span>
                {/if}
            </div>
        </div>

        <!-- Totales alineados a la derecha -->
        <div class="flex items-center gap-4 text-right sm:ml-auto w-full sm:w-auto flex-shrink-0">
            <div class="text-xs flex gap-x-2">
                <div class="font-semibold text-green-700">${payment.total_in_dolars}</div>
                <span>•</span>
                <div class="text-gray-700">{formatBs(payment.total_in_bs)} Bs</div>
            </div>
        </div>
    </div>

    <!-- Separador -->
    <div class=""></div>

    <!-- Fila inferior: Referencia + Estudiantes count + Acciones -->
    <div class="px-4 py-3 pt-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm">
        <div class="flex items-center gap-2 flex-1 min-w-0">
            {#if payment.reference}
                <span class="text-gray-500">Ref:</span>
                <span
                    class="font-mono text-gray-800 truncate cursor-pointer hover:text-color1"
                    on:click|stopPropagation={handleCopyReference}
                    title="Copiar referencia"
                >
                    {payment.reference}
                </span>
            {/if}

            <!-- Contador de estudiantes -->
            <span class="flex items-center gap-1 text-gray-400 ml-2">
                <iconify-icon icon="mdi:account-group" width="14" height="14"></iconify-icon>
                <span>{studentsCount} estudiante{studentsCount !== 1 ? 's' : ''}</span>
            </span>
        </div>

        <!-- Acciones -->
        <div class="flex items-center gap-2 sm:ml-auto">
            {#if isDeleted}
                <span class="inline-flex items-center px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded-full">
                    <iconify-icon icon="mdi:alert-circle-outline" width="12" height="12" class="mr-1"></iconify-icon>
                    Eliminado
                </span>
            {/if}

        </div>
    </div>
</div>