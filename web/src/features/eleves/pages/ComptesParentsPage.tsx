import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { KeyRound, FileDown, Users2, Check, X, Ban, Trash2 } from 'lucide-react'
import { fetchTuteurs, creerCompteParent, creerComptesParentLot, basculerAccesParent, supprimerCompteParent, type TuteurCompte } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { ouvrirDocument } from '@/shared/lib/download'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, identifiantsOuverts, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Comptes du portail parent, un tuteur par ligne — le rattrapage pour les
 * familles inscrites avant que le portail n'existe, et le point d'entrée
 * pour ouvrir un accès au coup par coup.
 */
export function ComptesParentsPage() {
  const queryClient = useQueryClient()
  // En mode agrégé (super admin, « Toutes les écoles »), le tableau réunit les
  // tuteurs de tout le complexe : la confirmation doit annoncer ce périmètre-là.
  const ecoleActive = useAuthStore((s) => s.activeSchool())
  const [page, setPage] = useState(1)
  const [sansCompteSeulement, setSansCompteSeulement] = useState(false)
  const [ouvertureEnCours, setOuvertureEnCours] = useState<number | null>(null)
  const [lotEnCours, setLotEnCours] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  const { data, isLoading, isError } = useQuery({
    queryKey: ['tuteurs', { page, sansCompteSeulement }],
    queryFn: () => fetchTuteurs({ page, sans_compte: sansCompteSeulement || undefined, per_page: 50 }),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['tuteurs'] })

  const basculerAcces = async (tuteur: TuteurCompte) => {
    try {
      await basculerAccesParent(tuteur.id)
      invalider()
      succes(tuteur.acces_bloque ? 'Accès parent débloqué.' : 'Accès parent bloqué.')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerSelection = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return
    const ok = await confirmer({
      titre: `Supprimer ${ids.length} compte(s) parent(s) ?`,
      message: 'Les fiches des parents et leurs enfants seront conservées, seul l’accès au portail sera supprimé.',
      action: 'Supprimer',
    })
    if (!ok) return
    try {
      await Promise.all(ids.map((id) => supprimerCompteParent(id)))
      setSelectedIds(new Set())
      invalider()
      succes(`${ids.length} compte(s) parent(s) supprimé(s).`)
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const ouvrirAcces = async (tuteur: TuteurCompte) => {
    setOuvertureEnCours(tuteur.id)
    try {
      const { identifiant, mot_de_passe_provisoire } = await creerCompteParent(tuteur.id)
      identifiantsOuverts(identifiant, mot_de_passe_provisoire)
      invalider()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setOuvertureEnCours(null)
    }
  }

  const ouvrirEnMasse = async () => {
    const ok = await confirmer({
      titre: 'Ouvrir tous les accès manquants ?',
      message: `Un compte sera créé pour chaque tuteur ${ecoleActive ? `de ${ecoleActive.name}` : 'de toutes les écoles affichées'
        } qui a un numéro de téléphone mais pas encore d'accès. Les tuteurs sans numéro seront ignorés.`,
      action: 'Ouvrir les accès',
      destructif: false,
    })
    if (!ok) return

    setLotEnCours(true)
    try {
      const { crees, ignores } = await creerComptesParentLot()
      succes(
        ignores.length > 0
          ? `${crees} accès ouvert(s), ${ignores.length} tuteur(s) ignoré(s) faute de numéro exploitable.`
          : `${crees} accès ouvert(s).`,
      )
      invalider()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setLotEnCours(false)
    }
  }

  const colonnes: Colonne<TuteurCompte>[] = [
    {
      cle: 'selection',
      entete: (
        <input
          type="checkbox"
          checked={(data?.items.length ?? 0) > 0 && selectedIds.size === data?.items.length}
          onChange={() => setSelectedIds(selectedIds.size === data?.items.length ? new Set() : new Set(data?.items.map((t) => t.id)))}
          className="h-4 w-4 rounded border-navy-300"
          aria-label="Tout sélectionner"
        />
      ),
      valeur: () => '',
      cellule: (t) => (
        <input
          type="checkbox"
          checked={selectedIds.has(t.id)}
          onChange={() => setSelectedIds((actuels) => {
            const suivants = new Set(actuels)
            if (suivants.has(t.id)) suivants.delete(t.id)
            else suivants.add(t.id)
            return suivants
          })}
          className="h-4 w-4 rounded border-navy-300"
          aria-label={t.nom_complet}
        />
      ),
      largeur: '48px',
    },
    {
      cle: 'nom',
      entete: 'Nom complet',
      valeur: (t) => t.nom_complet,
      cellule: (t) => <span className="font-semibold text-navy-900">{t.nom_complet}</span>,
    },
    {
      cle: 'telephone',
      entete: 'Téléphone',
      valeur: (t) => t.telephone,
      cellule: (t) => <span className="font-mono text-xs">{t.telephone ?? '—'}</span>,
    },
    {
      cle: 'enfants',
      entete: 'Enfant(s)',
      valeur: (t) => t.enfants.map((e) => e.nom_complet).join(', '),
      cellule: (t) => <span className="text-navy-600">{t.enfants.map((e) => e.nom_complet).join(', ') || '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'statut',
      entete: 'Accès parent',
      valeur: (t) => (t.a_compte ? 'oui' : 'non'),
      cellule: (t) =>
        t.a_compte ? (
          <Badge tone="green">
            {t.acces_bloque ? <Ban className="h-3 w-3" /> : <Check className="h-3 w-3" />}
            {t.acces_bloque ? 'Bloqué' : 'Ouvert'}
          </Badge>
        ) : (
          <Badge tone="neutral">
            <X className="h-3 w-3" />
            Non ouvert
          </Badge>
        ),
    },
    {
      cle: 'actions',
      entete: '',
      cellule: (t) => (
        <div className="flex items-center gap-1">
          {!t.a_compte && (
            <Button size="sm" variant="secondary" disabled={ouvertureEnCours === t.id} onClick={() => ouvrirAcces(t)}>
              <KeyRound className="h-3.5 w-3.5" />
              {ouvertureEnCours === t.id ? 'Ouverture…' : "Ouvrir l'accès"}
            </Button>
          )}
          {t.a_compte && (
            <button
              type="button"
              title={t.acces_bloque ? 'Débloquer l’accès' : 'Bloquer l’accès'}
              onClick={() => basculerAcces(t)}
              className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-600"
            >
              <Ban className="h-4 w-4" />
            </button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Comptes parents"
        sousTitre="Accès au portail parent, par tuteur — identifiant : le numéro de téléphone."
        icon={Users2}
        actions={
          <>
            <Button variant="secondary" onClick={() => ouvrirDocument('/tuteurs/identifiants/pdf')}>
              <FileDown className="h-4 w-4" />
              Identifiants (PDF)
            </Button>
            <Button onClick={ouvrirEnMasse} disabled={lotEnCours}>
              <KeyRound className="h-4 w-4" />
              {lotEnCours ? 'Ouverture en cours…' : 'Ouvrir tous les accès manquants'}
            </Button>
          </>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data.items}
          cleLigne={(t) => t.id}
          placeholderRecherche="Rechercher un tuteur, un numéro…"
          messageVide="Aucun tuteur pour cet établissement."
          largeurMin={760}
          outils={
            <div className="flex flex-wrap items-center gap-3">
              {selectedIds.size > 0 && (
                <Button variant="danger" onClick={supprimerSelection}>
                  <Trash2 className="h-4 w-4" />
                  Supprimer ({selectedIds.size})
                </Button>
              )}
              <label className="flex items-center gap-2 text-sm text-navy-600">
                <input
                  type="checkbox"
                  checked={sansCompteSeulement}
                  onChange={(e) => {
                    setSansCompteSeulement(e.target.checked)
                    setPage(1)
                    setSelectedIds(new Set())
                  }}
                  className="rounded border-navy-300"
                />
                Sans accès seulement
              </label>
            </div>
          }
        />
      )}

      {data && data.pagination.last_page > 1 && (
        <div className="flex justify-center gap-2">
          <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
            Précédent
          </Button>
          <span className="self-center text-xs text-navy-400">
            Page {data.pagination.current_page} / {data.pagination.last_page}
          </span>
          <Button variant="secondary" size="sm" disabled={page >= data.pagination.last_page} onClick={() => setPage((p) => p + 1)}>
            Suivant
          </Button>
        </div>
      )}
    </div>
  )
}
