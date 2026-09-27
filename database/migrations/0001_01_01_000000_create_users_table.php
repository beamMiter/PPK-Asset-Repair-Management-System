<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // เลขประจำตัวประชาชน 13 หลัก (ใช้เป็น Username หลักในการเข้าสู่ระบบ)
            $table->string('citizen_id', 13)->unique();

            // ชื่อ-นามสกุล ของผู้ใช้งาน
            $table->string('name');

            // ที่อยู่อีเมล (ไม่บังคับ แต่ต้องไม่ซ้ำถ้ามีการระบุ)
            $table->string('email')->nullable()->unique();
            
            // วันที่ยืนยันอีเมล
            $table->timestamp('email_verified_at')->nullable();

            // รหัสผ่านที่ผ่านการ Hash แล้ว
            $table->string('password');

            // รหัสผ่านที่ผู้ดูแลระบบตั้งให้คนอื่นถูกรู้โดยผู้ดูแล (และมักจดไว้ในกระดาษ): เจ้าของบัญชีต้องเปลี่ยนเองก่อนใช้งาน
            // ตั้งเป็น true เมื่อแอดมินตั้งรหัสให้ผู้อื่น และเคลียร์เมื่อเจ้าตัวเปลี่ยนรหัส (หน้าโปรไฟล์) หรือรีเซ็ตทางอีเมล
            $table->boolean('must_change_password')->default(false);

            // รหัสแผนก/หน่วยงาน (อ้างอิงจาก code ในตาราง departments)
            $table->string('department', 100)->nullable()->index();

            // บทบาทของผู้ใช้งาน (เช่น admin, technician, member) อ้างอิงจาก code ในตาราง roles
            $table->string('role', 50)->default('member')->index();

            // บัญชีถูกระงับ (ไม่ลบ): ผู้ใช้ถูกอ้างอิงโดยใบงาน ล็อก การมอบหมาย คะแนน และแชท (บางตารางลบต่อเนื่อง) การลบจึงทำลายข้อมูลของคนอื่น
            // บัญชีที่ถูกระงับเก็บประวัติทั้งหมดไว้ แต่เข้าสู่ระบบและรับงานใหม่ไม่ได้ ผู้ดูแลเปิดใช้งานอีกครั้งได้
            $table->timestamp('suspended_at')->nullable();

            // พาธเก็บไฟล์รูปภาพโปรไฟล์ (Original และ Thumbnail)
            $table->string('profile_photo_path', 2048)->nullable();
            $table->string('profile_photo_thumb', 2048)->nullable();

            // ไฟล์เสียงแจ้งเตือนที่ผู้ใช้เลือกใช้งาน
            $table->string('notification_sound')->default('new-request.mp3');

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
