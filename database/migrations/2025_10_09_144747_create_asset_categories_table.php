<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();

            // ชื่อหมวดหมู่ครุภัณฑ์ (เช่น คอมพิวเตอร์, ยานพาหนะ, เครื่องมือแพทย์)
            $table->string('name')->unique();

            // ชื่ออ้างอิงสั้นๆ หรือ URL Slug สำหรับหมวดหมู่
            $table->string('slug')->unique();

            // รหัสสีประจำหมวดหมู่ (HEX Code) สำหรับใช้แสดงผลใน UI/Charts
            $table->string('color', 20)->nullable();

            // คำอธิบายรายละเอียดเพิ่มเติมเกี่ยวกับหมวดหมู่นี้
            $table->text('description')->nullable();

            // สถานะการเปิดใช้งานหมวดหมู่ (true = ใช้งานปกติ, false = ปิดใช้งาน)
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_categories');
    }
};
