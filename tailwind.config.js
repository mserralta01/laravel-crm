/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./packages/Webkul/**/src/Resources/**/*.blade.php",
    "./packages/Webkul/**/src/Resources/**/*.js",
    "./packages/Webkul/**/src/Resources/**/*.vue",
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('@tailwindcss/forms'),
  ],
}