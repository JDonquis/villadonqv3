import axios from "axios";

const HISTORICAL_URL =
    "https://ve.dolarapi.com/v1/historicos/dolares/oficial";

function parseLocalDate(dateStr) {
    const [year, month, day] = String(dateStr).split("-").map(Number);
    return new Date(year, (month || 1) - 1, day || 1);
}

function formatLocalDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

function isWeekend(date) {
    const day = date.getDay();
    return day === 0 || day === 6;
}

function startOfToday() {
    const now = new Date();
    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

async function tryHistorical(dateStr) {
    try {
        const apiDateFormat = dateStr.replace(/-/g, "/");
        const { data } = await axios.get(
            `${HISTORICAL_URL}/${apiDateFormat}`,
        );

        return data?.promedio != null
            ? Number(data.promedio).toFixed(2)
            : null;
    } catch (error) {
        if (error.response && error.response.status === 404) {
            return null;
        }

        throw error;
    }
}

async function tryCurrent() {
    try {
        const { data } = await axios.get(
            "https://ve.dolarapi.com/v1/dolares/oficial",
        );

        return data?.promedio != null
            ? Number(data.promedio).toFixed(2)
            : null;
    } catch (error) {
        return null;
    }
}

/**
 * Obtiene la tasa BCV para una fecha.
 *
 * Reglas:
 * - Fines de semana y días feriados usan la tasa del **próximo día hábil**
 *   (BCV publica con antelación la tasa del siguiente día hábil).
 * - Si el próximo día hábil aún no está publicado (p. ej. fin de semana actual),
 *   se usa el endpoint "actual" (última tasa publicada).
 * - Como último recurso, se retrocede hasta la última tasa disponible.
 *
 * Devuelve { rate, dateFound, reason } donde reason es:
 *   'weekend' | 'holiday' | 'previous' | null.
 */
export async function getDolarRateByDate(targetDate, maxAttempts = 15) {
    if (!targetDate) throw new Error("No date provided");

    const target = parseLocalDate(targetDate);
    const today = startOfToday();
    const targetIsWeekend = isWeekend(target);

    // Primer día hábil >= fecha objetivo.
    let cursor = new Date(target);

    while (isWeekend(cursor)) {
        cursor.setDate(cursor.getDate() + 1);
    }

    const holidayLimit = 10;

    for (let i = 0; i < holidayLimit; i++) {
        const dateStr = formatLocalDate(cursor);
        const rate = await tryHistorical(dateStr);

        if (rate) {
            const exact = dateStr === targetDate;

            return {
                rate,
                dateFound: dateStr,
                reason: exact ? null : targetIsWeekend ? "weekend" : "holiday",
            };
        }

        // No hay tasa para ese día (feriado o aún no publicada): avanzar al
        // siguiente día hábil y seguir buscando hasta encontrarla.
        cursor.setDate(cursor.getDate() + 1);

        while (isWeekend(cursor)) {
            cursor.setDate(cursor.getDate() + 1);
        }
    }

    // Fin de semana / feriado actual: el próximo hábil aún no está en histórico.
    if (target >= today) {
        const rate = await tryCurrent();

        if (rate) {
            return {
                rate,
                dateFound: formatLocalDate(cursor),
                reason: targetIsWeekend ? "weekend" : "holiday",
                fromLatest: true,
            };
        }
    }

    // Último recurso: retroceder hasta la última tasa publicada.
    let back = new Date(target);

    for (let i = 0; i < maxAttempts; i++) {
        back.setDate(back.getDate() - 1);

        if (isWeekend(back)) continue;

        const rate = await tryHistorical(formatLocalDate(back));

        if (rate) {
            return {
                rate,
                dateFound: formatLocalDate(back),
                reason: "previous",
            };
        }
    }

    const err = new Error("No rate found");
    err.name = "NoRate";
    throw err;
}

export function convertUsdToBs(usd, rate) {
    const n = parseFloat(usd) || 0;
    if (!rate || isNaN(rate)) return 0;
    return (n * rate).toFixed(2);
}
