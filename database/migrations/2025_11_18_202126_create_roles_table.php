<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ทำการสร้างตาราง roles
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            // รหัสอ้างอิง Role (admin, technician, member)
            $table->string('code', 50)->unique();

            // ชื่อ Role สำหรับแสดงผลภาษาไทย
            $table->string('name_th', 100);

            // ชื่อ Role สำหรับแสดงผลภาษาอังกฤษ (ถ้ามี)
            $table->string('name_en', 100)->nullable();

            // ลำดับในการแสดงผลใน List หรือ Select
            $table->unsignedInteger('sort_order')->default(0);

            // สถานะการเปิด/ปิดใช้งาน Role นี้ในระบบ
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    // ยกเลิกการสร้างตาราง roles
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
