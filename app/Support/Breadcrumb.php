<?php

namespace App\Support;


class Breadcrumb
{
    public static function generate(): array
    {
        $segments = request()->segments();
        $url = '';
        $breadcrumbs = [];

        // หน้าแรก Dashboard
        $breadcrumbs[] = self::make('Dashboard', url('/dashboard'));

        foreach ($segments as $key => $segment) {
            // เก็บ URL จริงไว้ก่อน (เอาไว้ทำ Link)
            $url .= '/' . $segment;

            // ---------------------------------------------------
            // 1. จัดการ Path ที่เป็นแค่ "โฟลเดอร์" (ไม่มีหน้าจริง)
            // ---------------------------------------------------

            // ข้าม 'maintenance'
            if ($segment === 'maintenance') continue;

            // ข้าม 'repair'
            if ($segment === 'repair') continue;

            // ข้าม 'admin'
            if ($segment === 'admin') continue;

            // ข้าม 'rating' เฉพาะเมื่อมันเป็นทางผ่านไปหา evaluate หรือ technicians
            // (เช็คว่าตัวถัดไปมีไหม และตัวถัดไปคือ evaluate/technicians หรือไม่)
            if ($segment === 'rating' && isset($segments[$key + 1])) {
                $next = $segments[$key + 1];
                if (in_array($next, ['evaluate', 'technicians'])) {
                    continue;
                }
            }

            // ข้าม 'dashboard' (เพราะเรามีปุ่ม Dashboard อันแรกสุดแล้ว)
            if ($segment === 'dashboard') continue;


            // ---------------------------------------------------
            // 2. ตั้งชื่อ Label ให้ถูกต้อง
            // ---------------------------------------------------

            if (is_numeric($segment)) {
                $label = '#' . $segment;

                // NEW: If we are on a maintenance request route, try to use formal request_no
                $route = request()->route();
                if ($route) {
                    // MaintenanceRequest can be bound to 'req' or 'maintenanceRequest' parameters
                    $reqObj = $route->parameter('req') ?? $route->parameter('maintenanceRequest');

                    // If the segment matches the ID of the bound request, use its formal number
                    if ($reqObj instanceof \App\Models\MaintenanceRequest && (string) $reqObj->id === (string) $segment) {
                        $label = '#' . ($reqObj->request_no ?? $segment);
                    }
                }
            } else {
                // ถ้าไม่ใช่ตัวเลข ให้แปลงชื่อตามกฎที่เราตั้ง
                $label = self::getLabel($segment);
            }

            $isLast = ($key === count($segments) - 1);

            $breadcrumbs[] = self::make(
                $label,
                $isLast ? null : url($url),
                $isLast
            );
        }

        return $breadcrumbs;
    }

    public static function make(string $label, ?string $url = null, bool $active = false): array
    {
        return compact('label', 'url', 'active');
    }

    /**
     * แปลงชื่อ URL เป็นชื่อที่ต้องการแสดงผล
     */
    private static function getLabel(string $segment): string
    {
        $map = [
            // แปลง requests -> Maintenance Requests (เพราะเราข้ามคำว่า maintenance มาแล้ว)
            'requests' => 'Maintenance Requests',

            // แปลง my-jobs -> Repair Jobs
            'my-jobs'  => 'Repair Jobs',

            // แปลง evaluate -> Evaluate Ratings (เพราะเราข้าม rating มา)
            'evaluate' => 'Evaluate Ratings',

            // อื่นๆ
            'users'       => 'User Management',
            'create'      => 'Create',
            'edit'        => 'Edit',
            'technicians' => 'Technician Dashboard',
        ];

        // ถ้าไม่มีใน Map ให้เอาขีดออกแล้วทำตัวใหญ่ตามปกติ
        return $map[$segment] ?? ucwords(str_replace(['-', '_'], ' ', $segment));
    }
}
