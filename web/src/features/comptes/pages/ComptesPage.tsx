import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Users, History, KeyRound, AlertTriangle } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { fetchComptesUtilisateurs, type CompteUtilisateur, type TypeCompte } from '@/features/comptes/api'
import { ReinitialiserMotDePasseModal } from '@/features/comptes/pages/ReinitialiserMotDePasseModal'
import { ActiviteCompteModal } from '@/features/comptes/pages/ActiviteCompteModal'

const LIBELLE_TYPE: Record<TypeCompte, string> = {
  personnel: 'Personnel',
  parent: 'Parent',
  super_admin: 'Super admin',
  autre: 'Autre',
}

function formaterDate(iso: string | null): string {
  if (!iso) return 'Jamais'
  return new Date(iso).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

/**
 * Administration des comptes utilisateurs, réservée au super administrateur.
 *
 * Réunit tout compte de connexion — personnel, parent, super admin — quelle
 * que soit la fiche qu'il représente : c'est le seul écran où l'on peut
 * consulter l'activité d'un compte ou lui fixer un nouveau mot de passe sans
 * passer par la gestion propre à chaque type de fiche.
 */
export function ComptesPage() {
  const [reinitialisationPour, setReinitialisationPour] = useState<CompteUtilisateur | null>(null)
  const [activitePour, setActivitePour] = useState<CompteUtilisateur | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['comptes-utilisateurs'],
    queryFn: fetchComptesUtilisateurs,
  })

  const colonnes: Colonne<CompteUtilisateur>[] = [
    {
      cle: 'compte',
      entete: 'Compte',
      largeur: '220px',
      valeur: (c) => `${c.nom} ${c.email ?? ''} ${c.phone ?? ''}`,
      cellule: (c) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{c.nom}</div>
          <div className="truncate text-xs text-navy-400">{c.email ?? c.phone ?? '—'}</div>
        </div>
      ),
    },
    {
      cle: 'type',
      entete: 'Type',
      largeur: '110px',
      valeur: (c) => LIBELLE_TYPE[c.type],
      cellule: (c) => (
        <Badge tone={c.type === 'super_admin' ? 'purple' : c.type === 'parent' ? 'blue' : 'neutral'}>
          {LIBELLE_TYPE[c.type]}
        </Badge>
      ),
    },
    {
      cle: 'role',
      entete: 'Rôle',
      valeur: (c) => c.role ?? '',
      cellule: (c) => <span className="text-navy-600">{c.role ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'school',
      entete: 'École',
      valeur: (c) => c.school?.name ?? '',
      cellule: (c) => <span className="text-navy-600">{c.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: 'Statut',
      cellule: (c) => (
        <div className="flex flex-wrap gap-1">
          <Badge tone={c.est_actif ? 'green' : 'red'}>{c.est_actif ? 'Actif' : 'Désactivé'}</Badge>
          {c.doit_changer_mot_de_passe && <Badge tone="gold">Doit changer son mot de passe</Badge>}
        </div>
      ),
    },
    {
      cle: 'derniere_connexion',
      entete: 'Dernière connexion',
      largeur: '150px',
      valeur: (c) => c.derniere_connexion ?? '',
      cellule: (c) => <span className="text-xs tabular-nums text-navy-400">{formaterDate(c.derniere_connexion)}</span>,
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: '',
      sticky: 'right',
      largeur: '110px',
      cellule: (c) => (
        <div className="flex justify-end gap-1.5">
          <Button size="sm" variant="secondary" title="Activité du compte" onClick={() => setActivitePour(c)}>
            <History className="h-3.5 w-3.5" />
          </Button>
          <Button size="sm" title="Réinitialiser le mot de passe" onClick={() => setReinitialisationPour(c)}>
            <KeyRound className="h-3.5 w-3.5" />
          </Button>
        </div>
      ),
    },
  ]

  const desactives = data?.filter((c) => !c.est_actif).length ?? 0
  const doiventChanger = data?.filter((c) => c.doit_changer_mot_de_passe).length ?? 0

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Comptes utilisateurs"
        sousTitre="Tous les comptes de connexion de l'établissement — personnel, parents, super administrateurs."
        icon={Users}
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-3">
            <StatCard label="Comptes" value={data.length} icon={Users} accent="navy" />
            <StatCard
              label="Désactivés"
              value={desactives}
              icon={AlertTriangle}
              accent={desactives > 0 ? 'red' : 'green'}
            />
            <StatCard
              label="Mot de passe à changer"
              value={doiventChanger}
              icon={KeyRound}
              accent={doiventChanger > 0 ? 'gold' : 'green'}
              hint="à la prochaine connexion"
            />
          </div>

          <DataTable
            colonnes={colonnes}
            lignes={data}
            cleLigne={(c) => c.id}
            placeholderRecherche="Rechercher un compte…"
            messageVide="Aucun compte utilisateur."
            largeurMin={700}
          />
        </>
      )}

      {reinitialisationPour && (
        <ReinitialiserMotDePasseModal
          compte={reinitialisationPour}
          onClose={() => setReinitialisationPour(null)}
          onReinitialise={() => setReinitialisationPour(null)}
        />
      )}

      {activitePour && <ActiviteCompteModal compte={activitePour} onClose={() => setActivitePour(null)} />}
    </div>
  )
}
