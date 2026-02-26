/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
    "./src/**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        // Floryn Garden Refined Color Palette
        'lavender-purple': {
          50: '#f7f5ff',
          100: '#ebe8ff',
          200: '#ddd6fe',
          300: '#c4b5fd',
          400: '#a78bfa',
          500: '#5e548e', // Primary Color
          600: '#4c46763',
          700: '#3d3856',
          800: '#2e2c47',
          900: '#252239',
        },
        'aqua-blue': {
          50: '#f0f9ff',
          100: '#e0f2fe',
          200: '#bae6fd',
          300: '#7dd3fc',
          400: '#39a8c9', // Accent Color
          500: '#0ea5e9',
          600: '#0284c7',
          700: '#0369a1',
          800: '#075985',
          900: '#0c4a6e',
        },
        'cream-white': '#FFF8F0', // Background
        'charcoal-gray': '#2E2E2E', // High contrast text
      },
      fontFamily: {
        heading: ['Poppins', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        body: ['Roboto', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        sans: ['Roboto', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      animation: {
        'slide-in': 'slideInFromLeft 0.3s ease-out',
      },
      keyframes: {
        slideInFromLeft: {
          '0%': { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(0)' },
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms')({
      strategy: 'class',
    }),
  ],
}