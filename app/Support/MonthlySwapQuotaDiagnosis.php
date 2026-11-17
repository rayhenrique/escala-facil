<?php

namespace App\Support;

use App\Enums\ShiftSwapStatus;
use App\Models\ShiftSwap;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Compares evidence; never infers a replacement approval date. */
class MonthlySwapQuotaDiagnosis
{
    public const TIMEZONE = 'America/Maceio';

    public function inspect(ShiftSwap $swap, Collection $approvals, CarbonImmutable $asOf): ?array
    {
        $raw = $swap->getRawOriginal('approved_at');
        $approved = $this->date($raw);
        $historical = in_array($swap->status, [ShiftSwapStatus::Approved, ShiftSwapStatus::Reverted], true);
        if (! $historical && $raw === null && $approvals->isEmpty()) {
            return null;
        }

        $issues = [];
        if (! $approved) {
            $issues['missing_or_invalid_approved_at'] = 'divergent';
        }
        if (! $historical) {
            $issues['unexpected_approval_for_status'] = 'divergent';
        }
        if ($approved && $approved->greaterThan($asOf)) {
            $issues['approval_in_future'] = 'divergent';
        }
        $created = $this->date($swap->getRawOriginal('created_at'));
        $updated = $this->date($swap->getRawOriginal('updated_at'));
        if ($approved && $created && $approved->lessThan($created)) {
            $issues['approval_before_creation'] = 'divergent';
        }
        if ($approved && $updated && $approved->greaterThan($updated)) {
            $issues['approval_after_updated_at'] = 'divergent';
        }

        if ($approvals->isEmpty()) {
            $issues['missing_approval_event'] = 'uncertain';
            if ($approved && $updated && $approved->equalTo($updated)) {
                $issues['possible_updated_at_backfill'] = 'uncertain';
            } elseif ($approved && $created && $approved->equalTo($created)) {
                $issues['possible_created_at_backfill'] = 'uncertain';
            }
        } elseif ($approvals->count() !== 1) {
            $issues['multiple_approval_events'] = 'uncertain';
        } else {
            $event = $approvals->first();
            $occurred = $this->date($event->getRawOriginal('occurred_at'));
            $changes = is_array($event->changes) ? $event->changes : [];
            if (($changes['status']['after'] ?? null) !== ShiftSwapStatus::Approved->value) {
                $issues['invalid_approval_event'] = 'uncertain';
            }
            // Old audit events did not capture this field. Absence is not evidence of tampering.
            $recorded = null;
            if (array_key_exists('approved_at', $changes)) {
                $recorded = $this->date($changes['approved_at']['after'] ?? null);
                if (! $recorded) {
                    $issues['invalid_audited_approved_at'] = 'uncertain';
                } elseif ($approved && ! $approved->equalTo($recorded)) {
                    $issues['audited_approved_at_mismatch'] = 'divergent';
                }
            }
            // Event time is corroboration, not the exact decision time: the
            // transaction can cross midnight after approved_at was captured.
            if (! $occurred) {
                $issues['invalid_event_date'] = 'uncertain';
            } elseif (! $recorded && $approved && $approved->format('Y-m-d H:i:s') !== $occurred->format('Y-m-d H:i:s')) {
                $issues['approval_event_date_mismatch'] = 'uncertain';
                if ($approved->format('Y-m') !== $occurred->format('Y-m')) {
                    $issues['approval_month_mismatch'] = 'uncertain';
                }
            }
        }

        if ($issues === []) {
            return null;
        }

        return [
            'swap_id' => $swap->id,
            'status' => $swap->status->value,
            'requester_id' => $swap->requester_id,
            'substitute_id' => $swap->substitute_id,
            'approved_at' => $raw,
            'created_at' => $swap->getRawOriginal('created_at'),
            'updated_at' => $swap->getRawOriginal('updated_at'),
            'quota_month' => $swap->status === ShiftSwapStatus::Approved ? $approved?->format('Y-m') : null,
            'approval_events' => $approvals->map(fn ($event) => [
                'id' => $event->id,
                'occurred_at' => $event->getRawOriginal('occurred_at'),
                'approved_at' => $event->changes['approved_at']['after'] ?? null,
            ])->values()->all(),
            'classification' => in_array('divergent', $issues, true) ? 'divergent' : 'uncertain',
            'issues' => array_keys($issues),
        ];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::parse($value, self::TIMEZONE)->setTimezone(self::TIMEZONE);
            $errors = CarbonImmutable::getLastErrors();

            return $errors && ($errors['warning_count'] || $errors['error_count']) ? null : $date;
        } catch (\Exception) {
            return null;
        }
    }
}
