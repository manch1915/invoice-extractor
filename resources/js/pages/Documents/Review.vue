<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { onKeyStroke } from '@vueuse/core';
import { ArrowLeft, Check, ChevronLeft, ChevronRight, Keyboard, RotateCcw, Trash2, Undo2 } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import CategoryChip from '@/components/documents/CategoryChip.vue';
import DocumentViewer, { type Box } from '@/components/review/DocumentViewer.vue';
import FieldRow from '@/components/review/FieldRow.vue';
import IssueList from '@/components/review/IssueList.vue';
import Button from '@/components/ui/Button.vue';
import Dialog from '@/components/ui/Dialog.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { useI18n } from '@/i18n';
import { dateTime, decimalText, money } from '@/lib/format';
import type { Category, DocumentDetail, Field, FieldKey, Issue, PageWord, SharedProps } from '@/types';

const props = defineProps<{
    document: DocumentDetail;
    categories: Category[];
    queue: { total: number; position: number | null; next: string | null; previous: string | null };
}>();

const { t, locale } = useI18n();
const page = usePage<SharedProps>();
const doc = computed(() => props.document);

const GROUPS: { key: string; fields: FieldKey[] }[] = [
    { key: 'supplier', fields: ['vendor_name', 'vendor_tax_id'] },
    { key: 'document', fields: ['document_number', 'issue_date', 'due_date'] },
    { key: 'amounts', fields: ['subtotal', 'tax_rate', 'tax_amount', 'total', 'currency'] },
    { key: 'buyer', fields: ['buyer_name', 'buyer_tax_id'] },
];
const ORDER = GROUPS.flatMap((g) => g.fields);

const fields = computed(() => Object.fromEntries(doc.value.fields.map((f) => [f.key, f])) as Record<FieldKey, Field>);
const editable = computed(() => doc.value.status === 'needs_review' || doc.value.status === 'ready');
const processing = computed(() => doc.value.status === 'queued' || doc.value.status === 'processing');
const openIssues = computed(() => doc.value.issues.filter((i) => !i.resolved));
const blocking = computed(() => openIssues.value.filter((i) => i.severity === 'blocking'));

function problemFor(key: FieldKey): 'blocking' | 'warning' | null {
    const issues = openIssues.value.filter((i) => i.field === key);
    if (issues.some((i) => i.severity === 'blocking')) return 'blocking';
    return issues.length ? 'warning' : null;
}

// --- selection and editing -------------------------------------------------------------

const active = ref<FieldKey | null>(null);
const editing = ref<FieldKey | null>(null);
const draft = ref('');
const picked = ref<{ bbox: [number, number, number, number]; page: number } | null>(null);
const tab = ref<'document' | 'fields'>('fields');
const helpOpen = ref(false);
const busy = ref(false);
const startedAt = ref(Date.now());

onMounted(() => {
    // start on the first value that needs a look
    active.value =
        ORDER.find((k) => problemFor(k) === 'blocking' || fields.value[k]?.status === 'review') ?? ORDER[0] ?? null;
});

watch(
    () => props.document.id,
    () => {
        startedAt.value = Date.now();
        editing.value = null;
        active.value =
            ORDER.find((k) => problemFor(k) === 'blocking' || fields.value[k]?.status === 'review') ?? ORDER[0] ?? null;
    },
);

function select(key: FieldKey) {
    active.value = key;
    if (editing.value && editing.value !== key) editing.value = null;
}

function startEdit(key: FieldKey) {
    if (!editable.value) return;
    active.value = key;
    editing.value = key;
    draft.value = fields.value[key]?.value ?? '';
    picked.value = null;
}

function cancelEdit() {
    editing.value = null;
    picked.value = null;
}

const visit = {
    preserveScroll: true,
    preserveState: true,
    onStart: () => (busy.value = true),
    onFinish: () => (busy.value = false),
};

