<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Facades\Log;

class HisAssetSyncService
{
    /**
     * Map HIS payload to our internal Asset format.
     *
     * TODO: รอ HIS API จริง — map field ตรงนี้ที่เดียว
     * เมื่อ HIS API จริงพร้อม แก้ key mapping ใน method นี้จุดเดียวจบ
     */
    private function mapHisPayload(array $hisData): array
    {
        return [
            'his_asset_id'   => $hisData['asset_no']       ?? null,
            // assets.asset_code is NOT NULL + unique; on a first-time sync there
            // is no internal code yet, so seed it from the HIS number (same
            // convention as the fetch-his autofill). A later manual edit can
            // replace it with the real inventory code.
            'asset_code'     => $hisData['asset_no']       ?? null,
            'name'           => $hisData['name']            ?? null,
            'brand'          => $hisData['brand']           ?? null,
            'model'          => $hisData['model']           ?? null,
            'serial_number'  => $hisData['serial']          ?? null,
            'vendor_name'    => $hisData['vendor_name']     ?? null,
            'vendor_phone'   => $hisData['vendor_phone']    ?? null,
            'internal_phone' => $hisData['internal_phone']  ?? null,
            'price'          => $hisData['price']           ?? null,
            'warranty_start' => $hisData['warranty_start']  ?? null,
            'warranty_expire'=> $hisData['warranty_expire'] ?? null,
            // his_raw & his_synced_at มี column อยู่แล้วใน create_assets_table (line 39-40)
            'his_raw'        => $hisData,
            'his_synced_at'  => now(),
        ];
    }

    /**
     * Return mock HIS data for the given HIS ID.
     * ใช้ระหว่างรอ HIS API จริง — ลบ method นี้และเปลี่ยนไปใช้ HTTP client จริงในอนาคต
     *
     * @param  string $hisId  เลข รพจ ที่ต้องการดึงข้อมูล
     * @return array|null     null = ไม่พบข้อมูล
     */
    public function getMockHisData(string $hisId): ?array
    {
        $cleanId = trim($hisId);
        if (empty($cleanId)) {
            return null;
        }

        // --- เพิ่ม Seed เพื่อให้ข้อมูลที่ดึงมา "คงที่" สำหรับรหัสเดิม (Deterministic Mock) ---
        // ใช้ crc32 แปลงรหัสเป็นตัวเลขเพื่อเซ็ต seed ให้ชุดข้อมูลจำลองไม่เปลี่ยนทุกรอบที่กด
        $seed = abs(crc32($cleanId));
        mt_srand($seed);

        $start = now()->setYear(2024)->setMonth(1)->setDay(1)->addDays($seed % 365);

        $brands = ['Mindray','Philips','GE','Siemens','Toshiba'];
        $vendors = [
            'บริษัท เมดิคอล ซัพพลาย จำกัด',
            'บริษัท ไทย เมดิคอล อีควิปเมนท์ จำกัด',
            'บริษัท ฟาร์มาแล็บ จำกัด',
            'ห้างหุ้นส่วนจำกัด สยามเมดิคอล',
        ];

        $data = [
            'asset_no'       => $cleanId,
            'name'           => 'ครุภัณฑ์ ' . strtoupper($cleanId),
            'brand'          => $brands[$seed % count($brands)],
            'model'          => strtoupper(substr($cleanId, -4)) . '-' . (100 + ($seed % 900)),
            'serial'         => 'SN' . strtoupper($cleanId) . (1000 + ($seed % 9000)),
            'vendor_name'    => $vendors[$seed % count($vendors)],
            'internal_phone' => '02-' . (100 + ($seed % 899)) . '-' . (1000 + ($seed % 8999)), 
            'vendor_phone'   => '08' . (1 + ($seed % 9)) . '-' . (100 + ($seed % 899)) . '-' . (1000 + ($seed % 8999)),
            'price'          => 10000 + (($seed % 49) * 10000),
            'warranty_start' => $start->format('Y-m-d'),
            'warranty_expire'=> $start->copy()->addYears(2)->format('Y-m-d'),
            
            // Mocking fields for Step 4
            'category_id'    => 1 + ($seed % 6), // สุ่ม 1-6
            'department_id'  => 1 + ($seed % 10), // สุ่ม 1-10
            'status'         => ['active', 'in_repair', 'disposed'][$seed % 3],
            'note'           => 'ข้อมูลจำลอง (Deterministic) ดึงเข้าสู่ระบบเมื่อ ' . now()->format('d/m/Y H:i'),
        ];

        // Reset seed ให้ระบบอื่นทำงานปกติ
        mt_srand();

        return $data;
    }

    /**
     * Find an asset by its HIS ID.
     */
    public function findByHisId(string $hisId): ?Asset
    {
        return Asset::where('his_asset_id', $hisId)->first();
    }

    /**
     * Sync an asset from HIS payload. Creates if not exists, updates if exists.
     */
    public function syncFromHis(array $hisPayload): Asset
    {
        $mapped = $this->mapHisPayload($hisPayload);

        if (empty($mapped['his_asset_id'])) {
            throw new \InvalidArgumentException("HIS Asset ID is missing from payload.");
        }

        $existing = Asset::where('his_asset_id', $mapped['his_asset_id'])->first();

        if ($existing) {
            // Never let a re-sync overwrite the internal inventory code that a
            // human may have set after the first import.
            unset($mapped['asset_code']);
            $existing->fill($mapped)->save();
            $asset = $existing;
        } else {
            $asset = Asset::create($mapped);
        }

        Log::info('[HisAssetSyncService] Synced asset from HIS', [
            'his_asset_id' => $asset->his_asset_id,
            'internal_id'  => $asset->id,
        ]);

        return $asset;
    }
}
