import { useState, type Dispatch, type SetStateAction } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { clsx } from 'clsx'
import { BookOpen, Download, FileUp, Pencil, Plus, Search, Trash2, X } from 'lucide-react'
import {
  fetchBibliotheque,
  uploaderDocument,
  importerDocuments,
  modifierCiblageDocument,
  supprimerDocument,
  type CibleBibliotheque,
  type DocumentBibliotheque,
} from '@/features/bibliotheque/api'
import { fetchSchools, fetchClasses, type Classe, type School } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Textarea } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Pagination } from '@/shared/ui/Pagination'
import { Spinner, EmptyState, ErrorState } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function formatTaille(octets: number): string {
  if (octets < 1024) return `${octets} o`
  if (octets < 1024 * 1024) return `${(octets / 1024).toFixed(0)} Ko`
  return `${(octets / (1024 * 1024)).toFixed(1)} Mo`
}

/** Sélection multiple des écoles ayant accès — partagée entre l'ajout unitaire et l'import massif. */
function SelecteurEcoles({
  schools,
  selectionnees,
  onToggle,
}: {
  schools: School[] | undefined
  selectionnees: Set<number>
  onToggle: (id: number) => void
}) {
  return (
    <div className="flex flex-col gap-2">
      <span className="text-xs font-semibold tracking-wide text-navy-500 uppercase">Écoles ayant accès</span>
      <div className="flex flex-wrap gap-2">
        {schools?.map((s) => {
          const active = selectionnees.has(s.id)
          return (
            <button
              key={s.id}
              type="button"
              onClick={() => onToggle(s.id)}
              className={clsx(
                'rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors',
                active
                  ? 'border-navy-700 bg-navy-700 text-cream-50'
                  : 'border-navy-200 bg-white text-navy-600 hover:border-navy-300',
              )}
            >
              {s.name}
            </button>
          )
        })}
      </div>
    </div>
  )
}

/**
 * Restreint le document à une ou plusieurs classes des écoles déjà
 * sélectionnées — aucune classe cochée signifie « toute l'école », le
 * comportement historique. N'affiche que les classes des écoles choisies :
 * cibler une classe d'une école non sélectionnée n'aurait pas de sens.
 */
function SelecteurClasses({
  classes,
  ecolesSelectionnees,
  selectionnees,
  onToggle,
}: {
  classes: Classe[] | undefined
  ecolesSelectionnees: Set<number>
  selectionnees: Set<number>
  onToggle: (id: number) => void
}) {
  const classesFiltrees = classes?.filter((c) => c.school_id !== undefined && ecolesSelectionnees.has(c.school_id))

  if (ecolesSelectionnees.size === 0) return null

  return (
    <div className="flex flex-col gap-2">
      <span className="text-xs font-semibold tracking-wide text-navy-500 uppercase">
        Restreindre à des classes (optionnel)
      </span>
      <p className="text-xs text-navy-400">
        Aucune classe cochée : le document reste visible par toute l'école. Une ou plusieurs classes cochées : seuls
        les enseignants de ces classes et les parents des élèves qui y sont inscrits y ont accès.
      </p>
      {classesFiltrees && classesFiltrees.length > 0 ? (
        <div className="flex max-h-40 flex-wrap gap-2 overflow-y-auto rounded-xl border border-navy-100 p-2">
          {classesFiltrees.map((c) => {
            const active = selectionnees.has(c.id)
            return (
              <button
                key={c.id}
                type="button"
                onClick={() => onToggle(c.id)}
                className={clsx(
                  'rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors',
                  active
                    ? 'border-gold-600 bg-gold-500 text-navy-900'
                    : 'border-navy-200 bg-white text-navy-600 hover:border-navy-300',
                )}
              >
                {c.nom}
              </button>
            )
          })}
        </div>
      ) : (
        <p className="text-xs text-navy-300">Aucune classe trouvée pour ces écoles.</p>
      )}
    </div>
  )
}

const OPTIONS_CIBLES: [CibleBibliotheque, string][] = [
  ['personnel', 'Personnel'],
  ['parents', 'Parents'],
]

/** Destinataires du document — aucune coche = tout le monde (comportement historique). */
function SelecteurCibles({
  selectionnees,
  onToggle,
}: {
  selectionnees: Set<CibleBibliotheque>
  onToggle: (cible: CibleBibliotheque) => void
}) {
  return (
    <div className="flex flex-col gap-2">
      <span className="text-xs font-semibold tracking-wide text-navy-500 uppercase">Destinataires (optionnel)</span>
      <p className="text-xs text-navy-400">Aucune coche : visible par tout le monde (personnel et parents).</p>
      <div className="flex flex-wrap gap-2">
        {OPTIONS_CIBLES.map(([valeur, libelle]) => {
          const active = selectionnees.has(valeur)
          return (
            <button
              key={valeur}
              type="button"
              onClick={() => onToggle(valeur)}
              className={clsx(
                'rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors',
                active
                  ? 'border-navy-700 bg-navy-700 text-cream-50'
                  : 'border-navy-200 bg-white text-navy-600 hover:border-navy-300',
              )}
            >
              {libelle}
            </button>
          )
        })}
      </div>
    </div>
  )
}

