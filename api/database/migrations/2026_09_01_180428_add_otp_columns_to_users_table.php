<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mot de passe oublié, par OTP envoyé par e-mail : réservé aux comptes qui
     * en ont un (personnel), un parent se connectant par téléphone (cf.
     * `2026_08_20_090000_allow_phone_login_on_users_table`). Le code est
     * haché comme le mot de passe — jamais stocké en clair — et expire vite,
     * sur le modèle d'un jeton de session.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_code')->nullable()->after('remember_token');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'otp_expires_at', 'otp_attempts']);
        });
    }
};
