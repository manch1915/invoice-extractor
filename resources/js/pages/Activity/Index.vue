<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/i18n';
import { dateTime } from '@/lib/format';
import type { Paginated } from '@/types';

interface Event {
    id: number;
    action: string;
    user: string | null;
    subject_type: string;
    subject_id: string;
    changes: Record<string, unknown> | null;
    created_at: string;
}

defineProps<{ events: Paginated<Event>; filter: string }>();
const { t, has, locale } = useI18n();

const FILTERS = ['document', 'field', 'export', 'rule', 'integration'];

function describe(event: Event): string {
    const key = `activity.actions.${event.action.replace('.', '_')}`;
    if (!has(key)) return event.action;
    const changes = event.changes ?? {};
    const field = typeof changes.field === 'string' ? t(`fields.${changes.field}`) : '';
    const file = typeof changes.file === 'string' ? changes.file : '';
    return t(key, { field, file });
}

const text = (value: unknown) => (typeof value === 'string' || typeof value === 'number' ? String(value) : '—');

function detail(event: Event): string | null {
    const c = event.changes ?? {};
    if (event.action === 'field.updated') return `${text(c.from)} → ${text(c.to)}`;
    if (event.action === 'export.started' && typeof c.target === 'string') return t(`exports.targets.${c.target}.name`);
    if (event.action === 'export.failed' && typeof c.error === 'string') return c.error;
    if (event.action === 'rule.learned' && typeof c.vendor === 'string') return c.vendor;
    return null;
}
</script>

<template>
    <Head :title="t('activity.title')" />

    <div class="mx-auto max-w-[1080px] px-4 pt-6 pb-16 sm:px-6 lg:px-10 lg:pt-10">
        <h1 class="text-[26px] font-semibold tracking-[-0.02em]">{{ t('activity.title') }}</h1>
        <p class="mt-1 text-[14px] text-ink-2">{{ t('activity.lead') }}</p>

        <nav class="mt-5 flex flex-wrap gap-1.5" :aria-label="t('activity.title')">
            <Link
                :href="route('activity')"
                class="h-8 rounded-full border px-3 text-[13px] leading-[30px]"
                :class="
                    filter === '' ? 'border-ink bg-ink text-canvas' : 'border-line text-ink-2 hover:border-line-strong'
                "
            >
                {{ t('activity.all') }}
            </Link>
            <Link
                v-for="f in FILTERS"
                :key="f"
                :href="route('activity', { action: f })"
                class="h-8 rounded-full border px-3 text-[13px] leading-[30px]"
                :class="
                    filter === f ? 'border-ink bg-ink text-canvas' : 'border-line text-ink-2 hover:border-line-strong'
                "
            >
                {{ t(`activity.filters.${f}`) }}
            </Link>
        </nav>

        <p v-if="!events.data.length" class="mt-8 text-ink-3">{{ t('activity.empty') }}</p>
        <ol v-else class="mt-5 divide-y divide-line rounded-xl border border-line bg-surface shadow-card">
            <li
                v-for="event in events.data"
                :key="event.id"
                class="grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 px-4 py-2.5 sm:grid-cols-[9rem_minmax(0,1fr)_auto]"
            >
                <time :datetime="event.created_at" class="text-[12.5px] whitespace-nowrap text-ink-3 sm:pt-px">{{
                    dateTime(event.created_at, locale)
                }}</time>
                <div class="col-span-2 min-w-0 sm:col-span-1">
                    <p class="text-[13.5px]">
                        <span class="font-medium">{{ event.user ?? t('activity.system') }}</span>
                        {{ describe(event) }}
                    </p>
                    <p v-if="detail(event)" class="mt-0.5 truncate text-[12.5px] text-ink-3">{{ detail(event) }}</p>
                </div>
                <Link
                    v-if="event.subject_type === 'Document' && event.action !== 'document.deleted'"
                    :href="route('documents.show', event.subject_id)"
                    class="row-start-1 self-start text-[12.5px] text-pen hover:underline sm:row-auto"
                >
                    {{ t('activity.open') }}
                </Link>
            </li>
        </ol>

        <div v-if="events.meta.last_page > 1" class="mt-4 flex justify-end gap-2">
            <Button v-if="events.links.prev" :href="events.links.prev" size="sm">{{ t('documents.previous') }}</Button>
            <Button v-if="events.links.next" :href="events.links.next" size="sm">{{ t('documents.next') }}</Button>
        </div>
    </div>
</template>
