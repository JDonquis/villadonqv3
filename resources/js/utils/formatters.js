export function formatBsInput(value) {
    const raw = String(value ?? "").replace(/[^\d]/g, "");

    if (!raw) return "";

    const digits = raw.replace(/^0+(?=\d)/, "");
    const integerPart = digits.slice(0, -2) || "0";
    const decimalPart = digits.slice(-2).padStart(2, "0");

    return `${Number(integerPart).toLocaleString("de-DE")},${decimalPart}`;
}
