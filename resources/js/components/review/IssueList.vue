<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CircleCheck, OctagonAlert, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { date, decimalText, money } from '@/lib/format';
import type { Issue } from '@/types';

const props = defineProps<{ issues: Issue[]; currency: string | null; disabled: boolean }>();
const emit = defineEmits<{ show: [issue: Issue]; resolve: [issue: Issue] }>();
const { t, locale } = useI18n();

const MONEY_KEYS = ['expected', 'actual', 'items_sum', 'total', 'median'];
const DATE_KEYS = ['date', 'due', 'issued'];

const sorted = computed(() =>
    [...props.issues].sort(
        (a, b) =>
            Number(a.resolved) - Number(b.resolved) ||
            Number(a.severity !== 'blocking') - Number(b.severity !== 'blocking'),
    ),
);

function message(issue: Issue): string {
    const params: Record<string, string | number> = {};
    for (const [key, value] of Object.entries(issue.context)) {
        if (value === null) continue;
        if (MONEY_KEYS.includes(key) && typeof value === 'number')
            params[key] = money(value, props.currency, locale.value);
        else if (DATE_KEYS.includes(key) && typeof value === 'string') params[key] = date(value, locale.value);
        else if (key === 'rate' || key === 'ratio') params[key] = decimalText(String(value), locale.value);
        else params[key] = value;
    }
    if (issue.field) params.field = t(`fields.${issue.field}`);
    return t(`issues.${issue.code}`, params);
}
</script>

<template>
    <ul class="grid gap-2">
        <li
            v-for="issue in sorted"
            :key="issue.id"
            class="rounded-lg border px-3 py-2.5"
            :class="
                issue.resolved
                    ? 'border-line bg-surface text-ink-3'
                    : issue.severity === 'blocking'
                      ? 'border-red-pen/30 bg-red-soft'
                      : 'border-marker/70 bg-marker-soft'
            "
        >
            <div class="flex gap-2.5">
                <CircleCheck v-if="issue.resolved" class="mt-0.5 size-4 shrink-0 text-ok" aria-hidden="true" />
                <OctagonAlert
                    v-else-if="issue.severity === 'blocking'"
                    class="mt-0.5 size-4 shrink-0 text-red-pen"
                    aria-hidden="true"
                />
                <TriangleAlert v-else class="mt-0.5 size-4 shrink-0 text-marker-ink" aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <p class="text-[13.5px] leading-snug" :class="issue.resolved ? '' : 'text-ink'">
                        {{ message(issue) }}
                    </p>
                    <p class="mt-0.5 text-[12px] text-ink-3">
                        {{
                            issue.resolved
                                ? t('issues.resolved')
                                : issue.severity === 'blocking'
                                  ? t('issues.blocking')
                                  : t('issues.warning')
                        }}
                    </p>
                    <div v-if="!issue.resolved" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[12.5px] font-medium">
                        <button
                            v-if="issue.field"
                            type="button"
                            class="text-ink-2 underline decoration-line-strong underline-offset-2 hover:text-ink"
                            @click="emit('show', issue)"
                        >
                            {{ t('issues.show') }}
                        </button>
                        <Link
                            v-if="issue.code === 'duplicate' && issue.context.original_id"
                            :href="route('documents.show', String(issue.context.original_id))"
                            class="text-ink-2 underline decoration-line-strong underline-offset-2 hover:text-ink"
                        >
                            {{ t('issues.open_original') }}
                        </Link>
                        <button
                            v-if="issue.code !== 'no_category' && issue.code !== 'missing_field'"
                            type="button"
                            class="text-pen underline decoration-pen/30 underline-offset-2 hover:decoration-pen disabled:opacity-50"
                            :disabled="disabled"
                            @click="emit('resolve', issue)"
                        >
                            {{ t('issues.resolve') }}
                        </button>
                    </div>
                </div>
            </div>
        </li>
    </ul>
</template>
