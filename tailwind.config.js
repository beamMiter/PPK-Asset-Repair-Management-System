// tailwind.config.js (ESM)
import daisyui from 'daisyui'
import forms from '@tailwindcss/forms'

/**
 * จุดเด่นเวอร์ชันนี้
 * - Theme "govclean" โทน รพ./ราชการ-clean เข้ากับ Topbar #0E2B51
 * - กำหนด design tokens ของ daisyUI เพิ่ม (rounded, border, animation)
 * - เปิด @tailwindcss/forms ให้ input/select ดูเรียบร้อย
 * - เผื่อ path ของ Blade/JS/TS/TSX ทั้งหมด + cache view ของ Laravel
 * - ปิด logs daisyUI (ไม่รก console)
 */
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
  plugins: [forms, daisyui],

  daisyui: {
    logs: false,
    // ตั้งค่า theme เริ่มต้นให้ตรงกับ data-theme="govclean" ใน <html>
    // (ถ้าอยากสลับธีมภายหลัง ก็เปลี่ยน data-theme ได้เลย)
    themes: [
      {
        govclean: {
          /* พาเลตหลัก */
          primary:   '#0E2B51', // Navy
          secondary: '#14B8A6', // Emerald/Mint
          accent:    '#E5B80B', // Royal Gold
          neutral:   '#2C3E50',

          /* พื้นหลัง/เส้นแบ่ง */
          'base-100': '#FFFFFF',
          'base-200': '#F8FAFC',
          'base-300': '#E2E8F0',

          /* สถานะ */
          info:    '#1D4ED8',
          success: '#059669',
          warning: '#F59E0B',
          error:   '#DC2626',

          /* Design tokens เพิ่มเติมให้ทั้งระบบเนียน */
          '--rounded-box': '0.9rem',   // card radius
          '--rounded-btn': '0.75rem',  // button radius
          '--rounded-badge': '9999px',
          '--tab-radius': '0.7rem',
          '--border-btn': '1px',

          /* ความหนาฟอนต์เริ่มต้น */
          '--btn-text-case': 'none',
        },
      },
    ],
  },
}
