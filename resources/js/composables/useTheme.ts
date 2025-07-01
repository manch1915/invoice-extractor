import { ref } from 'vue';

const dark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

export function useTheme() {
    function toggle() {
        dark.value = !dark.value;
        document.documentElement.classList.toggle('dark', dark.value);
        try {
            localStorage.setItem('theme', dark.value ? 'dark' : 'light');
        } catch {
            // private mode: the choice just isn't remembered
        }
    }

    return { dark, toggle };
}
