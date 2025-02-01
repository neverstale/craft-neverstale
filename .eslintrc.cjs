module.exports = {
  extends: [
    '@zaengle/eslint-config-vue-ts',
    'plugin:tailwindcss/recommended',
  ],
  rules: {
    '@typescript-eslint/no-non-null-assertion': 'off',
    '@typescript-eslint/no-unused-vars': 'warn',
    'no-undef': 'off',
    'tailwindcss/enforces-negative-arbitrary-values': 'off',
    'tailwindcss/no-custom-classname': [
      'warn',
      {
        cssFiles: ['resources/css/**/*.css'],
        whitelist: [
          'ns-btn',
          'ns-flags',
          'ns-stale-warning',
        ],
      },
    ],
    'vue/multi-word-component-names': 'off',
    'vue/no-v-html': 'off',
  },
}
