<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ChartColumnBig,
    FileOutput,
    Files,
    Highlighter,
    History,
    LogOut,
    Menu,
    Moon,
    Settings2,
    Sun,
    Tags,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Logo from '@/components/Logo.vue';
import FlashToast from '@/components/ui/FlashToast.vue';
import { useTheme } from '@/composables/useTheme';
import { useI18n, type Locale } from '@/i18n';
import type { SharedProps } from '@/types';

const page = usePage<SharedProps>();
const { t, locale } = useI18n();
const { dark, toggle } = useTheme();
const drawer = ref(false);

const user = computed(() => page.props.auth.user);

const nav = computed(() => {
    const items = [
        {
            label: t('nav.overview'),
            href: route('dashboard'),
            icon: ChartColumnBig,
            active: route().current('dashboard'),
        },
        {
            label: t('nav.documents'),
            href: route('documents.index'),
            icon: Files,
            active: route().current('documents.index') && !page.url.includes('status=needs_review'),
        },
        {
            label: t('nav.review'),
            href: route('documents.index', { status: 'needs_review' }),
            icon: Highlighter,
            active: page.url.includes('status=needs_review') || route().current('documents.show'),
            count: page.props.queue_count,
        },
        { label: t('nav.exports'), href: '/exports', icon: FileOutput, active: page.url.startsWith('/exports') },
        { label: t('nav.rules'), href: '/categories', icon: Tags, active: page.url.startsWith('/categories') },
        { label: t('nav.activity'), href: '/activity', icon: History, active: page.url.startsWith('/activity') },
    ];
    if (user.value?.can_manage) {
        items.push({
            label: t('nav.settings'),
            href: '/settings',
            icon: Settings2,
            active: page.url.startsWith('/settings'),
        });
    }
    return items;
});

watch(
    () => page.url,
    () => (drawer.value = false),
);

function setLocale(value: Locale) {
    if (value !== locale.value) {
        router.post(route('locale'), { locale: value }, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <a
        href="#main"
        class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2"
    >
        {{ t('app.skip') }}
    </a>

    <div class="min-h-dvh lg:grid lg:grid-cols-[232px_1fr]">
        <!-- mobile top bar -->
        <header
            class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-canvas/90 px-4 backdrop-blur lg:hidden"
        >
            <Link :href="route('dashboard')" class="flex items-center gap-2 font-semibold">
                <Logo :size="24" />
                <span>{{ t('app.name') }}</span>
            </Link>
            <button
                type="button"
                class="grid size-10 place-items-center rounded-lg text-ink-2 hover:bg-surface"
                :aria-label="t('nav.menu')"
                :aria-expanded="drawer"
                aria-controls="sidebar"
                @click="drawer = true"
            >
                <Menu class="size-5" />
            </button>
        </header>

        <div
            v-if="drawer"
            class="fixed inset-0 z-40 bg-ink/30 backdrop-blur-[2px] lg:hidden"
            aria-hidden="true"
            @click="drawer = false"
        />

        <aside
            id="sidebar"
            class="fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col border-r border-line bg-canvas px-3 pt-4 pb-3 transition-transform duration-200 ease-[var(--ease-out-quart)] lg:sticky lg:top-0 lg:z-auto lg:h-dvh lg:w-auto lg:translate-x-0"
            :class="drawer ? 'translate-x-0 shadow-pop' : '-translate-x-full'"
        >
            <div class="flex items-center justify-between px-2 pb-5">
                <Link
                    :href="route('dashboard')"
                    class="flex items-center gap-2.5 text-[15px] font-semibold tracking-[-0.01em]"
                >
                    <Logo />
                    <span>{{ t('app.name') }}</span>
                </Link>
                <button
                    type="button"
                    class="grid size-9 place-items-center rounded-lg text-ink-3 hover:bg-surface lg:hidden"
                    :aria-label="t('nav.close')"
                    @click="drawer = false"
                >
                    <X class="size-4" />
                </button>
            </div>

            <nav class="flex flex-col gap-0.5" aria-label="Main">
                <Link
                    v-for="item in nav"
                    :key="item.href"
                    :href="item.href"
                    class="group flex h-9 items-center gap-3 rounded-lg px-2.5 text-[13.5px] transition-colors"
                    :class="
                        item.active
                            ? 'bg-surface font-medium text-ink shadow-card'
                            : 'text-ink-2 hover:bg-surface/70 hover:text-ink'
                    "
                    :aria-current="item.active ? 'page' : undefined"
                >
                    <component
                        :is="item.icon"
                        class="size-[17px] shrink-0"
                        :class="item.active ? 'text-pen' : 'text-ink-3 group-hover:text-ink-2'"
                    />
                    <span class="flex-1 truncate">{{ item.label }}</span>
                    <span
                        v-if="item.count"
                        class="min-w-6 rounded-full bg-marker px-1.5 text-center figures text-[11.5px] leading-5 font-semibold text-[#3d2f00]"
                    >
                        {{ item.count }}
                    </span>
                </Link>
            </nav>

            <div class="mt-auto flex flex-col gap-3 px-1">
                <p
                    v-if="page.props.demo"
                    class="rounded-lg border border-dashed border-line-strong px-3 py-2 text-[12px] leading-snug text-ink-3"
                >
                    {{ t('app.demo_note') }}
                </p>

                <div class="flex items-center gap-1">
                    <div
                        class="flex rounded-lg border border-line bg-surface p-0.5"
                        role="group"
                        :aria-label="t('nav.language')"
                    >
                        <button
                            v-for="code in ['en', 'ru'] as const"
                            :key="code"
                            type="button"
                            class="h-7 rounded-md px-2.5 text-[12px] font-medium uppercase transition-colors"
                            :class="locale === code ? 'bg-ink text-canvas' : 'text-ink-3 hover:text-ink'"
                            :aria-pressed="locale === code"
                            @click="setLocale(code)"
                        >
                            {{ code }}
                        </button>
                    </div>
                    <button
                        type="button"
                        class="ml-auto grid size-8 place-items-center rounded-lg text-ink-3 hover:bg-surface hover:text-ink"
                        :aria-label="dark ? t('nav.theme_light') : t('nav.theme_dark')"
                        @click="toggle"
                    >
                        <Sun v-if="dark" class="size-4" />
                        <Moon v-else class="size-4" />
                    </button>
                </div>

                <div v-if="user" class="flex items-center gap-2.5 border-t border-line pt-3">
                    <span
                        class="grid size-8 shrink-0 place-items-center rounded-full bg-pen-soft text-[12px] font-semibold text-pen"
                        aria-hidden="true"
                    >
                        {{ user.name.slice(0, 1) }}
                    </span>
                    <div class="min-w-0 flex-1 leading-tight">
                        <p class="truncate text-[13px] font-medium">{{ user.name }}</p>
                        <p class="truncate text-[12px] text-ink-3">{{ user.role ? t(`roles.${user.role}`) : '' }}</p>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="grid size-8 place-items-center rounded-lg text-ink-3 hover:bg-surface hover:text-ink"
                        :aria-label="t('nav.sign_out')"
                    >
                        <LogOut class="size-4" />
                    </Link>
                </div>
            </div>
        </aside>

        <main id="main" class="min-w-0">
            <slot />
        </main>
    </div>

    <FlashToast />
</template>