function save(key: FieldKey, value: string) {
    router.patch(
        route('review.field', [doc.value.id, key]),
        { value, ...(picked.value ? { bbox: picked.value.bbox, page: picked.value.page } : {}) },
        { ...visit, onSuccess: () => advance(key) },
    );
    editing.value = null;
}

function confirmField(key: FieldKey) {
    if (!editable.value || !fields.value[key]?.value) return;
    router.post(route('review.confirm', [doc.value.id, key]), {}, { ...visit, onSuccess: () => advance(key) });
}

function advance(from: FieldKey) {
    const index = ORDER.indexOf(from);
    active.value = ORDER[Math.min(index + 1, ORDER.length - 1)] ?? from;
}

function onPick(word: PageWord, pageNumber: number, extend: boolean) {
    if (!editing.value) return;
    if (extend && picked.value && picked.value.page === pageNumber) {
        draft.value = `${draft.value} ${word.text}`.trim();
        const [x, y, w, h] = picked.value.bbox;
        const [wx, wy, ww, wh] = word.bbox;
        const x1 = Math.max(x + w, wx + ww);
        const y1 = Math.max(y + h, wy + wh);
        const nx = Math.min(x, wx);
        const ny = Math.min(y, wy);
        picked.value = { bbox: [nx, ny, x1 - nx, y1 - ny], page: pageNumber };
    } else {
        draft.value = word.text;
        picked.value = { bbox: word.bbox, page: pageNumber };
    }
}

function setCategory(id: number) {
    router.put(route('review.category', doc.value.id), { category_id: id }, visit);
}

function resolveIssue(issue: Issue) {
    router.post(route('review.resolve', [doc.value.id, issue.id]), {}, visit);
}

function showIssue(issue: Issue) {
    if (issue.field) {
        active.value = issue.field;
        tab.value = 'document';
    }
}

function approve() {
    if (blocking.value.length || !editable.value) return;
    const seconds = Math.round((Date.now() - startedAt.value) / 1000);
    router.post(
        route('review.approve', doc.value.id),
        { seconds },
        { onStart: () => (busy.value = true), onFinish: () => (busy.value = false) },
    );
}

function setAside() {
    router.post(route('review.reject', doc.value.id), {}, visit);
}

function reopen() {
    router.post(route('review.reopen', doc.value.id), {}, visit);
}

function reprocess() {
    router.post(route('documents.reprocess', doc.value.id), {}, visit);
}

const confirmDelete = ref(false);
function destroy() {
    router.delete(route('documents.destroy', doc.value.id));
}

// --- boxes on the page -----------------------------------------------------------------

const boxes = computed<Box[]>(() => {
    const list: Box[] = [];
    for (const field of doc.value.fields) {
        if (!field.bbox || !field.page) continue;
        const problem = problemFor(field.key);
        list.push({
            id: field.key,
            page: field.page,
            bbox: field.bbox,
            tone:
                problem === 'blocking'
                    ? 'blocking'
                    : field.status === 'confirmed' || field.status === 'corrected'
                      ? 'checked'
                      : field.status === 'review'
                        ? 'review'
                        : 'auto',
            label: t(`fields.${field.key}`),
        });
    }
    return list;
});

function onBoxSelect(id: string) {
    if (ORDER.includes(id as FieldKey)) {
        select(id as FieldKey);
        tab.value = 'fields';
        document.getElementById(`field-${id}`)?.scrollIntoView({ block: 'nearest' });
    }
}

// --- keyboard -------------------------------------------------------------------------

function typing(event: KeyboardEvent) {
    const target = event.target as HTMLElement | null;
    return (
        !!target &&
        (target.tagName === 'INPUT' ||
            target.tagName === 'TEXTAREA' ||
            target.tagName === 'SELECT' ||
            target.isContentEditable)
    );
}

function move(step: number) {
    const index = active.value ? ORDER.indexOf(active.value) : -1;
    const next = ORDER[Math.max(0, Math.min(ORDER.length - 1, index + step))];
    if (next) {
        select(next);
        document.getElementById(`field-${next}`)?.scrollIntoView({ block: 'nearest' });
    }
}

