<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployScriptTest extends TestCase
{
    public function test_script_uses_strict_bash_mode_and_guards_execution_context(): void
    {
        $script = $this->script();

        $this->assertStringStartsWith('#!/usr/bin/env bash', $script);
        $this->assertStringContainsString('set -Eeuo pipefail', $script);
        $this->assertStringContainsString('não como root', $script);
        $this->assertStringContainsString('Arquivo .env não encontrado', $script);
        $this->assertStringContainsString('git status --porcelain', $script);
        $this->assertStringContainsString('git merge --ff-only origin/main', $script);
        $this->assertStringContainsString('git merge-base --is-ancestor HEAD origin/main', $script);
    }

    public function test_validation_runs_before_maintenance_and_build_is_verified_before_publish(): void
    {
        $script = $this->script();

        $down = strpos($script, 'artisan down');
        $validate = strpos($script, 'composer validate --strict');
        $build = strpos($script, 'npm run build');
        $manifestCheck = strpos($script, 'public/build/manifest.json');
        $smokePhase = strpos($script, 'FASE C');

        $this->assertNotFalse($down);
        $this->assertNotFalse($validate);
        $this->assertNotFalse($build);
        $this->assertNotFalse($manifestCheck);
        $this->assertNotFalse($smokePhase);

        $this->assertLessThan($down, $validate, 'composer validate deve rodar antes da manutenção.');
        $this->assertLessThan($smokePhase, $build, 'O build deve concluir antes da fase de fumaça.');
        $this->assertLessThan($smokePhase, $manifestCheck, 'O manifest do build deve ser verificado antes de publicar.');
    }

    public function test_database_backup_runs_before_migrations_with_retention_and_safe_permissions(): void
    {
        $script = $this->script();

        $backup = strpos($script, 'mysqldump');
        $migrate = strpos($script, 'artisan migrate --force');

        $this->assertNotFalse($backup);
        $this->assertNotFalse($migrate);
        $this->assertLessThan($migrate, $backup, 'O backup do banco deve preceder as migrations.');

        $this->assertStringContainsString('DEPLOY_BACKUP_KEEP', $script);
        $this->assertStringContainsString('chmod 600', $script);
        $this->assertStringContainsString('chmod 700', $script);
        $this->assertStringContainsString('clpctl', $script);
    }

    public function test_code_rollback_is_safe_and_never_reverts_migrations_automatically(): void
    {
        $script = $this->script();

        $this->assertStringContainsString('rollback_code', $script);
        $this->assertStringContainsString('git reset --hard', $script);
        $this->assertStringContainsString('migrated', $script);
        $this->assertStringContainsString('NENHUM rollback automático de banco', $script);

        $this->assertStringNotContainsString('migrate:rollback', $script);
        $this->assertStringNotContainsString('migrate:fresh', $script);
        $this->assertStringNotContainsString('migrate:refresh', $script);
        $this->assertStringNotContainsString('db:wipe', $script);
    }

    public function test_prerequisites_disk_and_failure_diagnostics_are_covered(): void
    {
        $script = $this->script();

        foreach (['git', 'node', 'npm', 'curl', 'mysqldump'] as $requirement) {
            $this->assertStringContainsString($requirement, $script);
        }

        $this->assertStringContainsString('df ', $script);
        $this->assertStringContainsString('DEPLOY_MIN_DISK_KB', $script);
        $this->assertStringContainsString('Diagnóstico:', $script);
        $this->assertStringContainsString('Recuperação:', $script);
        $this->assertStringContainsString('deploy.md', $script);
        $this->assertStringContainsString("trap '", $script);
    }

    public function test_deploy_guide_documents_backup_rollback_and_recovery(): void
    {
        $guide = file_get_contents(base_path('deploy.md'));

        $this->assertStringContainsString('backup', strtolower($guide));
        $this->assertStringContainsString('retenção', strtolower($guide));
        $this->assertStringContainsString('rollback', strtolower($guide));
        $this->assertStringContainsString('recuperação', strtolower($guide));
        $this->assertStringContainsString('migrate:rollback', $guide);
        $this->assertStringContainsString('mysqldump', $guide);
    }

    private function script(): string
    {
        return file_get_contents(base_path('scripts/deploy-production.sh'));
    }
}
