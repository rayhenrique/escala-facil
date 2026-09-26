<?php

namespace App\Services;

use App\Enums\AbsenceStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataService
{
    public const BATCH_KEY = 'escala-facil-demo-v1';

    /** @return array<string, int> */
    public function seed(): array
    {
        if (DB::table('demo_data_batches')->where('key', self::BATCH_KEY)->exists()) {
            throw new DomainException('Os dados de demonstração já estão instalados. Use "refresh" para recriá-los.');
        }

        return DB::transaction(function (): array {
            $manifest = [
                'categories' => [],
                'sectors' => [],
                'users' => [],
                'shifts' => [],
                'shift_swaps' => [],
                'absences' => [],
            ];

            $manager = User::query()->where('role', UserRole::Manager)->first();

            if (! $manager) {
                $manager = User::query()->create([
                    'name' => 'Gerente Demonstração',
                    'email' => 'demo.gerente@escalafacil.test',
                    'password' => Hash::make('password'),
                    'role' => UserRole::Manager,
                ]);
                $manifest['users'][] = $manager->id;
            }

            $teams = [
                ['Enfermagem (Demo)', 'Emergência Adulto (Demo)', ['Alice Martins', 'Bruno Rocha', 'Camila Nunes', 'Diego Alves']],
                ['Medicina Intensiva (Demo)', 'UTI Adulto (Demo)', ['Elisa Freitas', 'Fábio Moreira', 'Gabriela Lima', 'Henrique Costa']],
                ['Pediatria (Demo)', 'Unidade Pediátrica (Demo)', ['Isabela Souza', 'João Mendes', 'Karina Ribeiro', 'Lucas Barros']],
                ['Fisioterapia (Demo)', 'Unidade Respiratória (Demo)', ['Mariana Gomes', 'Nicolas Araújo', 'Olívia Castro', 'Paulo Teixeira']],
                ['Técnico de Enfermagem (Demo)', 'Centro Cirúrgico (Demo)', ['Queila Santos', 'Rafael Melo', 'Sofia Cardoso', 'Thiago Fernandes']],
            ];

            $teamRecords = [];
            $password = Hash::make('password');
            $professionalNumber = 1;

            foreach ($teams as [$categoryName, $sectorName, $names]) {
                $category = Category::query()->create(['name' => $categoryName]);
                $sector = Sector::query()->create(['name' => $sectorName]);
                $manifest['categories'][] = $category->id;
                $manifest['sectors'][] = $sector->id;
                $professionals = [];

                foreach ($names as $name) {
                    $professional = User::query()->create([
                        'name' => $name.' (Demo)',
                        'email' => sprintf('demo.profissional.%02d@escalafacil.test', $professionalNumber++),
                        'password' => $password,
                        'role' => UserRole::Professional,
                        'category_id' => $category->id,
                        'sector_id' => $sector->id,
                    ]);
                    $manifest['users'][] = $professional->id;
                    $professionals[] = $professional;
                }

                $teamRecords[] = compact('category', 'sector', 'professionals');
            }

            $swapCandidates = [];
            $shiftNumber = 0;
            $firstDate = CarbonImmutable::today()->subDays(12);

            for ($day = 0; $day < 68; $day++) {
                $date = $firstDate->addDays($day);
                $dailySlots = $day % 2 === 0
                    ? [['07:00', '13:00'], ['13:00', '19:00']]
                    : [['07:00', '19:00']];

                foreach ($dailySlots as $slotIndex => [$startTime, $endTime]) {
                    $teamIndex = ($day + $slotIndex) % count($teamRecords);
                    $team = $teamRecords[$teamIndex];
                    $professional = $team['professionals'][$shiftNumber % count($team['professionals'])];
                    $status = ShiftStatus::Scheduled;
                    $userId = $professional->id;

                    if ($shiftNumber % 17 === 0) {
                        $status = ShiftStatus::AbsenceReported;
                        $userId = null;
                    } elseif ($shiftNumber % 11 === 0) {
                        $status = ShiftStatus::Uncovered;
                        $userId = null;
                    }

                    $shift = Shift::query()->create([
                        'date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'sector_id' => $team['sector']->id,
                        'category_id' => $team['category']->id,
                        'user_id' => $userId,
                        'status' => $status,
                    ]);
                    $manifest['shifts'][] = $shift->id;

                    if ($status === ShiftStatus::AbsenceReported) {
                        $absence = Absence::query()->create([
                            'shift_id' => $shift->id,
                            'user_id' => $professional->id,
                            'reason' => 'Indisponibilidade clínica registrada para demonstração do fluxo.',
                            'attachment_path' => null,
                            'status' => $date->isPast() ? AbsenceStatus::Acknowledged : AbsenceStatus::Pending,
                        ]);
                        $manifest['absences'][] = $absence->id;
                    } elseif ($status === ShiftStatus::Scheduled && $date->greaterThanOrEqualTo(CarbonImmutable::today())) {
                        $swapCandidates[] = [$shift, $professional, $team['professionals']];
                    }

                    $shiftNumber++;
                }
            }

            $swapStatuses = [
                ShiftSwapStatus::PendingProfessional,
                ShiftSwapStatus::PendingManager,
                ShiftSwapStatus::Approved,
                ShiftSwapStatus::Rejected,
            ];

            foreach (array_slice($swapCandidates, 0, 12) as $index => [$shift, $requester, $professionals]) {
                $substitute = collect($professionals)->first(fn (User $user): bool => $user->id !== $requester->id);
                $status = $swapStatuses[$index % count($swapStatuses)];

                if ($status === ShiftSwapStatus::Approved) {
                    $shift->update(['user_id' => $substitute->id]);
                }

                $swap = ShiftSwap::query()->create([
                    'shift_id' => $shift->id,
                    'requester_id' => $requester->id,
                    'substitute_id' => $substitute->id,
                    'status' => $status,
                    'manager_id' => in_array($status, [ShiftSwapStatus::Approved, ShiftSwapStatus::Rejected], true)
                        ? $manager->id
                        : null,
                ]);
                $manifest['shift_swaps'][] = $swap->id;
            }

            DB::table('demo_data_batches')->insert([
                'key' => self::BATCH_KEY,
                'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->summary($manifest);
        });
    }

    /** @return array<string, int> */
    public function clear(): array
    {
        return DB::transaction(function (): array {
            $batch = DB::table('demo_data_batches')
                ->where('key', self::BATCH_KEY)
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                throw new DomainException('Nenhum lote de demonstração está instalado.');
            }

            $manifest = json_decode($batch->manifest, true, flags: JSON_THROW_ON_ERROR);
            $categoryIds = $manifest['categories'] ?? [];
            $sectorIds = $manifest['sectors'] ?? [];
            $userIds = $manifest['users'] ?? [];

            $shiftIds = Shift::query()
                ->whereIn('id', $manifest['shifts'] ?? [])
                ->orWhereIn('category_id', $categoryIds)
                ->orWhereIn('sector_id', $sectorIds)
                ->orWhereIn('user_id', $userIds)
                ->pluck('id');

            $deletedAbsences = Absence::query()
                ->whereIn('id', $manifest['absences'] ?? [])
                ->orWhereIn('shift_id', $shiftIds)
                ->orWhereIn('user_id', $userIds)
                ->delete();

            $deletedSwaps = ShiftSwap::query()
                ->whereIn('id', $manifest['shift_swaps'] ?? [])
                ->orWhereIn('shift_id', $shiftIds)
                ->orWhereIn('requester_id', $userIds)
                ->orWhereIn('substitute_id', $userIds)
                ->orWhereIn('manager_id', $userIds)
                ->delete();

            $deletedShifts = Shift::query()->whereIn('id', $shiftIds)->delete();
            $deletedUsers = User::query()->whereIn('id', $userIds)->delete();
            $deletedCategories = Category::query()->whereIn('id', $categoryIds)->delete();
            $deletedSectors = Sector::query()->whereIn('id', $sectorIds)->delete();

            DB::table('demo_data_batches')->where('id', $batch->id)->delete();

            return [
                'categories' => $deletedCategories,
                'sectors' => $deletedSectors,
                'users' => $deletedUsers,
                'shifts' => $deletedShifts,
                'shift_swaps' => $deletedSwaps,
                'absences' => $deletedAbsences,
            ];
        });
    }

    /** @param array<string, array<int, int>> $manifest
     * @return array<string, int>
     */
    private function summary(array $manifest): array
    {
        return collect($manifest)
            ->map(fn (array $ids): int => count($ids))
            ->all();
    }
}
