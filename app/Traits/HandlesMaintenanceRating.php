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
    /** Days after completion that a request can still be rated. */
    protected int $ratingDeadlineDays = 30;

    /** Per-request memo for resolveTechnicianIdForRating(). */
    private array $resolvedTechnicianId = [];

    /**
     * A request is ratable while the first of closed_at / resolved_at /
     * completed_date is in the past and within the deadline. Carbon 3's
     * diffInDays() is signed, so pass true for an absolute day count.
     */
    protected function withinRatingWindow(MaintenanceRequest $maintenanceRequest): bool
    {
        $base = $maintenanceRequest->closed_at
            ?? $maintenanceRequest->resolved_at
            ?? $maintenanceRequest->completed_date;

        if (! $base) {
            return false;
        }

        return $base->isPast()
            && (int) now()->diffInDays($base, true) <= $this->ratingDeadlineDays;
    }

    /**
     * The team member a rating is attributed to: the lead assignment,
     * preferring a completed one, most recently assigned. Memoised because
     * the guard and the write both need it.
     */
    protected function resolveTechnicianIdForRating(MaintenanceRequest $maintenanceRequest): ?int
    {
        $key = $maintenanceRequest->getKey() ?? spl_object_id($maintenanceRequest);
        if (array_key_exists($key, $this->resolvedTechnicianId)) {
            return $this->resolvedTechnicianId[$key];
        }

        $assignment = $maintenanceRequest->assignments()
            ->whereHas('user', fn ($q) => $q->whereIn('role', User::teamRoles()))
            ->orderByDesc('is_lead')
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END DESC")
            ->orderByDesc('assigned_at')
            ->first();

        return $this->resolvedTechnicianId[$key] = $assignment?->user_id;
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
