<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Camera, Mail, Upload } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/i18n';

defineProps<{ mailbox?: string | null; compact?: boolean }>();

const { t } = useI18n();
const form = useForm<{ files: File[] }>({ files: [] });
const picker = ref<HTMLInputElement | null>(null);
const camera = ref<HTMLInputElement | null>(null);
const dragging = ref(false);
let depth = 0;

const ACCEPT = '.pdf,.jpg,.jpeg,.png,.webp,.tif,.tiff,application/pdf,image/*';

function send(files: FileList | File[] | null | undefined) {
    const list = Array.from(files ?? []);
    if (list.length === 0 || form.processing) return;
    form.files = list;
    form.post(route('documents.store'), {
        preserveScroll: true,
        onFinish: () => form.reset(),
    });
}

function onPicked(event: Event) {
    const input = event.target as HTMLInputElement;
    send(input.files);
    input.value = '';
}

// The whole window is a drop target: people drag files from the desktop wherever the page is.
function hasFiles(event: DragEvent) {
    return Array.from(event.dataTransfer?.types ?? []).includes('Files');
}
function onEnter(event: DragEvent) {
    if (!hasFiles(event)) return;
    depth++;
    dragging.value = true;
}
function onLeave(event: DragEvent) {
    if (!hasFiles(event)) return;
    depth = Math.max(0, depth - 1);
    if (depth === 0) dragging.value = false;
}
function onOver(event: DragEvent) {
    if (hasFiles(event)) event.preventDefault();
}
function onDrop(event: DragEvent) {
    if (!hasFiles(event)) return;
    event.preventDefault();
    depth = 0;
    dragging.value = false;
    send(event.dataTransfer?.files);
}

onMounted(() => {
    window.addEventListener('dragenter', onEnter);
    window.addEventListener('dragleave', onLeave);
    window.addEventListener('dragover', onOver);
    window.addEventListener('drop', onDrop);
});
onBeforeUnmount(() => {
    window.removeEventListener('dragenter', onEnter);
    window.removeEventListener('dragleave', onLeave);
    window.removeEventListener('dragover', onOver);
    window.removeEventListener('drop', onDrop);
});

defineExpose({ open: () => picker.value?.click() });
</script>

<template>
    <input
        ref="picker"
        type="file"
        class="sr-only"
        :accept="ACCEPT"
        multiple
        tabindex="-1"
        aria-hidden="true"
        @change="onPicked"
    />
    <input
        ref="camera"
        type="file"
        class="sr-only"
        accept="image/*"
        capture="environment"
        tabindex="-1"
        aria-hidden="true"
        @change="onPicked"
    />

    <div
        class="relative overflow-hidden rounded-xl border border-dashed border-line-strong bg-surface/60"
        :class="compact ? 'px-4 py-3' : 'px-6 py-8'"
    >
        <div class="flex flex-col gap-4" :class="compact ? 'sm:flex-row sm:items-center' : 'items-center text-center'">
            <div :class="compact ? 'flex-1' : ''">
                <p class="font-medium">
                    {{ t('documents.drop_title') }}
                    <span class="font-normal text-ink-3">{{ t('documents.or') }}</span
                    >{{ ' '
                    }}<button
                        type="button"
                        class="font-medium text-pen underline decoration-pen/30 underline-offset-2 hover:decoration-pen"
                        @click="picker?.click()"
                    >
                        {{ t('documents.choose') }}
                    </button>
                </p>
                <p class="mt-0.5 text-[13px] text-ink-3">{{ t('documents.drop_hint') }}</p>
            </div>
            <div class="flex flex-wrap gap-2" :class="compact ? '' : 'justify-center'">
                <Button variant="primary" :loading="form.processing" @click="picker?.click()">
                    <Upload v-if="!form.processing" class="size-4" />
                    {{ t('documents.upload') }}
                </Button>
                <Button class="sm:hidden" @click="camera?.click()">
                    <Camera class="size-4" />
                    {{ t('documents.take_photo') }}
                </Button>
            </div>
        </div>
        <p v-if="mailbox && !compact" class="mt-5 flex items-center justify-center gap-2 text-[13px] text-ink-3">
            <Mail class="size-3.5" />
            {{ t('documents.mailbox', { address: mailbox }) }}
        </p>
        <div
            v-if="form.progress"
            class="absolute inset-x-0 bottom-0 h-0.5 bg-line"
            role="progressbar"
            :aria-valuenow="form.progress.percentage ?? 0"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full bg-pen transition-[width] duration-200"
                :style="{ width: `${form.progress.percentage ?? 0}%` }"
            />
        </div>
    </div>

    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="dragging"
                class="pointer-events-none fixed inset-0 z-50 grid place-items-center bg-pen/10 p-6 backdrop-blur-[1px]"
            >
                <div
                    class="grid place-items-center gap-3 rounded-2xl border-2 border-dashed border-pen bg-surface px-12 py-10 shadow-pop"
                >
                    <Upload class="size-7 text-pen" />
                    <p class="text-[17px] font-semibold">{{ t('documents.drop_overlay') }}</p>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
