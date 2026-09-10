import { useEffect, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { KeyRound, FileDown, Users2, Check, X, Ban, Trash2, UserX, RefreshCw, UserPlus } from 'lucide-react'
import { fetchTuteurs, creerCompteParent, fetchTuteursSansCompte, assurerComptesParentChunk, basculerAccesParent, supprimerCompteParent, supprimerTuteur, reinitialiserMotDePasseParent, rattacherEnfantsParent, fetchEleves, type TuteurCompte } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { ouvrirDocument } from '@/shared/lib/download'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
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
  const [rechercheActive, setRechercheActive] = useState('')
  const [ouvertureEnCours, setOuvertureEnCours] = useState<number | null>(null)
  const [reinitialisationEnCours, setReinitialisationEnCours] = useState<number | null>(null)
  const [tuteurEnfants, setTuteurEnfants] = useState<TuteurCompte | null>(null)
  const [rechercheEnfant, setRechercheEnfant] = useState('')
  const [rechercheEnfantDebounced, setRechercheEnfantDebounced] = useState('')
  const [enfantsSelectionnes, setEnfantsSelectionnes] = useState<Set<number>>(new Set())
  const [rattachementEnCours, setRattachementEnCours] = useState(false)
  const [lotEnCours, setLotEnCours] = useState(false)
  const [lotProgres, setLotProgres] = useState<{ traites: number; total: number } | null>(null)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  useEffect(() => {
    const id = setTimeout(() => {
      setRechercheEnfantDebounced(rechercheEnfant)
    }, 250)
    return () => clearTimeout(id)
  }, [rechercheEnfant])

  const { data, isLoading, isError } = useQuery({
    queryKey: ['tuteurs', { page, sansCompteSeulement, rechercheActive }],
    queryFn: () =>
      fetchTuteurs({
        page,
        sans_compte: sansCompteSeulement || undefined,
        search: rechercheActive || undefined,
        per_page: 50,
      }),
  })

  const enfantsQuery = useQuery({
    queryKey: ['eleves-rattachement-parent', rechercheEnfantDebounced],
    queryFn: () => fetchEleves({ search: rechercheEnfantDebounced || undefined, page: 1, per_page: 50 }),
    enabled: tuteurEnfants !== null && rechercheEnfantDebounced.trim().length >= 2,
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

  const rechercher = () => {
    setRechercheActive(recherche.trim())
    setPage(1)
    setSelectedIds(new Set())
  }

  const reinitialiser = async (tuteur: TuteurCompte) => {
    const ok = await confirmer({
      titre: `Réinitialiser le mot de passe de ${tuteur.nom_complet} ?`,
      message: 'Le mot de passe par défaut de son école sera rétabli et ses sessions ouvertes seront fermées.',
      action: 'Réinitialiser',
    })
    if (!ok) return
    setReinitialisationEnCours(tuteur.id)
    try {
      await reinitialiserMotDePasseParent(tuteur.id)
      succes('Mot de passe parent réinitialisé.')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setReinitialisationEnCours(null)
    }
  }

  const ouvrirRattachement = (tuteur: TuteurCompte) => {
    setTuteurEnfants(tuteur)
    setRechercheEnfant('')
    setRechercheEnfantDebounced('')
    setEnfantsSelectionnes(new Set())
  }

  const rattacherEnfants = async () => {
    if (!tuteurEnfants || enfantsSelectionnes.size === 0) return
    setRattachementEnCours(true)
    try {
      await rattacherEnfantsParent(tuteurEnfants.id, Array.from(enfantsSelectionnes))
      setTuteurEnfants(null)
      invalider()
      succes('Les enfants sélectionnés ont été rattachés à ce parent.')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setRattachementEnCours(false)
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
          {t.a_compte && (
            <button
              type="button"
              title="Réinitialiser le mot de passe"
              onClick={() => reinitialiser(t)}
              disabled={reinitialisationEnCours === t.id}
              className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700 disabled:opacity-50"
            >
              <RefreshCw className="h-4 w-4" />
            </button>
          )}
          <button
            type="button"
            title="Rattacher des enfants"
            onClick={() => ouvrirRattachement(t)}
            className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700"
          >
            <UserPlus className="h-4 w-4" />
          </button>
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
          placeholderRecherche="Rechercher un tuteur, un numéro… puis Entrée"
          terme={recherche}
          onTermeChange={setRecherche}
          onTermeSubmit={rechercher}
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

      {tuteurEnfants && (
        <Modal title={`Rattacher des enfants — ${tuteurEnfants.nom_complet}`} onClose={() => setTuteurEnfants(null)}>
          <p className="mb-4 text-sm text-navy-500">Recherchez un ou plusieurs élèves, puis cochez ceux à rattacher à ce parent.</p>
          <input
            autoFocus
            value={rechercheEnfant}
            onChange={(e) => setRechercheEnfant(e.target.value)}
            placeholder="Nom ou matricule de l'enfant…"
            className="mb-4 w-full rounded-xl border border-navy-200 px-3 py-2.5 text-sm focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
          />
          <div className="max-h-72 overflow-y-auto rounded-xl border border-navy-100">
            {enfantsQuery.isFetching ? <div className="p-4"><Spinner /></div> : enfantsQuery.data?.items.length ? enfantsQuery.data.items.map((eleve) => (
              <label key={eleve.id} className="flex cursor-pointer items-center gap-3 border-b border-navy-50 px-3 py-3 last:border-0 hover:bg-cream-50">
                <input
                  type="checkbox"
                  checked={enfantsSelectionnes.has(eleve.id)}
                  onChange={() => setEnfantsSelectionnes((actuels) => {
                    const suivants = new Set(actuels)
                    if (suivants.has(eleve.id)) suivants.delete(eleve.id)
                    else suivants.add(eleve.id)
                    return suivants
                  })}
                  className="h-4 w-4 rounded border-navy-300"
                />
                <span className="min-w-0 text-sm text-navy-800">
                  <span className="block font-semibold">{eleve.nom_complet}</span>
                  <span className="text-xs text-navy-400">{eleve.matricule ?? 'Sans matricule'}{eleve.school ? ` · ${eleve.school.name}` : ''}</span>
                </span>
              </label>
            )) : <p className="p-4 text-sm text-navy-400">Saisissez au moins deux caractères pour rechercher un élève.</p>}
          </div>
          <div className="mt-5 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setTuteurEnfants(null)}>Annuler</Button>
            <Button type="button" onClick={rattacherEnfants} disabled={rattachementEnCours || enfantsSelectionnes.size === 0}>
              <UserPlus className="h-4 w-4" />
              {rattachementEnCours ? 'Rattachement…' : `Rattacher (${enfantsSelectionnes.size})`}
            </Button>
          </div>
        </Modal>
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
