<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ShiftReallocation
{
    public function shiftSignature(Shift $shift): string
    {
        $attributes = $shift->getAttributes();
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    public function compatibleProfessionals(Shift $shift): Builder
    {
        return app(ShiftAssignmentAnalysis::class)->compatibleProfessionals($shift);
    }

    public function schedule(Shift $shift, array $ids, bool $lockForUpdate = false): Collection
    {
        return app(ShiftAssignmentAnalysis::class)->schedule($shift, $ids, $lockForUpdate);
    }

    public function availability(Shift $shift, User $user, Collection $schedule): array
    {
        return app(ShiftAssignmentAnalysis::class)->availability($shift, $user, $schedule);
    }

    public function candidates(Shift $shift): array
    {
        $users = $this->compatibleProfessionals($shift)->orderBy('name')->orderBy('id')->get();
        $schedule = $this->schedule($shift, $users->modelKeys());
        $eligible = [];
        $unavailable = 0;
        foreach ($users as $user) {
            $availability = $this->availability($shift, $user, $schedule);
            if ($availability['conflicts'] !== []) {
                $unavailable++;

                continue;
            }
            $eligible[] = ['id' => $user->id, 'name' => $user->name, 'doubles' => $availability['doubles']];
        }

        return ['professionals' => $eligible, 'unavailable' => $unavailable];
    }

    public function preview(Shift $shift, User $user, bool $lockForUpdate = false): array
    {
        if (app(ShiftAssignmentAnalysis::class)->compatibility($user, $shift) !== []) {
            throw ValidationException::withMessages(['reallocationUserId' => 'O profissional não pertence mais ao setor e à categoria deste plantão ou não possui perfil Profissional.']);
        }
        $schedule = $this->schedule($shift, [$user->id], $lockForUpdate);
        $assignment = clone $shift;
        $assignment->user_id = $user->id;
        $assignment->status = ShiftStatus::Scheduled;
        $availability = app(ShiftAssignmentAnalysis::class)->analyze($assignment, $user, $schedule, overlapBlocks: true);
        if ($availability['blocks'] !== []) {
            throw ValidationException::withMessages(['reallocationUserId' => $availability['blocks']]);
        }

        return [
            'name' => $user->name,
            'doubles' => $availability['doubles'],
            'blocks' => $availability['blocks'],
            'warnings' => $availability['warnings'],
            'fingerprint' => hash('sha256', json_encode([
                $this->shiftSignature($shift), $user->only(['id', 'name', 'role', 'sector_id', 'category_id']),
                $schedule->map->getAttributes()->all(),
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    public function snapshot(Shift $shift): array
    {
        $shift->loadMissing(['user', 'sector', 'category']);

        return [
            'id' => $shift->id,
            'date' => $shift->date->toDateString(), 'start_time' => $shift->start_time,
            'end_time' => $shift->end_time, 'end_date' => $shift->end_date?->toDateString(),
            'sector_id' => $shift->sector_id, 'sector' => $shift->sector->name,
            'category_id' => $shift->category_id, 'category' => $shift->category->name,
            'user_id' => $shift->user_id, 'professional' => $shift->user?->name,
            'status' => $shift->status->value,
        ];
    }
}
