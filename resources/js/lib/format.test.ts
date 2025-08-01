import { describe, expect, it } from 'vitest';
import { date, decimalText, money } from './format';

const nbsp = (s: string) => s.replace(/[  ]/g, ' ');

describe('money', () => {
    it('formats minor units in the reader’s locale', () => {
        expect(nbsp(money(123456, 'RUB', 'ru'))).toBe('1 234,56 ₽');
        expect(money(123456, 'GBP', 'en')).toBe('£1,234.56');
    });

    it('shows a dash when the amount is unknown', () => {
        expect(money(null, 'RUB', 'ru')).toBe('—');
    });

    it('works without a currency', () => {
        expect(nbsp(money(5000, null, 'ru'))).toBe('50,00');
    });
});

describe('date', () => {
    it('does not shift the day with the time zone', () => {
        expect(date('2026-10-01', 'en')).toBe('1 Oct 2026');
    });
});

describe('decimalText', () => {
    it('drops trailing zeros of quantities', () => {
        expect(decimalText('8.000', 'en')).toBe('8');
        expect(decimalText('38.420', 'ru')).toBe('38,42');
    });
});
