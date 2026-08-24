import axios from "axios";

export async function getDolarRateByDate(targetDate, maxAttempts = 15) {
    if (!targetDate) throw new Error("No date provided");

    let currentDateStr = targetDate;
    let attempts = 0;

    while (attempts < maxAttempts) {
        try {
            const apiDateFormat = currentDateStr.replace(/-/g, "/");
            const url = `https://ve.dolarapi.com/v1/historicos/dolares/oficial/${apiDateFormat}`;

            const response = await axios.get(url);

            const rate = response.data.promedio;
            return { rate, dateFound: currentDateStr };
        } catch (error) {
            if (error.response && error.response.status === 404) {
                attempts++;
                const dateObj = new Date(`${currentDateStr}T00:00:00`);
                dateObj.setDate(dateObj.getDate() - 1);
                currentDateStr = dateObj.toISOString().split("T")[0];
                continue;
            }

            throw error;
        }
    }

    const err = new Error("No rate found in previous days");
    err.name = "NoRate";
    throw err;
}

export function convertUsdToBs(usd, rate) {
    const n = parseFloat(usd) || 0;
    if (!rate || isNaN(rate)) return 0;
    return (n * rate).toFixed(2);
}
