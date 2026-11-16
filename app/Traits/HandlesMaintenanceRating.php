<?php

namespace App\Traits;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Shared rating rules for the web and API rating controllers so the two
 * entry points stay in lock-step: the eligibility window, which team member
 * a rating is attributed to, and the score/comment validation.
 */
trait HandlesMaintenanceRating
{
    /** Days after completion that a request can still be rated (also what the user manual says). */
    public const RATING_DEADLINE_DAYS = 30;

    protected int $ratingDeadlineDays = self::RATING_DEADLINE_DAYS;

    /** Per-request memo for resolveTechnicianIdForRating(). */
    private array $resolvedTechnicianId = [];

    /** The date the rating window is counted from: the first of closed_at / resolved_at / completed_date. */
    protected function ratingBaseDate(MaintenanceRequest $maintenanceRequest): ?\Carbon\CarbonInterface
    {
        return $maintenanceRequest->closed_at
            ?? $maintenanceRequest->resolved_at
            ?? $maintenanceRequest->completed_date;
    }

    /**
     * A request is ratable while the first of closed_at / resolved_at /
     * completed_date is in the past and within the deadline. Carbon 3's
     * diffInDays() is signed, so pass true for an absolute day count.
     */
    protected function withinRatingWindow(MaintenanceRequest $maintenanceRequest): bool
    {
        return $this->ratingDaysLeft($maintenanceRequest) !== null;
    }

    /**
     * Whole days left to rate it: the deadline on the day it was closed, 0 on the last day, null when it can no longer (or
     * not yet) be rated. The one place that counts, so the list's "N days left" and the guard's "too late" cannot disagree.
     */
    protected function ratingDaysLeft(MaintenanceRequest $maintenanceRequest): ?int
    {
        $base = $this->ratingBaseDate($maintenanceRequest);

        if (! $base || ! $base->isPast()) {
            return null;
        }

        $used = (int) now()->diffInDays($base, true);

        return $used <= $this->ratingDeadlineDays ? $this->ratingDeadlineDays - $used : null;
    }

    /**
     * The one team member the rating row stores in `technician_id` (the API has always shown it). It is a representative, not the
     * credit: the rating counts for everybody on the team (see User::technicianRatings). It is the person who pressed "ซ่อมเสร็จ"
     * when they are still on the team, else whoever was put on first, preferring the staff who do the repair to an admin or supervisor
     * who runs the process. People taken off the job are never picked. Null when nobody is on it, and then it cannot be rated.
     * Memoised because the guard and the write both need it.
     */
    protected function resolveTechnicianIdForRating(MaintenanceRequest $maintenanceRequest): ?int
    {
        $key = $maintenanceRequest->getKey() ?? spl_object_id($maintenanceRequest);
        if (array_key_exists($key, $this->resolvedTechnicianId)) {
            return $this->resolvedTechnicianId[$key];
        }

        $team = $maintenanceRequest->assignments()
            ->where('status', '!=', \App\Models\MaintenanceAssignment::STATUS_CANCELLED)
            ->whereHas('user', fn ($q) => $q->whereIn('role', User::teamRoles()))
            ->with('user:id,role')
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();

        if ($team->isEmpty()) {
            return $this->resolvedTechnicianId[$key] = null;
        }

        $resolver = \App\Models\MaintenanceLog::query()
            ->where('request_id', $maintenanceRequest->id)
            ->where('action', \App\Models\MaintenanceLog::ACTION_TRANSITION)
            ->where('to_status', MaintenanceRequest::STATUS_RESOLVED)
            ->latest('id')
            ->value('user_id');

        $pick = $team->firstWhere('user_id', $resolver)
            ?? $team->first(fn ($a) => in_array($a->user->role, User::workerRoles(), true))
            ?? $team->first();

        return $this->resolvedTechnicianId[$key] = (int) $pick->user_id;
    }

    /** Score/comment rules shared by both controllers. */
    protected function ratingRules(): array
    {
        return [
            'score'   => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** 1–2 star ratings must carry a comment. */
    protected function requireCommentForLowScore(Validator $validator): void
    {
        $data    = $validator->getData();
        $score   = isset($data['score']) ? (int) $data['score'] : null;
        $comment = trim((string) ($data['comment'] ?? ''));

        if ($score !== null && $score <= 2 && $comment === '') {
            $validator->errors()->add('comment', 'ถ้าให้ 1–2 ดาว กรุณาระบุความคิดเห็นเพิ่มเติม');
        }
    }
}
