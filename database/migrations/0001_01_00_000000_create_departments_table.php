<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            // รหัสย่อของหน่วยงาน (Unique) เช่น IT, HR, MED เพื่อใช้ในการอ้างอิงภายใน
            $table->string('code', 20)->unique();

            // ชื่อหน่วยงานเต็มรูปแบบในภาษาไทย
            $table->string('name_th');

            // ชื่อหน่วยงานในภาษาอังกฤษ (ถ้ามี)
            $table->string('name_en')->nullable();

            $table->timestamps();

            $table->index(['name_th', 'name_en']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('departments');
    }
};
