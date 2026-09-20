// Presentation only: keep the original precision in calculations.
export function formatVisitMinutes(value) {
    if (value == null || value === '' || !Number.isFinite(Number(value)) || Number(value) < 0) return 'Unavailable';
    const minutes = Number(value);
    if (minutes > 0 && minutes < 1) return '<1 min';
    const rounded = Math.round(minutes);
    return rounded < 60 ? rounded + ' min' : Math.floor(rounded / 60) + 'h ' + String(rounded % 60).padStart(2, '0') + 'm';
}
