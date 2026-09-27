<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\MaintenanceRequestType;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The lookup data the application cannot work without: roles, departments, asset categories and request types.
 * Safe to run anywhere (every row is matched on its natural key), which is why production gets only this seeder.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->roles();
        $this->departments();
        $this->assetCategories();
        $this->requestTypes();
    }

    private function roles(): void
    {
        $roles = [
            ['admin',      'ผู้ดูแลระบบ',          'Administrator',    10],
            ['supervisor', 'หัวหน้าหน่วยงาน',      'Supervisor',       20],
            ['it_support', 'เจ้าหน้าที่ Hardware', 'Hardware Support', 30],
            ['network',    'เจ้าหน้าที่ Network',  'Network Admin',    40],
            ['programmer', 'นักพัฒนา',             'Developer',        50],
            ['technician', 'เจ้าหน้าที่ซ่อมบำรุง', 'Technician',       60],
            ['member',     'บุคลากรทั่วไป',       'Member',           80],
        ];

        foreach ($roles as [$code, $th, $en, $order]) {
            Role::updateOrCreate(['code' => $code], ['name_th' => $th, 'name_en' => $en, 'sort_order' => $order, 'is_active' => true]);
        }
    }

    /** The departments the demo users and assets belong to (a user's `department` is one of these codes). */
    private function departments(): void
    {
        $departments = [
            ['IT',    'กลุ่มงานเทคโนโลยีสารสนเทศ', 'Information Technology'],
            ['FAC',   'ฝ่ายซ่อมบำรุง',            'Facilities & Maintenance'],
            ['BME',   'วิศวกรรมชีวการแพทย์',      'Biomedical Engineering'],
            ['OPD',   'ผู้ป่วยนอก',               'Out-Patient Department'],
            ['IPD',   'หอผู้ป่วยใน',              'In-Patient Department'],
            ['ER',    'เวชศาสตร์ฉุกเฉิน',         'Emergency Medicine'],
            ['LAB',   'ห้องปฏิบัติการ',           'Laboratory & Pathology'],
            ['PHARM', 'เภสัชกรรม',                'Pharmacy'],
            ['RAD',   'รังสีวิทยา',               'Radiology'],
            ['ADM',   'ฝ่ายบริหารทั่วไป',         'General Administration'],
            ['FIN',   'ฝ่ายการเงินและบัญชี',      'Finance & Accounting'],
            ['HR',    'ฝ่ายทรัพยากรบุคคล',        'Human Resources'],
        ];

        foreach ($departments as [$code, $th, $en]) {
            Department::updateOrCreate(['code' => $code], ['name_th' => $th, 'name_en' => $en]);
        }
    }

    private function assetCategories(): void
    {
        $categories = [
            ['คอมพิวเตอร์',        'computer',   '#2563eb', 'เครื่องคอมพิวเตอร์ ตั้งโต๊ะ / โน้ตบุ๊ก / เซิร์ฟเวอร์'],
            ['เครื่องพิมพ์',       'printer',    '#7c3aed', 'เครื่องพิมพ์ เครื่องสแกน เครื่องพิมพ์ฉลาก'],
            ['เครือข่าย',          'network',    '#0891b2', 'สวิตช์ Access Point และอุปกรณ์เครือข่าย'],
            ['เครื่องมือแพทย์',    'medical',    '#dc2626', 'เครื่องมือแพทย์และเครื่องวิเคราะห์'],
            ['เครื่องปรับอากาศ',   'aircon',     '#0ea5e9', 'เครื่องปรับอากาศและระบบระบายอากาศ'],
            ['ระบบไฟฟ้า',          'electrical', '#f59e0b', 'เครื่องสำรองไฟ เครื่องกำเนิดไฟฟ้า และอุปกรณ์ไฟฟ้า'],
            ['เฟอร์นิเจอร์',       'furniture',  '#78716c', 'เตียง โต๊ะ เก้าอี้ และครุภัณฑ์สำนักงาน'],
            ['ยานพาหนะ',           'vehicle',    '#16a34a', 'รถพยาบาลและยานพาหนะของหน่วยงาน'],
        ];

        foreach ($categories as [$name, $slug, $color, $description]) {
            AssetCategory::updateOrCreate(['slug' => $slug], ['name' => $name, 'color' => $color, 'description' => $description, 'is_active' => true]);
        }
    }

    /**
     * `default_role_code` must be a real role: it decides who the assign-team picker suggests. (Hardware used to say
     * "support", which no user has.) The response / resolution minutes become each request's SLA due dates.
     */
    private function requestTypes(): void
    {
        $types = [
            ['Software',   'ปัญหาโปรแกรม ระบบ HIS การเข้าใช้งาน แก้ไขข้อมูล',   'IT',  'programmer', 60,  480,  1, true],
            ['Network',    'อินเทอร์เน็ต LAN WiFi สายสัญญาณ',                    'IT',  'network',    30,  240,  2, true],
            ['Hardware',   'คอมพิวเตอร์ เครื่องพิมพ์ จอภาพ อุปกรณ์ต่อพ่วง',        'IT',  'it_support', 120, 1440, 3, true],
            ['Facilities', 'อาคาร ไฟฟ้า ประปา เครื่องปรับอากาศ เครื่องมือแพทย์',  'FAC', 'technician', 60,  720,  4, true],
            ['ระบบเดิม',   'ประเภทของระบบเก่า ปิดใช้งานแล้ว เหลือไว้ให้ประวัติเดิม', 'IT',  'it_support', null, null, 9, false],
        ];

        foreach ($types as [$name, $description, $dept, $role, $response, $resolution, $order, $active]) {
            MaintenanceRequestType::updateOrCreate(['name' => $name], [
                'description' => $description,
                'default_department_code' => $dept,
                'default_role_code' => $role,
                'default_response_minutes' => $response,
                'default_resolution_minutes' => $resolution,
                'sort_order' => $order,
                'is_active' => $active,
            ]);
        }
    }
}