/** Bascule une valeur dans un `Set` — évite de répéter le même montage/démontage à chaque sélecteur multiple. */
function useSelectionMultiple<T>() {
  const [selection, setSelection] = useState<Set<T>>(new Set())
  const toggle = (valeur: T) =>
    setSelection((actuel) => {
      const suivant = new Set(actuel)
      suivant.has(valeur) ? suivant.delete(valeur) : suivant.add(valeur)
      return suivant
    })
  return [selection, toggle] as const
}

function DocumentFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })
  const [ecolesSelectionnees, toggleEcole] = useSelectionMultiple<number>()
  const [classesSelectionnees, toggleClasse] = useSelectionMultiple<number>()
  const [ciblesSelectionnees, toggleCible] = useSelectionMultiple<CibleBibliotheque>()
  const [fichier, setFichier] = useState<File | null>(null)
  const [erreurForm, setErreurForm] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{ titre: string; description: string }>()

  const onSubmit = async (values: { titre: string; description: string }) => {
    setErreurForm(null)
    if (!fichier) {
      setErreurForm('Choisissez un fichier.')
      return
    }
    if (ecolesSelectionnees.size === 0) {
      setErreurForm('Sélectionnez au moins une école.')
      return
    }

    try {
      await uploaderDocument({
        titre: values.titre,
        description: values.description || undefined,
        fichier,
        school_ids: [...ecolesSelectionnees],
        classe_ids: [...classesSelectionnees],
        cibles: [...ciblesSelectionnees],
      })
      succes('Document ajouté à la bibliothèque.')
      onCreated()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <Modal title="Ajouter un document" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label="Titre"
          autoFocus
          error={errors.titre?.message}
          {...register('titre', { required: 'Le titre est requis.', maxLength: 150 })}
        />
        <Textarea label="Description" rows={3} {...register('description', { maxLength: 1000 })} />

        <div className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold tracking-wide text-navy-500 uppercase">Fichier</span>
          <input
            type="file"
            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png"
            onChange={(e) => setFichier(e.target.files?.[0] ?? null)}
            className="rounded-xl border border-navy-200 px-3 py-2 text-sm"
          />
        </div>

        <SelecteurEcoles schools={schools} selectionnees={ecolesSelectionnees} onToggle={toggleEcole} />
        <SelecteurClasses
          classes={classes}
          ecolesSelectionnees={ecolesSelectionnees}
          selectionnees={classesSelectionnees}
          onToggle={toggleClasse}
        />
        <SelecteurCibles selectionnees={ciblesSelectionnees} onToggle={toggleCible} />

        {erreurForm && <p className="text-xs text-red-500">{erreurForm}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? '…' : 'Ajouter'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

/**
 * Import massif : plusieurs fichiers en une fois, mêmes écoles et même
 * description pour tous — pas de champ « titre » ici, chaque document
 * reprend le nom de son fichier (cf. `BibliothequeService::importer()`
 * côté API).
 */
function ImportMassifModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })
  const [ecolesSelectionnees, toggleEcole] = useSelectionMultiple<number>()
  const [classesSelectionnees, toggleClasse] = useSelectionMultiple<number>()
  const [ciblesSelectionnees, toggleCible] = useSelectionMultiple<CibleBibliotheque>()
  const [fichiers, setFichiers] = useState<File[]>([])
  const [description, setDescription] = useState('')
  const [enCours, setEnCours] = useState(false)
  const [erreurForm, setErreurForm] = useState<string | null>(null)

  const ajouterFichiers = (liste: FileList | null) => {
    if (!liste) return
    setFichiers((actuels) => {
      const noms = new Set(actuels.map((f) => f.name + f.size))
      const nouveaux = Array.from(liste).filter((f) => !noms.has(f.name + f.size))
      return [...actuels, ...nouveaux]
    })
  }

  const retirerFichier = (index: number) => setFichiers((actuels) => actuels.filter((_, i) => i !== index))

  const onSubmit = async () => {
    setErreurForm(null)
    if (fichiers.length === 0) {
      setErreurForm('Choisissez au moins un fichier.')
      return
    }
    if (ecolesSelectionnees.size === 0) {
      setErreurForm('Sélectionnez au moins une école.')
      return
    }

    setEnCours(true)
    try {
      const crees = await importerDocuments({
        fichiers,
        description: description || undefined,
        school_ids: [...ecolesSelectionnees],
        classe_ids: [...classesSelectionnees],
        cibles: [...ciblesSelectionnees],
      })
      succes(`${crees.length} document(s) ajouté(s) à la bibliothèque.`)
      onCreated()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal title="Importer plusieurs fichiers" onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
          Chaque fichier devient un document distinct, nommé d'après son nom de fichier — la description et les
          écoles ci-dessous s'appliquent à tous.
        </p>

        <div className="flex flex-col gap-1.5">
          <span className="text-xs font-semibold tracking-wide text-navy-500 uppercase">Fichiers</span>
          <input
            type="file"
            multiple
            accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png"
            onChange={(e) => {
              ajouterFichiers(e.target.files)
              e.target.value = ''
            }}
            className="rounded-xl border border-navy-200 px-3 py-2 text-sm"
          />
        </div>

        {fichiers.length > 0 && (
          <ul className="flex max-h-48 flex-col gap-1 overflow-y-auto rounded-xl border border-navy-100 p-2">
            {fichiers.map((f, i) => (
              <li key={f.name + f.size} className="flex items-center justify-between gap-2 rounded-lg px-2 py-1 text-xs hover:bg-cream-50">
                <span className="min-w-0 truncate text-navy-700">{f.name}</span>
                <span className="flex flex-none items-center gap-2 text-navy-400">
                  {formatTaille(f.size)}
                  <button type="button" onClick={() => retirerFichier(i)} className="hover:text-red-500">
                    <X className="h-3.5 w-3.5" />
                  </button>
                </span>
              </li>
            ))}
          </ul>
        )}

        <Textarea
          label="Description (appliquée à tous les fichiers)"
          rows={2}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
        />

        <SelecteurEcoles schools={schools} selectionnees={ecolesSelectionnees} onToggle={toggleEcole} />
        <SelecteurClasses
          classes={classes}
          ecolesSelectionnees={ecolesSelectionnees}
          selectionnees={classesSelectionnees}
          onToggle={toggleClasse}
        />
        <SelecteurCibles selectionnees={ciblesSelectionnees} onToggle={toggleCible} />

        {erreurForm && <p className="text-xs text-red-500">{erreurForm}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="button" onClick={onSubmit} disabled={enCours}>
            {enCours ? '…' : `Importer${fichiers.length > 0 ? ` (${fichiers.length})` : ''}`}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/**
 * Ajuste le ciblage d'un document déjà déposé (écoles, classes,
 * destinataires) sans repasser par un nouveau fichier — utile quand
 * l'admin s'est trompé de classe ou veut ouvrir un document à toute l'école.
 */
function EditCiblageModal({
  document,
  onClose,
  onSaved,
}: {
  document: DocumentBibliotheque
  onClose: () => void
  onSaved: () => void
}) {
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: fetchClasses })
  const [ecolesSelectionnees, setEcolesSelectionnees] = useState<Set<number>>(() => new Set(document.ecoles.map((e) => e.id)))
  const [classesSelectionnees, setClassesSelectionnees] = useState<Set<number>>(() => new Set(document.classes.map((c) => c.id)))
  const [ciblesSelectionnees, setCiblesSelectionnees] = useState<Set<CibleBibliotheque>>(() => new Set(document.cibles ?? []))
  const [enCours, setEnCours] = useState(false)

  const basculer = <T,>(setter: Dispatch<SetStateAction<Set<T>>>) => (valeur: T) =>
    setter((actuel) => {
      const suivant = new Set(actuel)
      suivant.has(valeur) ? suivant.delete(valeur) : suivant.add(valeur)
      return suivant
    })
  const toggleEcole = basculer<number>(setEcolesSelectionnees)
  const toggleClasse = basculer<number>(setClassesSelectionnees)
  const toggleCible = basculer<CibleBibliotheque>(setCiblesSelectionnees)

  const onSubmit = async () => {
    setEnCours(true)
    try {
      await modifierCiblageDocument(document.id, {
        school_ids: [...ecolesSelectionnees],
        classe_ids: [...classesSelectionnees],
        cibles: [...ciblesSelectionnees],
      })
      succes('Ciblage mis à jour.')
      onSaved()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal title={`Ciblage de « ${document.titre} »`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <SelecteurEcoles schools={schools} selectionnees={ecolesSelectionnees} onToggle={toggleEcole} />
        <SelecteurClasses
          classes={classes}
          ecolesSelectionnees={ecolesSelectionnees}
          selectionnees={classesSelectionnees}
          onToggle={toggleClasse}
        />
        <SelecteurCibles selectionnees={ciblesSelectionnees} onToggle={toggleCible} />

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="button" onClick={onSubmit} disabled={enCours || ecolesSelectionnees.size === 0}>
            {enCours ? '…' : 'Enregistrer'}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

export function BibliothequePage() {
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)
  const [showImport, setShowImport] = useState(false)
  const [editingCiblage, setEditingCiblage] = useState<DocumentBibliotheque | null>(null)
  const [recherche, setRecherche] = useState('')
  const [page, setPage] = useState(1)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['bibliotheque', recherche, page],
    queryFn: () => fetchBibliotheque({ search: recherche || undefined, page }),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['bibliotheque'] })

  const documents = data?.documents

  const supprimer = async (document: DocumentBibliotheque) => {
    const confirme = await confirmerSuppression(`Supprimer « ${document.titre} » ?`)
    if (!confirme) return

    try {
      await supprimerDocument(document.id)
      invalider()
      succes('Document supprimé.')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Bibliothèque numérique"
        sousTitre="Documents partagés avec le personnel et les parents des écoles concernées."
        icon={BookOpen}
        actions={
          can('bibliotheque.manage') && (
            <div className="flex gap-2">
              <Button variant="secondary" onClick={() => setShowImport(true)}>
                <FileUp className="h-4 w-4" />
                Importer plusieurs fichiers
              </Button>
              <Button onClick={() => setShowForm(true)}>
                <Plus className="h-4 w-4" />
                Ajouter un document
              </Button>
            </div>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          {(documents!.length > 0 || recherche !== '') && (
            <div className="relative max-w-sm">
              <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-navy-300" />
              <input
                value={recherche}
                onChange={(e) => {
                  setRecherche(e.target.value)
                  setPage(1)
                }}
                placeholder="Rechercher un document…"
                className="w-full rounded-xl border border-navy-200 py-2 pr-3 pl-9 text-sm outline-none focus:border-navy-400"
              />
            </div>
          )}

          {documents!.length === 0 ? (
            <EmptyState
              label={recherche ? 'Aucun document ne correspond à cette recherche.' : 'Aucun document dans la bibliothèque.'}
            />
          ) : (
            <div className="flex flex-col gap-3">
              {documents!.map((document) => (
                <div key={document.id} className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                      <h2 className="font-display text-base font-bold text-navy-900">{document.titre}</h2>
                      <p className="mt-0.5 text-xs text-navy-400">
                        {formatTaille(document.taille)} · {document.ecoles.map((e) => e.name).join(', ')}
                        {document.uploade_par && ` · déposé par ${document.uploade_par}`}
                      </p>
                    </div>
                    <div className="flex flex-none items-center gap-1">
                      <a
                        href={document.fichier_url}
                        target="_blank"
                        rel="noreferrer"
                        title="Télécharger"
                        className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                      >
                        <Download className="h-4 w-4" />
                      </a>
                      {can('bibliotheque.manage') && (
                        <button
                          title="Modifier le ciblage"
                          onClick={() => setEditingCiblage(document)}
                          className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                        >
                          <Pencil className="h-4 w-4" />
                        </button>
                      )}
                      {can('bibliotheque.manage') && (
                        <button
                          title="Supprimer"
                          onClick={() => supprimer(document)}
                          className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      )}
                    </div>
                  </div>
                  {document.description && (
                    <p className="mt-2 whitespace-pre-wrap text-sm text-navy-700">{document.description}</p>
                  )}
                  {(document.classes.length > 0 || document.cibles) && (
                    <div className="mt-2 flex flex-wrap gap-1.5">
                      {document.classes.map((c) => (
                        <span key={c.id} className="rounded-full bg-gold-100 px-2 py-0.5 text-[11px] font-semibold text-gold-700">
                          {c.nom}
                        </span>
                      ))}
                      {document.cibles?.map((cible) => (
                        <span key={cible} className="rounded-full bg-navy-50 px-2 py-0.5 text-[11px] font-semibold text-navy-600">
                          {cible === 'personnel' ? 'Personnel' : 'Parents'}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}

          {data && <Pagination pagination={data.pagination} onChange={setPage} />}
        </>
      )}

      {showForm && (
        <DocumentFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            invalider()
          }}
        />
      )}

      {showImport && (
        <ImportMassifModal
          onClose={() => setShowImport(false)}
          onCreated={() => {
            setShowImport(false)
            invalider()
          }}
        />
      )}

      {editingCiblage && (
        <EditCiblageModal
          document={editingCiblage}
          onClose={() => setEditingCiblage(null)}
          onSaved={() => {
            setEditingCiblage(null)
            invalider()
          }}
        />
      )}
    </div>
  )
}
