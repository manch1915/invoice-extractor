<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { FileQuestion, Highlighter, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CategoryChip from '@/components/documents/CategoryChip.vue';
import UploadPanel from '@/components/documents/UploadPanel.vue';
import Button from '@/components/ui/Button.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useI18n } from '@/i18n';
import { date, money } from '@/lib/format';
import type { Category, DocumentRow, Paginated } from '@/types';

const props = defineProps<{
    documents: Paginated<DocumentRow>;
    counts: Record<string, number>;
    filters: { status: string; q: string; category: number | null };
    categories: Category[];
    mailbox: string | null;
    busy: boolean;
}>();

const { t, locale } = useI18n();

const tabs = computed(() => {
    const c = props.counts;
    return [
        {
            key: 'all',
            count: Object.entries(c)
                .filter(([s]) => s !== 'rejected')
                .reduce((sum, [, n]) => sum + n, 0),
        },
        { key: 'needs_review', count: c.needs_review ?? 0 },
        { key: 'ready', count: c.ready ?? 0 },
        { key: 'exported', count: c.exported ?? 0 },
        { key: 'processing', count: (c.queued ?? 0) + (c.processing ?? 0) },
        { key: 'failed', count: c.failed ?? 0 },
        { key: 'rejected', count: c.rejected ?? 0 },
    ].filter(
        (tab) => tab.count > 0 || tab.key === 'all' || tab.key === 'needs_review' || tab.key === props.filters.status,
    );
});

const search = ref(props.filters.q);
const category = ref<number | null>(props.filters.category);

function apply(overrides: Record<string, string | number | null> = {}) {
    const query = { status: props.filters.status, q: search.value, category: category.value, ...overrides };
    router.get(
        route('documents.index'),
        Object.fromEntries(Object.entries(query).filter(([, v]) => v !== null && v !== '' && v !== 'all')),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const applySearch = useDebounceFn(() => apply(), 300);
watch(search, applySearch);
watch(category, () => apply());

// Documents being read update themselves; polling stops as soon as nothing is in flight.
const { start, stop } = usePoll(2500, { only: ['documents', 'counts', 'busy', 'queue_count'] }, { autoStart: false });
watch(
    () => props.busy,
    (busy) => (busy ? start() : stop()),
    { immediate: true },
);

const vendorFallback = (doc: DocumentRow) =>
    doc.status === 'queued' || doc.status === 'processing' ? t('documents.reading') : t('documents.no_vendor');

const filtered = computed(() => props.filters.q !== '' || props.filters.category !== null);
const showFullUpload = computed(
    () => props.documents.meta.total === 0 && !filtered.value && props.filters.status === 'all',
);
</script>

<template>
    <Head :title="t('documents.title')" />

    <div class="mx-auto max-w-[1280px] px-4 pt-6 pb-16 sm:px-6 lg:px-10 lg:pt-10">
        <div class="flex items-end justify-between gap-4">
            <h1 class="text-[26px] font-semibold tracking-[-0.02em]">{{ t('documents.title') }}</h1>
            <Button
                v-if="counts.needs_review"
                :href="route('documents.index', { status: 'needs_review' })"
                size="sm"
                class="sm:hidden"
            >
                <Highlighter class="size-4" />{{ counts.needs_review }}
            </Button>
        </div>

        <div class="mt-6">
            <UploadPanel :mailbox="mailbox" :compact="!showFullUpload" />
        </div>

        <div class="mt-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <nav class="-mx-4 min-w-0 overflow-x-auto px-4 sm:mx-0 sm:px-0" :aria-label="t('documents.title')">
                <ul class="flex min-w-max gap-1 border-b border-line">
                    <li v-for="tab in tabs" :key="tab.key">
                        <Link
                            :href="route('documents.index', tab.key === 'all' ? {} : { status: tab.key })"
                            preserve-scroll
                            class="-mb-px flex h-10 items-center gap-2 border-b-2 px-3 text-[13.5px] transition-colors"
                            :class="
                                filters.status === tab.key
                                    ? 'border-ink font-medium text-ink'
                                    : 'border-transparent text-ink-3 hover:text-ink'
                            "
                            :aria-current="filters.status === tab.key ? 'page' : undefined"
                        >
                            {{ t(`documents.tabs.${tab.key}`) }}
                            <span
                                class="rounded-full px-1.5 figures text-[11.5px] leading-[18px]"
                                :class="
                                    tab.key === 'needs_review' && tab.count > 0
                                        ? 'bg-marker font-semibold text-[#3d2f00]'
                                        : 'bg-surface-2 text-ink-3'
                                "
                            >
                                {{ tab.count }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="flex gap-2">
                <label class="relative flex-1 lg:w-72 lg:flex-none">
                    <span class="sr-only">{{ t('documents.search') }}</span>
                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-3" />
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('documents.search')"
                        class="h-9 w-full rounded-lg border border-line-strong bg-surface pr-3 pl-9 text-[13.5px] outline-none placeholder:text-ink-3 focus:border-pen focus:ring-3 focus:ring-pen/15"
                    />
                </label>
                <label>
                    <span class="sr-only">{{ t('documents.all_categories') }}</span>
                    <select
                        v-model="category"
                        class="h-9 max-w-44 rounded-lg border border-line-strong bg-surface px-2.5 text-[13.5px] outline-none focus:border-pen focus:ring-3 focus:ring-pen/15"
                    >
                        <option :value="null">{{ t('documents.all_categories') }}</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">
                            {{ locale === 'ru' ? c.name_ru : c.name_en }}
                        </option>
                    </select>
                </label>
            </div>
        </div>

        <!-- empty states -->
        <div
            v-if="documents.data.length === 0"
            class="mt-10 grid place-items-center rounded-xl border border-line bg-surface px-6 py-14 text-center"
        >
            <FileQuestion class="size-8 text-ink-3" />
            <p class="mt-3 font-medium">{{ filtered ? t('documents.empty_filtered') : t('documents.empty_title') }}</p>
            <p v-if="!filtered" class="mt-1 max-w-sm text-[13.5px] text-ink-3">{{ t('documents.empty_hint') }}</p>
            <Button v-else class="mt-4" size="sm" @click="((search = ''), (category = null))">{{
                t('documents.clear_filters')
            }}</Button>
        </div>

        <template v-else>
            <!-- desktop table -->
            <div class="mt-4 hidden overflow-hidden rounded-xl border border-line bg-surface shadow-card md:block">
                <table class="w-full table-fixed text-left">
                    <thead class="border-b border-line bg-surface-2 text-[12.5px] text-ink-3">
                        <tr>
                            <th scope="col" class="w-[38%] py-2.5 pr-3 pl-4 font-medium">
                                {{ t('documents.columns.document') }}
                            </th>
                            <th scope="col" class="w-[14%] px-3 font-medium">{{ t('documents.columns.number') }}</th>
                            <th scope="col" class="w-[12%] px-3 font-medium">{{ t('documents.columns.date') }}</th>
                            <th scope="col" class="w-[16%] px-3 font-medium">{{ t('documents.columns.category') }}</th>
                            <th scope="col" class="w-[13%] px-3 text-right font-medium">
                                {{ t('documents.columns.total') }}
                            </th>
                            <th scope="col" class="w-[14%] py-2.5 pr-4 pl-3 font-medium">
                                {{ t('documents.columns.status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr
                            v-for="doc in documents.data"
                            :key="doc.id"
                            class="group relative transition-colors hover:bg-surface-2"
                        >
                            <td class="py-2.5 pr-3 pl-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-[46px] w-[34px] shrink-0 overflow-hidden rounded-[2px] bg-desk shadow-[0_0_0_1px_var(--line)]"
                                    >
                                        <img
                                            v-if="doc.thumbnail"
                                            :src="doc.thumbnail"
                                            alt=""
                                            loading="lazy"
                                            class="size-full object-cover object-top"
                                        />
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="route('documents.show', doc.id)"
                                            class="block truncate font-medium outline-none after:absolute after:inset-0 focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-pen"
                                        >
                                            {{
                                                doc.vendor_name ??
                                                (doc.status === 'queued' || doc.status === 'processing'
                                                    ? t('documents.reading')
                                                    : t('documents.no_vendor'))
                                            }}
                                        </Link>
                                        <p class="truncate text-[12.5px] text-ink-3">
                                            <span v-if="doc.doc_type">{{ t(`doc_type.${doc.doc_type}`) }}, </span
                                            >{{ doc.filename }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3">
                                <span class="block truncate ident text-ink-2">{{ doc.document_number ?? '—' }}</span>
                            </td>
                            <td class="px-3 text-[13px] whitespace-nowrap text-ink-2">
                                {{ date(doc.issue_date, locale) }}
                            </td>
                            <td class="px-3"><CategoryChip :category="doc.category" /></td>
                            <td class="px-3 text-right figures font-medium whitespace-nowrap">
                                {{ money(doc.total, doc.currency, locale) }}
                            </td>
                            <td class="py-2.5 pr-4 pl-3">
                                <div class="flex flex-col items-start gap-1">
                                    <StatusBadge :status="doc.status" />
                                    <span
                                        v-if="doc.status === 'needs_review' && doc.issues_count"
                                        class="text-[12px] text-ink-3"
                                    >
                                        {{ t('documents.issues_count', { count: doc.issues_count }) }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- phone list -->
            <ul class="mt-4 grid grid-cols-1 gap-2 md:hidden">
                <li v-for="doc in documents.data" :key="doc.id">
                    <Link
                        :href="route('documents.show', doc.id)"
                        class="flex gap-3 rounded-xl border border-line bg-surface p-3 shadow-card active:bg-surface-2"
                    >
                        <div
                            class="h-[58px] w-[42px] shrink-0 overflow-hidden rounded-[2px] bg-desk shadow-[0_0_0_1px_var(--line)]"
                        >
                            <img
                                v-if="doc.thumbnail"
                                :src="doc.thumbnail"
                                alt=""
                                loading="lazy"
                                class="size-full object-cover object-top"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate font-medium">{{ doc.vendor_name ?? vendorFallback(doc) }}</p>
                                <p class="figures font-medium whitespace-nowrap">
                                    {{ money(doc.total, doc.currency, locale) }}
                                </p>
                            </div>
                            <p class="truncate text-[12.5px] text-ink-3">
                                <span class="ident">{{ doc.document_number ?? '—' }}</span
                                >, {{ date(doc.issue_date, locale) }}
                            </p>
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <CategoryChip :category="doc.category" />
                                <StatusBadge :status="doc.status" />
                            </div>
                        </div>
                    </Link>
                </li>
            </ul>

            <div
                v-if="documents.meta.last_page > 1"
                class="mt-4 flex items-center justify-between text-[13px] text-ink-3"
            >
                <span class="figures">{{
                    t('documents.shown', {
                        from: documents.meta.from ?? 0,
                        to: documents.meta.to ?? 0,
                        total: documents.meta.total,
                    })
                }}</span>
                <div class="flex gap-2">
                    <Button v-if="documents.links.prev" :href="documents.links.prev" size="sm">{{
                        t('documents.previous')
                    }}</Button>
                    <Button v-if="documents.links.next" :href="documents.links.next" size="sm">{{
                        t('documents.next')
                    }}</Button>
                </div>
            </div>
        </template>
    </div>
</template>
