import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import en from './en';
import ru from './ru';

export type Locale = 'en' | 'ru';
type Dictionary = typeof en;
type Params = Record<string, string | number>;

const dictionaries: Record<Locale, Dictionary> = { en, ru };

function lookup(dict: Dictionary, key: string): unknown {
    return key.split('.').reduce<unknown>((node, part) => {
        if (node && typeof node === 'object' && part in node) {
            return (node as Record<string, unknown>)[part];
        }
        return undefined;
    }, dict);
}

function interpolate(text: string, params: Params): string {
    return text.replace(/\{(\w+)\}/g, (_, name: string) => String(params[name] ?? `{${name}}`));
}

/** Russian has three plural forms: 1 документ, 2 документа, 5 документов. */
export function pluralIndex(locale: Locale, count: number): number {
    if (locale === 'en') {
        return count === 1 ? 0 : 1;
    }
    const mod10 = count % 10;
    const mod100 = count % 100;
    if (mod10 === 1 && mod100 !== 11) return 0;
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 1;
    return 2;
}

export function translate(locale: Locale, key: string, params: Params = {}): string {
    const value = lookup(dictionaries[locale], key) ?? lookup(dictionaries.en, key);
    if (typeof value === 'string') {
        return interpolate(value, params);
    }
    if (Array.isArray(value) && typeof params.count === 'number') {
        const forms = value as string[];
        const form = forms[Math.min(pluralIndex(locale, params.count), forms.length - 1)] ?? key;
        return interpolate(form, params);
    }
    return key;
}

export function useI18n() {
    const page = usePage<{ locale: Locale }>();
    const locale = computed<Locale>(() => (page.props.locale === 'ru' ? 'ru' : 'en'));

    return {
        locale,
        t: (key: string, params?: Params) => translate(locale.value, key, params),
        list: (key: string): string[] => {
            const value = lookup(dictionaries[locale.value], key);
            return Array.isArray(value) ? (value as string[]) : [];
        },
        has: (key: string) => lookup(dictionaries[locale.value], key) !== undefined,
    };
}
