// Dates travel as 'YYYY-MM-DD'. `new Date('2026-08-02')` would parse that as UTC
// and can land on the previous day, so always build local dates explicitly.
export function parseDate(ymd) {
    const [y, m, d] = ymd.split('-').map(Number);
    return new Date(y, m - 1, d);
}

export function toYmd(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export const todayYmd = () => toYmd(new Date());

export function formatDate(ymd, options) {
    return parseDate(ymd).toLocaleDateString('en-GB', options);
}

// "6 Nov", with the year once it isn't the current one
export function shortDate(ymd) {
    const sameYear = parseDate(ymd).getFullYear() === new Date().getFullYear();
    return formatDate(ymd, { day: 'numeric', month: 'short', ...(sameYear ? {} : { year: 'numeric' }) });
}

export function daysBetween(fromYmd, toYmdValue = todayYmd()) {
    return Math.round((parseDate(toYmdValue) - parseDate(fromYmd)) / 86_400_000);
}

// Weights and distances: at most one decimal, no trailing zero, thousands separator
export function num(value) {
    return Number(value).toLocaleString('en-GB', { maximumFractionDigits: 1 });
}

export const setLabel = (set) => `${num(set.weight)} × ${set.reps}`;

export const volume = (sets) => sets.reduce((sum, s) => sum + s.weight * s.reps, 0);

export function paceLabel(seconds) {
    return seconds ? `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')} /km` : null;
}

export const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

// "3 sets: 10, 10 and 8 reps" when the weight never changed, otherwise each set in full
export function setsSentence(sets) {
    if (!sets.length) return '';
    const sameWeight = sets.every((s) => s.weight === sets[0].weight);
    if (!sameWeight) return sets.map(setLabel).join(', ');
    const reps = sets.map((s) => s.reps);
    const list = reps.length > 1 ? `${reps.slice(0, -1).join(', ')} and ${reps.at(-1)}` : `${reps[0]}`;
    return `${plural(sets.length, 'set')}: ${list} reps`;
}

export const topWeight = (sets) => (sets.length ? Math.max(...sets.map((s) => s.weight)) : null);
