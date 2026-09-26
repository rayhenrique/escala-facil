<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = DB::table('categories')->pluck('id', 'name');
        $sectorIds = DB::table('sectors')->pluck('id', 'name');
        $now = now();
        $password = Hash::make('password');

        DB::table('users')->upsert([
            [
                'name' => 'Gerente de Plantões',
                'email' => 'gerente@escalafacil.test',
                'password' => $password,
                'role' => 'manager',
                'category_id' => null,
                'sector_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Ana Silva',
                'email' => 'ana@escalafacil.test',
                'password' => $password,
                'role' => 'professional',
                'category_id' => $categoryIds['Médico Clínico'],
                'sector_id' => $sectorIds['Emergência'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Bruno Costa',
                'email' => 'bruno@escalafacil.test',
                'password' => $password,
                'role' => 'professional',
                'category_id' => $categoryIds['Médico Clínico'],
                'sector_id' => $sectorIds['Emergência'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Carla Oliveira',
                'email' => 'carla@escalafacil.test',
                'password' => $password,
                'role' => 'professional',
                'category_id' => $categoryIds['Enfermeiro Padrão'],
                'sector_id' => $sectorIds['UTI'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Diego Santos',
                'email' => 'diego@escalafacil.test',
                'password' => $password,
                'role' => 'professional',
                'category_id' => $categoryIds['Enfermeiro Padrão'],
                'sector_id' => $sectorIds['UTI'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Elisa Lima',
                'email' => 'elisa@escalafacil.test',
                'password' => $password,
                'role' => 'professional',
                'category_id' => $categoryIds['Médico Clínico'],
                'sector_id' => $sectorIds['UTI'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['email'], [
            'name',
            'password',
            'role',
            'category_id',
            'sector_id',
            'updated_at',
        ]);
    }
}
