<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const DEFAULT_EMAIL = 'admin@elites-school.test';

    public function up(): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $email = (string) env('SUPER_ADMIN_EMAIL', self::DEFAULT_EMAIL);
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'is_active' => true,
                'doit_changer_mot_de_passe' => true,
            ],
        );

        $user->assignRole($role);
    }

    public function down(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', self::DEFAULT_EMAIL);
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->removeRole('super_admin');
            $user->delete();
        }
    }
};
