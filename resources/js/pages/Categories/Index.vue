<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/i18n';
import type { Category } from '@/types';

interface Row extends Category {
    documents: number;
    keywords: string[];
}

interface Rule {
    id: number;
    category_id: number;
    vendor: { name: string; tax_id: string | null } | null;
    pattern: string | null;
    source: 'learned' | 'manual';
    hits: number;
    author: string | null;
}

const props = defineProps<{ categories: Row[]; rules: Rule[] }>();
const { t, locale } = useI18n();

const name = (c: Category) => (locale.value === 'ru' ? c.name_ru : c.name_en);
const sorted = computed(() => [...props.categories].sort((a, b) => name(a).localeCompare(name(b), locale.value)));

const form = useForm({ pattern: '', category_id: props.categories[0]?.id ?? 0 });
function addRule() {
    form.post(route('rules.store'), { preserveScroll: true, onSuccess: () => form.reset('pattern') });
}

function saveAccount(category: Row, field: 'account_code' | 'account_1c', value: string) {
    if ((category[field] ?? '') === value) return;
    router.patch(
        route('categories.update', category.id),
        { account_code: category.account_code, account_1c: category.account_1c, [field]: value },
        { preserveScroll: true },
    );
}

function changeRule(rule: Rule, categoryId: number) {
    router.patch(route('rules.update', rule.id), { category_id: categoryId }, { preserveScroll: true });
}

function deleteRule(rule: Rule) {
    router.delete(route('rules.destroy', rule.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('categories.title')" />

    <div class="mx-auto max-w-[1080px] px-4 pt-6 pb-16 sm:px-6 lg:px-10 lg:pt-10">
        <h1 class="text-[26px] font-semibold tracking-[-0.02em]">{{ t('categories.title') }}</h1>
        <p class="mt-1 max-w-2xl text-[14px] text-ink-2">{{ t('categories.lead') }}</p>

        <section
            class="mt-6 overflow-x-auto rounded-xl border border-line bg-surface shadow-card"
            aria-labelledby="accounts"
        >
            <h2 id="accounts" class="sr-only">{{ t('categories.accounts') }}</h2>
            <table class="w-full min-w-[560px] text-left text-[13.5px]">
                <thead class="border-b border-line bg-surface-2 text-[12.5px] text-ink-3">
                    <tr>
                        <th scope="col" class="py-2.5 pr-3 pl-4 font-medium">{{ t('categories.category') }}</th>
                        <th scope="col" class="w-36 px-3 font-medium">{{ t('categories.xero_account') }}</th>
                        <th scope="col" class="w-36 px-3 font-medium">{{ t('categories.account_1c') }}</th>
                        <th scope="col" class="w-28 py-2.5 pr-4 pl-3 text-right font-medium">
                            {{ t('categories.documents') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    <tr v-for="c in sorted" :key="c.id">
                        <td class="py-2 pr-3 pl-4">
                            <span class="flex items-center gap-2.5">
                                <span
                                    class="size-2.5 shrink-0 rounded-[3px]"
                                    :style="{ backgroundColor: c.color }"
                                    aria-hidden="true"
                                />
                                <span class="font-medium">{{ name(c) }}</span>
                            </span>
                        </td>
                        <td class="px-3">
                            <input
                                :value="c.account_code ?? ''"
                                class="h-8 w-24 rounded-md border border-transparent bg-transparent px-2 ident hover:border-line focus:border-pen focus:bg-surface focus:ring-3 focus:ring-pen/15 focus:outline-none"
                                :aria-label="`${t('categories.xero_account')}: ${name(c)}`"
                                @change="saveAccount(c, 'account_code', ($event.target as HTMLInputElement).value)"
                            />
                        </td>
                        <td class="px-3">
                            <input
                                :value="c.account_1c ?? ''"
                                class="h-8 w-24 rounded-md border border-transparent bg-transparent px-2 ident hover:border-line focus:border-pen focus:bg-surface focus:ring-3 focus:ring-pen/15 focus:outline-none"
                                :aria-label="`${t('categories.account_1c')}: ${name(c)}`"
                                @change="saveAccount(c, 'account_1c', ($event.target as HTMLInputElement).value)"
                            />
                        </td>
                        <td class="py-2 pr-4 pl-3 text-right figures text-ink-2">{{ c.documents }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="mt-10" aria-labelledby="rules-title">
            <h2 id="rules-title" class="text-[18px] font-semibold">{{ t('categories.rules') }}</h2>
            <p class="mt-1 max-w-2xl text-[13.5px] text-ink-2">{{ t('categories.rules_lead') }}</p>

            <form
                class="mt-4 flex flex-col gap-2 rounded-xl border border-line bg-surface p-3 shadow-card sm:flex-row sm:items-end"
                @submit.prevent="addRule"
            >
                <label class="grid flex-1 gap-1">
                    <span class="text-[12.5px] text-ink-3">{{ t('categories.keyword') }}</span>
                    <input
                        v-model="form.pattern"
                        required
                        minlength="3"
                        class="h-9 rounded-lg border border-line-strong bg-surface px-3 outline-none focus:border-pen focus:ring-3 focus:ring-pen/15"
                        :aria-invalid="!!form.errors.pattern"
                    />
                </label>
                <label class="grid gap-1 sm:w-64">
                    <span class="text-[12.5px] text-ink-3">{{ t('categories.category') }}</span>
                    <select
                        v-model="form.category_id"
                        class="h-9 rounded-lg border border-line-strong bg-surface px-2.5 outline-none focus:border-pen focus:ring-3 focus:ring-pen/15"
                    >
                        <option v-for="c in sorted" :key="c.id" :value="c.id">{{ name(c) }}</option>
                    </select>
                </label>
                <Button type="submit" variant="primary" :loading="form.processing"
                    ><Plus class="size-4" />{{ t('categories.add') }}</Button
                >
            </form>
            <p v-if="form.errors.pattern" class="mt-1 text-[12.5px] text-red-pen">{{ form.errors.pattern }}</p>

            <p v-if="!rules.length" class="mt-4 text-[13.5px] text-ink-3">{{ t('categories.no_rules') }}</p>
            <ul v-else class="mt-4 divide-y divide-line rounded-xl border border-line bg-surface shadow-card">
                <li
                    v-for="rule in rules"
                    :key="rule.id"
                    class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_14rem_6rem_auto]"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            <template v-if="rule.vendor">{{ rule.vendor.name }}</template>
                            <template v-else>{{ t('categories.rule_keyword') }} «{{ rule.pattern }}»</template>
                        </p>
                        <p class="text-[12px] text-ink-3">
                            {{ rule.vendor ? t('categories.rule_supplier') : t('categories.rule_keyword') }},
                            {{ rule.source === 'learned' ? t('categories.learned') : t('categories.manual')
                            }}<template v-if="rule.author">, {{ rule.author }}</template>
                        </p>
                    </div>
                    <select
                        class="col-span-2 h-8 rounded-md border border-line-strong bg-surface px-2 text-[13px] sm:col-span-1"
                        :value="rule.category_id"
                        :aria-label="t('categories.category')"
                        @change="changeRule(rule, Number(($event.target as HTMLSelectElement).value))"
                    >
                        <option v-for="c in sorted" :key="c.id" :value="c.id">{{ name(c) }}</option>
                    </select>
                    <span class="figures text-[12.5px] text-ink-3 sm:text-right"
                        >{{ t('categories.hits') }}: {{ rule.hits }}</span
                    >
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-md text-ink-3 hover:bg-red-soft hover:text-red-pen"
                        :aria-label="t('categories.delete')"
                        @click="deleteRule(rule)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