onKeyStroke(['j', 'ArrowDown'], (e) => !typing(e) && !helpOpen.value && (e.preventDefault(), move(1)));
onKeyStroke(['k', 'ArrowUp'], (e) => !typing(e) && !helpOpen.value && (e.preventDefault(), move(-1)));
onKeyStroke('Enter', (e) => {
    if (typing(e) || helpOpen.value) return;
    if ((e.ctrlKey || e.metaKey) && !e.repeat) {
        e.preventDefault();
        approve();
    } else if (active.value && !(e.target instanceof HTMLButtonElement)) {
        e.preventDefault();
        confirmField(active.value);
    }
});
onKeyStroke('e', (e) => !typing(e) && active.value && (e.preventDefault(), startEdit(active.value)));
onKeyStroke('n', (e) => !typing(e) && props.queue.next && router.visit(route('documents.show', props.queue.next)));
onKeyStroke('?', (e) => !typing(e) && (helpOpen.value = true));

// --- reading in progress ----------------------------------------------------------------

const { start, stop } = usePoll(2000, { only: ['document', 'queue', 'queue_count'] }, { autoStart: false });
watch(processing, (value) => (value ? start() : stop()), { immediate: true });

const itemsSum = computed(() => doc.value.line_items.reduce((sum, i) => sum + (i.amount ?? 0), 0));
const categoryName = (c: Category) => (locale.value === 'ru' ? c.name_ru : c.name_en);
const title = computed(
    () => [doc.value.vendor_name, doc.value.document_number].filter(Boolean).join(', ') || doc.value.filename,
);
</script>

