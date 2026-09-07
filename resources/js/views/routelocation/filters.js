export const filterFields = [
    { key: "entities", label: "Legal Entity", value: "entity", text: "entity" },
    { key: "clusters", label: "Cluster", value: "clustercode", text: "clustername" },
    { key: "divisions", label: "Division", value: "cmpycode", text: "name" },
    { key: "regions", label: "Region", value: "regionmstcode", text: "regionmstname" },
    { key: "routes", label: "Route", value: "routecode", text: "routename" },
];

export function dateRangeForPreset(preset, today) {
    const start = new Date(`${today}T00:00:00Z`);
    if (preset === "yesterday") start.setUTCDate(start.getUTCDate() - 1);
    if (preset === "week") start.setUTCDate(start.getUTCDate() - (start.getUTCDay() + 6) % 7);
    if (preset === "month") start.setUTCDate(1);
    const from = start.toISOString().slice(0, 10);
    return { from, to: preset === "yesterday" ? from : today };
}

export function dateRangeError(from, to) {
    if (!from || !to) return "Choose both From Date and To Date.";
    if (from > to) return "From Date must be on or before To Date.";
    return "";
}

export function filterOptions(rows, selected, field) {
    const matches = rows.filter((row) => filterFields.every((other) => {
        // Regions are independent of the company hierarchy; routes connect them.
        if (other.key === field.key ||
            (field.key === "regions" && other.key !== "routes") ||
            (other.key === "regions" && field.key !== "routes")) return true;
        return !selected[other.key].length || selected[other.key].some((value) => String(value) === String(row[other.value]));
    }));
    const options = new Map();
    for (const row of matches) {
        const value = row[field.value];
        if (value === null || value === undefined || String(value).trim() === "") continue;
        options.set(String(value), {
            value,
            label: field.key === "routes" ? `${value} - ${row[field.text] ?? ""}` : (row[field.text] || String(value)),
        });
    }
    return [...options.values()].sort((a, b) => a.label.localeCompare(b.label));
}
