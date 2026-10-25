export const TIME_ZONE = 'America/Bogota';

export function formatDate(value) {
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        const [year, month, day] = value.split('-');
        const probe = new Date(`${value}T12:00:00Z`);
        if (!Number.isFinite(probe.getTime()) || probe.toISOString().slice(0, 10) !== value) throw new RangeError('Fecha inválida.');
        return `${day}/${month}/${year}`;
    }
    // Los instantes necesitan zona explícita: no interpretar fechas locales como UTC.
    if (!(value instanceof Date) && (typeof value !== 'string' || !/(Z|[+-]\d{2}:\d{2})$/.test(value))) throw new RangeError('Fecha inválida o sin zona horaria.');
    const date = value instanceof Date ? value : new Date(value);
    if (!Number.isFinite(date.getTime())) throw new RangeError('Fecha inválida.');
    return new Intl.DateTimeFormat('es-CO', { timeZone: TIME_ZONE, day: '2-digit', month: '2-digit', year: 'numeric' }).format(date);
}

export function todayBogota() {
    const parts = new Intl.DateTimeFormat('en-CA', { timeZone: TIME_ZONE, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
    const get = (name) => parts.find((part) => part.type === name).value;
    return `${get('year')}-${get('month')}-${get('day')}`;
}

export function periodRange(period, today = todayBogota()) {
    const [year,month] = today.split('-').map(Number);
    const date = (y,m,d) => new Date(Date.UTC(y,m-1,d)).toISOString().slice(0,10);
    if (period === 'mes-anterior') return {from:date(year,month-1,1),to:date(year,month,0)};
    if (period === 'trimestre') return {from:date(year,Math.floor((month-1)/3)*3+1,1),to:today};
    if (period === 'ano') return {from:date(year,1,1),to:today};
    return {from:date(year,month,1),to:today};
}
