<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { BriefcaseBusiness, Calculator, LoaderCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import Logo from '@/components/Logo.vue';
import Button from '@/components/ui/Button.vue';
import FlashToast from '@/components/ui/FlashToast.vue';
import { useI18n, type Locale } from '@/i18n';

defineProps<{ demo: boolean }>();

const { t, list, locale } = useI18n();
const entering = ref<string | null>(null);
const form = useForm({ email: '', password: '', remember: true });

function enterDemo(role: 'owner' | 'accountant') {
    entering.value = role;
    router.post(route('demo.login', role), {}, { onFinish: () => (entering.value = null) });
}

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}

function setLocale(value: Locale) {
    router.post(route('locale'), { locale: value }, { preserveState: true });
}

const roles = [
    { key: 'owner' as const, icon: BriefcaseBusiness },
    { key: 'accountant' as const, icon: Calculator },
];
</script>

<template>
    <Head :title="t('auth.title')" />

    <div class="min-h-dvh bg-desk lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(420px,1fr)]">
        <section class="relative flex flex-col px-6 pt-8 pb-10 sm:px-10 lg:px-16 lg:py-14">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 text-[15px] font-semibold">
                    <Logo />
                    {{ t('app.name') }}
                </div>
                <div class="flex rounded-lg border border-line-strong/70 bg-surface/60 p-0.5 lg:hidden">
                    <button
                        v-for="code in ['en', 'ru'] as const"
                        :key="code"
                        type="button"
                        class="h-7 rounded-md px-2.5 text-[12px] font-medium uppercase"
                        :class="locale === code ? 'bg-ink text-canvas' : 'text-ink-3'"
                        @click="setLocale(code)"
                    >
                        {{ code }}
                    </button>
                </div>
            </div>

            <div class="mt-10 max-w-xl lg:mt-24">
                <h1
                    class="text-[30px] leading-[1.12] font-semibold tracking-[-0.02em] text-balance sm:text-[40px]"
                    style="font-variation-settings: 'wdth' 92"
                >
                    {{ t('auth.lead') }}
                </h1>
                <ul class="mt-8 hidden space-y-3 text-[15px] text-ink-2 sm:block">
                    <li v-for="(point, i) in list('auth.points')" :key="i" class="flex gap-3">
                        <span class="mt-[9px] h-[3px] w-4 shrink-0 rounded-full bg-pen" aria-hidden="true" />
                        <span>{{ point }}</span>
                    </li>
                </ul>
            </div>

            <!-- a sheet as it looks in review: one value checked in blue, one highlighted, one crossed out in red -->
            <div
                class="pointer-events-none mt-12 hidden max-w-md rotate-[-1.5deg] rounded-[3px] bg-white p-6 text-[#20242c] shadow-sheet lg:block"
                aria-hidden="true"
            >
                <div class="flex items-baseline justify-between">
                    <p class="text-[13px] font-semibold">ООО «Мельница Север»</p>
                    <p class="text-[11px] text-[#6b7280]">ИНН 7813456723</p>
                </div>
                <p class="mt-3 text-[15px] font-semibold">Счет на оплату № 1455 от 14.09.2026</p>
                <div class="mt-4 space-y-2 text-[12px]">
                    <div class="flex justify-between border-b border-[#e5e7eb] pb-2">
                        <span>Мука пшеничная в/с, 50 кг × 50</span><span class="figures">97 000,00</span>
                    </div>
                    <div class="flex justify-between border-b border-[#e5e7eb] pb-2">
                        <span>Мука ржаная обдирная, 50 кг × 15</span><span class="figures">25 200,00</span>
                    </div>
                </div>
                <div class="mt-4 flex justify-end gap-6 text-[12.5px]">
                    <span>В т.ч. НДС 10%</span>
                    <span class="relative px-1 figures line-through decoration-[#c4261d] decoration-2">11 404,55</span>
                </div>
                <div class="mt-2 flex justify-end gap-6 text-[13.5px] font-semibold">
                    <span>Итого</span>
                    <span class="rounded-[2px] bg-[#ffe066]/80 px-1 figures">124 640,00</span>
                </div>
            </div>
        </section>

        <section
            class="flex items-start justify-center bg-canvas px-6 py-10 sm:px-10 lg:items-center lg:border-l lg:border-line"
        >
            <div class="w-full max-w-[380px]">
                <div class="mb-8 hidden justify-end lg:flex">
                    <div class="flex rounded-lg border border-line bg-surface p-0.5">
                        <button
                            v-for="code in ['en', 'ru'] as const"
                            :key="code"
                            type="button"
                            class="h-7 rounded-md px-2.5 text-[12px] font-medium uppercase"
                            :class="locale === code ? 'bg-ink text-canvas' : 'text-ink-3 hover:text-ink'"
                            :aria-pressed="locale === code"
                            @click="setLocale(code)"
                        >
                            {{ code }}
                        </button>
                    </div>
                </div>

                <template v-if="demo">
                    <h2 class="text-[20px] font-semibold tracking-[-0.01em]">{{ t('auth.demo_title') }}</h2>
                    <p class="mt-1 text-ink-3">{{ t('auth.demo_hint') }}</p>
                    <div class="mt-6 grid gap-3">
                        <button
                            v-for="role in roles"
                            :key="role.key"
                            type="button"
                            class="group flex items-center gap-4 rounded-xl border border-line bg-surface p-4 text-left shadow-card transition-[border-color,transform] duration-150 hover:border-pen active:scale-[0.99] disabled:opacity-60"
                            :disabled="entering !== null"
                            @click="enterDemo(role.key)"
                        >
                            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-pen-soft text-pen">
                                <LoaderCircle v-if="entering === role.key" class="size-5 animate-spin" />
                                <component :is="role.icon" v-else class="size-5" />
                            </span>
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ t(`auth.as_${role.key}`) }}</span>
                                <span class="block text-[13px] text-ink-3">{{ t(`auth.as_${role.key}_hint`) }}</span>
                            </span>
                        </button>
                    </div>
                    <div class="my-8 flex items-center gap-3 text-[12.5px] text-ink-3">
                        <span class="h-px flex-1 bg-line" />
                        {{ t('auth.or_email') }}
                        <span class="h-px flex-1 bg-line" />
                    </div>
                </template>
                <h2 v-else class="mb-6 text-[20px] font-semibold">{{ t('auth.title') }}</h2>

                <form class="grid gap-4" novalidate @submit.prevent="submit">
                    <label class="grid gap-1.5">
                        <span class="text-[13px] font-medium">{{ t('auth.email') }}</span>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            required
                            class="h-10 rounded-lg border border-line-strong bg-surface px-3 outline-none focus:border-pen focus:ring-3 focus:ring-pen/15"
                            :aria-invalid="!!form.errors.email"
                            aria-describedby="email-error"
                        />
                        <span v-if="form.errors.email" id="email-error" class="text-[12.5px] text-red-pen">{{
                            form.errors.email
                        }}</span>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-[13px] font-medium">{{ t('auth.password') }}</span>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="h-10 rounded-lg border border-line-strong bg-surface px-3 outline-none focus:border-pen focus:ring-3 focus:ring-pen/15"
                        />
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-ink-2">
                        <input v-model="form.remember" type="checkbox" class="size-4 accent-pen" />
                        {{ t('auth.remember') }}
                    </label>
                    <Button type="submit" :variant="demo ? 'secondary' : 'primary'" :loading="form.processing">{{
                        t('auth.submit')
                    }}</Button>
                </form>
            </div>
        </section>
    </div>
    <FlashToast />
</template>
