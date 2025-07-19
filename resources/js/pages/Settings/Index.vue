<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, KeyRound, Landmark, Mail, Sheet } from 'lucide-vue-next';
import { ref } from 'vue';
import Button from '@/components/ui/Button.vue';
import { useI18n } from '@/i18n';
import { dateTime, relativeTime } from '@/lib/format';

interface Integration {
    provider: 'google' | 'xero';
    configured: boolean;
    connected: boolean;
    account: string | null;
    connected_at: string | null;
}

defineProps<{
    integrations: Integration[];
    mailbox: { address: string | null; configured: boolean };
    tokens: { id: number; name: string; last_used_at: string | null; created_at: string }[];
    new_token: string | null;
    api_url: string;
    engine: string;
    demo_locked: boolean;
}>();

const { t, locale } = useI18n();
const icons = { google: Sheet, xero: Landmark };
const tokenForm = useForm({ name: '' });
const copied = ref(false);

function createToken() {
    tokenForm.post(route('tokens.store'), { preserveScroll: true, onSuccess: () => tokenForm.reset() });
}

async function copy(value: string) {
    await navigator.clipboard.writeText(value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}

function revoke(id: number) {
    router.delete(route('tokens.destroy', id), { preserveScroll: true });
}

function disconnect(provider: string) {
    router.delete(route('integrations.disconnect', provider), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('settings.title')" />

    <div class="mx-auto max-w-[860px] px-4 pt-6 pb-16 sm:px-6 lg:px-10 lg:pt-10">
        <h1 class="text-[26px] font-semibold tracking-[-0.02em]">{{ t('settings.title') }}</h1>

        <section class="mt-8" aria-labelledby="integrations">
            <h2 id="integrations" class="text-[18px] font-semibold">{{ t('settings.integrations') }}</h2>
            <p class="mt-1 text-[13.5px] text-ink-2">{{ t('settings.integrations_lead') }}</p>
            <ul class="mt-4 divide-y divide-line rounded-xl border border-line bg-surface shadow-card">
                <li
                    v-for="item in integrations"
                    :key="item.provider"
                    class="flex flex-wrap items-center gap-4 px-4 py-4"
                >
                    <span class="grid size-10 place-items-center rounded-lg bg-surface-2 text-ink-2"
                        ><component :is="icons[item.provider]" class="size-5"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">{{ t(`settings.${item.provider}`) }}</p>
                        <p class="text-[13px]" :class="item.connected ? 'text-ok' : 'text-ink-3'">
                            <template v-if="item.connected">{{
                                t('settings.connected', { account: item.account ?? '' })
                            }}</template>
                            <template v-else-if="!item.configured">{{ t('settings.not_configured') }}</template>
                            <template v-else-if="demo_locked">{{ t('settings.demo_locked') }}</template>
                            <template v-else>{{ t('settings.not_connected') }}</template>
                        </p>
                    </div>
                    <Button v-if="item.connected" size="sm" variant="danger" @click="disconnect(item.provider)">{{
                        t('settings.disconnect')
                    }}</Button>
                    <a
                        v-else-if="item.configured && !demo_locked"
                        :href="route('integrations.connect', item.provider)"
                        class="inline-flex h-8 items-center rounded-lg bg-pen px-3 text-[13px] font-medium text-pen-ink hover:bg-pen-hover"
                    >
                        {{ t('settings.connect') }}
                    </a>
                </li>
            </ul>
        </section>

        <section class="mt-10" aria-labelledby="mailbox">
            <h2 id="mailbox" class="text-[18px] font-semibold">{{ t('settings.mailbox') }}</h2>
            <p
                class="mt-3 flex items-start gap-3 rounded-xl border border-line bg-surface px-4 py-3.5 text-[13.5px] shadow-card"
            >
                <Mail class="mt-0.5 size-4 shrink-0 text-ink-3" />
                <span v-if="mailbox.configured && mailbox.address">{{
                    t('settings.mailbox_on', { address: mailbox.address })
                }}</span>
                <span v-else class="text-ink-2">{{ t('settings.mailbox_off') }}</span>
            </p>
        </section>

        <section class="mt-10" aria-labelledby="api">
            <h2 id="api" class="text-[18px] font-semibold">{{ t('settings.api') }}</h2>
            <p class="mt-1 text-[13.5px] text-ink-2">{{ t('settings.api_lead') }}</p>
            <pre
                class="mt-3 overflow-x-auto rounded-xl border border-line bg-surface-2 px-4 py-3 ident text-[12.5px] leading-relaxed text-ink-2"
            >
curl -H "Authorization: Bearer $TOKEN" \
     -F "files[]=@invoice.pdf" {{ api_url }}</pre>

            <div v-if="new_token" class="mt-4 rounded-xl border border-marker bg-marker-soft p-4" role="status">
                <p class="text-[13.5px] font-medium text-ink">{{ t('settings.new_token') }}</p>
                <div class="mt-2 flex gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-md bg-surface px-3 py-2 ident text-[12.5px]">{{
                        new_token
                    }}</code>
                    <Button size="sm" @click="copy(new_token)"
                        ><Copy class="size-3.5" />{{ copied ? t('settings.copied') : t('settings.copy') }}</Button
                    >
                </div>
            </div>

            <form class="mt-4 flex gap-2" @submit.prevent="createToken">
                <label class="flex-1">
                    <span class="sr-only">{{ t('settings.token_name') }}</span>
                    <input
                        v-model="tokenForm.name"
                        required
                        maxlength="60"
                        :placeholder="t('settings.token_name')"
                        class="h-9 w-full rounded-lg border border-line-strong bg-surface px-3 outline-none placeholder:text-ink-3 focus:border-pen focus:ring-3 focus:ring-pen/15"
                    />
                </label>
                <Button type="submit" :loading="tokenForm.processing"
                    ><KeyRound class="size-4" />{{ t('settings.create_token') }}</Button
                >
            </form>

            <p v-if="!tokens.length" class="mt-3 text-[13px] text-ink-3">{{ t('settings.no_tokens') }}</p>
            <ul v-else class="mt-3 divide-y divide-line rounded-xl border border-line bg-surface">
                <li v-for="token in tokens" :key="token.id" class="flex items-center gap-3 px-4 py-2.5">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ token.name }}</p>
                        <p class="text-[12px] text-ink-3">
                            {{ dateTime(token.created_at, locale) }},
                            {{
                                token.last_used_at
                                    ? t('settings.last_used', { when: relativeTime(token.last_used_at, locale) })
                                    : t('settings.never_used')
                            }}
                        </p>
                    </div>
                    <Button size="sm" variant="ghost" @click="revoke(token.id)">{{ t('settings.revoke') }}</Button>
                </li>
            </ul>
        </section>

        <section class="mt-10" aria-labelledby="engine">
            <h2 id="engine" class="text-[18px] font-semibold">{{ t('settings.engine') }}</h2>
            <p class="mt-2 text-[13.5px] text-ink-2">{{ t(`settings.engine_${engine}`) }}</p>
        </section>
    </div>
</template>
