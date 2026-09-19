<x-guest-layout title="Verify email">
    <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ __('ขอบคุณที่สมัครใช้งาน! ก่อนเริ่มใช้งาน ระบบขอให้คุณยืนยันอีเมลโดยคลิกลิงก์ที่ส่งไปให้ทางอีเมลที่ใช้สมัคร แม้ว่าคุณจะล็อกอินด้วยเลขบัตรประชาชน แต่การยืนยันอีเมลจะช่วยให้คุณสามารถกู้รหัสผ่านและรับการแจ้งเตือนได้') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            {{ __('ได้ส่งลิงก์ยืนยันอีเมลไปที่อีเมลที่คุณใช้สมัครเรียบร้อยแล้ว') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('ส่งลิงก์ยืนยันอีเมลอีกครั้ง') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit"
                    class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md
                           focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                {{ __('ออกจากระบบ') }}
            </button>
        </form>
    </div>
</x-guest-layout>
