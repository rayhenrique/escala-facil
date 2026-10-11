<?php

namespace App\Support;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Models\Absence;
use App\Models\OperationalAuditEvent;
use App\Models\ShiftSwap;
use Illuminate\Support\Collection;

/** Read-only review shared by the Policy, UI and locked confirmation. */
class ShiftSwapReversion
{
    public const BINDINGS = ['shift_id', 'substitute_shift_id', 'requester_id', 'substitute_id'];

    public function approvalState(ShiftSwap $swap): array
    {
        $swap->loadMissing(['shift', 'substituteShift']);
        $before = [];
        foreach ([$swap->shift, $swap->substituteShift] as $shift) {
            if ($shift) {
                $before[$shift->id] = app(OperationalAudit::class)->snapshot($shift);
            }
        }

        return $this->state($swap, $before);
    }

    private function state(ShiftSwap $swap, array $before): array
    {
        $after = $before;
        foreach ([$swap->shift_id => $swap->substitute_id, $swap->substitute_shift_id => $swap->requester_id] as $id => $owner) {
            if ($id && isset($after[$id])) {
                $after[$id]['user_id'] = $owner;
                $after[$id]['status'] = ShiftStatus::Scheduled->value;
            }
        }

        return ['bindings' => $swap->only(self::BINDINGS), 'before' => $before, 'after' => $after];
    }

    /** Legacy approvals are usable only with a complete append-only creation/change trail. */
    private function legacyState(ShiftSwap $swap, OperationalAuditEvent $approval): ?array
    {
        $events = OperationalAuditEvent::query()->where('entity_type', 'swap')->where('entity_id', $swap->id)
            ->where('id', '<', $approval->id)->orderBy('id')->get();
        if ($events->first()?->action !== 'created') {
            return null;
        }
        $bindings = array_fill_keys(self::BINDINGS, null);
        foreach ($events as $event) {
            foreach (self::BINDINGS as $field) {
                if (isset($event->changes[$field])) {
                    $bindings[$field] = $event->changes[$field]['after'];
                }
            }
        }
        if ($bindings !== $swap->only(self::BINDINGS)) {
            return null;
        }
        $before = [];
        foreach (array_filter([$swap->shift_id, $swap->substitute_shift_id]) as $id) {
            $events = OperationalAuditEvent::query()->where('entity_type', 'shift')->where('entity_id', $id)
                ->where('id', '<', $approval->id)->orderBy('id')->get();
            if ($events->first()?->action !== 'created') {
                return null;
            }
            $snapshot = ['end_date' => null];
            foreach ($events as $event) {
                foreach ($event->changes as $field => $change) {
                    $snapshot[$field] = $change['after'];
                }
            }
            foreach (['date', 'start_time', 'end_time', 'sector_id', 'category_id', 'user_id', 'status'] as $field) {
                if (! isset($snapshot[$field])) {
                    return null;
                }
            }
            $before[$id] = $snapshot;
        }

        return $this->state($swap, $before);
    }

