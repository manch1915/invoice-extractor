<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CircleCheck, Highlighter } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/i18n';
import { compactMoney, date, money, monthLabel, percent } from '@/lib/format';
import type { DocumentRow, SharedProps } from '@/types';

interface Slice {
    code: string | null;
    name_en: string | null;
    name_ru: string | null;
    color: string | null;
    amount: number;
    documents: number;
}

const props = defineProps<{
    days: number;
    metrics: {
        period: { from: string; to: string };
        base_currency: string;
        totals: {
            documents: number;
            spend: number;
            vendors: number;
            hours_saved: number;
            review_seconds: number | null;
        };
        by_category: Slice[];
        by_vendor: { id: number; name: string; amount: number; documents: number }[];
        by_month: { month: string; amount: number }[];
        automation: { automatic: number; reviewed: number; waiting: number };
    };
    waiting: DocumentRow[];
}>();

const { t, locale } = useI18n();
const page = usePage<SharedProps>();
const cur = computed(() => props.metrics.base_currency);

const auto = computed(() => props.metrics.automation);
const autoTotal = computed(() => auto.value.automatic + auto.value.reviewed + auto.value.waiting);
const autoShare = computed(() => (autoTotal.value ? auto.value.automatic / autoTotal.value : 0));
const segments = computed(() =>
    [
        { key: 'automatic', value: auto.value.automatic, class: 'bg-pen' },
        { key: 'reviewed', value: auto.value.reviewed, class: 'bg-pen/35' },
        { key: 'waiting', value: auto.value.waiting, class: 'bg-marker' },
    ].filter((s) => s.value > 0),
);

const maxCategory = computed(() => Math.max(1, ...props.metrics.by_category.map((c) => c.amount)));
const maxMonth = computed(() => Math.max(1, ...props.metrics.by_month.map((m) => m.amount)));
const maxVendor = computed(() => Math.max(1, ...props.metrics.by_vendor.map((v) => v.amount)));

function categoryName(slice: Slice) {
    if (!slice.code) return t('dashboard.uncategorized');
    return (locale.value === 'ru' ? slice.name_ru : slice.name_en) ?? slice.code;
}

function waitingMeta(doc: DocumentRow) {
    return [
        doc.document_number,
        doc.issue_date ? date(doc.issue_date, locale.value) : null,
        doc.issues_count ? t('documents.issues_count', { count: doc.issues_count }) : null,
    ]
        .filter(Boolean)
        .join(', ');
}

