<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->string('email')->nullable()->change();
            $table->string('username')->nullable();
            $table->string('role')->default('recepcionista');
        });

        $usernames = [];

        DB::table('users')->orderBy('id')->get(['id', 'name'])->each(function (object $user) use (&$usernames): void {
            $base = Str::slug(Str::before(trim($user->name), ' '));
            $base = $base !== '' ? $base : 'usuario-'.$user->id;
            $username = $base;
            $suffix = 2;

            while (isset($usernames[$username])) {
                $username = $base.$suffix;
                $suffix++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
            $usernames[$username] = true;
        });

        $initialAdmin = DB::table('users')
            ->where('email', 'admin@bodyevolutiongym.test')
            ->first(['id']);

        if ($initialAdmin !== null) {
            $existingCristian = DB::table('users')
                ->where('username', 'cristian')
                ->where('id', '!=', $initialAdmin->id)
                ->first(['id']);

            if ($existingCristian !== null) {
                DB::table('users')
                    ->where('id', $existingCristian->id)
                    ->update(['username' => 'cristian-'.$existingCristian->id]);
            }

            DB::table('users')->where('id', $initialAdmin->id)->update([
                'name' => 'Cristian',
                'username' => 'cristian',
                'role' => 'administrador',
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
            $table->unique('username');
        });

        DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email) WHERE email IS NOT NULL');
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('email')->exists()) {
            throw new RuntimeException('No es posible revertir roles mientras existan usuarios sin correo.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropUnique('users_email_unique');
            $table->dropColumn(['username', 'role']);
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};
