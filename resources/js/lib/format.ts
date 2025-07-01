import type { Locale } from '@/i18n';

const intlLocale = (locale: Locale) => (locale === 'ru' ? 'ru-RU' : 'en-GB');

/** Amounts arrive in minor units (kopecks, cents). */
export function money(minor: number | null | undefined, currency: string | null | undefined, locale: Locale): string {
    if (minor === null || minor === undefined) {
        return '—';
    }
    const value = minor / 100;
    if (!currency) {
        return new Intl.NumberFormat(intlLocale(locale), { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(
            value,
        );
    }
    return new Intl.NumberFormat(intlLocale(locale), {
        style: 'currency',
        currency,
        currencyDisplay: 'narrowSymbol',
    }).format(value);
}

/** Large figures for charts: 1.2 млн ₽, £48K. */
export function compactMoney(minor: number, currency: string, locale: Locale): string {
    return new Intl.NumberFormat(intlLocale(locale), {
        style: 'currency',
        currency,
        currencyDisplay: 'narrowSymbol',
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(minor / 100);
}

export function decimalText(value: string | null, locale: Locale): string {
    if (value === null || value === '') {
        return '';
    }
    const number = Number(value);
    if (Number.isNaN(number)) {
        return value;
    }
    return new Intl.NumberFormat(intlLocale(locale), { maximumFractionDigits: 3 }).format(number);
}

export function date(iso: string | null | undefined, locale: Locale): string {
    if (!iso) {
        return '—';
    }
    const [year, month, day] = iso.slice(0, 10).split('-').map(Number);
    const value = new Date(Date.UTC(year ?? 1970, (month ?? 1) - 1, day ?? 1));
    return new Intl.DateTimeFormat(intlLocale(locale), {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(value);
}

export function dateTime(iso: string | null | undefined, locale: Locale): string {
    if (!iso) {
        return '—';
    }
    return new Intl.DateTimeFormat(intlLocale(locale), {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

export function relativeTime(iso: string, locale: Locale, now: Date = new Date()): string {
    const seconds = Math.round((new Date(iso).getTime() - now.getTime()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(intlLocale(locale), { numeric: 'auto' });
    const steps: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, size] of steps) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }
    return rtf.format(seconds, 'second');
}

export function percent(value: number, locale: Locale): string {
    return new Intl.NumberFormat(intlLocale(locale), { style: 'percent', maximumFractionDigits: 0 }).format(value);
}

export function monthLabel(yearMonth: string, locale: Locale): string {
    const [year, month] = yearMonth.split('-').map(Number);
    return new Intl.DateTimeFormat(intlLocale(locale), { month: 'short', timeZone: 'UTC' }).format(
        new Date(Date.UTC(year ?? 1970, (month ?? 1) - 1, 1)),
    );
}
