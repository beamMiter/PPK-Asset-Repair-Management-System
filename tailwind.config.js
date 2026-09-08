// tailwind.config.js (ESM) — Tailwind v4
//
// v4 อ่านไฟล์นี้ผ่าน `@config` ใน resources/css/app.css
// - content: กันไว้ครบทุก path ของ Blade/JS + cache view ของ Laravel
// - theme.extend: font ระบบ + เงา card
//
// daisyUI และ @tailwindcss/forms ย้ายไปโหลดแบบ CSS-first (`@plugin`) ใน app.css แล้ว
// ธีม govclean ก็ประกาศใน app.css ผ่าน `@plugin "daisyui/theme"`
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/**/*.{js,ts,vue,tsx}',
    './resources/js/**/*.{js,ts,tsx}',
    './storage/framework/views/*.php',
  ],
  theme: {
    extend: {
      fontFamily: {
        // ใช้ระบบฟอนต์เป็นหลักให้โหลดไว
        sans: ['system-ui', 'ui-sans-serif', 'Inter', 'Segoe UI', 'Roboto', 'Helvetica Neue', 'Arial', 'Noto Sans Thai', 'sans-serif'],
      },
      boxShadow: {
        'card': '0 6px 24px rgba(0,0,0,.06)',
      },
    },
  },
}
