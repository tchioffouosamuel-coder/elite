<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit exhaustif : une ligne par requête API (connexion,
 * consultation, création, modification, suppression, export…), alimenté
 * automatiquement par {@see \App\Http\Middleware\JournaliserAudit}.
 *
 * Volontairement distinct de `activity_logs` : ce dernier ne porte que des
 * événements choisis (connexions, inscriptions…) dont dépendent la
 * « dernière connexion » d'un compte, l'activité récente du tableau de bord
 * et les statistiques d'usage parents — les noyer sous chaque consultation
 * fausserait ces écrans et les ralentirait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            // Pas de clé étrangère : une ligne d'audit doit survivre à la
            // suppression du compte ou de l'école qu'elle concerne.
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            // Nom et rôle figés au moment de l'action, comme `activity_logs`.
            $table->string('user_nom')->nullable();
            $table->string('user_role')->nullable();
            $table->string('action', 40);
            $table->string('module', 80)->nullable();
            $table->string('route', 191)->nullable();
            $table->string('methode', 10);
            $table->string('url', 2048);
            $table->json('parametres')->nullable();
            $table->json('donnees')->nullable();
            $table->json('changements')->nullable();
            $table->unsignedSmallInteger('statut_http');
            $table->unsignedInteger('duree_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['school_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index(['module', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
