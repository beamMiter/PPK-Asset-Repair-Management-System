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
        Schema::create('cache', function (Blueprint $table) {
            // คีย์สำหรับเก็บข้อมูล Cache
            $table->string('key')->primary();

            // ค่าข้อมูลที่เก็บไว้ใน Cache
            $table->mediumText('value');

            // เวลาหมดอายุ (Timestamp)
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            // คีย์สำหรับการล็อค (Atomic Lock)
            $table->string('key')->primary();

            // ผู้ที่เป็นเจ้าของ Lock
            $table->string('owner');

            // เวลาหมดอายุการล็อค
            $table->integer('expiration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
