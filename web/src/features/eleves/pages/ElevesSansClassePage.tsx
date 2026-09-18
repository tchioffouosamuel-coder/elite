import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, FileSpreadsheet, Sparkles, UserRound, Upload } from 'lucide-react'
import {
  changerClasseEleve,
  fetchEleves,
  fetchNonPreinscritsSansHistorique,
  supprimerNonPreinscritsSansHistorique,
  type Eleve,
} from '@/features/eleves/api'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { Select } from '@/shared/ui/Select'
import { ImportModal } from '@/shared/ui/ImportModal'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import { telechargerFichier } from '@/shared/lib/download'
import type { ApiError } from '@/shared/types/api'

/**
 * Aperçu + suppression des doublons vides avant d'agir : jamais un bouton
 * qui supprime à l'aveugle. `Eleve` n'a pas de suppression douce côté API —
 * chaque fiche listée ici est vérifiée sans classe, jamais préinscrite et
 * sans la moindre trace d'activité (cf. `Eleve::scopeNonPreinscritSansHistorique`),
 * mais la liste reste affichée pour revue avant le clic de confirmation.
 */
function NettoyageDoublonsModal({ onClose, onSupprime }: { onClose: () => void; onSupprime: () => void }) {
  const [suppression, setSuppression] = useState(false)
  const { data: candidats, isLoading, isError } = useQuery({
    queryKey: ['eleves', 'non-preinscrits-sans-historique'],
    queryFn: () => fetchNonPreinscritsSansHistorique(),
  })

  const supprimer = async () => {
    if (!candidats || candidats.length === 0) return
    if (!(await confirmerSuppression(
      `${candidats.length} fiche(s) sans historique`,
      'Ces fiches seront définitivement supprimées — action irréversible, sans sauvegarde possible.',
    ))) return

    setSuppression(true)
    try {
      const { deleted } = await supprimerNonPreinscritsSansHistorique()
      succes(`${deleted} fiche(s) supprimée(s).`)
      onSupprime()
      onClose()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSuppression(false)
    }
  }

  return (
    <Modal title="Nettoyer les doublons sans historique" onClose={onClose} taille="lg">
      <div className="flex flex-col gap-4">
        <p className="rounded-lg bg-cream-100 p-3 text-xs text-navy-500">
          Fiches sans classe, jamais préinscrites et sans la moindre trace d'activité (aucune note,
          présence, versement, sanction…) — typiquement des doublons laissés par un import massif.
          Un ancien élève réellement parti garde, lui, son historique et n'apparaît jamais ici.
        </p>

        {isLoading ? (
          <Spinner />
        ) : isError || !candidats ? (
          <ErrorState />
        ) : candidats.length === 0 ? (
          <EmptyState label="Aucune fiche sans historique à supprimer." />
        ) : (
          <>
            <p className="text-sm font-semibold text-navy-800">{candidats.length} fiche(s) trouvée(s) :</p>
            <div className="max-h-72 overflow-y-auto rounded-lg border border-navy-100">
              {candidats.map((eleve) => (
                <div key={eleve.id} className="flex items-center justify-between gap-3 border-b border-navy-50 px-3 py-2 text-sm last:border-0">
                  <span className="font-medium text-navy-800">{eleve.nom_complet}</span>
                  <span className="font-mono text-xs text-navy-400">{eleve.matricule ?? '—'}</span>
                </div>
              ))}
            </div>
          </>
        )}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          {candidats && candidats.length > 0 && (
            <Button type="button" onClick={supprimer} disabled={suppression}>
              Supprimer ces {candidats.length} fiche(s)
            </Button>
          )}
        </div>
      </div>
    </Modal>
  )
}

function ClasseSelect({ eleve, classes }: { eleve: Eleve; classes: Classe[] }) {
    const queryClient = useQueryClient()
    const classesDeLEcole = classes.filter((classe) => (classe.school_id ?? classe.school?.id) === eleve.school_id)
    const options = classesDeLEcole.map((classe) => ({ value: classe.id, label: classe.nom }))

    return (
        <div className="min-w-52" onClick={(event) => event.stopPropagation()}>
            <Select
                options={options}
                placeholder="Définir la classe..."
                isClearable={false}
                isSearchable
                onChange={async (option) => {
                    if (!option) return
                    const classeId = Number(option.value)

                    try {
                        await changerClasseEleve(eleve.id, classeId)
                        succes(`${eleve.nom_complet} a été affecté à la classe sélectionnée.`)
                        await queryClient.invalidateQueries({ queryKey: ['eleves', 'sans-classe'] })
                        queryClient.invalidateQueries({ queryKey: ['eleves'] })
                        queryClient.invalidateQueries({ queryKey: ['classes'] })
                    } catch (err) {
                        erreur((err as ApiError).message)
                    }
                }}
            />
        </div>
    )
}

