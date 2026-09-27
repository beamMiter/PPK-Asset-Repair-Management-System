<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Asset;
use App\Models\Department;
use App\Models\AssetCategory; 

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    // กำหนดค่าเริ่มต้นให้กับ Model Asset
    public function definition(): array
    {
        // รายการหมวดหมู่ย่อยและยี่ห้อสินค้าสำหรับสุ่มข้อมูล
        $cats   = ['คอมพิวเตอร์', 'เครื่องพิมพ์', 'เครือข่าย', 'ยานพาหนะ', 'เครื่องมือ', 'เฟอร์นิเจอร์'];
        $brands = ['HP','Dell','Lenovo','Acer','Canon','Brother','Cisco','MikroTik','Yamaha','3M'];
        $locs   = ['สำนักงานใหญ่', 'อาคาร A', 'อาคาร B', 'คลังสินค้า', 'สาขาเชียงใหม่', 'สาขาภูเก็ต'];

        // จับคู่หมวดหมู่ (Category) กับประเภทหลัก (Type)
        $typeMap = [
            'คอมพิวเตอร์'  => 'IT',
            'เครื่องพิมพ์' => 'IT',
            'เครือข่าย'    => 'IT',
            'ยานพาหนะ'     => 'Vehicle',
            'เครื่องมือ'    => 'Tool',
            'เฟอร์นิเจอร์'  => 'Office',
        ];

        $categoryName = fake()->randomElement($cats);
        $type         = $typeMap[$categoryName] ?? fake()->randomElement(['IT','Electrical','Office','Tool','Vehicle']);

        // ค้นหา ID ของหมวดหมู่จากตาราง asset_categories (ถ้าไม่พบจะสุ่มจากที่มีอยู่)
        $categoryId = AssetCategory::where('name', $categoryName)->inRandomOrder()->value('id')
            ?? AssetCategory::inRandomOrder()->value('id');

        return [
            'asset_code'      => 'ASSET-'.fake()->unique()->numerify('#####'),
            'name'            => fake()->words(2, true),
            'type'            => $type,
            'category_id'     => $categoryId,
            'brand'           => fake()->randomElement($brands),
            'model'           => strtoupper(fake()->bothify('??-###')),
            'serial_number'   => strtoupper(fake()->unique()->bothify('SN########')),
            'location'        => fake()->randomElement($locs),
            'purchase_date'   => $purchaseDate = fake()->dateTimeBetween('-5 years', '-6 months')->format('Y-m-d'),
            // warranty_start >= purchase_date, warranty_expire = warranty_start + 1-3 ปี
            'warranty_start'  => $wStart = fake()->dateTimeBetween($purchaseDate, 'now')->format('Y-m-d'),
            'warranty_expire' => (new \DateTime($wStart))->modify('+'.rand(1,3).' years')->format('Y-m-d'),
            'vendor_name'     => fake()->company(),
            'internal_phone'  => '02-' . fake()->numerify('###-####'),
            'vendor_phone'    => '08' . fake()->numberBetween(1, 9) . '-' . fake()->numerify('###-####'),
            'price'           => fake()->numberBetween(1000, 500000),
            'status'          => fake()->randomElement(['active','in_repair','disposed']),
            'department_id'   => Department::inRandomOrder()->value('id') ?? null,
            'his_asset_id'    => fake()->boolean(60) ? 'HIS-'.fake()->unique()->numerify('######') : null,
            'his_synced_at'   => fake()->boolean(60) ? now() : null,
        ];
    }
}
