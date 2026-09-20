// Interpret database wall-clock timestamps without shifting them to the browser timezone.
const timestamp = value => typeof value === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)
    ? Date.parse(value.replace(' ', 'T') + 'Z') : NaN;
const DAY = 86400000;
export const clockTime = minute => {
    const total = Math.min(1440, Math.max(0, Math.floor(minute)));
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
};

export function journeyTimeline(journeys) {
    const rows = [];
    for (const journey of journeys) {
        const start = timestamp(journey.start), end = timestamp(journey.end);
        if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) continue;
        const intervals = (journey.visits ?? []).map(([from, to]) => [Math.max(start, timestamp(from)), Math.min(end, timestamp(to))])
            .filter(([from, to]) => Number.isFinite(from) && Number.isFinite(to) && to > from)
            .sort((a, b) => a[0] - b[0]);
        const merged = [];
        for (const interval of intervals) {
            const previous = merged.at(-1);
            if (previous && interval[0] <= previous[1]) previous[1] = Math.max(previous[1], interval[1]);
            else merged.push([...interval]);
        }
        const otp = (journey.otp_visits ?? []).map(([a, b]) => [timestamp(a), timestamp(b)])
            .filter(([a, b]) => Number.isFinite(a) && Number.isFinite(b) && b > a);
        for (let day = Math.floor(start / DAY) * DAY; day < end; day += DAY) {
            const from = Math.max(start, day), to = Math.min(end, day + DAY);
            const date = new Date(day).toISOString().slice(0, 10);
            const segments = [];
            const add = (kind, a, b) => {
                if (b > a) segments.push({ kind, from: (a - day) / 60000, to: (b - day) / 60000, minutes: (b - a) / 60000 });
            };
            let cursor = from;
            for (const [a, b] of merged) {
                const visitStart = Math.max(a, from), visitEnd = Math.min(b, to);
                if (visitEnd <= visitStart) continue;
                add('outside', cursor, visitStart);
                const boundaries = [...new Set([visitStart, visitEnd, ...otp.flatMap(([a, b]) => [Math.max(visitStart, Math.min(visitEnd, a)), Math.max(visitStart, Math.min(visitEnd, b))])])].sort((a, b) => a - b);
                for (let i = 1; i < boundaries.length; i++) {
                    const a = boundaries[i - 1], b = boundaries[i];
                    const kind = otp.some(([s, e]) => s < b && e > a) ? 'otp' : 'visit';
                    const previous = segments.at(-1);
                    if (previous?.kind === kind && previous.to === (a - day) / 60000) {
                        previous.to = (b - day) / 60000;
                        previous.minutes += (b - a) / 60000;
                    } else add(kind, a, b);
                }
                cursor = visitEnd;
            }
            add('outside', cursor, to);
            const duration = (to - from) / 60000;
            const visitMinutes = segments.filter(segment => segment.kind === 'visit').reduce((sum, segment) => sum + segment.minutes, 0);
            const otpMinutes = segments.filter(segment => segment.kind === 'otp').reduce((sum, segment) => sum + segment.minutes, 0);
            rows.push({
                key: `${journey.routekey}:${date}`, routecode: journey.routecode,
                label: `${journey.routecode} - ${journey.route} · ${date}`,
                date, closed: journey.closed, from: (from - day) / 60000, to: (to - day) / 60000,
                duration, visitMinutes, otpMinutes, outsideMinutes: duration - visitMinutes - otpMinutes, segments,
            });
        }
    }
    return rows.sort((a, b) => String(a.routecode).localeCompare(String(b.routecode), undefined, { numeric: true }) || a.date.localeCompare(b.date) || a.from - b.from);
}

export function selectTimelineRoutes(journeys, expanded = false) {
    if (expanded) return journeys;
    const durations = new Map();
    for (const journey of journeys) {
        const duration = timestamp(journey.end) - timestamp(journey.start);
        if (Number.isFinite(duration) && duration > 0) durations.set(String(journey.routecode), (durations.get(String(journey.routecode)) ?? 0) + duration);
    }
    const selected = new Set([...durations].sort((a, b) => b[1] - a[1]).slice(0, 10).map(([code]) => code));
    return journeys.filter(journey => selected.has(String(journey.routecode)));
}
