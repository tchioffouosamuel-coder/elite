<?php

namespace App\Services\Push;

interface PushDriver
{
    /**
     * @param  list<string>  $jetons  jetons FCM des appareils visés
     * @param  array<string, string>  $donnees  charge utile lue par l'app (écran à ouvrir, id concerné…)
     * @return int  nombre d'envois acceptés
     */
    public function envoyer(array $jetons, string $titre, string $message, array $donnees = []): int;
}
