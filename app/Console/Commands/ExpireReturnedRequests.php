<?php

namespace App\Console\Commands;

use App\Models\MaintenanceRequest;
use App\Services\MaintenanceTransitionService;
use Illuminate\Console\Command;

/**
 * A request the staff sent back to its reporter ("ส่งกลับแก้ไข") does not wait for ever. Every night this cancels the ones whose
 * `repair.return_expires_after_days` (14) Thai calendar days are up and that were not sent again, so a request nobody comes back to is
 * not left in the system. It goes through the same transition as any cancellation (the asset is freed, the history says "ยกเลิกอัตโนมัติ" with
 * no person behind it). 0 turns the rule off. `--dry-run` says what it would cancel and does nothing. The request and the list count the days
 * down for the reporter (MaintenanceRequest::returnDaysLeft).
 */
class ExpireReturnedRequests extends Command
{
    /** Thai time (Asia/Bangkok), after the chat sweeps (03:00, 03:10): routes/console.php schedules it. */
    public const RUNS_AT = '03:30';

    protected $signature = 'repair:expire-returned {--dry-run : only say what would be cancelled}';

    protected $description = 'Cancel the requests sent back to their reporter that were not sent again in time';

    public function handle(MaintenanceTransitionService $transitions): int
    {
        $days = (int) config('repair.return_expires_after_days');

        if ($days <= 0) {
            $this->info('The rule is off (repair.return_expires_after_days = 0): nothing to do.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $cancelled = 0;
        $failed = 0;

        MaintenanceRequest::query()->returnedWithinDays(0)->chunkById(100, function ($chunk) use ($transitions, $dry, $days, &$cancelled, &$failed) {
            foreach ($chunk as $request) {
                if ($dry) {
                    $cancelled++;
                    $this->line("would cancel #{$request->request_no}");

                    continue;
                }

                try {
                    $transitions->applyTransition(
                        $request,
                        ['status' => MaintenanceRequest::STATUS_CANCELLED, 'note' => "ยกเลิกอัตโนมัติ: ผู้แจ้งไม่แก้ไขและส่งใบงานอีกครั้งภายใน {$days} วัน"],
                        null   // the system: nobody pressed anything
                    );
                    $cancelled++;
                } catch (\Throwable $e) {
                    // it changed under us (sent again a second ago) or something is wrong with this one: say so, and go on with the rest
                    $failed++;
                    report($e);
                }
            }
        });

        $this->info(($dry ? 'would cancel ' : 'cancelled ').$cancelled.' request(s)'.($failed ? ", $failed failed (see the log)" : ''));

        return self::SUCCESS;
    }
}
