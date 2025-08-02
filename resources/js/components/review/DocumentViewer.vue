<script setup lang="ts">
import { useResizeObserver } from '@vueuse/core';
import { ExternalLink, Eye, EyeOff, Minus, Plus, ScanSearch } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from '@/i18n';
import type { BBox, DocumentPage, PageWord } from '@/types';

export interface Box {
    id: string;
    page: number;
    bbox: BBox;
    tone: 'auto' | 'review' | 'blocking' | 'checked';
    label: string;
}

const props = defineProps<{
    pages: DocumentPage[];
    boxes: Box[];
    activeId: string | null;
    picking: boolean;
    originalUrl: string;
}>();

const emit = defineEmits<{
    select: [id: string];
    pick: [word: PageWord, page: number, extend: boolean];
}>();

const { t } = useI18n();
const scroller = ref<HTMLElement | null>(null);
const width = ref(800);
const zoom = ref(1);
const showAll = ref(true);
const currentPage = ref(1);

useResizeObserver(scroller, (entries) => {
    const entry = entries[0];
    if (entry) width.value = Math.max(280, entry.contentRect.width - 48);
});

const sheetWidth = computed(() => Math.round(width.value * zoom.value));

function setZoom(value: number) {
    zoom.value = Math.min(3, Math.max(0.5, Math.round(value * 100) / 100));
}

const visibleBoxes = computed(() =>
    props.boxes.filter((b) => showAll.value || b.id === props.activeId || b.tone === 'blocking'),
);

function boxStyle(bbox: BBox) {
    const [x, y, w, h] = bbox;
    return { left: `${x * 100}%`, top: `${y * 100}%`, width: `${w * 100}%`, height: `${h * 100}%` };
}

// Bring the highlighted value into the middle of the view when a field is selected.
watch(
    () => props.activeId,
    async (id) => {
        if (!id || !scroller.value) return;
        await nextTick();
        const el = scroller.value.querySelector<HTMLElement>(`[data-box="${CSS.escape(id)}"]`);
        el?.scrollIntoView({
            block: 'center',
            inline: 'center',
            behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
        });
    },
);

function onScroll() {
    const el = scroller.value;
    if (!el) return;
    const sheets = Array.from(el.querySelectorAll<HTMLElement>('[data-page]'));
    const middle = el.scrollTop + el.clientHeight / 3;
    const visible = sheets.filter((s) => s.offsetTop <= middle).pop();
    currentPage.value = Number(visible?.dataset.page ?? 1);
}
</script>

<template>
    <div class="flex h-full min-h-0 flex-col bg-desk">
        <div
            class="flex h-11 shrink-0 items-center gap-1 border-b border-line bg-canvas/80 px-3 text-[13px] text-ink-2 backdrop-blur"
        >
            <span class="mr-auto pl-1 figures">{{
                t('review.page', { number: currentPage, total: pages.length })
            }}</span>
            <button
                type="button"
                class="grid size-8 place-items-center rounded-md hover:bg-surface hover:text-ink"
                :aria-label="t('review.zoom_out')"
                @click="setZoom(zoom - 0.25)"
            >
                <Minus class="size-4" />
            </button>
            <button
                type="button"
                class="h-8 min-w-14 rounded-md px-2 figures hover:bg-surface hover:text-ink"
                :aria-label="`${Math.round(zoom * 100)}%, ${t('review.fit')}`"
                :title="t('review.fit')"
                @click="setZoom(1)"
            >
                {{ Math.round(zoom * 100) }}%
            </button>
            <button
                type="button"
                class="grid size-8 place-items-center rounded-md hover:bg-surface hover:text-ink"
                :aria-label="t('review.zoom_in')"
                @click="setZoom(zoom + 0.25)"
            >
                <Plus class="size-4" />
            </button>
            <span class="mx-1 h-5 w-px bg-line" aria-hidden="true" />
            <button
                type="button"
                class="grid size-8 place-items-center rounded-md hover:bg-surface hover:text-ink"
                :aria-label="t('review.show_boxes')"
                :aria-pressed="showAll"
                :title="t('review.show_boxes')"
                @click="showAll = !showAll"
            >
                <Eye v-if="showAll" class="size-4" />
                <EyeOff v-else class="size-4" />
            </button>
            <a
                :href="originalUrl"
                target="_blank"
                rel="noopener"
                class="grid size-8 place-items-center rounded-md hover:bg-surface hover:text-ink"
                :aria-label="t('review.open_original')"
                :title="t('review.open_original')"
            >
                <ExternalLink class="size-4" />
            </a>
        </div>

        <div
            ref="scroller"
            class="min-h-0 flex-1 overflow-auto px-6 py-6"
            :class="picking ? 'cursor-crosshair' : ''"
            @scroll.passive="onScroll"
        >
            <div v-if="pages.length === 0" class="grid h-full place-items-center text-ink-3">
                <ScanSearch class="size-8" />
            </div>
            <div class="mx-auto flex flex-col items-center gap-6" :style="{ width: `${sheetWidth}px` }">
                <div
                    v-for="page in pages"
                    :key="page.number"
                    :data-page="page.number"
                    class="relative w-full rounded-[2px] bg-white shadow-sheet"
                    :style="{ aspectRatio: `${page.width} / ${page.height}` }"
                >
                    <img
                        :src="page.url"
                        :alt="t('review.page', { number: page.number, total: pages.length })"
                        class="block size-full rounded-[2px] select-none"
                        draggable="false"
                    />

                    <!-- found values: grey outline, yellow marker when it needs a look, red pen when it blocks.
                         A mouse shortcut only: the field list on the right is the keyboard path to the same fields. -->
                    <div
                        v-for="box in visibleBoxes.filter((b) => b.page === page.number)"
                        :key="box.id"
                        aria-hidden="true"
                        :data-box="box.id"
                        class="absolute cursor-pointer rounded-[3px] transition-[background-color,box-shadow] duration-150"
                        :class="[
                            box.id === activeId
                                ? 'z-10 bg-[#ffe066]/55 mix-blend-multiply shadow-[0_0_0_2px_#d9a400]'
                                : {
                                      auto: 'shadow-[0_0_0_1px_rgb(42_62_209/0.35)] hover:bg-[#2a3ed1]/8',
                                      checked: 'bg-[#2a3ed1]/6 shadow-[0_0_0_1px_rgb(42_62_209/0.6)]',
                                      review: 'bg-[#ffe066]/40 mix-blend-multiply hover:bg-[#ffe066]/60',
                                      blocking: 'shadow-[0_0_0_2px_#c4261d] hover:bg-[#c4261d]/8',
                                  }[box.tone],
                            picking ? 'pointer-events-none' : '',
                        ]"
                        :style="boxStyle(box.bbox)"
                        :title="box.label"
                        @click="emit('select', box.id)"
                    />

                    <!-- while editing a field, every word on the page can be clicked to take its text -->
                    <template v-if="picking">
                        <span
                            v-for="(word, i) in page.words"
                            :key="i"
                            role="button"
                            tabindex="-1"
                            class="absolute z-20 rounded-[2px] hover:bg-[#2a3ed1]/15 hover:shadow-[0_0_0_1px_#2a3ed1]"
                            :style="boxStyle(word.bbox)"
                            :title="word.text"
                            @mousedown.prevent
                            @click="emit('pick', word, page.number, $event.shiftKey)"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
