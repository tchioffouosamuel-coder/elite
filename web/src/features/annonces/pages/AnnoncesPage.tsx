import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { clsx } from 'clsx'
import { Megaphone, Plus, Search, Trash2, X } from 'lucide-react'
import {
  fetchAnnonces,
  creerAnnonce,
  supprimerAnnonce,
  fetchFonctionsAnnonces,
  rechercherDestinatairesAnnonces,
  type Annonce,
  type CibleType,
  type Destinataire,
} from '@/features/annonces/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, EmptyState, ErrorState } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const CIBLE_OPTIONS: { value: CibleType; label: string }[] = [
  { value: 'tous', label: 'Tous' },
  { value: 'fonction', label: 'Fonction' },
  { value: 'utilisateurs', label: 'Utilisateurs' },
]

/** Sélecteur de fonctions du référentiel, pour le ciblage « par fonction ». */
function SelecteurFonctions({
  schoolId,
  selectionnees,
  onToggle,
}: {
  schoolId: number | null
  selectionnees: Set<number>
  onToggle: (id: number) => void
}) {
  const { data: fonctions, isLoading } = useQuery({
    queryKey: ['annonces-fonctions', schoolId],
    queryFn: () => fetchFonctionsAnnonces(schoolId),
  })

  if (isLoading) return <Spinner />
  if (!fonctions || fonctions.length === 0) {
    return <p className="text-xs text-navy-400">Aucune fonction n'est définie pour cet établissement.</p>
  }

  return (
    <div className="flex flex-wrap gap-2">
      {fonctions.map((f) => {
        const active = selectionnees.has(f.id)
        return (
          <button
            key={f.id}
            type="button"
            onClick={() => onToggle(f.id)}
            className={clsx(
              'rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors',
              active
                ? 'border-navy-700 bg-navy-700 text-cream-50'
                : 'border-navy-200 bg-white text-navy-600 hover:border-navy-300',
            )}
          >
            {f.label}
          </button>
        )
      })}
    </div>
  )
}