    public function inspect(ShiftSwap $swap, ?Collection $lockedSchedule = null): array
    {
        $swap->loadMissing(['shift.sector', 'shift.category', 'shift.user', 'substituteShift.sector', 'substituteShift.category', 'substituteShift.user', 'requester', 'substitute']);
        $blocks = [];
        $warnings = [];
        $rows = [];
        if ($swap->status !== ShiftSwapStatus::Approved) {
            $blocks[] = 'Este pedido não está aprovado. Atualize o histórico para conferir sua situação.';
        }
        $ids = array_values(array_filter([$swap->shift_id, $swap->substitute_shift_id]));
        if (count(array_unique($ids)) !== count($ids) || $swap->requester_id === $swap->substitute_id) {
            $blocks[] = 'Os vínculos deste pedido são inconsistentes. Solicite análise administrativa.';
        }
        $approvals = OperationalAuditEvent::query()->where('entity_type', 'swap')->where('entity_id', $swap->id)
            ->where('action', 'approved')->orderBy('id')->get();
        $approval = $approvals->count() === 1 ? $approvals->first() : null;
        $state = $approval ? ($approval->changes['approval_state']['after'] ?? $this->legacyState($swap, $approval)) : null;
        if (! $state || ($state['bindings'] ?? null) !== $swap->only(self::BINDINGS)) {
            $blocks[] = 'Não há evidência completa e confiável da aprovação original ou os vínculos foram alterados. Compare os comprovantes na análise administrativa; nenhuma atribuição será restaurada automaticamente.';
            $state = null;
        }
        $later = $approval ? OperationalAuditEvent::query()->where('entity_type', 'shift')->whereIn('entity_id', $ids)
            ->where('id', '>', $approval->id)->where('operation_id', '!=', $approval->operation_id)->orderBy('id')->limit(20)->get() : collect();
        if ($later->isNotEmpty()) {
            $blocks[] = 'Existem alterações posteriores à aprovação nos plantões envolvidos (inclusive realocações ou trocas encadeadas). Consulte o histórico operacional e analise a correção administrativa.';
        }
        $pending = ShiftSwap::query()->whereKeyNot($swap->id)->whereIn('status', [ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager])
            ->where(fn ($q) => $q->whereIn('shift_id', $ids)->orWhereIn('substitute_shift_id', $ids))->orderBy('id')->limit(20)->get();
        if ($pending->isNotEmpty()) {
            $blocks[] = 'Há solicitações pendentes que dependem de um dos plantões: '.implode(', ', $pending->map(fn ($item) => '#'.$item->id)->all()).'. Resolva esses pedidos antes de rever a reversão.';
        }
        $absenceIds = Absence::query()->whereIn('shift_id', $ids)->orderBy('id')->pluck('id')->all();
        if ($absenceIds !== []) {
            $blocks[] = 'Existe registro de ausência em um dos plantões. Confira as ausências na análise administrativa antes de qualquer correção.';
        }
        $assignment = app(ShiftAssignmentAnalysis::class);
        $schedules = [];
        foreach ([[$swap->shift, $swap->requester, $swap->substitute_id], [$swap->substituteShift, $swap->substitute, $swap->requester_id]] as [$shift, $original, $expectedOwner]) {
            if (! $shift) {
                if ($rows === [] || $swap->substitute_shift_id) {
                    $blocks[] = 'Um plantão vinculado não está disponível. Verifique os registros na análise administrativa.';
                }

                continue;
            }
            $current = app(OperationalAudit::class)->snapshot($shift);
            $originalState = $state['before'][$shift->id] ?? null;
            if ($state && (! $originalState || $originalState['user_id'] !== $original?->id || $originalState['status'] !== ShiftStatus::Scheduled->value)) {
                $blocks[] = 'A atribuição original do plantão #'.$shift->id.' não está comprovada. Consulte a análise administrativa.';
            }
            if ($state && $current != ($state['after'][$shift->id] ?? null)) {
                $blocks[] = 'O plantão #'.$shift->id.' foi alterado depois da aprovação (profissional, horário, setor, categoria ou situação). Não restaure o registro sem análise administrativa.';
            }
            if ($shift->user_id !== $expectedOwner) {
                $blocks[] = 'A atribuição atual do plantão #'.$shift->id.' não corresponde à troca aprovada. Confira realocações e trocas encadeadas.';
            }
            if ($shift->status !== ShiftStatus::Scheduled) {
                $blocks[] = 'O plantão #'.$shift->id.' não está agendado. Confira sua situação na análise administrativa.';
            }
            if ($shift->period()->start->lessThanOrEqualTo(now())) {
                $blocks[] = 'O plantão #'.$shift->id.' já começou ou sua data já passou. É necessária análise de correção administrativa; o histórico e a escala não serão sobrescritos.';
            }
            $candidate = clone $shift;
            $candidate->user_id = $original?->id;
            if (! $original || $original->requires_activation) {
                $blocks[] = 'O profissional original do plantão #'.$shift->id.' não tem cadastro ativo para a restauração.';
            } else {
                $start = $candidate->period()->start->subDay()->toDateString();
                $end = $candidate->period()->end->addDay()->toDateString();
                $schedule = ($lockedSchedule ?? $assignment->schedule($candidate, [$original->id]))
                    ->where('user_id', $original->id)->whereNotIn('id', $ids)
                    ->filter(fn ($other) => $other->status === ShiftStatus::Scheduled
                        && $other->date->toDateString() <= $end
                        && ($other->end_date?->toDateString() ?? $other->date->toDateString()) >= $start)->values();
                $schedules[$shift->id] = $schedule->map(fn ($other) => ['id' => $other->id] + app(OperationalAudit::class)->snapshot($other))->all();
                $analysis = $assignment->analyze($candidate, $original, $schedule, overlapBlocks: true);
                foreach ($analysis['blocks'] as $block) {
                    $blocks[] = 'Plantão #'.$shift->id.': '.$block;
                }
                $warnings = [...$warnings, ...$analysis['warnings']];
            }
            $rows[] = ['id' => $shift->id, 'period' => $shift->period()->datedLabel(), 'location' => $shift->sector->name.' · '.$shift->category->name,
                'current' => $shift->user?->name ?? 'Sem profissional alocado', 'current_status' => $shift->status->label(),
                'after' => $original?->name ?? 'Cadastro indisponível', 'after_id' => $original?->id, 'state' => $current];
        }
        // Ignore unrelated schedule rows so the preview and locked confirmation
        // sign exactly the same relevant intervals, even when the lock set is broader.
        foreach ($schedules as $id => $schedule) {
            $candidate = $id === $swap->shift_id ? $swap->shift : $swap->substituteShift;
            $start = $candidate->period()->start->subDay()->toDateString();
            $end = $candidate->period()->end->addDay()->toDateString();
            $schedules[$id] = array_values(array_filter($schedule, fn ($other) => $other['date'] <= $end && ($other['end_date'] ?? $other['date']) >= $start));
        }
        $blocks = array_values(array_unique($blocks));

        return ['rows' => $rows, 'blocks' => $blocks, 'warnings' => array_values(array_unique($warnings)),
            'can_revert' => $blocks === [], 'approval_event_id' => $approval?->id, 'approval_state' => $state,
            'later_events' => $later->map->only(['id', 'entity_id', 'action'])->all(),
            'fingerprint' => hash('sha256', json_encode([$swap->only(['id', 'status', ...self::BINDINGS]), $rows, $blocks, $warnings,
                $approval?->id, $state, $later->pluck('id')->all(), $pending->pluck('id')->all(), $absenceIds,
                $swap->requester?->only(['id', 'name', 'role', 'sector_id', 'category_id', 'requires_activation']),
                $swap->substitute?->only(['id', 'name', 'role', 'sector_id', 'category_id', 'requires_activation']), $schedules], JSON_THROW_ON_ERROR))];
    }
}
