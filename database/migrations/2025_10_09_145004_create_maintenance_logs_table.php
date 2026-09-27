<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();

            // หมายเลขใบงานที่เกี่ยวข้อง
            $table->foreignId('request_id')
                ->constrained('maintenance_requests')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // ผู้ที่กระทำการ (Action) นั้น ๆ
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // ชื่อเรียกเหตุการณ์หรือการกระทำ (เช่น status_change, technician_assigned, note_added)
            $table->string('action', 100);

            // บันทึกข้อความหรือรายละเอียดของการกระทำนั้น
            $table->text('note')->nullable();

            // สถานะตั้งต้นก่อนการเปลี่ยนแปลง
            $table->string('from_status', 50)->nullable();

            // สถานะเป้าหมายหลังการเปลี่ยนแปลง
            $table->string('to_status', 50)->nullable();

            $table->timestamps();

            // ===== Index ให้ตรง schema dump =====
            $table->index(['request_id', 'created_at']); // maintenance_logs_request_id_created_at_index
            $table->index('action');                     // maintenance_logs_action_index
            $table->index('user_id');                    // maintenance_logs_user_id_index
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_logs');
    }
};
