export const filterFields = [
    { key: "entities", label: "Legal Entity", value: "entity", text: "entity" },
    { key: "clusters", label: "Cluster", value: "clustercode", text: "clustername" },
    { key: "divisions", label: "Division", value: "cmpycode", text: "name" },
    { key: "regions", label: "Region", value: "regionmstcode", text: "regionmstname" },
    { key: "routes", label: "Route", value: "routecode", text: "routename" },
];

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
