<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\MaintenanceRequest;
use App\Models\Asset;
use App\Models\User;
use Carbon\Carbon;

class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    // กำหนดค่าเริ่มต้นให้กับ Model MaintenanceRequest
    public function definition(): array
    {
        // สุ่มสถานะของใบแจ้งซ่อม
        $status = $this->faker->randomElement([
            MaintenanceRequest::STATUS_PENDING,
            MaintenanceRequest::STATUS_ACCEPTED,
            MaintenanceRequest::STATUS_IN_PROGRESS,
            MaintenanceRequest::STATUS_RESOLVED,
            MaintenanceRequest::STATUS_CLOSED,
        ]);

        // สุ่มวันที่แจ้งซ่อมย้อนหลังไม่เกิน 11 เดือน
        $requestDate = Carbon::instance(
            $this->faker->dateTimeBetween('-11 months', 'now')
        );

        // คำนวณลำดับเวลา (Timestamp Waterfall) ตามสถานะของงาน
        $acknowledgedAt = in_array($status, ['accepted','in_progress','resolved','closed'], true)
            ? $requestDate->copy()->addHours(rand(1, 12))
            : null;

        $acceptedAt = in_array($status, ['accepted','in_progress','resolved','closed'], true)
            ? optional($acknowledgedAt)->copy()->addHours(rand(1, 24))
            : null;

        $startedAt = in_array($status, ['in_progress','resolved','closed'], true)
            ? optional($acceptedAt)->copy()->addHours(rand(1, 24))
            : null;

        $resolvedAt = in_array($status, ['resolved','closed'], true)
            ? optional($startedAt)->copy()->addHours(rand(2, 72))
            : null;

        $closedAt = $status === 'closed'
            ? optional($resolvedAt)->copy()->addHours(rand(1, 24))
            : null;

        // เลือกเฉพาะทีมช่างจริง
        $technicianId = User::query()
            ->whereIn('role', User::teamRoles())
            ->inRandomOrder()
            ->value('id');

        return [
            'asset_id'      => Asset::query()->inRandomOrder()->value('id') ?? Asset::factory(),
            'reporter_id'   => User::query()->inRandomOrder()->value('id') ?? User::factory(),

            'title'         => 'แจ้งซ่อม: '.$this->faker->words(3, true),
            'description'   => $this->faker->sentence(12),

            'status'        => $status,

            'technician_id' => $technicianId,

            'request_date'  => $requestDate,
            'assigned_date' => $acceptedAt,
            'acknowledged_at' => $acknowledgedAt,
            'accepted_at'   => $acceptedAt,
            'started_at'    => $startedAt,
            'resolved_at'   => $resolvedAt,
            'closed_at'     => $closedAt,
            'completed_date'=> $closedAt,
            
            'sla_due_date'  => $requestDate->copy()->addDays(7),
            'paused_duration_minutes' => 0,

            'cost'          => in_array($status, ['resolved','closed'], true)
                ? $this->faker->randomFloat(2, 200, 15000)
                : null,

            'resolution_note' => in_array($status, ['resolved','closed'], true)
                ? $this->faker->sentence(10)
                : null,
        ];
    }
    public function configure()
    {
        return $this->afterCreating(function (MaintenanceRequest $mr) {
            if (in_array($mr->status, [
                MaintenanceRequest::STATUS_PENDING,
                MaintenanceRequest::STATUS_ACKNOWLEDGED,
                MaintenanceRequest::STATUS_ACCEPTED,
                MaintenanceRequest::STATUS_IN_PROGRESS,
                MaintenanceRequest::STATUS_ON_HOLD
            ], true)) {
                $mr->asset()->update(['status' => \App\Models\Asset::STATUS_IN_REPAIR]);
            }
        });
    }
}
