<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_thread_reads', function (Blueprint $table) {
            $table->id();

            // ผู้ใช้งานที่เข้ามาอ่านห้องแชท
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // ห้องแชทที่ผู้ใช้กำลังอ่าน
            $table->foreignId('chat_thread_id')->constrained('chat_threads')->cascadeOnDelete();

            // ID ของข้อความล่าสุดที่ผู้ใช้คนนี้ได้อ่านแล้ว
            $table->unsignedBigInteger('last_read_message_id')->nullable();

            // วันที่และเวลาที่มีการเข้ามาอ่านล่าสุด
            $table->timestamp('last_read_at')->nullable();

            // ผู้ใช้ซ่อนกระทู้นี้จาก "กระทู้ที่มีส่วนร่วม" ของตนเอง (ไม่ลบให้ใคร) — เคลียร์เมื่อเขียนในกระทู้นั้นอีกครั้ง
            $table->timestamp('hidden_at')->nullable();

            $table->timestamps();

            $table->unique(['user_id','chat_thread_id'], 'utr_user_thread_unique');
            $table->index(['chat_thread_id','last_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_thread_reads');
    }
};
