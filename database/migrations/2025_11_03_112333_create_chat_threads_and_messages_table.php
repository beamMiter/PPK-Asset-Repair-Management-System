<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $t) {
            $t->id();

            // หัวข้อของห้องแชท หรือหัวเรื่องการสนทนา
            $t->string('title', 180);

            // ผู้สร้างห้องแชท (Author)
            $t->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            // สถานะการล็อคห้องแชท (true = ปิดรับข้อความใหม่, false = คุยต่อได้)
            $t->boolean('is_locked')->default(false);

            // เวลาที่ถูกล็อก (null = ไม่ได้ล็อก): กระทู้ที่ล็อกค้างครบ chat.delete_locked_after_days จะถูกลบโดย chat:expire-idle
            // นับจากเวลาที่ล็อก ไม่ใช่เวลาที่มีข้อความล่าสุด (updated_at)
            $t->timestamp('locked_at')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index('created_at', 'chat_threads_created_at_idx');
        });

        Schema::create('chat_messages', function (Blueprint $t) {
            $t->id();

            // ห้องแชทที่ข้อความนี้สังกัดอยู่
            $t->foreignId('chat_thread_id')->constrained('chat_threads')->cascadeOnDelete();

            // ผู้ส่งข้อความ
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // เนื้อหาข้อความการสนทนา
            $t->text('body');

            // รหัสที่หน้าเว็บสร้างให้การส่งแต่ละครั้ง (UUID): ถ้าสัญญาณหลุดหลังเซิร์ฟเวอร์บันทึกแล้ว การส่งซ้ำด้วยรหัสเดิมจะได้ข้อความเดิม
            // ไม่บันทึกซ้ำ (idempotency key); ข้อความที่ส่งโดยไม่มีรหัส (ฟอร์มธรรมดา, ไคลเอนต์เก่า) เป็น null
            $t->char('client_uuid', 36)->nullable();

            // เวลาที่เจ้าของแก้ไขข้อความล่าสุด (null = ไม่เคยแก้): หน้าแชทแสดงป้าย "แก้ไขแล้ว"
            $t->timestamp('edited_at')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['chat_thread_id', 'created_at'], 'chat_messages_thread_created_idx');
            $t->index('user_id', 'chat_messages_user_id_idx');
            $t->unique(['user_id', 'client_uuid'], 'chat_messages_user_client_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
    }
};
