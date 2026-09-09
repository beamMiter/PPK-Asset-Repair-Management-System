<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            // เชื่อมโยงไปยังไฟล์จริงในตาราง files
            $table->foreignId('file_id')
                  ->constrained('files')
                  ->cascadeOnDelete();

            // ความสัมพันธ์แบบ Polymorphic (เป้าหมายที่ไฟล์นี้ไปแนบอยู่ เช่น MaintenanceRequest, User)
            $table->morphs('attachable');

            // ชื่อไฟล์ดั้งเดิมตอนที่ผู้ใช้อัปโหลด
            $table->string('original_name', 255);

            // นามสกุลของไฟล์ (เช่น jpg, pdf, docx)
            $table->string('extension', 16)->nullable();

            // คำอธิบายรูปภาพหรือไฟล์เบื้องต้น
            $table->string('caption', 512)->nullable();

            // ข้อความ Alternative Text สำหรับคนพิการหรือกรณีรูปไม่โหลด
            $table->string('alt_text', 512)->nullable();

            // ลำดับการแสดงผลของไฟล์ (กรณีมีหลายไฟล์แนบในเป้าหมายเดียวกัน)
            // signed: hero image ใช้ค่า sentinel Attachment::HERO_ORDER (-1) เพื่อให้ sort มาก่อน gallery
            $table->integer('order_column')->default(0);

            // กำหนดความละเอียดอ่อนของไฟล์ (true = เฉพาะผู้เกี่ยวข้องเห็น, false = สาธารณะ)
            $table->boolean('is_private')->default(false);

            // ผู้ใช้งานที่เป็นคนอัปโหลดไฟล์นี้
            $table->foreignId('uploaded_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            // แหล่งที่มาของไฟล์ (เช่น web = อัปโหลดผ่านเว็บ, api = ผ่านแอป)
            $table->string('source', 32)->default('web'); 

            // วันที่และเวลาที่ไฟล์นี้จะหมดอายุ (ถ้ามีการกำหนดนโยบายการจัดเก็บ)
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes ที่ใช้จริง
            $table->index(['attachable_type', 'attachable_id', 'created_at'], 'attachments_target_created_idx');
            $table->index(['is_private'], 'attachments_is_private_idx');
            $table->index(['uploaded_by'], 'attachments_uploaded_by_idx');

            // กันแนบไฟล์เดียวกันซ้ำกับ target เดิม (ถ้าไม่ต้องการ uniqueness ให้คอมเมนต์ออก)
            $table->unique(['attachable_type', 'attachable_id', 'file_id'], 'attachments_unique_per_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
