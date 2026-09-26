<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class ApplicationVersion
{
    public function current(): string
    {
        return (string) config('version.current');
    }

    /** @return array{version: string, date: string, title: string, summary: string, highlights: array<int, string>} */
    public function currentRelease(): array
    {
        $release = $this->releases()->firstWhere('version', $this->current());

        if (! $release) {
            throw new RuntimeException('A versão atual não está registrada no histórico de lançamentos.');
        }

        return $release;
    }

    /** @return Collection<int, array{version: string, date: string, title: string, summary: string, highlights: array<int, string>}> */
    public function releases(): Collection
    {
        return collect(config('version.releases', []));
    }

    public function isUnseenBy(User $user): bool
    {
        return $user->last_seen_version !== $this->current();
    }
}
