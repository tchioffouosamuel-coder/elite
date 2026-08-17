<?php

namespace App\Services;

use App\Models\NotificationInterne;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Notifications internes : la cloche affichée dans l'application, à
 * destination du personnel (les parents n'ont pas de compte — eux sont
 * touchés par SMS, via `App\Services\Sms\SmsService` directement, en
 * complément de cet appel plutôt qu'à travers lui).
 */
class NotificationService extends BaseService
{
    /**
     * Notifie en interne chaque utilisateur listé — une ligne par
     * destinataire, pour que chacun marque la sienne comme lue indépendamment
     * des autres.
     *
     * @param  iterable<int>  $userIds
     */
    public function notifier(int $schoolId, iterable $userIds, string $type, string $titre, string $message, ?string $lien = null): void
    {
        $lignes = collect($userIds)->unique()->values()->map(fn (int $userId) => [
            'school_id' => $schoolId,
            'user_id' => $userId,
            'type' => $type,
            'titre' => $titre,
            'message' => $message,
            'lien' => $lien,
            'lu' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($lignes->isEmpty()) {
            return;
        }

        NotificationInterne::insert($lignes->all());
    }

    /**
     * Notifie tout le personnel de l'école qui détient le privilège donné —
     * évite à chaque appelant de recomposer cette requête pour toucher « le
     * censeur et l'administrateur », par exemple.
     */
    public function notifierParPermission(int $schoolId, string $permission, string $type, string $titre, string $message, ?string $lien = null): void
    {
        $userIds = User::where('school_id', $schoolId)
            ->permission($permission)
            ->pluck('id');

        $this->notifier($schoolId, $userIds, $type, $titre, $message, $lien);
    }

    /**
     * @return Collection<int, NotificationInterne>
     */
    public function pourUtilisateur(int $userId, bool $nonLuesSeulement = false): Collection
    {
        return NotificationInterne::pourUtilisateur($userId)
            ->when($nonLuesSeulement, fn ($q) => $q->where('lu', false))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }
}
