<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();

            // หมายเลขใบงาน (เช่น REPAIR-202310-0001)
            $table->string('request_no', 32)->nullable()->unique();

            // ครุภัณฑ์ที่เกี่ยวข้อง (เชื่อมโยงกับตาราง assets)
            $table->foreignId('asset_id')
                ->nullable()
                ->constrained('assets')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // ข้อมูลผู้แจ้งซ่อม (เชื่อมโยงกับตาราง users)
            $table->foreignId('reporter_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // ชื่อ-นามสกุล ของผู้แจ้งซ่อม (บันทึกเผื่อไว้กรณีข้อมูลผู้ใช้มีการเปลี่ยนแปลง)
            $table->string('reporter_name', 255)->nullable();

            // เบอร์โทรศัพท์สำหรับติดต่อผู้แจ้ง
            $table->string('reporter_phone', 30)->nullable();

            // อีเมลสำหรับติดต่อผู้แจ้ง
            $table->string('reporter_email', 255)->nullable();

            // แผนกที่เกิดปัญหา (เชื่อมโยงกับตาราง departments)
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // ประเภทของงานที่แจ้ง (เชื่อมโยงกับตาราง maintenance_request_types)
            $table->foreignId('type_id')
                ->nullable()
                ->constrained('maintenance_request_types')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // รายละเอียดสถานที่เกิดเหตุ (กรณีไม่ได้ระบุในครุภัณฑ์ หรือต้องการระบุเพิ่ม)
            $table->string('location_text', 255)->nullable();

            // หัวข้อปัญหาหรืออาการเสียเบื้องต้น
            $table->string('title', 255);

            // รายละเอียดปัญหาอย่างละเอียด
            $table->text('description')->nullable();

            // สถานะปัจจุบันของใบงาน (เช่น pending, acknowledged, accepted, in_progress, resolved, closed, rejected)
            $table->string('status', 32)->default('pending');

            // วันที่และเวลาที่มีการเปลี่ยนสถานะล่าสุด
            $table->timestamp('status_updated_at')->nullable();

            // ผู้ใช้งานที่เป็นคนอัปเดตสถานะล่าสุด
            $table->foreignId('status_updated_by')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // ช่างผู้รับผิดชอบหลัก (เชื่อมโยงกับตาราง users)
            $table->foreignId('technician_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // วันที่และเวลาที่บันทึกการแจ้งซ่อมเข้าสู่ระบบ
            $table->timestamp('request_date')->useCurrent();

            // วันที่และเวลาที่มอบหมายงานให้ช่าง
            $table->timestamp('assigned_date')->nullable();

            // (Legacy) วันที่ทำงานเสร็จ
            $table->timestamp('completed_date')->nullable(); 

            // วันที่และเวลาที่ช่างรับทราบงาน (Acknowledged)
            $table->timestamp('acknowledged_at')->nullable();

            // วันที่และเวลาที่ช่างตอบรับเข้าทำงาน (Accepted)
            $table->timestamp('accepted_at')->nullable();

            // วันที่และเวลาที่เริ่มลงมือปฏิบัติงาน (Started)
            $table->timestamp('started_at')->nullable();

            // วันที่และเวลาที่พักงานชั่วคราว (On Hold)
            $table->timestamp('on_hold_at')->nullable();

            // วันที่และเวลาที่แก้ไขปัญหาได้สำเร็จ (Resolved)
            $table->timestamp('resolved_at')->nullable();

            // วันที่และเวลาที่ปิดงานอย่างเป็นทางการ (Closed)
            $table->timestamp('closed_at')->nullable();
            
            // กำหนดเวลาที่ต้องตอบรับงาน (SLA Response Due)
            $table->timestamp('response_due_date')->nullable();

            // กำหนดเวลาที่ต้องแก้ไขงานให้เสร็จ (SLA Resolution Due)
            $table->timestamp('sla_due_date')->nullable();

            // จำนวนเวลาที่งานถูกพักไว้ (นาที) เพื่อนำมาหักลบในการคำนวณ SLA
            $table->unsignedInteger('paused_duration_minutes')->default(0);

            // หมายเหตุเพิ่มเติมจากการซ่อม
            $table->text('remark')->nullable();

            // บันทึกสรุปการแก้ไขปัญหา
            $table->text('resolution_note')->nullable();

            // ค่าใช้จ่ายที่เกิดขึ้นจากการซ่อม (บาท)
            $table->decimal('cost', 10, 2)->nullable();

            // ช่องทางการรับข้อมูล (เช่น web, mobile, manual)
            $table->string('source', 32)->default('web');

            // ข้อมูลเพิ่มเติมอื่น ๆ ในรูปแบบ JSON
            $table->json('extra')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // indexs
            $table->index(['asset_id', 'request_date']);
            $table->index(['status']);
            $table->index(['technician_id', 'status']);
            $table->index(['resolved_at', 'closed_at']);
            $table->index(['department_id', 'status']);
            $table->index(['type_id', 'status']);

            // index audit
            $table->index(['status_updated_at']);
            $table->index(['status_updated_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
