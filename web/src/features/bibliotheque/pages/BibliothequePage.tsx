import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { clsx } from 'clsx'
import { BookOpen, Download, Plus, Trash2 } from 'lucide-react'
import {
  fetchBibliotheque,
  uploaderDocument,
  supprimerDocument,
  type DocumentBibliotheque,
} from '@/features/bibliotheque/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Textarea } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, EmptyState, ErrorState } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function formatTaille(octets: number): string {
  if (octets < 1024) return `${octets} o`
  if (octets < 1024 * 1024) return `${(octets / 1024).toFixed(0)} Ko`
  return `${(octets / (1024 * 1024)).toFixed(1)} Mo`
}

function DocumentFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const [ecolesSelectionnees, setEcolesSelectionnees] = useState<Set<number>>(new Set())
  const [fichier, setFichier] = useState<File | null>(null)
  const [erreurForm, setErreurForm] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{ titre: string; description: string }>()

  const toggleEcole = (id: number) =>
    setEcolesSelectionnees((actuel) => {
      const suivant = new Set(actuel)
      suivant.has(id) ? suivant.delete(id) : suivant.add(id)
      return suivant
    })

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

        <div className="flex flex-col gap-2">
          <span className="text-xs font-semibold tracking-wide text-navy-500 uppercase">Écoles ayant accès</span>
          <div className="flex flex-wrap gap-2">
            {schools?.map((s) => {
              const active = ecolesSelectionnees.has(s.id)
              return (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => toggleEcole(s.id)}
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

export function BibliothequePage() {
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['bibliotheque'], queryFn: fetchBibliotheque })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['bibliotheque'] })

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
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              Ajouter un document
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucun document dans la bibliothèque." />
      ) : (
        <div className="flex flex-col gap-3">
          {data.map((document) => (
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
                      title="Supprimer"
                      onClick={() => supprimer(document)}
                      className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  )}
                </div>
              </div>
              {document.description && <p className="mt-2 whitespace-pre-wrap text-sm text-navy-700">{document.description}</p>}
            </div>
          ))}
        </div>
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
    </div>
  )
}
