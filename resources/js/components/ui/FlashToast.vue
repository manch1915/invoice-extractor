<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from 'lucide-vue-next';
import { onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from '@/i18n';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const { t } = useI18n();
const message = ref<{ text: string; kind: 'success' | 'error' } | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => [page.props.flash?.success, page.props.flash?.error, page.props.errors] as const,
    ([success, error, errors]) => {
        const firstError = errors ? Object.values(errors)[0] : undefined;
        const text = error ?? firstError ?? success;
        if (!text) return;
        message.value = { text, kind: success && !error && !firstError ? 'success' : 'error' };
        clearTimeout(timer);
        timer = setTimeout(() => (message.value = null), 5000);
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex justify-center sm:inset-x-auto sm:right-6 sm:bottom-6"
        aria-live="polite"
    >
        <Transition
            enter-active-class="transition duration-200 ease-[var(--ease-out-quart)]"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="message"
                class="pointer-events-auto flex max-w-md items-start gap-3 rounded-xl border border-line bg-surface py-3 pr-2 pl-4 shadow-pop"
                :role="message.kind === 'error' ? 'alert' : 'status'"
            >
                <CircleCheck
                    v-if="message.kind === 'success'"
                    class="mt-0.5 size-4 shrink-0 text-ok"
                    aria-hidden="true"
                />
                <CircleAlert v-else class="mt-0.5 size-4 shrink-0 text-red-pen" aria-hidden="true" />
                <p class="text-[13.5px] leading-snug">{{ message.text }}</p>
                <button
                    type="button"
                    class="-my-1 grid size-7 shrink-0 place-items-center rounded-md text-ink-3 hover:bg-surface-2 hover:text-ink"
                    :aria-label="t('common.close')"
                    @click="message = null"
                >
                    <X class="size-3.5" />
                </button>
            </div>
        </Transition>
    </div>
</template>
