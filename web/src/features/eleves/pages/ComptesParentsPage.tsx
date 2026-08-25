import { useEffect, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { KeyRound, FileDown, Users2, Check, X, Ban, Trash2, UserX } from 'lucide-react'
import { fetchTuteurs, creerCompteParent, fetchTuteursSansCompte, assurerComptesParentChunk, basculerAccesParent, supprimerCompteParent, supprimerTuteur, type TuteurCompte } from '@/features/eleves/api'
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
  const [recherche, setRecherche] = useState('')
  const [rechercheDebounced, setRechercheDebounced] = useState('')
  const [ouvertureEnCours, setOuvertureEnCours] = useState<number | null>(null)
  const [lotEnCours, setLotEnCours] = useState(false)
  const [lotProgres, setLotProgres] = useState<{ traites: number; total: number } | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  // La recherche doit porter sur tous les tuteurs de l'établissement, pas
  // seulement sur la page de 50 déjà chargée — elle part donc au serveur
  // (`GET /tuteurs?search=`), avec un court débounce pour ne pas déclencher
  // une requête à chaque frappe.
  useEffect(() => {
    const id = setTimeout(() => {
      setRechercheDebounced(recherche)
      setPage(1)
    }, 300)
    return () => clearTimeout(id)
  }, [recherche])

  const { data, isLoading, isError } = useQuery({
    queryKey: ['tuteurs', { page, sansCompteSeulement, rechercheDebounced }],
    queryFn: () =>
      fetchTuteurs({
        page,
        sans_compte: sansCompteSeulement || undefined,
        search: rechercheDebounced || undefined,
        per_page: 50,
      }),
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

  const supprimerAccesSelection = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return
    const ok = await confirmer({
      titre: `Supprimer l’accès de ${ids.length} compte(s) parent(s) ?`,
      message: 'Les fiches des parents et leurs enfants seront conservées, seul l’accès au portail sera supprimé.',
      action: 'Supprimer',
    })
    if (!ok) return
    try {
      await Promise.all(ids.map((id) => supprimerCompteParent(id)))
      setSelectedIds(new Set())
      invalider()
      succes(`Accès supprimé pour ${ids.length} compte(s) parent(s).`)
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerTuteurs = async (ids: number[]) => {
    if (ids.length === 0) return
    const ok = await confirmer({
      titre: ids.length === 1 ? 'Supprimer ce tuteur ?' : `Supprimer ${ids.length} tuteur(s) ?`,
      message:
        'La fiche du tuteur, son lien avec ses enfants, son accès au portail s’il en a un, et son historique propre (justifications, préinscriptions, demandes de modification) seront définitivement supprimés. Les fiches des enfants ne sont pas concernées.',
      action: 'Supprimer',
    })
    if (!ok) return
    try {
      await Promise.all(ids.map((id) => supprimerTuteur(id)))
      setSelectedIds((actuels) => {
        const suivants = new Set(actuels)
        ids.forEach((id) => suivants.delete(id))
        return suivants
      })
      invalider()
      succes(ids.length === 1 ? 'Tuteur supprimé.' : `${ids.length} tuteur(s) supprimé(s).`)
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

  // Petits lots plutôt qu'un seul envoi : `Hash::make()` (bcrypt) est
  // délibérément coûteux, et plusieurs centaines de tuteurs dans une seule
  // requête dépassaient le délai d'exécution du serveur (408) sur un gros
  // établissement.
  const TAILLE_LOT = 25

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
      const ids = await fetchTuteursSansCompte()
      setLotProgres({ traites: 0, total: ids.length })

      let crees = 0
      const ignores: { tuteur: string; motif: string }[] = []

      for (let i = 0; i < ids.length; i += TAILLE_LOT) {
        const lot = ids.slice(i, i + TAILLE_LOT)
        const resultat = await assurerComptesParentChunk(lot)
        crees += resultat.crees
        ignores.push(...resultat.ignores)
        setLotProgres({ traites: Math.min(i + TAILLE_LOT, ids.length), total: ids.length })
      }

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
      setLotProgres(null)
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
          <button
            type="button"
            title="Supprimer ce tuteur"
            onClick={() => supprimerTuteurs([t.id])}
            className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-600"
          >
            <Trash2 className="h-4 w-4" />
          </button>
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
              {lotEnCours
                ? lotProgres && lotProgres.total > 0
                  ? `Ouverture… ${lotProgres.traites}/${lotProgres.total}`
                  : 'Ouverture en cours…'
                : 'Ouvrir tous les accès manquants'}
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
          terme={recherche}
          onTermeChange={setRecherche}
          messageVide="Aucun tuteur pour cet établissement."
          largeurMin={760}
          // La pagination se fait déjà côté serveur (Précédent/Suivant plus
          // bas, sur data.pagination) : sans ça, DataTable redécoupait la
          // page de 50 en pages de 15 par-dessus, doublant l'affichage.
          parPage={0}
          outils={
            <div className="flex flex-wrap items-center gap-3">
              {selectedIds.size > 0 && (
                <>
                  <Button variant="danger" onClick={() => supprimerTuteurs(Array.from(selectedIds))}>
                    <Trash2 className="h-4 w-4" />
                    Supprimer les tuteurs ({selectedIds.size})
                  </Button>
                  <Button variant="secondary" onClick={supprimerAccesSelection}>
                    <UserX className="h-4 w-4" />
                    Supprimer l’accès ({selectedIds.size})
                  </Button>
                </>
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
