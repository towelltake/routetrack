export const dimensions = [
    { key: "route", label: "Route", id: "routecode" },
    { key: "division", label: "Division", id: "cmpycode" },
    { key: "entity", label: "Legal entity", id: "entity" },
    { key: "cluster", label: "Cluster", id: "clustercode" },
    { key: "region", label: "Region", id: "regionmstcode" },
    { key: "salesperson", label: "Salesperson", id: "salesmancode" },
];

export const number = (value, digits = 0) => value == null ? "—" : Number(value).toLocaleString(undefined, { maximumFractionDigits: digits });
export const rate = (value, total) => total > 0 ? value / total * 100 : null;
export const percent = (value, total) => total > 0 ? `${number(rate(value, total), 1)}%` : "—";
export const trackUrl = (row) => `/route-tracking?routecode=${encodeURIComponent(row.routecode)}&date=${encodeURIComponent(row.date)}`;

export function groupJourneys(rows, dimension = "route") {
    const field = dimensions.find((field) => field.key === dimension) ?? { key: "date", id: "date" };
    const groups = new Map();
    for (const row of rows) {
        const id = String(row[field.id] ?? "");
        if (!groups.has(id)) groups.set(id, {
            id, label: dimension === "route" ? `${row.routecode} - ${row.route}` : row[field.key] || "Unassigned",
            rows: [], customers: new Set(), closed: 0, issues: 0, duration_count: 0, distance_count: 0,
            amounts: { sales: {}, orders: {}, collections: {} },
        });
        const group = groups.get(id);
        group.rows.push(row);
        for (const key of ["planned", "covered", "pending", "missed", "visits", "completed", "productive", "nonproductive", "unplanned", "out_of_sequence", "repeat", "actual_cft", "expected_cft", "configured_actual_cft", "configured_visits", "missing_cft", "incomplete_visits", "otp"]) {
            group[key] = (group[key] ?? 0) + (Number(row[key]) || 0);
        }
        for (const code of row.customer_codes) group.customers.add(code);
        group.closed += row.closed ? 1 : 0;
        group.issues += row.issues.length > 0 ? 1 : 0;
        for (const key of ["duration", "visit_time", "remaining_time", "distance"]) {
            if (row[key] != null) {
                group[key] = (group[key] ?? 0) + Number(row[key]);
                if (key === "duration" || key === "distance") group[`${key}_count`]++;
            }
        }
        for (const type of ["sales", "orders", "collections"]) {
            for (const amount of row.amounts[type] ?? []) {
                const code = amount.currencycode;
                group.amounts[type][code] ??= { currency: amount.currency, amount: 0 };
                group.amounts[type][code].amount += Number(amount.amount);
            }
        }
    }
    return [...groups.values()].sort((a, b) => a.label.localeCompare(b.label, undefined, { numeric: true }));
}

export function chartOptions({ horizontal = false, stacked = false, onClick } = {}) {
    return {
        responsive: true, maintainAspectRatio: false, indexAxis: horizontal ? "y" : "x",
        animation: false, interaction: { mode: "index", intersect: false },
        onClick: (_event, elements) => { if (elements.length && onClick) onClick(elements[0].index); },
        plugins: {
            legend: { position: "bottom", labels: { usePointStyle: true, pointStyle: "circle", boxWidth: 7, padding: 18, color: "#64748b", font: { size: 11 } } },
            tooltip: { backgroundColor: "#172b45", padding: 12, cornerRadius: 8, titleFont: { weight: "600" }, callbacks: { label: (context) => `${context.dataset.label}: ${number(context.parsed[horizontal ? "x" : "y"], 1)}` } },
        },
        scales: {
            x: { stacked, beginAtZero: true, grid: { display: horizontal, color: "#eef2f6" }, border: { display: false }, ticks: { color: "#64748b", font: { size: 10 }, maxRotation: 30 } },
            y: { stacked, beginAtZero: true, grid: { display: !horizontal, color: "#eef2f6" }, border: { display: false }, ticks: { color: "#64748b", font: { size: 10 }, precision: 0 } },
        },
    };
}

export const dataset = (label, data, color) => ({ label, data, backgroundColor: color, borderRadius: 4, maxBarThickness: 28 });