<template>
    <Head :title="title" />

    <div class="flex min-h-dvh flex-col lg:h-dvh">
        <!-- header -->
        <header
            class="sticky top-14 z-20 flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line bg-canvas/95 px-4 py-2.5 backdrop-blur lg:top-0 lg:px-5"
        >
            <Link
                :href="route('documents.index', doc.status === 'needs_review' ? { status: 'needs_review' } : {})"
                class="flex items-center gap-1.5 text-[13px] text-ink-3 hover:text-ink"
            >
                <ArrowLeft class="size-4" />
                <span class="hidden sm:inline">{{ t('review.back') }}</span>
            </Link>
            <span class="hidden h-5 w-px bg-line sm:block" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <h1 class="truncate text-[15px] leading-tight font-semibold">{{ title }}</h1>
                <p class="truncate text-[12px] text-ink-3">
                    <span v-if="doc.doc_type">{{ t(`doc_type.${doc.doc_type}`) }}, </span>
                    <span class="figures">{{ money(doc.total, doc.currency, locale) }}</span>
                </p>
            </div>

            <div class="flex items-center gap-1">
                <span v-if="queue.total" class="hidden px-2 figures text-[12.5px] text-ink-3 md:inline">
                    {{
                        queue.position
                            ? t('review.position', { position: queue.position, total: queue.total })
                            : t('review.not_in_queue', { total: queue.total })
                    }}
                </span>
                <Button
                    v-if="queue.previous"
                    :href="route('documents.show', queue.previous)"
                    variant="ghost"
                    size="sm"
                    :aria-label="t('review.previous')"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <Button
                    v-if="queue.next"
                    :href="route('documents.show', queue.next)"
                    variant="ghost"
                    size="sm"
                    :aria-label="t('review.next')"
                >
                    <ChevronRight class="size-4" />
                </Button>
                <button
                    type="button"
                    class="hidden size-8 place-items-center rounded-lg text-ink-3 hover:bg-surface hover:text-ink lg:grid"
                    :aria-label="t('review.shortcuts')"
                    @click="helpOpen = true"
                >
                    <Keyboard class="size-4" />
                </button>
            </div>

            <div class="flex w-full items-center gap-2 sm:w-auto">
                <StatusBadge :status="doc.status" class="mr-1" />
                <template v-if="editable">
                    <Button size="sm" variant="ghost" :disabled="busy" @click="setAside">{{
                        t('review.set_aside')
                    }}</Button>
                    <Button
                        size="sm"
                        variant="primary"
                        class="ml-auto sm:ml-0"
                        :disabled="blocking.length > 0"
                        :loading="busy"
                        :title="blocking.length ? t('review.approve_blocked') : 'Ctrl + Enter'"
                        @click="approve"
                    >
                        <Check class="size-4" />{{ t('review.approve') }}
                    </Button>
                </template>
                <Button v-else-if="doc.status === 'rejected' || doc.status === 'exported'" size="sm" @click="reopen">
                    <Undo2 class="size-4" />{{ t('review.reopen') }}
                </Button>
                <Button v-else-if="doc.status === 'failed'" size="sm" @click="reprocess">
                    <RotateCcw class="size-4" />{{ t('review.reprocess') }}
                </Button>
            </div>
        </header>

        <!-- phone: switch between the page and the fields -->
        <div class="sticky top-[118px] z-10 grid grid-cols-2 border-b border-line bg-canvas lg:hidden" role="tablist">
            <button
                v-for="name in ['document', 'fields'] as const"
                :key="name"
                type="button"
                role="tab"
                class="h-10 border-b-2 text-[13.5px]"
                :class="tab === name ? 'border-ink font-medium' : 'border-transparent text-ink-3'"
                :aria-selected="tab === name"
                @click="tab = name"
            >
                {{ t(`review.${name}_tab`) }}
            </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(400px,460px)]">
            <section
                class="h-[72dvh] min-h-0 lg:h-auto"
                :class="tab === 'document' ? 'block' : 'hidden lg:block'"
                :aria-label="t('review.document_tab')"
            >
                <DocumentViewer
                    :pages="doc.pages"
                    :boxes="boxes"
                    :active-id="active"
                    :picking="editing !== null"
                    :original-url="doc.original_url"
                    @select="onBoxSelect"
                    @pick="onPick"
                />
            </section>

            <aside
                class="min-h-0 overflow-y-auto border-line bg-surface lg:border-l"
                :class="tab === 'fields' ? 'block' : 'hidden lg:block'"
                :aria-label="t('review.fields_tab')"
            >
                <div class="grid gap-6 px-4 py-5 lg:px-5">
                    <!-- state notes -->
                    <p v-if="processing" class="rounded-lg bg-pen-soft px-3 py-2.5 text-[13.5px] text-pen">
                        {{ t('review.processing_note') }}
                    </p>
                    <p
                        v-else-if="doc.status === 'failed'"
                        class="rounded-lg bg-red-soft px-3 py-2.5 text-[13.5px] text-red-pen"
                    >
                        {{ t('review.failed_note', { error: doc.error ?? '' }) }}
                    </p>
                    <p
                        v-else-if="doc.status === 'exported'"
                        class="rounded-lg bg-surface-2 px-3 py-2.5 text-[13.5px] text-ink-2"
                    >
                        {{ t('review.exported_note') }}
                    </p>
                    <p
                        v-else-if="doc.status === 'rejected'"
                        class="rounded-lg bg-surface-2 px-3 py-2.5 text-[13.5px] text-ink-2"
                    >
                        {{ t('review.rejected_note') }}
                    </p>

                    <section v-if="!processing && doc.status !== 'failed'" aria-labelledby="problems-title">
                        <h2 id="problems-title" class="mb-2 text-[13px] font-semibold text-ink-2">
                            {{ t('review.problems') }}
                        </h2>
                        <IssueList
                            v-if="doc.issues.length"
                            :issues="doc.issues"
                            :currency="doc.currency"
                            :disabled="!editable || busy"
                            @show="showIssue"
                            @resolve="resolveIssue"
                        />
                        <p v-else class="flex items-center gap-2 text-[13.5px] text-ok">
                            <Check class="size-4" />{{ t('review.no_problems') }}
                        </p>
                    </section>

                    <section v-if="!processing && doc.status !== 'failed'" aria-labelledby="category-title">
                        <h2 id="category-title" class="mb-2 text-[13px] font-semibold text-ink-2">
                            {{ t('review.category') }}
                        </h2>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="doc.category"
                                class="size-3 shrink-0 rounded-[4px]"
                                :style="{ backgroundColor: doc.category.color }"
                                aria-hidden="true"
                            />
                            <select
                                class="h-9 min-w-0 flex-1 rounded-lg border bg-surface px-2.5 text-[14px] outline-none focus:border-pen focus:ring-3 focus:ring-pen/15 disabled:opacity-60"
                                :class="doc.category ? 'border-line-strong' : 'border-red-pen/60'"
                                :value="doc.category?.id ?? ''"
                                :disabled="!editable || busy"
                                :aria-label="t('review.category')"
                                @change="setCategory(Number(($event.target as HTMLSelectElement).value))"
                            >
                                <option value="" disabled>{{ t('review.pick_category') }}</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ categoryName(c) }}</option>
                            </select>
                        </div>
                        <p v-if="doc.category_source" class="mt-1.5 text-[12px] text-ink-3">
                            {{ t(`review.category_sources.${doc.category_source}`) }}
                        </p>
                    </section>

                    <section
                        v-for="group in GROUPS"
                        v-show="!processing"
                        :key="group.key"
                        :aria-labelledby="`group-${group.key}`"
                    >
                        <h2 :id="`group-${group.key}`" class="mb-1 text-[13px] font-semibold text-ink-2">
                            {{ t(`groups.${group.key}`) }}
                        </h2>
                        <div class="-mx-1.5 grid gap-0.5">
                            <template v-for="key in group.fields" :key="key">
                                <FieldRow
                                    v-if="fields[key]"
                                    v-model:draft="draft"
                                    :field="fields[key]"
                                    :currency="doc.currency"
                                    :active="active === key"
                                    :editing="editing === key"
                                    :problem="problemFor(key)"
                                    :disabled="!editable || busy"
                                    @select="select(key)"
                                    @edit="startEdit(key)"
                                    @confirm="confirmField(key)"
                                    @save="(value) => save(key, value)"
                                    @cancel="cancelEdit"
                                />
                            </template>
                        </div>
                        <p v-if="editing && group.fields.includes(editing)" class="mt-1 pl-3 text-[12px] text-pen">
                            {{ t('review.pick_hint') }}
                        </p>
                    </section>

                    <section v-if="!processing" aria-labelledby="lines-title">
                        <div class="mb-2 flex items-baseline justify-between gap-2">
                            <h2 id="lines-title" class="text-[13px] font-semibold text-ink-2">
                                {{ t('review.lines') }}
                            </h2>
                            <span v-if="doc.line_items.length" class="figures text-[12px] text-ink-3">
                                {{ t('review.lines_sum', { sum: money(itemsSum, doc.currency, locale) }) }}
                            </span>
                        </div>
                        <p v-if="!doc.line_items.length" class="text-[13px] text-ink-3">{{ t('review.no_lines') }}</p>
                        <div v-else class="overflow-x-auto rounded-lg border border-line">
                            <table class="w-full text-[13px]">
                                <thead class="bg-surface-2 text-[12px] text-ink-3">
                                    <tr>
                                        <th scope="col" class="px-2.5 py-1.5 text-left font-medium">
                                            {{ t('review.line_columns.description') }}
                                        </th>
                                        <th scope="col" class="px-2 py-1.5 text-right font-medium">
                                            {{ t('review.line_columns.quantity') }}
                                        </th>
                                        <th scope="col" class="px-2.5 py-1.5 text-right font-medium">
                                            {{ t('review.line_columns.amount') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    <tr
                                        v-for="item in doc.line_items"
                                        :key="item.id"
                                        :class="item.confidence < 0.8 ? 'bg-marker-soft/60' : ''"
                                    >
                                        <td class="px-2.5 py-1.5 leading-snug">{{ item.description }}</td>
                                        <td class="px-2 py-1.5 text-right figures whitespace-nowrap text-ink-2">
                                            {{ decimalText(item.quantity, locale) }}
                                            <span v-if="item.unit_price !== null" class="block text-[11.5px] text-ink-3"
                                                >× {{ money(item.unit_price, null, locale) }}</span
                                            >
                                        </td>
                                        <td class="px-2.5 py-1.5 text-right figures font-medium whitespace-nowrap">
                                            {{ money(item.amount, null, locale) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section aria-labelledby="details-title" class="border-t border-line pt-5 text-[12.5px] text-ink-3">
                        <h2 id="details-title" class="sr-only">{{ t('review.details') }}</h2>
                        <p v-if="doc.engine && doc.text_source">
                            {{
                                t('review.read_with', {
                                    engine: t(`review.engine.${doc.engine}`),
                                    source: t(`review.source.${doc.text_source}`),
                                })
                            }}
                            <template v-if="doc.processing_ms">{{
                                t('review.took', { seconds: (doc.processing_ms / 1000).toFixed(1) })
                            }}</template>
                        </p>
                        <p class="mt-1 truncate">
                            <span class="ident">{{ doc.filename }}</span>
                        </p>
                        <p class="mt-1">{{ t('review.uploaded', { when: dateTime(doc.created_at, locale) }) }}</p>
                        <p v-if="doc.reviewer" class="mt-1">{{ t('review.reviewed_by', { name: doc.reviewer }) }}</p>
                        <p v-if="doc.category && !editable" class="mt-2"><CategoryChip :category="doc.category" /></p>
                        <div v-if="page.props.auth.user?.can_manage" class="mt-4 flex gap-2">
                            <Button
                                v-if="doc.status !== 'exported' && !processing"
                                size="sm"
                                variant="ghost"
                                @click="reprocess"
                            >
                                <RotateCcw class="size-3.5" />{{ t('review.reprocess') }}
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="text-red-pen hover:bg-red-soft hover:text-red-pen"
                                @click="confirmDelete = true"
                            >
                                <Trash2 class="size-3.5" />{{ t('review.delete') }}
                            </Button>
                        </div>
                    </section>
                </div>
            </aside>
        </div>
    </div>

    <Dialog v-model:open="helpOpen" :title="t('review.shortcuts')">
        <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2.5 text-[13.5px]">
            <template
                v-for="[keys, label] in [
                    [['J', 'K'], 'move'],
                    [['Enter'], 'confirm'],
                    [['E'], 'edit'],
                    [['Esc'], 'cancel'],
                    [['Ctrl', 'Enter'], 'approve'],
                    [['N'], 'next_doc'],
                    [['?'], 'help'],
                ] as const"
                :key="label"
            >
                <dt class="flex gap-1">
                    <kbd
                        v-for="k in keys"
                        :key="k"
                        class="rounded-md border border-line-strong bg-surface-2 px-1.5 py-0.5 ident text-[12px] shadow-[inset_0_-1px_0_var(--line-strong)]"
                        >{{ k }}</kbd
                    >
                </dt>
                <dd class="text-ink-2">{{ t(`review.keys.${label}`) }}</dd>
            </template>
        </dl>
    </Dialog>

    <Dialog v-model:open="confirmDelete" :title="t('review.delete')">
        <p class="text-[14px] text-ink-2">{{ t('review.delete_confirm') }}</p>
        <div class="mt-5 flex justify-end gap-2">
            <Button size="sm" @click="confirmDelete = false">{{ t('common.cancel') }}</Button>
            <Button size="sm" variant="danger" @click="destroy">{{ t('review.delete') }}</Button>
        </div>
    </Dialog>
</template>
