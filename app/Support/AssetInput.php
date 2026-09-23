<?php

namespace App\Support;

use App\Models\Asset;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;

/**
 * What an asset form / API call may contain, shared by the pages and the JSON API (they used to carry four copies).
 */
final class AssetInput
{
    /** Copy shown when someone tries to put an asset back to "active" while a repair is still open. */
    public const REACTIVATION_BLOCKED = 'ไม่สามารถเปลี่ยนสถานะเป็น "ใช้งานปกติ" ได้ เนื่องจากยังมีใบแจ้งซ่อมที่ยังไม่แล้วเสร็จค้างอยู่';

    private const FIELD_NAMES = [
        'asset_code' => 'รหัสครุภัณฑ์',
        'name' => 'ชื่อครุภัณฑ์',
        'serial_number' => 'Serial',
        'category_id' => 'หมวดหมู่',
        'department_id' => 'หน่วยงาน',
        'warranty_expire' => 'หมดประกัน',
    ];

    /** Rules for a new asset, or — with `$asset` — for changing it (unique fields ignore the asset itself). */
    public static function rules(?Asset $asset = null): array
    {
        $required = $asset ? 'sometimes' : 'required';
        $ignore = $asset ? ','.$asset->id : '';

        return [
            'asset_code' => [$required, 'string', 'max:100', 'unique:assets,asset_code'.$ignore],
            'name' => [$required, 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100', 'unique:assets,serial_number'.$ignore],
            'location' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'his_asset_id' => ['nullable', 'string', 'max:100', 'unique:assets,his_asset_id'.$ignore],
            'purchase_date' => ['nullable', 'date'],
            'warranty_start' => ['nullable', 'date'],
            'warranty_expire' => ['nullable', 'date', 'after_or_equal:warranty_start'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'vendor_phone' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'hero_image' => ['nullable', 'image', 'max:5120'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:10240'],
            'status' => ['nullable', Rule::in([Asset::STATUS_ACTIVE, Asset::STATUS_IN_REPAIR, Asset::STATUS_DISPOSED])],
        ];
    }

    /** "ข้อมูลไม่ถูกต้อง: <the fields that failed>" — by the names above, else the Thai one in lang/th/validation.php, the raw key last. */
    public static function failureMessage(MessageBag $errors): string
    {
        // the whole table, looked up by key: the names have dots in them ("files.*"), which trans('validation.attributes.files.*') would
        // read as nesting — the validator itself reads the table the same way
        $attributes = trans('validation.attributes');
        $attributes = is_array($attributes) ? $attributes : [];

        $bad = collect($errors->keys())
            ->map(fn ($field) => self::FIELD_NAMES[$field]
                ?? $attributes[$field]
                ?? $attributes[preg_replace('/\.\d+/', '.*', $field)]   // files.0 -> files.*
                ?? $field)
            ->unique()
            ->implode(', ');

        return $bad ? 'ข้อมูลไม่ถูกต้อง: '.$bad : 'ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
    }

    /** Setting a not-active asset back to active by hand is refused while a repair for it is still open. */
    public static function blocksReactivation(Asset $asset, array $data): bool
    {
        return ($data['status'] ?? null) === Asset::STATUS_ACTIVE
            && $asset->status !== Asset::STATUS_ACTIVE
            && $asset->hasOpenMaintenance();
    }
}
