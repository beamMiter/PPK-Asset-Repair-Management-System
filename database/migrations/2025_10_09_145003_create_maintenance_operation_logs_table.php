<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_operation_logs', function (Blueprint $table) {
            $table->id();

            // เชื่อมโยงกับใบงานแจ้งซ่อม (1 ใบงานต่อ 1 รายงานการปฏิบัติงาน)
            $table->foreignId('maintenance_request_id')
                ->constrained('maintenance_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // ผู้ที่บันทึกรายงานการปฏิบัติงาน (ปกติคือช่าง)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // วันที่ทำการปฏิบัติงานจริง
            $table->date('operation_date')->nullable();

            // วิธีการดำเนินการ (requisition = เบิกอะไหล่, service_fee = ส่งซ่อมภายนอก/เสียค่าบริการ, other = อื่น ๆ)
            $table->enum('operation_method', ['requisition', 'service_fee', 'other'])
                ->nullable();

            // หมายเลขครุภัณฑ์ (รพจ.) สำหรับตรวจสอบความถูกต้องหน้างาน
            $table->string('property_code', 100)->nullable();

            // บังคับให้มีการขออนุญาต/ตรวจสอบก่อนปฏิบัติงาน (เช่น การปิดเครื่องมือแพทย์)
            $table->boolean('require_precheck')->default(false);

            // รายละเอียดหรือหมายเหตุเพิ่มเติมในการปฏิบัติงาน
            $table->text('remark')->nullable();

            // เป็นปัญหาที่เกี่ยวข้องกับ Software หรือไม่
            $table->boolean('issue_software')->default(false);

            // เป็นปัญหาที่เกี่ยวข้องกับ Hardware หรือไม่
            $table->boolean('issue_hardware')->default(false);

            $table->timestamps();

            // Index/Unique ให้ตรง schema dump
            $table->unique('maintenance_request_id', 'uniq_operation_log_request');

            $table->index('user_id');               // maintenance_operation_logs_user_id_index
            $table->index('operation_date');        // maintenance_operation_logs_operation_date_index
            $table->index('property_code');         // maintenance_operation_logs_property_code_index
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_operation_logs');
    }
};
