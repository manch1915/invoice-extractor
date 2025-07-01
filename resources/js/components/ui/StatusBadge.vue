<script setup lang="ts">
import { useI18n } from '@/i18n';
import type { DocumentStatus } from '@/types';
import { computed } from 'vue';

const props = defineProps<{ status: DocumentStatus }>();
const { t } = useI18n();

const tone = computed(
    () =>
        ({
            queued: 'bg-surface-2 text-ink-2 ring-line-strong',
            processing: 'bg-pen-soft text-pen ring-pen/25',
            needs_review: 'bg-marker-soft text-marker-ink ring-marker/70',
            ready: 'bg-ok-soft text-ok ring-ok/25',
            exported: 'bg-surface-2 text-ink-2 ring-line-strong',
            rejected: 'bg-surface-2 text-ink-3 ring-line',
            failed: 'bg-red-soft text-red-pen ring-red-pen/25',
        })[props.status],
);
</script>

<template>
    <span
        class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-[12px] font-medium whitespace-nowrap ring-1 ring-inset"
        :class="tone"
    >
        <span
            v-if="status === 'processing' || status === 'queued'"
            class="size-1.5 rounded-full bg-current motion-safe:animate-pulse"
            aria-hidden="true"
        />
        {{ t(`status.${status}`) }}
    </span>
</template>
