<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 28 });
</script>

<template>
    <!-- a sheet with a highlighted line: the product in one picture -->
    <svg :width="size" :height="size" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect x="6" y="3" width="20" height="26" rx="2.5" class="fill-surface stroke-ink" stroke-width="1.6" />
        <rect x="9" y="13.2" width="14" height="4.4" rx="1" class="fill-marker" />
        <path
            d="M10.5 8.5h11M10.5 15.4h9M10.5 21.5h11M10.5 25h6"
            class="stroke-ink"
            stroke-width="1.6"
            stroke-linecap="round"
        />
    </svg>
</template>
