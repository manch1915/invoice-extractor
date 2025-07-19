<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { Download, ExternalLink, FileSpreadsheet, Sheet, TableProperties, Landmark } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/i18n';
import { dateTime, money } from '@/lib/format';

interface Target {
    key: 'xlsx' | 'csv_1c' | 'google_sheets' | 'xero';
    available: boolean;
    configured?: boolean;
    account?: string | null;
}

interface ExportRow {
    id: string;
    target: Target['key'];
    status: 'queued' | 'running' | 'done' | 'failed';
    documents: number;
    user: string | null;
    created_at: string;
    download: string | null;
    external_url: string | null;
    error: string | null;
}

const props = defineProps<{
    ready: number;
    ready_total: Record<string, number>;
    targets: Target[];
    exports: ExportRow[];
    busy: boolean;
}>();

const { t, locale } = useI18n();
const icons = { xlsx: FileSpreadsheet, csv_1c: TableProperties, google_sheets: Sheet, xero: Landmark };
const chosen = ref<Target['key']>('xlsx');
const sending = ref(false);

const target = computed(() => props.targets.find((x) => x.key === chosen.value));
const totals = computed(() =>
    Object.entries(props.ready_total)
        .map(([currency, total]) => money(Number(total), currency, locale.value))
        .join(' + '),
);

function run() {
    router.post(
        route('exports.store'),
        { target: chosen.value },
        {
            preserveScroll: true,
            onStart: () => (sending.value = true),
            onFinish: () => (sending.value = false),
        },
    );
}

const { start, stop } = usePoll(2000, { only: ['exports', 'busy', 'ready', 'ready_total'] }, { autoStart: false });
watch(
    () => props.busy,
    (busy) => (busy ? start() : stop()),
    { immediate: true },
);

const statusTone = {
    queued: 'text-ink-3',
    running: 'text-pen',
    done: 'text-ok',
    failed: 'text-red-pen',
};
</script>

<template>
    <Head :title="t('exports.title')" />

    <div class="mx-auto max-w-[1080px] px-4 pt-6 pb-16 sm:px-6 lg:px-10 lg:pt-10">
        <h1 class="text-[26px] font-semibold tracking-[-0.02em]">{{ t('exports.title') }}</h1>

        <section class="mt-6 rounded-xl border border-line bg-surface p-5 shadow-card" aria-labelledby="new-export">
            <h2 id="new-export" class="font-semibold">{{ t('exports.new') }}</h2>
            <p class="mt-1 text-[13.5px] text-ink-2">
                <template v-if="ready">
                    {{ t('exports.ready', { count: ready })
                    }}<span v-if="totals" class="figures text-ink-3">, {{ totals }}</span>
                </template>
                <template v-else>{{ t('exports.none_ready') }}</template>
            </p>

            <fieldset class="mt-5">
                <legend class="sr-only">{{ t('exports.columns.target') }}</legend>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label
                        v-for="item in targets"
                        :key="item.key"
                        class="relative flex cursor-pointer flex-col gap-2 rounded-lg border p-3.5 transition-[border-color,background-color] has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-pen"
                        :class="[
                            chosen === item.key ? 'border-pen bg-pen-soft/50' : 'border-line hover:border-line-strong',
                            !item.available ? 'cursor-default opacity-70' : '',
                        ]"
                    >
                        <input
                            v-model="chosen"
                            type="radio"
                            name="target"
                            :value="item.key"
                            class="sr-only"
                            :disabled="!item.available"
                        />
                        <component
                            :is="icons[item.key]"
                            class="size-5"
                            :class="chosen === item.key ? 'text-pen' : 'text-ink-3'"
                        />
                        <span class="font-medium">{{ t(`exports.targets.${item.key}.name`) }}</span>
                        <span class="text-[12.5px] leading-snug text-ink-3">{{
                            t(`exports.targets.${item.key}.hint`)
                        }}</span>
                        <span v-if="item.account" class="text-[12px] text-ok">{{
                            t('exports.connected_as', { account: item.account })
                        }}</span>
                        <span v-else-if="!item.available && item.configured === false" class="text-[12px] text-ink-3">{{
                            t('exports.not_configured')
                        }}</span>
                        <Link
                            v-else-if="!item.available"
                            :href="route('settings')"
                            class="text-[12px] font-medium text-pen underline decoration-pen/30 underline-offset-2"
                        >
                            {{ t('exports.connect') }}
                        </Link>
                    </label>
                </div>
            </fieldset>

            <div class="mt-5 flex justify-end">
                <Button variant="primary" :disabled="!ready || !target?.available" :loading="sending" @click="run">
                    {{ t('exports.run', { count: ready }) }}
                </Button>
            </div>
        </section>

        <section class="mt-8" aria-labelledby="export-log">
            <h2 id="export-log" class="font-semibold">{{ t('exports.log') }}</h2>
            <p v-if="!exports.length" class="mt-3 text-[13.5px] text-ink-3">{{ t('exports.log_empty') }}</p>
            <div v-else class="mt-3 overflow-x-auto rounded-xl border border-line bg-surface shadow-card">
                <table class="w-full min-w-[640px] text-left text-[13.5px]">
                    <thead class="border-b border-line bg-surface-2 text-[12.5px] text-ink-3">
                        <tr>
                            <th scope="col" class="py-2.5 pr-3 pl-4 font-medium">{{ t('exports.columns.when') }}</th>
                            <th scope="col" class="px-3 font-medium">{{ t('exports.columns.target') }}</th>
                            <th scope="col" class="px-3 text-right font-medium">
                                {{ t('exports.columns.documents') }}
                            </th>
                            <th scope="col" class="px-3 font-medium">{{ t('exports.columns.who') }}</th>
                            <th scope="col" class="py-2.5 pr-4 pl-3 font-medium">{{ t('exports.columns.result') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        <tr v-for="row in exports" :key="row.id">
                            <td class="py-2.5 pr-3 pl-4 whitespace-nowrap text-ink-2">
                                {{ dateTime(row.created_at, locale) }}
                            </td>
                            <td class="px-3">
                                <span class="flex items-center gap-2">
                                    <component :is="icons[row.target]" class="size-4 text-ink-3" />
                                    {{ t(`exports.targets.${row.target}.name`) }}
                                </span>
                            </td>
                            <td class="px-3 text-right figures">{{ row.documents }}</td>
                            <td class="px-3 text-ink-2">{{ row.user ?? '—' }}</td>
                            <td class="py-2.5 pr-4 pl-3">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="font-medium" :class="statusTone[row.status]">{{
                                        t(`exports.status.${row.status}`)
                                    }}</span>
                                    <a
                                        v-if="row.download"
                                        :href="row.download"
                                        class="inline-flex items-center gap-1 text-pen hover:underline"
                                    >
                                        <Download class="size-3.5" />{{ t('exports.download') }}
                                    </a>
                                    <a
                                        v-if="row.external_url"
                                        :href="row.external_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1 text-pen hover:underline"
                                    >
                                        <ExternalLink class="size-3.5" />{{ t('exports.open') }}
                                    </a>
                                </div>
                                <p v-if="row.error" class="mt-0.5 max-w-sm text-[12.5px] text-red-pen">
                                    {{ row.error }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
