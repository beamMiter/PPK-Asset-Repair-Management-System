<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_assignments', function (Blueprint $table) {
            $table->id();

            // หมายเลขใบงานที่มอบหมาย
            $table->foreignId('maintenance_request_id')
                ->constrained('maintenance_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // ช่างหรือผู้ปฏิบัติงานที่ได้รับมอบหมาย
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // บทบาทของช่างในงานนี้ (เช่น tech = ช่างเทคนิค, helper = ผู้ช่วย)
            $table->string('role', 50)->nullable();

            // ระบุว่าเป็นช่างหลัก (Lead Technician) หรือไม่
            $table->boolean('is_lead')->default(false);

            // วันที่และเวลาที่ได้รับการมอบหมายงาน
            $table->dateTime('assigned_at')->nullable();

            // สถานะการตอบรับงานของช่าง (pending = รอการตอบรับ, acknowledged = รับทราบแล้ว, accepted = ตอบรับงาน, rejected = ปฏิเสธงาน)
            $table->enum('response_status', [
                'pending',
                'acknowledged',
                'accepted',
                'rejected',
            ])->default('pending');

            // วันที่และเวลาที่ทำการตอบรับหรือปฏิเสธงาน
            $table->dateTime('responded_at')->nullable();

            // หมายเหตุหรือเหตุผลประกอบการตอบรับ (โดยเฉพาะกรณีปฏิเสธงาน)
            $table->string('remark', 2000)->nullable();

            // สถานะความคืบหน้าของช่างแต่ละคน (assigned = มอบหมายแล้ว, in_progress = กำลังซ่อม, done = งานในส่วนที่รับผิดชอบเสร็จแล้ว, cancelled = ยกเลิกงาน)
            $table->enum('status', [
                'assigned',
                'in_progress',
                'done',
                'cancelled',
            ])->default('assigned');

            $table->timestamps();

            // หนึ่งช่าง ต่อหนึ่งใบงาน
            $table->unique(
                ['maintenance_request_id', 'user_id'],
                'ma_req_user_uniq'
            );

            // index สำหรับงานของช่าง
            $table->index(
                ['user_id', 'status'],
                'ma_user_status_idx'
            );

            $table->index(
                ['maintenance_request_id', 'status'],
                'ma_req_status_idx'
            );

            // index สำหรับ MyJob (response)
            $table->index(
                ['user_id', 'response_status'],
                'ma_user_resp_idx'
            );

            $table->index(
                ['maintenance_request_id', 'response_status'],
                'ma_req_resp_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_assignments');
    }
};
