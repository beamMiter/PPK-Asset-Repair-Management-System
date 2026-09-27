<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();

            // ชื่อคิว (Queue Name)
            $table->string('queue')->index();

            // ข้อมูลงานที่ต้องประมวลผล (Serialized Job)
            $table->longText('payload');

            // จำนวนครั้งที่พยายามทำงานนี้แล้ว
            $table->unsignedTinyInteger('attempts');

            // เวลาที่งานถูกจองเพื่อประมวลผล
            $table->unsignedInteger('reserved_at')->nullable();

            // เวลาที่งานสามารถเริ่มประมวลผลได้
            $table->unsignedInteger('available_at');

            // เวลาที่งานถูกสร้างขึ้น
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();

            // ID เฉพาะของงานที่ล้มเหลว
            $table->string('uuid')->unique();

            // ชื่อการเชื่อมต่อ (Connection)
            $table->text('connection');

            // ชื่อคิว
            $table->text('queue');

            // ข้อมูลงานที่ล้มเหลว
            $table->longText('payload');

            // รายละเอียดความผิดพลาด (Exception)
            $table->longText('exception');

            // วันที่และเวลาที่งานล้มเหลว
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
