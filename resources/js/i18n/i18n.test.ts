import { describe, expect, it } from 'vitest';
import en from './en';
import { pluralIndex, translate } from './index';
import ru from './ru';

describe('plurals', () => {
    it('picks the three Russian forms', () => {
        expect([1, 2, 5, 11, 21, 22, 25, 112].map((n) => pluralIndex('ru', n))).toEqual([0, 1, 2, 2, 0, 1, 2, 2]);
    });

    it('fills counts into the right form', () => {
        expect(translate('ru', 'documents.issues_count', { count: 3 })).toBe('3 проблемы');
        expect(translate('en', 'documents.issues_count', { count: 1 })).toBe('1 problem');
    });
});

describe('dictionaries', () => {
    const keys = (node: object, prefix = ''): string[] =>
        Object.entries(node).flatMap(([key, value]) =>
            value && typeof value === 'object' && !Array.isArray(value)
                ? keys(value as object, `${prefix}${key}.`)
                : [`${prefix}${key}`],
        );

    it('have the same keys in both languages', () => {
        expect(keys(ru).sort()).toEqual(keys(en).sort());
    });
});
