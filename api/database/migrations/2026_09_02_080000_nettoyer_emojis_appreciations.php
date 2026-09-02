<?php

use App\Models\Appreciation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Un émoji collé depuis le sélecteur Windows traîne souvent un sélecteur
     * de variante (U+FE0F) : invisible au clavier, mais sans glyphe dans la
     * police PDF du bulletin, où il s'affiche en tofu juste après l'émoji.
     * Les nouvelles saisies sont déjà nettoyées (cf. Appreciation::setEmojiAttribute) —
     * ceci ne fait que rattraper ce qui était déjà enregistré.
     */
    public function up(): void
    {
        Appreciation::whereNotNull('emoji')->get(['id', 'emoji'])->each(
            fn (Appreciation $appreciation) => $appreciation->update(['emoji' => $appreciation->emoji])
        );
    }

    /** Nettoyage non destructif — rien à défaire. */
    public function down(): void {}
};
