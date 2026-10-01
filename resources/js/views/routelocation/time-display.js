// Divide unrounded totals by every filtered route/date start, not just measured journeys.
export function displayedTimeMinutes(total, routesStarted, mode = "average") {
    if (total == null || !Number.isFinite(Number(total))) return null;
    if (mode === "totals") return Number(total);
    const divisor = Number(routesStarted);
    return Number.isFinite(divisor) && divisor > 0 ? Number(total) / divisor : null;
}
