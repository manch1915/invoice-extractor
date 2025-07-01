<script setup lang="ts">
import { Check, Pencil } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from '@/i18n';
import { date, decimalText, money } from '@/lib/format';
import type { Field } from '@/types';

const props = defineProps<{
    field: Field;
    currency: string | null;
    active: boolean;
    editing: boolean;
    problem: 'blocking' | 'warning' | null;
    disabled: boolean;
    draft: string;
}>();

const emit = defineEmits<{
    select: [];
    edit: [];
    confirm: [];
    save: [value: string];
    cancel: [];
    'update:draft': [value: string];
}>();

const { t, locale } = useI18n();
const input = ref<HTMLInputElement | null>(null);

const MONEY = ['subtotal', 'tax_amount', 'total'];
const DATES = ['issue_date', 'due_date'];

function display(value: string | null): string {
    if (value === null || value === '') return '';
    if (MONEY.includes(props.field.key)) return money(Math.round(Number(value) * 100), props.currency, locale.value);
    if (DATES.includes(props.field.key)) return date(value, locale.value);
    if (props.field.key === 'tax_rate') return `${decimalText(value, locale.value)}%`;
    return value;
}

const shown = computed(() => display(props.field.value));
const corrected = computed(
    () => props.field.status === 'corrected' && props.field.original_value !== props.field.value,
);
const tone = computed(() => {
    if (props.problem === 'blocking') return 'blocking';
    if (props.field.status === 'confirmed' || props.field.status === 'corrected') return 'checked';
    if (props.field.status === 'review' || props.problem === 'warning') return 'review';
    return 'auto';
});

watch(
    () => props.editing,
    async (editing) => {
        if (editing) {
            await nextTick();
            input.value?.focus();
            input.value?.select();
        }
    },
);

function onKey(event: KeyboardEvent) {
    if (event.key === 'Enter') {
        event.preventDefault();
        emit('save', props.draft);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        emit('cancel');
    }
}
</script>

<template>
    <div
        :id="`field-${field.key}`"
        class="group relative grid grid-cols-[minmax(0,7.5rem)_minmax(0,1fr)_auto] items-center gap-x-3 rounded-lg py-1.5 pr-1.5 pl-3 transition-colors"
        :class="[active ? 'bg-surface-2 shadow-[inset_0_0_0_1px_var(--line-strong)]' : 'hover:bg-surface-2/70']"
        @click="!editing && emit('select')"
    >
        <!-- margin mark: what the checks think of this value -->
        <span
            class="absolute top-2 bottom-2 left-0 w-[3px] rounded-full"
            :class="{ blocking: 'bg-red-pen', review: 'bg-marker', checked: 'bg-pen', auto: 'bg-transparent' }[tone]"
            aria-hidden="true"
        />
        <span :id="`label-${field.key}`" class="truncate text-[13px] text-ink-3">{{ t(`fields.${field.key}`) }}</span>

        <div v-if="editing" class="col-span-2 flex items-center gap-1.5" @click.stop>
            <input
                ref="input"
                :value="draft"
                class="h-8 min-w-0 flex-1 rounded-md border border-pen bg-surface px-2.5 text-[14px] ring-3 ring-pen/15 outline-none"
                :aria-labelledby="`label-${field.key}`"
                :aria-describedby="`hint-${field.key}`"
                @input="emit('update:draft', ($event.target as HTMLInputElement).value)"
                @keydown="onKey"
            />
            <button
                type="button"
                class="h-8 rounded-md bg-pen px-2.5 text-[13px] font-medium text-pen-ink hover:bg-pen-hover"
                @click="emit('save', draft)"
            >
                {{ t('review.save') }}
            </button>
            <button
                type="button"
                class="h-8 rounded-md px-2 text-[13px] text-ink-3 hover:bg-surface hover:text-ink"
                @click="emit('cancel')"
            >
                {{ t('review.cancel') }}
            </button>
            <p :id="`hint-${field.key}`" class="sr-only">{{ t('review.pick_hint') }}</p>
        </div>

        <template v-else>
            <div class="min-w-0 py-0.5">
                <span
                    v-if="shown"
                    class="box-decoration-clone px-0.5 text-[14px] leading-6 font-medium break-words"
                    :class="[
                        tone === 'review' ? 'rounded-[2px] bg-[#ffe066] text-[#2b2100]' : '',
                        field.key === 'document_number' || field.key.endsWith('tax_id') ? 'ident' : '',
                        MONEY.includes(field.key) ? 'figures' : '',
                    ]"
                >
                    {{ shown }}
                </span>
                <span v-else class="text-[13.5px] text-ink-3 italic">{{ t('review.empty_value') }}</span>
                <span
                    v-if="corrected && field.original_value"
                    class="ml-2 text-[12px] text-ink-3 line-through decoration-red-pen/70"
                >
                    {{ display(field.original_value) }}
                </span>
            </div>
            <div
                class="flex items-center gap-0.5"
                :class="active ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 focus-within:opacity-100'"
            >
                <button
                    v-if="tone !== 'checked' && field.value"
                    type="button"
                    class="grid size-7 place-items-center rounded-md text-ink-3 hover:bg-pen-soft hover:text-pen disabled:opacity-40"
                    :aria-label="`${t('review.confirm')}: ${t(`fields.${field.key}`)}`"
                    :title="`${t('review.confirm')} (Enter)`"
                    :disabled="disabled"
                    @click.stop="emit('confirm')"
                >
                    <Check class="size-4" />
                </button>
                <button
                    type="button"
                    class="grid size-7 place-items-center rounded-md text-ink-3 hover:bg-surface hover:text-ink disabled:opacity-40"
                    :aria-label="`${t('review.edit')}: ${t(`fields.${field.key}`)}`"
                    :title="`${t('review.edit')} (E)`"
                    :disabled="disabled"
                    @click.stop="emit('edit')"
                >
                    <Pencil class="size-3.5" />
                </button>
            </div>
        </template>

        <span v-if="!editing" class="col-start-2 col-end-4 text-[11.5px] text-ink-3" :class="active ? '' : 'sr-only'">
            {{ t(`field_status.${field.status}`)
            }}<template v-if="field.status === 'auto' || field.status === 'review'"
                >, {{ t('review.confidence', { value: `${Math.round(field.confidence * 100)}%` }) }}</template
            >
        </span>
    </div>
</template>
