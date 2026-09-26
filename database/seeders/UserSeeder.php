<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => '一般ユーザー1', 'email' => 'user1@example.com', 'admin_status' => false],
            ['name' => '一般ユーザー2', 'email' => 'user2@example.com', 'admin_status' => false],
            ['name' => '管理者ユーザー', 'email' => 'user3@example.com', 'admin_status' => true],
        ];

        collect($users)->each(fn (array $user): User => User::forceCreate([
            ...$user,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]));
    }
}
