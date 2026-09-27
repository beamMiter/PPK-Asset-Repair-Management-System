<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            // รหัสครุภัณฑ์ภายในระบบ
            $table->string('asset_code', 100)->unique();

            // เลข รพจ. ที่อ้างอิงจากระบบ HIS (Hospital Information System)
            $table->string('his_asset_id', 100)->nullable()->unique();

            // ชื่อเรียกครุภัณฑ์
            $table->string('name');

            // ประเภทของครุภัณฑ์
            $table->string('type', 100)->nullable();

            // ยี่ห้อของครุภัณฑ์
            $table->string('brand', 100)->nullable();

            // รุ่นของครุภัณฑ์
            $table->string('model', 100)->nullable();

            // หมายเลขซีเรียล (Serial Number) ประจำเครื่อง
            $table->string('serial_number', 100)->nullable()->unique();

            // สถานที่ตั้งของครุภัณฑ์
            $table->string('location')->nullable();

            // เบอร์โทรศัพท์ภายใน หรือเบอร์ที่ระบุบนป้ายครุภัณฑ์
            $table->string('internal_phone', 50)->nullable();

            // ชื่อบริษัทผู้ขาย หรือตัวแทนจำหน่าย
            $table->string('vendor_name', 255)->nullable();

            // เบอร์โทรศัพท์ติดต่อบริษัทผู้ขาย
            $table->string('vendor_phone', 50)->nullable();

            // ราคาจัดซื้อ (บาท)
            $table->decimal('price', 15, 2)->nullable();

            // แผนกที่ครอบครองครุภัณฑ์ (เชื่อมโยงกับตาราง departments)
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // หมวดหมู่ของครุภัณฑ์ (เชื่อมโยงกับตาราง asset_categories)
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('asset_categories')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // วันที่จัดซื้อ
            $table->date('purchase_date')->nullable();

            // วันที่เริ่มการรับประกัน
            $table->date('warranty_start')->nullable();

            // วันที่หมดอายุการรับประกัน
            $table->date('warranty_expire')->nullable();

            // สถานะของครุภัณฑ์ (active = ใช้งานปกติ, in_repair = อยู่ระหว่างซ่อม, disposed = จำหน่ายออก)
            $table->enum('status', ['active','in_repair','disposed'])->default('active');

            $table->timestamps();

            // เวลาที่ทำการซิงค์ข้อมูลกับระบบ HIS ล่าสุด
            $table->timestamp('his_synced_at')->nullable();

            // ข้อมูลดิบที่ได้รับจากระบบ HIS (ในรูปแบบ JSON)
            $table->json('his_raw')->nullable();

            // การลบข้อมูลแบบ Soft Delete (ไม่ลบทิ้งจริงเพื่อให้คงความสัมพันธ์ในประวัติการซ่อม)
            $table->softDeletes(); 

            $table->index(['type', 'location', 'department_id', 'category_id']);
            $table->index(['status', 'department_id'], 'assets_status_department_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
