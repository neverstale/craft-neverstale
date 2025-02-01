/** @type {import('tailwindcss').Config} */
export default {
  prefix: 'ns-',
  important: true,
  corePlugins: {
    preflight: false,
  },
  content: ['./src/**/*.{vue,js,twig}'],
  theme: {
    extend: {
      screens: {
        xl: '1200px',
      },
      typography: theme => ({}),
      colors: theme => ({
        neutral: {
          '100': 'var(--ns-color-neutral-100, #F4F7FC)',
          '200': 'var(--ns-color-neutral-200, #E6E7EB)',
          '300': 'var(--ns-color-neutral-300, #D9DEE7)',
          '700': 'var(--ns-color-neutral-700, #424D5A)',
        },
        status: {
          success: 'var(--ns-color-status-success, #48A397)',
          pending: 'var(--ns-color-status-pending, #E9A32D)',
          alert: 'var(--ns-color-status-alert, #CC3E2D)',
        },
        theme: {
          primary: 'var(--ns-color-theme-primary, #DC2626)',
        },
      }),
    },
  },
  plugins: [],
}

