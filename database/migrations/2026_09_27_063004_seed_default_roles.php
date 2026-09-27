<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $superAdminRole = Role::create([
            'name' => 'super-admin',
        ]);

        $superAdminAccount = User::create([
            'uuid' => Str::uuid(),
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => 'password',
        ]);

        $superAdminAccount->assignRole($superAdminRole);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
