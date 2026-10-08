<?php

use App\Models\ModificationEleve;
use App\Models\SyncOutbox;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Les validations/rejets de modifications créées hors-ligne pouvaient se
     * rejouer sur le serveur avec un id local inexistant côté distant. On
     * enrichit aussi les opérations déjà bloquées dans l'outbox locale, pas
     * seulement les futures.
     */
    public function up(): void
    {
        if (! config('sync.local_replica')) {
            return;
        }

        SyncOutbox::query()
            ->whereNull('pushed_at')
            ->where('chemin', 'like', 'modifications-eleves/%')
            ->get()
            ->each(function (SyncOutbox $operation) {
                if (! preg_match('#^modifications-eleves/(\d+)/(valider|rejeter)$#', $operation->chemin, $matches)) {
                    return;
                }

                $corps = $operation->corps ?? [];
                if (isset($corps['__sync']['modification_eleve'])) {
                    return;
                }

                $modification = ModificationEleve::find((int) $matches[1]);
                if (! $modification) {
                    return;
                }

                $corps['__sync'] = [
                    ...(is_array($corps['__sync'] ?? null) ? $corps['__sync'] : []),
                    'modification_eleve' => [
                        'id' => $modification->id,
                        'school_id' => $modification->school_id,
                        'eleve_id' => $modification->eleve_id,
                        'tuteur_id' => $modification->tuteur_id,
                        'donnees' => $modification->donnees ?? [],
                    ],
                ];

                $operation->update(['corps' => $corps]);
            });
    }

    public function down(): void
    {
        //
    }
};
