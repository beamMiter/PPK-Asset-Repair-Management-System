<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Livechat board: a handful of threads that read like the real thing — questions and answers between the people in
 * UserSeeder::ROSTER — including an announcement thread that has been locked, and read markers so some people have unread
 * threads (`read` = how many messages that person has read; absent = all of them).
 */
class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->startOfMinute();

        foreach ($this->threads() as $thread) {
            $started = $now->copy()->subMinutes((int) round($thread['age'] * 1440));
            $lastAt = $started->copy()->addMinutes(max(array_column($thread['messages'], 1)));

            $threadId = DB::table('chat_threads')->insertGetId([
                'title' => $thread['title'],
                'author_id' => UserSeeder::find($thread['by'])->id,
                'is_locked' => $thread['locked'] ?? false,
                'created_at' => $started,
                'updated_at' => $lastAt,
            ]);

            $messageIds = [];
            foreach ($thread['messages'] as [$key, $minutes, $body]) {
                $messageIds[] = DB::table('chat_messages')->insertGetId([
                    'chat_thread_id' => $threadId,
                    'user_id' => UserSeeder::find($key)->id,
                    'body' => $body,
                    'created_at' => $started->copy()->addMinutes($minutes),
                    'updated_at' => $started->copy()->addMinutes($minutes),
                ]);
            }

            // everybody who took part has read the thread up to their own last message, or as far as `read` says
            $readers = collect($thread['messages'])->pluck(0)->unique()->all();
            foreach ($thread['read'] ?? [] as $key => $count) {
                $readers[] = $key;
            }
            foreach (array_unique($readers) as $key) {
                $count = $thread['read'][$key] ?? count($messageIds);
                if ($count < 1) {
                    continue; // never opened: every message is unread
                }
                $readMessage = $thread['messages'][$count - 1];

                DB::table('chat_thread_reads')->insert([
                    'user_id' => UserSeeder::find($key)->id,
                    'chat_thread_id' => $threadId,
                    'last_read_message_id' => $messageIds[$count - 1],
                    'last_read_at' => $started->copy()->addMinutes($readMessage[1] + 2),
                    'created_at' => $started,
                    'updated_at' => $started,
                ]);
            }
        }
    }

    /** title · by (author) · age (days ago it started) · locked · messages [who, minutes after the start, text] · read [who => n read] */
    private function threads(): array
    {
        return [
            ['title' => 'ระบบ HIS ช้าในช่วงเช้า 08.00–09.30', 'by' => 'opd', 'age' => 2.2,
                'read' => ['you' => 4, 'lab' => 0],
                'messages' => [
                    ['opd', 0, 'ช่วงเช้าเปิดโปรแกรม HIS ช้ามากค่ะ ต้องรอประมาณ 2-3 นาทีต่อผู้ป่วยหนึ่งราย มีใครเป็นเหมือนกันไหมคะ'],
                    ['ipd', 12, 'หอผู้ป่วยในก็เป็นเหมือนกันค่ะ ช่วง 8 โมงถึงเก้าโมงครึ่ง'],
                    ['net', 45, 'รับทราบครับ เดี๋ยวตรวจสอบปริมาณการใช้งานเครือข่ายช่วงเช้าให้'],
                    ['dev', 180, 'ตรวจ log ฐานข้อมูลแล้ว พบมี query รายงานหนักรันช่วงเช้า จะปรับเวลารันไปช่วงบ่ายนะครับ'],
                    ['opd', 240, 'ขอบคุณค่ะ พรุ่งนี้เช้าจะลองดูอีกทีค่ะ'],
                    ['you', 300, 'ปรับตารางรายงานแล้วครับ ถ้ายังช้าให้แจ้งเป็นใบงานได้เลย'],
                ]],

            ['title' => 'ประกาศ: ปิดปรับปรุงเครือข่ายคืนวันเสาร์ 22.00–24.00', 'by' => 'sup', 'age' => 5, 'locked' => true,
                'read' => ['pharm' => 0, 'er' => 0],
                'messages' => [
                    ['sup', 0, 'แจ้งทุกหน่วยงาน: คืนวันเสาร์นี้ 22.00-24.00 น. ปิดปรับปรุงเครือข่ายหลัก ระบบ HIS และอินเทอร์เน็ตอาจใช้งานไม่ได้ชั่วคราว'],
                    ['net', 15, 'เตรียมอุปกรณ์สำรองและตั้งข้อความแจ้งเตือนบนหน้าจอระบบไว้แล้วครับ'],
                    ['you', 60, 'ขอให้ทุกหน่วยงานบันทึกข้อมูลผู้ป่วยให้เรียบร้อยก่อนเวลา 21.45 น.'],
                    ['sup', 300, 'ปิดกระทู้นี้เพื่อไม่ให้ตอบซ้ำ หากมีข้อสงสัยติดต่อกลุ่มงาน IT โดยตรงครับ'],
                ]],

            ['title' => 'วิธีแจ้งซ่อมให้ถูกต้อง (คู่มือเบื้องต้น)', 'by' => 'you', 'age' => 12,
                'messages' => [
                    ['you', 0, 'แจ้งซ่อมผ่านเมนู "แจ้งซ่อม" เลือกครุภัณฑ์และประเภทงานให้ตรง จะส่งถึงเจ้าหน้าที่ที่เกี่ยวข้องเร็วขึ้น'],
                    ['pharm', 200, 'ถ้าไม่มีรหัสครุภัณฑ์ต้องทำอย่างไรคะ'],
                    ['you', 260, 'เลือกแจ้งโดยไม่ระบุครุภัณฑ์ได้ครับ แล้วพิมพ์สถานที่ในช่อง "สถานที่" แทน'],
                    ['er', 1500, 'แนบรูปได้ไหมครับ'],
                    ['it1', 1560, 'แนบรูปได้ครับ สูงสุด 3 ไฟล์ ช่วยให้วินิจฉัยได้เร็วขึ้นมาก'],
                ]],

            ['title' => 'ขอคำแนะนำเลือกซื้อเครื่องพิมพ์ฉลากยา', 'by' => 'pharm', 'age' => 8,
                'messages' => [
                    ['pharm', 0, 'ห้องยากำลังจะจัดซื้อเครื่องพิมพ์ฉลากยาเพิ่ม 2 เครื่อง แนะนำรุ่นที่ใช้กับ HIS ได้ไหมคะ'],
                    ['it1', 90, 'แนะนำ Zebra ZD621 ครับ ใช้กับ HIS ได้ ติดตั้งไดรเวอร์ง่าย และหาอะไหล่ได้'],
                    ['lab', 400, 'ห้อง LAB ใช้ ZD421 เหมือนกัน ใช้มาปีกว่าไม่มีปัญหาค่ะ'],
                    ['pharm', 500, 'ขอบคุณทุกท่านค่ะ จะใช้ ZD621 ตามที่แนะนำ'],
                ]],

            ['title' => 'WiFi หอผู้ป่วยในสัญญาณอ่อนช่วงกลางคืน', 'by' => 'ipd', 'age' => 1.4,
                'read' => ['you' => 1],
                'messages' => [
                    ['ipd', 0, 'ช่วงดึกแท็บเล็ตพยาบาลหลุดจาก WiFi บ่อยมากค่ะ'],
                    ['net', 30, 'เปิดใบงานให้แล้วครับ กำลังตรวจสอบ Access Point ชั้น 3'],
                    ['ipd', 45, 'ขอบคุณค่ะ'],
                ]],

            ['title' => 'UPS ห้องเซิร์ฟเวอร์ครบกำหนดตรวจเช็ก', 'by' => 'it1', 'age' => 3,
                'messages' => [
                    ['it1', 0, 'UPS ห้องเซิร์ฟเวอร์ครบรอบตรวจเช็ก แบตเตอรี่เริ่มเสื่อม ต้องเปลี่ยนชุดใหม่'],
                    ['sup', 60, 'ประเมินราคาแล้วส่งให้ผมพิจารณาด้วย'],
                    ['tech1', 200, 'ถ้าต้องปิดห้องชั่วคราวให้แจ้งล่วงหน้า จะได้จัดคนช่วยย้ายอุปกรณ์'],
                    ['it1', 260, 'เปิดใบงานแล้วครับ อยู่ระหว่างรอแบตเตอรี่'],
                ]],
        ];
    }
}
