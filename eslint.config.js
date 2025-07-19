import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import pluginVue from 'eslint-plugin-vue';

export default defineConfigWithVueTs(
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'storage', 'worker', 'resources/js/ziggy.js'],
    },
    pluginVue.configs['flat/recommended'],
    vueTsConfigs.recommendedTypeChecked,
    {
        rules: {
            'vue/multi-word-component-names': 'off',
            // page props arrive from Laravel in snake_case
            'vue/prop-name-casing': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/html-indent': 'off',
            'vue/html-closing-bracket-newline': 'off',
            'vue/multiline-html-element-content-newline': 'off',
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        },
    },
);
