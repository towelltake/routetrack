export const days = [
  ['sunseq', 'Sunday'], ['monseq', 'Monday'], ['tueseq', 'Tuesday'],
  ['wedseq', 'Wednesday'], ['thuseq', 'Thursday'], ['friseq', 'Friday'], ['satseq', 'Saturday'],
];

export function hasCoordinates(row) {
  const lat = Number(row.fixedlatitude), lng = Number(row.fixedlongitude);
  return row.fixedlatitude != null && row.fixedlongitude != null &&
    String(row.fixedlatitude).trim() !== '' && String(row.fixedlongitude).trim() !== '' &&
    Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 &&
    Math.abs(lng) <= 180 && !(lat === 0 && lng === 0);
}

export function distance(a, b) {
  const rad = Math.PI / 180;
  const lat1 = Number(a.fixedlatitude) * rad, lat2 = Number(b.fixedlatitude) * rad;
  const dlat = lat2 - lat1, dlng = (Number(b.fixedlongitude) - Number(a.fixedlongitude)) * rad;
  const h = Math.sin(dlat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dlng / 2) ** 2;
  return 6371 * 2 * Math.asin(Math.sqrt(Math.min(1, Math.max(0, h))));
}

export function routeDistance(rows) {
  if (!rows.every(hasCoordinates)) return null;
  return rows.slice(1).reduce((sum, row, i) => sum + distance(rows[i], row), 0);
}

// Preview only: positive sequence values are candidates, not proof of scheduled visits.
// Preserve the first stop because no depot/start-location mapping is confirmed yet.
export function optimiseOrder(rows) {
  if (rows.length < 3 || !rows.every(hasCoordinates)) return [...rows];
  const remaining = rows.slice(1), result = [rows[0]];
  while (remaining.length) {
    let best = 0;
    for (let i = 1; i < remaining.length; i++) {
      if (distance(result.at(-1), remaining[i]) < distance(result.at(-1), remaining[best])) best = i;
    }
    result.push(remaining.splice(best, 1)[0]);
  }
  return routeDistance(result) < routeDistance(rows) ? result : [...rows];
}
