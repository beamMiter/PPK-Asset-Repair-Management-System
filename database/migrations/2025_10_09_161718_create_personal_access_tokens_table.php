<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();

            // โมเดลที่เป็นเจ้าของ Token (Polymorphic: User)
            $table->morphs('tokenable');

            // ชื่อเรียก Token
            $table->text('name');

            // ค่า Token ที่ใช้ในการยืนยันตัวตน (Hashed)
            $table->string('token', 64)->unique();

            // สิทธิ์การใช้งาน (Abilities) ของ Token นี้
            $table->text('abilities')->nullable();

            // วันที่และเวลาที่มีการใช้งานล่าสุด
            $table->timestamp('last_used_at')->nullable();

            // วันที่และเวลาที่ Token หมดอายุ
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