export function ElevesSansClassePage() {
    const navigate = useNavigate()
    const can = useAuthStore((s) => s.can)
    const queryClient = useQueryClient()
    const [showImport, setShowImport] = useState(false)
    const [showNettoyage, setShowNettoyage] = useState(false)
    const { data: classes = [], isLoading: classesLoading } = useQuery({
        queryKey: ['classes'],
        queryFn: () => fetchClasses(),
    })
    const { data, isLoading, isError } = useQuery({
        queryKey: ['eleves', 'sans-classe'],
        // Outil de correction de données : un élève sans classe n'a souvent
        // pas encore de préinscription traitée pour l'année active — il ne
        // doit pas disparaître de cette liste pour autant.
        queryFn: () => fetchEleves({ per_page: 10000, tous: true }),
    })

    const elevesSansClasse = (data?.items ?? []).filter((eleve) => eleve.classe === null)

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: ['eleves', 'sans-classe'] })
        queryClient.invalidateQueries({ queryKey: ['eleves'] })
        queryClient.invalidateQueries({ queryKey: ['classes'] })
    }

    const colonnes: Colonne<Eleve>[] = [
        {
            cle: 'matricule',
            entete: 'Matricule',
            valeur: (eleve) => eleve.matricule,
            cellule: (eleve) => <span className="font-mono text-xs">{eleve.matricule ?? '—'}</span>,
        },
        {
            cle: 'nom',
            entete: 'Élève',
            valeur: (eleve) => eleve.nom_complet,
            cellule: (eleve) => (
                <div className="flex flex-wrap items-center gap-2">
                    <span className={`font-semibold ${eleve.non_reinscrit_annee_active ? 'text-red-700' : 'text-navy-900'}`}>
                        {eleve.nom_complet}
                    </span>
                    <ClasseSelect eleve={eleve} classes={classes} />
                    {eleve.non_reinscrit_annee_active && <Badge tone="red">Non préinscrit</Badge>}
                </div>
            ),
        },
        {
            cle: 'sexe',
            entete: 'Sexe',
            valeur: (eleve) => eleve.sexe,
            cellule: (eleve) => <Badge tone={eleve.sexe === 'F' ? 'gold' : 'neutral'}>{eleve.sexe === 'F' ? 'Féminin' : 'Masculin'}</Badge>,
        },
        {
            cle: 'ecole',
            entete: 'École',
            valeur: (eleve) => eleve.school?.name,
            cellule: (eleve) => eleve.school?.name ?? '—',
        },
        {
            cle: 'tuteur',
            entete: 'Tuteur',
            valeur: (eleve) => eleve.tuteurs?.[0]?.nom_complet,
            cellule: (eleve) => eleve.tuteurs?.[0]?.nom_complet ?? '—',
        },
    ]

    return (
        <div className="flex flex-col gap-5">
            <PageHeader
                titre="Élèves sans classe"
                sousTitre="Élèves actuellement inscrits qui ne sont affectés à aucune classe."
                icon={UserRound}
                actions={
                    <>
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => telechargerFichier('/eleves/export', { sans_classe: 1 }, 'eleves-sans-classe.xlsx')}
                        >
                            <FileSpreadsheet className="h-4 w-4" />
                            Exporter la liste
                        </Button>
                        {can('eleves.manage') && (
                            <Button type="button" variant="secondary" onClick={() => setShowImport(true)}>
                                <Upload className="h-4 w-4" />
                                Importer les classes
                            </Button>
                        )}
                        {can('eleves.manage') && (
                            <Button type="button" variant="secondary" onClick={() => setShowNettoyage(true)}>
                                <Sparkles className="h-4 w-4" />
                                Nettoyer les doublons
                            </Button>
                        )}
                        <Button type="button" variant="secondary" onClick={() => navigate('/eleves')}>
                            <ArrowLeft className="h-4 w-4" />
                            Retour aux élèves
                        </Button>
                    </>
                }
            />

            {isLoading || classesLoading ? (
                <Spinner />
            ) : isError || !data ? (
                <ErrorState />
            ) : elevesSansClasse.length === 0 ? (
                <EmptyState label="Tous les élèves sont affectés à une classe." />
            ) : (
                <DataTable
                    colonnes={colonnes}
                    lignes={elevesSansClasse}
                    cleLigne={(eleve) => eleve.id}
                    onLigneClick={(eleve) => navigate(`/eleves/${eleve.id}`)}
                    placeholderRecherche="Rechercher un élève…"
                    messageVide="Aucun élève ne correspond à cette recherche."
                    largeurMin={760}
                />
            )}

            {showImport && (
                <ImportModal
                    title="Importer les classes"
                    url="/eleves/import"
                    decoupe={{ preparerUrl: '/eleves/import/preparer', traiterUrl: '/eleves/import/traiter' }}
                    columns={['Matricule', 'Nom complet', 'Sexe', 'Classe', 'Statut', 'Tuteur', 'Téléphone tuteur']}
                    note={
                        <p className="rounded-lg bg-cream-100 p-3 text-xs text-navy-500">
                            Reprenez le fichier exporté ci-dessus et complétez la colonne « Classe » pour chaque élève
                            avant de le réimporter — les autres colonnes restent inchangées.
                        </p>
                    }
                    onClose={() => setShowImport(false)}
                    onImported={invalidate}
                />
            )}

            {showNettoyage && (
                <NettoyageDoublonsModal onClose={() => setShowNettoyage(false)} onSupprime={invalidate} />
            )}
        </div>
    )
}