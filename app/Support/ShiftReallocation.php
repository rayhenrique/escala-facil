<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
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
        return User::query()->where('role', UserRole::Professional)
            ->where('sector_id', $shift->sector_id)->where('category_id', $shift->category_id);
    }

    public function schedule(Shift $shift, array $ids, bool $lockForUpdate = false): Collection
    {
        return Shift::query()->whereIn('user_id', $ids)->where('status', ShiftStatus::Scheduled)
            ->whereKeyNot($shift->id)
            ->whereDate('date', '<=', $shift->period()->end->addDay()->toDateString())
            ->where(fn ($query) => $query->whereDate('end_date', '>=', $shift->period()->start->subDay()->toDateString())
                ->orWhere(fn ($legacy) => $legacy->whereNull('end_date')->whereDate('date', '>=', $shift->period()->start->subDay()->toDateString())))
            ->orderBy('id')->when($lockForUpdate, fn ($query) => $query->lockForUpdate())->get();
    }

    public function availability(Shift $shift, User $user, Collection $schedule): array
    {
        $conflicts = [];
        $doubles = [];
        foreach ($schedule->where('user_id', $user->id) as $other) {
            if ($shift->period()->overlaps($other->period())) {
                $conflicts[] = 'Conflito com o plantão #'.$other->id.': '.$other->period()->datedLabel();
            } elseif ($shift->period()->overlaps($other->period(), includeAdjacent: true)) {
                $doubles[] = 'Dobra com o plantão #'.$other->id.': '.$other->period()->datedLabel().'. Confira se a dobra é autorizada.';
            }
        }

        return ['conflicts' => $conflicts, 'doubles' => $doubles];
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
        if (! $this->compatibleProfessionals($shift)->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages(['reallocationUserId' => 'O profissional não pertence mais ao setor e à categoria deste plantão ou não possui perfil Profissional.']);
        }
        $schedule = $this->schedule($shift, [$user->id], $lockForUpdate);
        $availability = $this->availability($shift, $user, $schedule);
        if ($availability['conflicts'] !== []) {
            throw ValidationException::withMessages(['reallocationUserId' => $availability['conflicts']]);
        }

        return [
            'name' => $user->name,
            'doubles' => $availability['doubles'],
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
