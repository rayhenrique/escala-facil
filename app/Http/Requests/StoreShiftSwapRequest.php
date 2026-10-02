<?php

namespace App\Http\Requests;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Validation\Validator;

class StoreShiftSwapRequest extends DataFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer', 'exists:shifts,id'],
            'substitute_id' => ['required', 'integer', 'different:requester_id', 'exists:users,id'],
            'substitute_shift_id' => ['nullable', 'integer', 'different:shift_id', 'exists:shifts,id'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $requester = $this->user();
                $shift = Shift::query()->find($this->integer('shift_id'));
                $substitute = User::query()->find($this->integer('substitute_id'));
                $substituteShiftId = $this->input('substitute_shift_id');
                $substituteShift = $substituteShiftId ? Shift::query()->find($substituteShiftId) : null;

                if (! $requester || ! $shift || ! $substitute) {
                    return;
                }

                if ($requester->role !== UserRole::Professional || $shift->user_id !== $requester->id) {
                    $validator->errors()->add('shift_id', 'Você só pode solicitar troca de um plantão atribuído a você.');
                }

                if (! $shift->date->isAfter(today()) || $shift->status !== ShiftStatus::Scheduled) {
                    $validator->errors()->add('shift_id', 'Somente plantões futuros e agendados podem ser trocados.');
                }

                $hasOpenSwap = ShiftSwap::query()
                    ->where(fn ($query) => $query->where('shift_id', $shift->id)->orWhere('substitute_shift_id', $shift->id))
                    ->whereIn('status', [
                        ShiftSwapStatus::PendingProfessional,
                        ShiftSwapStatus::PendingManager,
                        ShiftSwapStatus::Approved,
                    ])
                    ->exists();

                if ($hasOpenSwap) {
                    $validator->errors()->add('shift_id', 'Este plantão já possui uma solicitação de troca ativa.');
                }

                if ($substitute->id === $requester->id
                    || $substitute->role !== UserRole::Professional
                    || $substitute->category_id !== $requester->category_id
                    || $substitute->sector_id !== $requester->sector_id
                    || $substitute->category_id !== $shift->category_id
                    || $substitute->sector_id !== $shift->sector_id) {
                    $validator->errors()->add('substitute_id', 'O substituto deve ser outro profissional da mesma categoria e do mesmo setor.');
                }

                if ($substituteShift) {
                    if ($substituteShift->user_id !== $substitute->id) {
                        $validator->errors()->add('substitute_shift_id', 'O plantão de contrapartida deve pertencer ao substituto selecionado.');
                    }

                    if (! $substituteShift->date->isAfter(today()) || $substituteShift->status !== ShiftStatus::Scheduled) {
                        $validator->errors()->add('substitute_shift_id', 'Somente plantões futuros e agendados do substituto podem ser oferecidos na troca.');
                    }

                    if ($substituteShift->sector_id !== $shift->sector_id || $substituteShift->category_id !== $shift->category_id) {
                        $validator->errors()->add('substitute_shift_id', 'O plantão de contrapartida deve ser da mesma categoria e setor.');
                    }

                    $hasOpenSubstituteShiftSwap = ShiftSwap::query()
                        ->where(fn ($query) => $query->where('shift_id', $substituteShift->id)->orWhere('substitute_shift_id', $substituteShift->id))
                        ->whereIn('status', [
                            ShiftSwapStatus::PendingProfessional,
                            ShiftSwapStatus::PendingManager,
                            ShiftSwapStatus::Approved,
                        ])
                        ->exists();

                    if ($hasOpenSubstituteShiftSwap) {
                        $validator->errors()->add('substitute_shift_id', 'O plantão do substituto já possui uma solicitação de troca ativa.');
                    }
                }

                if ($requester->hasReachedSwapLimit(now()->month, now()->year)) {
                    $validator->errors()->add('shift_id', 'Você atingiu o limite mensal de quatro trocas aprovadas.');
                }

                if ($substitute->hasReachedSwapLimit(now()->month, now()->year)) {
                    $validator->errors()->add('substitute_id', 'O profissional selecionado atingiu o limite mensal de trocas.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'shift_id' => 'plantão',
            'substitute_id' => 'substituto',
            'substitute_shift_id' => 'plantão de contrapartida',
        ];
    }
}
