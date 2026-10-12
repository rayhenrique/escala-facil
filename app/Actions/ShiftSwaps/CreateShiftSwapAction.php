<?php

namespace App\Actions\ShiftSwaps;

use App\Enums\ShiftSwapStatus;
use App\Http\Requests\StoreShiftSwapRequest;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAuditContext;
use Illuminate\Database\DetectsConcurrencyErrors;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CreateShiftSwapAction
{
    use DetectsConcurrencyErrors;

    public function __construct(private readonly ShiftSwapNotificationService $notifications) {}

    /** @param array{shift_id: int|string, substitute_id: int|string, substitute_shift_id?: int|string|null} $payload */
    public function execute(User $requester, array $payload): ShiftSwap
    {
        try {
            return app(OperationalAuditContext::class)->transaction($requester, function () use ($requester, $payload): ShiftSwap {
                $users = User::query()->whereKey([$requester->id, $payload['substitute_id'] ?? null])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $requester = $users->get($requester->id);
                abort_unless($requester, 403);
                Shift::query()->whereKey(array_filter([$payload['shift_id'] ?? null, $payload['substitute_shift_id'] ?? null]))
                    ->orderBy('id')->lockForUpdate()->get();

                $validated = (new StoreShiftSwapRequest)->validatePayload($payload, $requester);

                $shiftSwap = ShiftSwap::query()->create([
                    ...$validated,
                    'requester_id' => $requester->id,
                    'status' => ShiftSwapStatus::PendingProfessional,
                ]);

                $this->notifications->requested($shiftSwap);

                return $shiftSwap;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (! $this->causedByConcurrencyError($exception)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['shift_id' => 'Outra operação está alterando os plantões ou profissionais. Volte e revise os detalhes antes de tentar novamente.']);
        }
    }
}
