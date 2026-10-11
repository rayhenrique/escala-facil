<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Shares interval/compatibility checks without creating new institutional limits. */
class ShiftAssignmentAnalysis
{
    public function compatibleProfessionals(Shift $candidate): Builder
    {
        return User::query()->where('role', UserRole::Professional)
            ->where('sector_id', $candidate->sector_id)->where('category_id', $candidate->category_id);
    }

    public function compatibility(User $user, Shift $candidate): array
    {
        $blocks = [];
        if ($user->role !== UserRole::Professional) {
            $blocks[] = 'Selecione um profissional para o plantão.';
        }
        if ($user->sector_id !== $candidate->sector_id || $user->category_id !== $candidate->category_id) {
            $blocks[] = 'O profissional deve pertencer à categoria e ao setor do plantão. Escolha um cadastro compatível ou revise os vínculos antes de salvar.';
        }

        return $blocks;
    }

    public function schedule(Shift $candidate, array $ids, bool $lockForUpdate = false): Collection
    {
        $start = $candidate->period()->start->subDay()->toDateString();

        return Shift::query()->whereIn('user_id', $ids)->where('status', ShiftStatus::Scheduled)
            ->when($candidate->getKey() !== null, fn ($q) => $q->whereKeyNot($candidate->getKey()))
            ->whereDate('date', '<=', $candidate->period()->end->addDay()->toDateString())
            ->where(fn ($q) => $q->whereDate('end_date', '>=', $start)
                ->orWhere(fn ($legacy) => $legacy->whereNull('end_date')->whereDate('date', '>=', $start)))
            ->orderBy('id')->when($lockForUpdate, fn ($q) => $q->lockForUpdate())->get();
    }

    public function availability(Shift $candidate, User $user, Collection $schedule): array
    {
        $conflicts = [];
        $doubles = [];
        foreach ($schedule as $other) {
            if ($other->user_id !== $user->id || $other->status !== ShiftStatus::Scheduled || ($candidate->id !== null && $other->id === $candidate->id)) {
                continue;
            }
            if ($candidate->period()->overlaps($other->period())) {
                $conflicts[] = 'Conflito com o plantão #'.$other->id.': '.$other->period()->datedLabel().'. Ajuste os horários ou selecione outro profissional.';
            } elseif (ShiftCoverage::findDoubleShiftIn(collect([$other]), $user->id, $candidate)) {
                $doubles[] = 'Dobra com o plantão #'.$other->id.': '.$other->period()->datedLabel().'. Os turnos são consecutivos. Confira se a dobra é autorizada antes de confirmar.';
            }
        }

        return ['conflicts' => $conflicts, 'doubles' => $doubles];
    }

    public function analyze(Shift $candidate, ?User $user, Collection $schedule, bool $overlapBlocks = false): array
    {
        $blocks = $user ? $this->compatibility($user, $candidate) : [];
        $availability = $user && ShiftCoverage::isAssignedScheduled($candidate)
            ? $this->availability($candidate, $user, $schedule) : ['conflicts' => [], 'doubles' => []];
        $warnings = $availability['doubles'];
        if ($overlapBlocks) {
            $blocks = [...$blocks, ...$availability['conflicts']];
        } else {
            $warnings = [...$availability['conflicts'], ...$warnings];
        }
        if (! ShiftCoverage::isAssignedScheduled($candidate)) {
            $warnings[] = 'Este plantão não contará como cobertura: está sem profissional ou não está marcado como Agendado. Confira a situação e preencha o plantão se precisar de cobertura.';
        }

        return ['blocks' => $blocks, 'warnings' => $warnings] + $availability;
    }

    public function preview(Shift $candidate, bool $lockForUpdate = false): array
    {
        $user = $candidate->user_id ? User::query()->find($candidate->user_id) : null;
        $schedule = $candidate->user_id ? $this->schedule($candidate, [$candidate->user_id], $lockForUpdate) : collect();
        $analysis = $this->analyze($candidate, $user, $schedule);

        return $analysis + [
            'period' => $candidate->period()->datedLabel(),
            'professional' => $user?->name ?? 'Sem profissional',
            'fingerprint' => hash('sha256', json_encode([
                app(OperationalAudit::class)->snapshot($candidate), $candidate->id,
                $user?->only(['id', 'name', 'role', 'sector_id', 'category_id']),
                $schedule->map->getAttributes()->all(),
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    public function validateConfirmation(array $preview, ?string $expected): void
    {
        if ($preview['blocks'] !== []) {
            throw ValidationException::withMessages(['user_id' => $preview['blocks']]);
        }
        if (($expected !== null && ! hash_equals($preview['fingerprint'], $expected)) || ($preview['warnings'] !== [] && $expected === null)) {
            throw ValidationException::withMessages(['shift' => 'Os dados ou a disponibilidade precisam ser revisados. Confira os alertas atualizados antes de confirmar.']);
        }
    }
}
