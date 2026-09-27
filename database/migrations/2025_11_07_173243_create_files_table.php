<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('files', function (Blueprint $t) {
            $t->id();

            // พาธที่เก็บไฟล์จริงในระบบ Storage (เช่น images/avatar.png)
            $t->string('path', 2048);

            // ชื่อ Disk ที่ใช้เก็บไฟล์ (ค่าเริ่มต้นคือ public)
            $t->string('disk', 50)->default('public');

            // ประเภทของไฟล์ (MIME Type เช่น image/jpeg, application/pdf)
            $t->string('mime', 100)->nullable();

            // ขนาดของไฟล์ในหน่วยไบต์ (Bytes)
            $t->unsignedBigInteger('size')->nullable();

            // ค่า Checksum SHA-256 ของไฟล์ (เพื่อตรวจสอบความถูกต้องและป้องกันการเก็บไฟล์ซ้ำ)
            $t->string('checksum_sha256', 64)->nullable()->unique();

            // ID ของไฟล์ที่เป็นต้นฉบับ (ใช้ในกรณีที่เป็นไฟล์ที่ถูกย่อขนาด หรือเป็น Variant ของไฟล์อื่น)
            $t->foreignId('variant_of_id')->nullable()->constrained('files')->nullOnDelete();

            // ข้อมูล Metadata เพิ่มเติมในรูปแบบ JSON (เช่น กว้าง/สูง ของรูปภาพ)
            $t->json('meta')->nullable();

            $t->timestamps();
            $t->softDeletes();

            // ค่า Hash ของ Path เพื่อใช้ในการค้นหาและทำ Index ที่รวดเร็วขึ้น
            $t->string('path_hash', 64)->nullable();

            $t->index(['mime']);
            $t->index(['disk', 'path_hash'], 'files_disk_path_hash_idx');
            $t->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
