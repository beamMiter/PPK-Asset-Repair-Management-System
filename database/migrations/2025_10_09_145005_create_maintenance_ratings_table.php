<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_ratings', function (Blueprint $table) {
            $table->id();

            // หมายเลขใบงานที่ถูกประเมินความพึงพอใจ
            $table->foreignId('maintenance_request_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // ผู้ที่ทำการประเมิน (ปกติคือผู้แจ้งซ่อม)
            $table->foreignId('rater_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // ช่างที่ได้รับคะแนนการประเมิน (เพื่อใช้ในการทำสถิติช่าง)
            $table->foreignId('technician_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // คะแนนความพึงพอใจ (1-5 ดาว)
            $table->enum('score', [1, 2, 3, 4, 5]);

            // ข้อคิดเห็นเพิ่มเติมจากการประเมิน
            $table->text('comment')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['maintenance_request_id', 'rater_id']);

            $table->index('rater_id');
            $table->index('maintenance_request_id');
            $table->index(['technician_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_ratings');
    }
};
