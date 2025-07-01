<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'sm' | 'md';
        href?: string;
        type?: 'button' | 'submit';
        loading?: boolean;
        disabled?: boolean;
    }>(),
    { variant: 'secondary', size: 'md', type: 'button', href: undefined },
);

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-lg font-medium whitespace-nowrap select-none',
    'transition-[background-color,border-color,color,transform] duration-150 ease-out active:scale-[0.98]',
    'disabled:pointer-events-none disabled:opacity-50',
    props.size === 'sm' ? 'h-8 px-3 text-[13px]' : 'h-10 px-4 text-sm',
    {
        primary: 'bg-pen text-pen-ink shadow-[inset_0_-1px_0_rgb(0_0_0/0.15)] hover:bg-pen-hover',
        secondary: 'border border-line-strong bg-surface text-ink hover:border-ink-3 hover:bg-surface-2',
        ghost: 'text-ink-2 hover:bg-surface-2 hover:text-ink',
        danger: 'border border-red-pen/40 bg-surface text-red-pen hover:bg-red-soft',
    }[props.variant],
]);
</script>

<template>
    <Link v-if="href" :href="href" :class="classes"><slot /></Link>
    <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading || undefined">
        <LoaderCircle v-if="loading" class="size-4 animate-spin" aria-hidden="true" />
        <slot />
    </button>
</template>
