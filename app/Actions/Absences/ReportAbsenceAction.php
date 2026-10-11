<?php

namespace App\Actions\Absences;

use App\Enums\AbsenceStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Http\Requests\ReportAbsenceRequest;
use App\Models\Absence;
use App\Models\Shift;
use App\Models\User;
use App\Support\OperationalAuditContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReportAbsenceAction
{
    /** @param array{shift_id: int|string, reason: string, attachment?: UploadedFile|null} $payload */
    public function execute(User $user, array $payload): Absence
    {
        $attachmentPath = null;

        try {
            return app(OperationalAuditContext::class)->transaction($user, function () use ($user, $payload, &$attachmentPath): Absence {
                $shift = Shift::query()->lockForUpdate()->findOrFail($payload['shift_id']);
                Gate::forUser($user)->authorize('create', [Absence::class, $shift]);

                $validated = (new ReportAbsenceRequest)->validatePayload($payload, $user);
                $attachment = $validated['attachment'] ?? null;

                if ($attachment instanceof UploadedFile) {
                    $attachmentPath = $attachment->store('absence-attachments', 'local');
                }

                $absence = Absence::query()->create([
                    'shift_id' => $shift->id,
                    'user_id' => $user->id,
                    'reason' => $validated['reason'],
                    'attachment_path' => $attachmentPath,
                    'status' => AbsenceStatus::Pending,
                ]);

                $shift->update([
                    'user_id' => null,
                    'status' => ShiftStatus::Uncovered,
                ]);

                $shift->swaps()
                    ->whereIn('status', [
                        ShiftSwapStatus::PendingProfessional,
                        ShiftSwapStatus::PendingManager,
                    ])
                    ->lockForUpdate()->eachById(fn ($swap) => $swap->update(['status' => ShiftSwapStatus::Rejected]));

                return $absence;
            });
        } catch (Throwable $exception) {
            if ($attachmentPath) {
                Storage::disk('local')->delete($attachmentPath);
            }

            throw $exception;
        }
    }
}
