<?php

namespace Tests\Unit;

use App\Support\FonctionRoles;
use PHPUnit\Framework\TestCase;

/**
 * La table des correspondances décide de deux choses : les privilèges par
 * défaut d'une fonction (FonctionPermissionSeeder) et le fait qu'un compte
 * « exerce une fonction d'enseignement » (User::estEnseignant), dont dépend
 * l'accès à la saisie des notes. Un libellé non reconnu laisse l'agent sans
 * privilège et hors de l'espace enseignant.
 */
class FonctionRolesTest extends TestCase
{
    public function test_les_libelles_anglais_du_referentiel_sont_reconnus(): void
    {
        $this->assertSame('enseignant', FonctionRoles::role('Teacher'));
        $this->assertSame('econome', FonctionRoles::role('Bursar'));
        $this->assertSame('admin_ecole', FonctionRoles::role('Headmaster'));
        $this->assertSame('censeur_sg', FonctionRoles::role('Vice-Principal'));
        $this->assertSame('surveillant_general', FonctionRoles::role('Discipline Master'));
        $this->assertSame('infirmier', FonctionRoles::role('Nurse'));
        $this->assertSame('chauffeur', FonctionRoles::role('Driver'));
    }

    public function test_les_variantes_francaises_d_enseignant_sont_reconnues(): void
    {
        foreach (['Enseignant', 'Enseignante', 'PROFESSEUR', 'Instituteur', 'Institutrice'] as $libelle) {
            $this->assertSame('enseignant', FonctionRoles::role($libelle), $libelle);
        }
    }

    /** Une fonction du fichier du personnel qui n'ouvre aucun accès. */
    public function test_une_fonction_sans_acces_reste_sans_role(): void
    {
        $this->assertNull(FonctionRoles::role('Documentaliste'));
        $this->assertNull(FonctionRoles::role('Librarian'));
    }

    public function test_un_libelle_inconnu_ou_vide_ne_donne_aucun_role(): void
    {
        $this->assertNull(FonctionRoles::role('Maître-nageur'));
        $this->assertNull(FonctionRoles::role(''));
        $this->assertNull(FonctionRoles::role(null));
    }
}
