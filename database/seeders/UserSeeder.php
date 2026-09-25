<?php

namespace Database\Seeders;

use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The demo people. Every role is represented, plus the awkward cases the screens have to cope with: an account that
 * has been suspended, and a member who has no e-mail (they sign in with the citizen id only).
 *
 * Sign-in is the 13-digit citizen id (`cid`) + password:
 *   - `you`  1234567890123 / Dev12345!   — the developer's own admin account
 *   - everybody else                     — 12345678
 * DemoDataSeeder and ChatSeeder refer to people by the keys of ROSTER.
 */
class UserSeeder extends Seeder
{
    public const DEFAULT_PASSWORD = '12345678';

    /** key => person */
    public const ROSTER = [
        'you'    => ['cid' => '1234567890123', 'name' => 'Admin',                'email' => 'dev@example.com',        'role' => User::ROLE_ADMIN,       'dept' => 'IT',    'password' => 'Dev12345!'],
        'sup'    => ['cid' => '1000000000002', 'name' => 'สมชาย วงศ์สวัสดิ์',     'email' => 'supervisor@example.com', 'role' => User::ROLE_SUPERVISOR,  'dept' => 'IT'],
        'it1'    => ['cid' => '1000000000003', 'name' => 'ธนวัฒน์ แก้วมณี',       'email' => 'it1@example.com',        'role' => User::ROLE_IT_SUPPORT,  'dept' => 'IT'],
        'it2'    => ['cid' => '1000000000004', 'name' => 'กิตติพงษ์ ศรีสุข',       'email' => 'it2@example.com',        'role' => User::ROLE_IT_SUPPORT,  'dept' => 'IT'],
        'net'    => ['cid' => '1000000000005', 'name' => 'ปรีชา นิลรัตน์',         'email' => 'net1@example.com',       'role' => User::ROLE_NETWORK,     'dept' => 'IT'],
        'dev'    => ['cid' => '1000000000006', 'name' => 'วรรณา จันทร์เพ็ญ',       'email' => 'dev1@example.com',       'role' => User::ROLE_DEVELOPER,   'dept' => 'IT'],
        'opd'    => ['cid' => '1000000000007', 'name' => 'อรทัย สุขสันต์',         'email' => 'member1@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'OPD'],
        'tech1'  => ['cid' => '1000000000008', 'name' => 'สุรชัย บุญมี',           'email' => 'tech1@example.com',      'role' => User::ROLE_TECHNICIAN,  'dept' => 'FAC'],
        'tech2'  => ['cid' => '1000000000009', 'name' => 'ประเสริฐ ทองดี',         'email' => 'tech2@example.com',      'role' => User::ROLE_TECHNICIAN,  'dept' => 'BME'],
        // left the team: suspended, keeps every request they worked on, is never offered for new work
        'gone'   => ['cid' => '1000000000010', 'name' => 'อดิศักดิ์ ใจเย็น',       'email' => 'it3@example.com',        'role' => User::ROLE_IT_SUPPORT,  'dept' => 'IT', 'suspended' => true],
        'ipd'    => ['cid' => '1000000000011', 'name' => 'พิมพ์ชนก รักษ์ไทย',      'email' => 'member2@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'IPD'],
        'lab'    => ['cid' => '1000000000012', 'name' => 'ชาติชาย ปัญญา',          'email' => 'member3@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'LAB'],
        'pharm'  => ['cid' => '1000000000013', 'name' => 'นภัสสร เจริญผล',         'email' => 'member4@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'PHARM'],
        // no e-mail: cannot use "forgot password" (an admin sets the password), signs in with the citizen id only
        'er'     => ['cid' => '1000000000014', 'name' => 'วิทยา กล้าหาญ',          'email' => null,                     'role' => User::ROLE_MEMBER,      'dept' => 'ER'],
        'fin'    => ['cid' => '1000000000015', 'name' => 'ศิริพร มั่นคง',          'email' => 'member5@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'FIN'],
        // more of the hospital's own staff, so the people who rate the technicians (and write the comments on a technician's rating
        // page) are not the same six: radiology, administration and personnel had nobody, and OPD / IPD had one member each
        'rad'    => ['cid' => '1000000000016', 'name' => 'กมลชนก พรหมมา',          'email' => 'member6@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'RAD'],
        'adm'    => ['cid' => '1000000000017', 'name' => 'ธีรพงษ์ อินทรวงศ์',       'email' => 'member7@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'ADM'],
        'hr'     => ['cid' => '1000000000018', 'name' => 'สุพัตรา ศรีวงศ์',         'email' => 'member8@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'HR'],
        'opd2'   => ['cid' => '1000000000019', 'name' => 'ณัฐพล เกษมสุข',          'email' => 'member9@example.com',    'role' => User::ROLE_MEMBER,      'dept' => 'OPD'],
        'ipd2'   => ['cid' => '1000000000020', 'name' => 'จิราภรณ์ ทองสุข',         'email' => 'member10@example.com',   'role' => User::ROLE_MEMBER,      'dept' => 'IPD'],
    ];

    public function run(): void
    {
        $hashes = [];

        User::unguarded(function () use (&$hashes) {
            foreach (self::ROSTER as $person) {
                $password = $person['password'] ?? self::DEFAULT_PASSWORD;
                $hashes[$password] ??= Hash::make($password); // one bcrypt per distinct password, not one per person

                User::updateOrCreate(['citizen_id' => $person['cid']], [
                    'name' => $person['name'],
                    'email' => $person['email'],
                    'email_verified_at' => $person['email'] ? now() : null,
                    'password' => $hashes[$password],
                    'role' => $person['role'],
                    'department' => $person['dept'],
                    'suspended_at' => ($person['suspended'] ?? false) ? now()->subDays(20) : null,
                ]);
            }
        });

        // the Network type suggests its own specialist first in the assign-team picker
        MaintenanceRequestType::where('name', 'Network')->update([
            'default_user_id' => User::where('citizen_id', self::ROSTER['net']['cid'])->value('id'),
        ]);
    }

    /** The user behind a ROSTER key. */
    public static function find(string $key): User
    {
        return User::where('citizen_id', self::ROSTER[$key]['cid'])->firstOrFail();
    }
}
