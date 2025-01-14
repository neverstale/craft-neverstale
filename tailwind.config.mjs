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
    },
  },
  plugins: [],
}

