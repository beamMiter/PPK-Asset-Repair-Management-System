<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_request_types', function (Blueprint $table) {
            $table->id();

            // ชื่อประเภทการแจ้งซ่อม (เช่น ซ่อมคอมพิวเตอร์, ซ่อมบำรุงอาคาร, งานระบบเครือข่าย)
            $table->string('name', 150)->unique();

            // รายละเอียดเพิ่มเติมเกี่ยวกับประเภทงานนี้
            $table->text('description')->nullable();

            // หน่วยงาน/แผนกเริ่มต้นที่จะรับผิดชอบงานประเภทนี้ (เก็บเป็น code)
            $table->string('default_department_code', 100)->nullable()->index();

            // บทบาทเริ่มต้นที่จะรับผิดชอบงานประเภทนี้ (เก็บเป็น code เช่น technician)
            $table->string('default_role_code', 50)->nullable()->index();

            // ผู้ใช้งานเริ่มต้นที่จะรับผิดชอบงานประเภทนี้ (กรณีระบุตัวบุคคล)
            $table->foreignId('default_user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // สถานะการเปิดใช้งานประเภทงานนี้
            $table->boolean('is_active')->default(true)->index();

            // ลำดับการแสดงผลในหน้าจอเลือกประเภทงาน
            $table->unsignedInteger('sort_order')->default(0)->index();

            // เวลาเป้าหมายในการตอบรับงาน (นาที) สำหรับคำนวณ SLA
            $table->unsignedInteger('default_response_minutes')->nullable();

            // เวลาเป้าหมายในการแก้ไขงานให้เสร็จสิ้น (นาที) สำหรับคำนวณ SLA
            $table->unsignedInteger('default_resolution_minutes')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_types');
    }
};
