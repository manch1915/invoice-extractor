<script setup lang="ts">
import { useI18n } from '@/i18n';
import type { Category } from '@/types';

defineProps<{ category: Category | null }>();
const { locale } = useI18n();
</script>

<template>
    <span v-if="category" class="inline-flex max-w-full items-center gap-1.5 text-[13px] text-ink-2">
        <span class="size-2 shrink-0 rounded-[3px]" :style="{ backgroundColor: category.color }" aria-hidden="true" />
        <span class="truncate">{{ locale === 'ru' ? category.name_ru : category.name_en }}</span>
    </span>
    <span v-else class="text-[13px] text-ink-3">—</span>
</template>
