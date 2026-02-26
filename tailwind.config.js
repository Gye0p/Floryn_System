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
        // Floryn Garden — actual palette used throughout the app
        'navy': {
          DEFAULT: '#293855',
          50: '#f4f6f9',
          100: '#e8edf2',
          200: '#c9d3e1',
          300: '#9baabe',
          400: '#6b7e9a',
          500: '#4a5f7d',
          600: '#3a4d6a',
          700: '#293855',
          800: '#1e2d47',
          900: '#16223a',
        },
        'blue': {
          DEFAULT: '#4265d6',
          50: '#eaeefc',
          100: '#d5defa',
          200: '#abbdf5',
          300: '#809bef',
          400: '#567aea',
          500: '#4265d6',
          600: '#3554b8',
          700: '#28439a',
          800: '#1c327c',
          900: '#10215e',
        },
        'mint': {
          DEFAULT: '#c2e7c9',
          50: '#f0faf2',
          100: '#e0f5e5',
          200: '#c2e7c9',
          300: '#95d4a2',
          400: '#68c17b',
          500: '#3daa57',
          600: '#2d8a4e',
          700: '#236a3c',
          800: '#1a4a2a',
          900: '#0f2a18',
        },
      },
      fontFamily: {
        heading: ['Bricolage Grotesque', 'ui-serif', 'Georgia', 'serif'],
        body: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      animation: {
        'slide-in': 'slideInFromLeft 0.3s ease-out',
        'fade-in': 'fadeIn 0.3s ease-out',
      },
      keyframes: {
        slideInFromLeft: {
          '0%': { transform: 'translateX(-100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(-4px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms')({
      strategy: 'class',
    }),
  ],
}