<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\Shift;
use App\Models\ShiftSwap;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Read-only ordering of operational work; approval rules remain in the Actions. */
class ManagerAttention
{
    public static function uncovered(CarbonInterface $now): Builder
    {
        $day = $now->toDateString();
        $time = $now->format('H:i:s');

        return Shift::query()->where('status', ShiftStatus::Uncovered)
            ->where(function ($query) use ($now, $day, $time): void {
                $query->whereDate('end_date', '>', $day)
                    ->orWhere(fn ($query) => $query->whereDate('end_date', $day)->whereRaw('TIME(end_time) > ?', [$time]))
                    ->orWhere(function ($query) use ($now, $day, $time): void {
                        $query->whereNull('end_date')->where(function ($query) use ($now, $day, $time): void {
                            $query->whereDate('date', '>', $day)
                                ->orWhere(fn ($query) => $query->whereDate('date', $day)->where(fn ($query) => $query
                                    ->whereRaw('TIME(end_time) <= TIME(start_time)')->orWhereRaw('TIME(end_time) > ?', [$time])))
                                ->orWhere(fn ($query) => $query->whereDate('date', $now->copy()->subDay())->whereRaw('TIME(end_time) <= TIME(start_time)')->whereRaw('TIME(end_time) > ?', [$time]));
                        });
                    });
            });
    }

    public function queue(CarbonInterface $now, int $days): LengthAwarePaginator
    {
        $until = $now->copy()->startOfDay()->addDays($days)->toDateString();
        $uncovered = self::uncovered($now)->whereDate('date', '<', $until)
            ->selectRaw("id, 'uncovered' as kind, 0 as risk, DATE(date) as shift_date, SUBSTR(start_time, 1, 5) as shift_time, created_at");

        // A mutual swap is due at its earlier shift, even when that is the counterpart.
        $earlier = 'counterpart.id IS NOT NULL AND (DATE(counterpart.date) < DATE(shifts.date) OR (DATE(counterpart.date) = DATE(shifts.date) AND counterpart.start_time < shifts.start_time))';
        $date = "CASE WHEN $earlier THEN DATE(counterpart.date) ELSE DATE(shifts.date) END";
        $time = "CASE WHEN $earlier THEN SUBSTR(counterpart.start_time, 1, 5) ELSE SUBSTR(shifts.start_time, 1, 5) END";
        $swaps = ShiftSwap::query()->join('shifts', 'shifts.id', '=', 'shift_swaps.shift_id')
            ->leftJoin('shifts as counterpart', 'counterpart.id', '=', 'shift_swaps.substitute_shift_id')
            ->where('shift_swaps.status', ShiftSwapStatus::PendingManager)
            ->whereRaw("($date) < ?", [$until])
            ->selectRaw("shift_swaps.id, 'swap' as kind, 1 as risk, ($date) as shift_date, ($time) as shift_time, shift_swaps.created_at");

        $queue = DB::query()->fromSub($uncovered->toBase()->unionAll($swaps->toBase()), 'attention')
            ->orderBy('risk')->orderBy('shift_date')->orderBy('shift_time')->orderBy('created_at')->orderBy('id')
            ->paginate(8);

        $rows = $queue->getCollection();
        $shifts = Shift::query()->with(['sector', 'category', 'latestAbsence.user'])
            ->whereIn('id', $rows->where('kind', 'uncovered')->pluck('id'))->get()->keyBy('id');
        $requests = ShiftSwap::query()->with(['shift.sector', 'shift.category', 'substituteShift.sector', 'substituteShift.category', 'requester', 'substitute'])
            ->whereIn('id', $rows->where('kind', 'swap')->pluck('id'))->get()->keyBy('id');

        $queue->setCollection($rows->map(function ($row) use ($shifts, $requests) {
            $row->record = $row->kind === 'uncovered' ? $shifts[$row->id] : $requests[$row->id];

            return $row;
        }));

        return $queue;
    }
}
