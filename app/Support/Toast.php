<?php

namespace App\Support;

class Toast
{
    // ตัวแปรสำหรับเก็บข้อมูลที่จะส่งไปแสดงผล
    protected array $data = [];
    public static function make(
        string $message,
        string $type = 'info',
        string $position = 'tc',
        int $timeout = 2000,
        string $size = 'sm'
    ): array {
        return compact('type', 'message', 'position', 'timeout', 'size');
    }

    public static function success(string $message, int $timeout = 1600): array
    {
        return self::make($message, 'success', 'tr', $timeout, 'lg');
    }
    public static function info(string $message, int $timeout = 1600): array
    {
        return self::make($message, 'info', 'tr', $timeout, 'lg');
    }
    public static function error(string $message, int $timeout = 2000): array
    {
        return self::make($message, 'error', 'tr', $timeout, 'lg');
    }
    public static function warning(string $message, int $timeout = 2000): array
    {
        return self::make($message, 'warning', 'tr', $timeout, 'lg');
    }
    // แปลงข้อมูลจาก Array ดิบให้เข้าฟอร์แมตของคลาส
    public static function from(array $raw): array
    {
        return self::make(
            $raw['message'] ?? '',
            $raw['type'] ?? 'info',
            $raw['position'] ?? 'tc',
            (int) ($raw['timeout'] ?? 2000),
            $raw['size'] ?? 'sm'
        );
    }

    public static function message(string $message): self
    {
        $instance = new self();
        // กำหนดค่าเริ่มต้น
        $instance->data = self::make($message);
        return $instance;
    }

    // เปลี่ยนตำแหน่งการแสดงผล (tc, tr, tl, bc, br, bl)
    public function position(string $position): self
    {
        $this->data['position'] = $position;
        return $this;
    }

    // กำหนดขนาด (sm, md, lg)
    public function size(string $size): self
    {
        $this->data['size'] = $size;
        return $this;
    }

    // กำหนดเวลาค้าง (มิลลิวินาที)
    public function timeout(int $ms): self
    {
        $this->data['timeout'] = $ms;
        return $this;
    }

    // ส่งข้อมูลเข้า Session ทันที (ไม่ต้องเขียน ->with ใน Controller)
    public function flash(): void
    {
        session()->flash('toast', $this->data);
    }

    // แปลง object เป็น array (กรณีจะใช้ร่วมกับ ->with)
    public function toArray(): array
    {
        return $this->data;
    }
}