/** Saisie à tags façon mentions : recherche par nom, suggestions sous le champ, sélection = tag amovible. */
function SelecteurDestinataires({
  schoolId,
  selectionnes,
  onChange,
}: {
  schoolId: number | null
  selectionnes: Destinataire[]
  onChange: (destinataires: Destinataire[]) => void
}) {
  const [saisie, setSaisie] = useState('')
  const [recherche, setRecherche] = useState('')

  useEffect(() => {
    const id = setTimeout(() => setRecherche(saisie), 300)
    return () => clearTimeout(id)
  }, [saisie])

  const { data: resultats, isLoading } = useQuery({
    queryKey: ['annonces-destinataires', recherche, schoolId],
    queryFn: () => rechercherDestinatairesAnnonces(recherche, schoolId),
    enabled: recherche.trim().length >= 2,
  })

  const dejaSelectionnes = new Set(selectionnes.map((d) => d.id))
  const suggestions = (resultats ?? []).filter((d) => !dejaSelectionnes.has(d.id))

  return (
    <div className="flex flex-col gap-2">
      {selectionnes.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {selectionnes.map((d) => (
            <span
              key={d.id}
              className="inline-flex items-center gap-1 rounded-full bg-navy-50 px-2.5 py-1 text-xs font-medium text-navy-700"
            >
              {d.nom_complet}
              <button
                type="button"
                onClick={() => onChange(selectionnes.filter((s) => s.id !== d.id))}
                className="text-navy-400 hover:text-navy-700"
              >
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}
        </div>
      )}
      <div className="relative">
        <Search className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-navy-300" />
        <input
          value={saisie}
          onChange={(e) => setSaisie(e.target.value)}
          placeholder="Rechercher un destinataire…"
          className="w-full rounded-xl border border-navy-200 py-2 pr-3 pl-9 text-sm outline-none focus:border-navy-400"
        />
      </div>
      {recherche.trim().length >= 2 && (
        <div className="max-h-48 overflow-y-auto rounded-xl border border-navy-100">
          {isLoading ? (
            <div className="p-2">
              <Spinner />
            </div>
          ) : suggestions.length === 0 ? (
            <p className="p-3 text-xs text-navy-400">Aucun résultat.</p>
          ) : (
            suggestions.map((d) => (
              <button
                key={d.id}
                type="button"
                onClick={() => {
                  onChange([...selectionnes, d])
                  setSaisie('')
                  setRecherche('')
                }}
                className="block w-full px-3 py-2 text-left text-sm text-navy-700 hover:bg-cream-100"
              >
                {d.nom_complet}
              </button>
            ))
          )}
        </div>
      )}
    </div>
  )
}

function AnnonceFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { t } = useTranslation()
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<{ titre: string; contenu: string; school_id?: number }>()

  const schoolIdSaisi = watch('school_id')
  const schoolId = schoolIdSaisi ? Number(schoolIdSaisi) : null

  const [cibleType, setCibleType] = useState<CibleType>('tous')
  const [fonctionsSelectionnees, setFonctionsSelectionnees] = useState<Set<number>>(new Set())
  const [destinatairesSelectionnes, setDestinatairesSelectionnes] = useState<Destinataire[]>([])
  const [erreurCible, setErreurCible] = useState<string | null>(null)

  const toggleFonction = (id: number) =>
    setFonctionsSelectionnees((actuel) => {
      const suivant = new Set(actuel)
      suivant.has(id) ? suivant.delete(id) : suivant.add(id)
      return suivant
    })

  const onSubmit = async (values: { titre: string; contenu: string; school_id?: number }) => {
    setErreurCible(null)
    const cible =
      cibleType === 'fonction'
        ? [...fonctionsSelectionnees]
        : cibleType === 'utilisateurs'
          ? destinatairesSelectionnes.map((d) => d.id)
          : undefined

    if (cibleType !== 'tous' && (!cible || cible.length === 0)) {
      setErreurCible('Précisez au moins un destinataire.')
      return
    }

    // « Toutes les écoles » ne se combine qu'avec la cible « Tous » : le
    // ciblage par fonction ou par utilisateurs est propre à un établissement.
    if (!values.school_id && (schools?.length ?? 0) > 1 && cibleType !== 'tous') {
      setErreurCible('Choisissez un établissement pour cibler des fonctions ou des utilisateurs.')
      return
    }

    try {
      if (!values.school_id && (schools?.length ?? 0) > 1) {
        await Promise.all(
          (schools ?? []).map((s) =>
            creerAnnonce({ ...values, school_id: s.id, cible_type: cibleType, cible }),
          ),
        )
      } else {
        await creerAnnonce({
          ...values,
          school_id: values.school_id ? Number(values.school_id) : null,
          cible_type: cibleType,
          cible,
        })
      }
      succes(t('annonces.created'))
      onCreated()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <Modal title={t('annonces.publier')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('classes.ecole')} error={errors.school_id?.message} {...register('school_id')}>
          {(schools?.length ?? 0) > 1 && <option value="">Toutes les écoles</option>}
          {schools?.map((s) => (
            <option key={s.id} value={s.id}>
              {s.name}
            </option>
          ))}
        </Select>
        <Input
          label={t('annonces.titre')}
          error={errors.titre?.message}
          autoFocus
          {...register('titre', { required: t('bus.field_required') as string, maxLength: 200 })}
        />
        <Textarea
          label={t('annonces.contenu')}
          rows={5}
          error={errors.contenu?.message}
          {...register('contenu', { required: t('bus.field_required') as string, maxLength: 2000 })}
        />

        <div className="flex flex-col gap-2">
          <span className="text-xs font-semibold tracking-wide text-navy-500 uppercase">Destinataires</span>
          <div className="inline-flex rounded-xl border border-navy-200 p-1">
            {CIBLE_OPTIONS.map((opt) => (
              <button
                key={opt.value}
                type="button"
                onClick={() => setCibleType(opt.value)}
                className={clsx(
                  'flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors',
                  cibleType === opt.value ? 'bg-navy-700 text-cream-50' : 'text-navy-500 hover:bg-cream-100',
                )}
              >
                {opt.label}
              </button>
            ))}
          </div>
          {cibleType === 'fonction' && (
            <SelecteurFonctions schoolId={schoolId} selectionnees={fonctionsSelectionnees} onToggle={toggleFonction} />
          )}
          {cibleType === 'utilisateurs' && (
            <SelecteurDestinataires
              schoolId={schoolId}
              selectionnes={destinatairesSelectionnes}
              onChange={setDestinatairesSelectionnes}
            />
          )}
          {erreurCible && <p className="text-xs text-red-500">{erreurCible}</p>}
        </div>

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {isSubmitting ? '…' : t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

export function AnnoncesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [showForm, setShowForm] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['annonces'], queryFn: () => fetchAnnonces() })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['annonces'] })

  const supprimer = async (annonce: Annonce) => {
    const confirme = await confirmerSuppression(t('annonces.delete_confirm_titre'))
    if (!confirme) return

    try {
      await supprimerAnnonce(annonce.id)
      invalider()
      succes(t('annonces.deleted'))
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('annonces.title')}
        sousTitre={t('annonces.hint')}
        icon={Megaphone}
        actions={
          can('annonces.publish') && (
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('annonces.publier')}
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.items.length === 0 ? (
        <EmptyState label={t('annonces.aucune')} />
      ) : (
        <div className="flex flex-col gap-3">
          {data.items.map((annonce) => (
            <div key={annonce.id} className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                  <h2 className="font-display text-base font-bold text-navy-900">{annonce.titre}</h2>
                  <p className="mt-0.5 text-xs text-navy-400">
                    {t('annonces.publiee_le')}{' '}
                    {new Date(annonce.publiee_le).toLocaleDateString('fr-FR', {
                      day: '2-digit',
                      month: 'long',
                      year: 'numeric',
                    })}
                    {annonce.publie_par && ` · ${t('annonces.publiee_par')} ${annonce.publie_par.nom_complet}`}
                    {annonce.school && ` · ${annonce.school.name}`}
                  </p>
                </div>
                {can('annonces.publish') && (
                  <button
                    title={t('common.delete')}
                    onClick={() => supprimer(annonce)}
                    className="flex-none rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </div>
              <p className="mt-2 whitespace-pre-wrap text-sm text-navy-700">{annonce.contenu}</p>
            </div>
          ))}
        </div>
      )}

      {showForm && (
        <AnnonceFormModal
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