function setDays(days: number) {
    router.get(route('dashboard'), { days }, { preserveScroll: true, preserveState: true, replace: true });
}
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="mx-auto max-w-[1280px] px-4 pt-6 pb-16 sm:px-6 lg:px-10 lg:pt-10">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-[26px] font-semibold tracking-[-0.02em]">{{ t('dashboard.title') }}</h1>
                <p class="mt-1 text-[13px] text-ink-3">
                    {{ date(metrics.period.from, locale) }} – {{ date(metrics.period.to, locale) }}
                </p>
            </div>
            <div class="flex rounded-lg border border-line bg-surface p-0.5" role="group">
                <button
                    v-for="d in [30, 90, 365]"
                    :key="d"
                    type="button"
                    class="h-8 rounded-md px-3 text-[13px] transition-colors"
                    :class="days === d ? 'bg-ink font-medium text-canvas' : 'text-ink-2 hover:text-ink'"
                    :aria-pressed="days === d"
                    @click="setDays(d)"
                >
                    {{ t(`dashboard.period.${d}`) }}
                </button>
            </div>
        </div>

        <!-- what needs a person, and how much did not -->
        <div class="mt-8 grid gap-5 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
            <section class="rounded-xl border border-line bg-surface shadow-card" aria-labelledby="waiting-title">
                <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                    <h2 id="waiting-title" class="flex items-center gap-2 font-semibold">
                        <Highlighter class="size-4 text-marker-ink" aria-hidden="true" />
                        {{ t('dashboard.waiting') }}
                        <span
                            v-if="page.props.queue_count"
                            class="rounded-full bg-marker px-2 figures text-[12px] leading-5 text-[#3d2f00]"
                            >{{ page.props.queue_count }}</span
                        >
                    </h2>
                    <Button
                        v-if="waiting.length"
                        :href="route('documents.show', waiting[0]!.id)"
                        variant="primary"
                        size="sm"
                        >{{ t('dashboard.start_review') }}</Button
                    >
                </div>
                <ul v-if="waiting.length" class="divide-y divide-line">
                    <li v-for="doc in waiting" :key="doc.id">
                        <Link
                            :href="route('documents.show', doc.id)"
                            class="flex items-center gap-4 px-5 py-3 transition-colors hover:bg-surface-2"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ doc.vendor_name ?? doc.filename }}</p>
                                <p class="truncate text-[12.5px] text-ink-3">{{ waitingMeta(doc) }}</p>
                            </div>
                            <span class="shrink-0 figures font-medium">{{
                                money(doc.total, doc.currency, locale)
                            }}</span>
                        </Link>
                    </li>
                </ul>
                <div v-else class="flex items-center gap-3 px-5 py-8 text-ink-2">
                    <CircleCheck class="size-5 shrink-0 text-ok" />
                    <p class="text-[14px]">{{ t('dashboard.waiting_empty') }}</p>
                </div>
            </section>

            <section
                class="flex flex-col rounded-xl border border-line bg-surface p-5 shadow-card"
                aria-labelledby="auto-title"
            >
                <h2 id="auto-title" class="text-[13px] font-semibold text-ink-2">{{ t('dashboard.automation') }}</h2>
                <p class="mt-2 figures text-[44px] leading-none font-semibold tracking-[-0.03em] condensed">
                    {{ percent(autoShare, locale) }}
                </p>
                <div
                    class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-surface-2"
                    role="img"
                    :aria-label="t('dashboard.automation_hint', { auto: auto.automatic, total: autoTotal })"
                >
                    <span
                        v-for="s in segments"
                        :key="s.key"
                        :class="s.class"
                        :style="{ width: `${(s.value / autoTotal) * 100}%` }"
                        class="h-full first:rounded-l-full last:rounded-r-full"
                    />
                </div>
                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] text-ink-2">
                    <li v-for="s in segments" :key="s.key" class="flex items-center gap-1.5">
                        <span class="size-2 rounded-[3px]" :class="s.class" aria-hidden="true" />
                        {{ t(`dashboard.legend.${s.key}`) }} <span class="figures text-ink-3">{{ s.value }}</span>
                    </li>
                </ul>
                <dl class="mt-auto grid grid-cols-2 gap-4 border-t border-line pt-4">
                    <div>
                        <dt class="text-[12.5px] text-ink-2">{{ t('dashboard.hours_saved') }}</dt>
                        <dd class="mt-1 figures text-[28px] leading-none font-semibold condensed">
                            {{ metrics.totals.hours_saved.toLocaleString(locale === 'ru' ? 'ru-RU' : 'en-GB') }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[12.5px] text-ink-2">{{ t('dashboard.review_time') }}</dt>
                        <dd class="mt-1 figures text-[28px] leading-none font-semibold condensed">
                            {{
                                metrics.totals.review_seconds
                                    ? t('dashboard.seconds', { value: metrics.totals.review_seconds })
                                    : '—'
                            }}
                        </dd>
                    </div>
                    <p class="col-span-2 text-[12px] text-ink-3">{{ t('dashboard.hours_hint') }}</p>
                </dl>
            </section>
        </div>

        <!-- where the money went -->
        <section class="mt-5 rounded-xl border border-line bg-surface shadow-card" aria-labelledby="spend-title">
            <div class="flex flex-wrap items-baseline gap-x-8 gap-y-3 border-b border-line px-5 py-4">
                <div>
                    <h2 id="spend-title" class="text-[13px] font-semibold text-ink-2">{{ t('dashboard.spend') }}</h2>
                    <p class="mt-1 figures text-[30px] leading-none font-semibold tracking-[-0.02em] condensed">
                        {{ money(metrics.totals.spend, cur, locale) }}
                    </p>
                </div>
                <dl class="flex gap-8 text-[13px]">
                    <div>
                        <dt class="text-ink-3">{{ t('dashboard.documents') }}</dt>
                        <dd class="mt-0.5 figures text-[18px] font-semibold">{{ metrics.totals.documents }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-3">{{ t('dashboard.vendors') }}</dt>
                        <dd class="mt-0.5 figures text-[18px] font-semibold">{{ metrics.totals.vendors }}</dd>
                    </div>
                </dl>
            </div>

            <div class="grid lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <div class="px-5 py-4 lg:border-r lg:border-line">
                    <h3 class="mb-3 text-[13px] font-medium text-ink-2">{{ t('dashboard.by_category') }}</h3>
                    <ul class="grid gap-2.5">
                        <li
                            v-for="slice in metrics.by_category"
                            :key="slice.code ?? 'none'"
                            class="grid grid-cols-[minmax(0,11rem)_minmax(0,1fr)_auto] items-center gap-3 text-[13px]"
                        >
                            <span class="truncate text-ink-2">{{ categoryName(slice) }}</span>
                            <span class="h-2 rounded-full bg-surface-2">
                                <span
                                    class="block h-full rounded-full"
                                    :style="{
                                        width: `${Math.max(2, (slice.amount / maxCategory) * 100)}%`,
                                        backgroundColor: slice.color ?? 'var(--line-strong)',
                                    }"
                                />
                            </span>
                            <span class="w-24 text-right figures font-medium">{{
                                compactMoney(slice.amount, cur, locale)
                            }}</span>
                        </li>
                    </ul>
                </div>

                <div class="border-t border-line px-5 py-4 lg:border-t-0">
                    <h3 class="mb-3 text-[13px] font-medium text-ink-2">{{ t('dashboard.by_month') }}</h3>
                    <div class="flex h-44 items-end gap-2" role="list">
                        <div
                            v-for="m in metrics.by_month"
                            :key="m.month"
                            class="flex h-full flex-1 flex-col items-center justify-end gap-1.5"
                            role="listitem"
                        >
                            <span class="figures text-[11.5px] text-ink-3">{{
                                m.amount ? compactMoney(m.amount, cur, locale) : ''
                            }}</span>
                            <span
                                class="w-full max-w-12 rounded-t-[4px] bg-pen"
                                :style="{ height: `${Math.max(m.amount ? 3 : 0, (m.amount / maxMonth) * 100)}%` }"
                            />
                            <span class="text-[12px] text-ink-2">{{ monthLabel(m.month, locale) }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <p class="border-t border-line px-5 py-2.5 text-[12px] text-ink-3">{{ t('dashboard.rates_note') }}</p>
        </section>

        <section class="mt-5 rounded-xl border border-line bg-surface shadow-card" aria-labelledby="vendors-title">
            <h2 id="vendors-title" class="border-b border-line px-5 py-3.5 font-semibold">
                {{ t('dashboard.top_vendors') }}
            </h2>
            <ul class="divide-y divide-line">
                <li
                    v-for="v in metrics.by_vendor"
                    :key="v.id"
                    class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1.5 px-5 py-3 sm:grid-cols-[minmax(0,16rem)_minmax(0,1fr)_auto]"
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ v.name }}</p>
                        <p class="text-[12px] text-ink-3">{{ t('dashboard.docs_count', { count: v.documents }) }}</p>
                    </div>
                    <span
                        class="col-span-2 row-start-2 h-1.5 rounded-full bg-surface-2 sm:col-span-1 sm:row-start-auto"
                    >
                        <span
                            class="block h-full rounded-full bg-ink-3/60"
                            :style="{ width: `${(v.amount / maxVendor) * 100}%` }"
                        />
                    </span>
                    <span class="text-right figures font-medium">{{ money(v.amount, cur, locale) }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>
