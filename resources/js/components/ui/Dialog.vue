<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from '@/i18n';

const open = defineModel<boolean>('open', { required: true });
defineProps<{ title: string }>();

const dialog = ref<HTMLDialogElement | null>(null);
const { t } = useI18n();

watch(open, (value) => {
    if (!dialog.value) return;
    if (value && !dialog.value.open) dialog.value.showModal();
    if (!value && dialog.value.open) dialog.value.close();
});

function onBackdrop(event: MouseEvent) {
    if (event.target === dialog.value) open.value = false;
}
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-[min(92vw,30rem)] rounded-xl bg-surface p-0 text-ink shadow-pop backdrop:bg-ink/30 backdrop:backdrop-blur-[2px] open:animate-[pop_160ms_var(--ease-out-quart)]"
        :aria-label="title"
        @close="open = false"
        @click="onBackdrop"
    >
        <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
            <h2 class="text-[15px] font-semibold">{{ title }}</h2>
            <button
                type="button"
                class="-mr-2 grid size-8 place-items-center rounded-md text-ink-3 hover:bg-surface-2 hover:text-ink"
                :aria-label="t('common.close')"
                @click="open = false"
            >
                <X class="size-4" />
            </button>
        </div>
        <div class="px-5 py-4">
            <slot />
        </div>
    </dialog>
</template>

<style>
@keyframes pop {
    from {
        opacity: 0;
        transform: translateY(6px) scale(0.98);
    }
}
</style>
