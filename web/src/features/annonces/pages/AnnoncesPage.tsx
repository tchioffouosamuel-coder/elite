import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Megaphone, Plus, Trash2 } from 'lucide-react'
import { fetchAnnonces, creerAnnonce, supprimerAnnonce, type Annonce } from '@/features/annonces/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, EmptyState, ErrorState } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function AnnonceFormModal({ onClose, onCreated }: { onClose: () => void; onCreated: () => void }) {
  const { t } = useTranslation()
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{ titre: string; contenu: string; school_id?: number }>()

  const onSubmit = async (values: { titre: string; contenu: string; school_id?: number }) => {
    try {
      await creerAnnonce({ ...values, school_id: values.school_id ? Number(values.school_id) : null })
      succes(t('annonces.created'))
      onCreated()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <Modal title={t('annonces.publier')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select
          label={`${t('classes.ecole')}${(schools?.length ?? 0) > 1 ? ' *' : ''}`}
          error={errors.school_id?.message}
          {...register('school_id', { required: (schools?.length ?? 0) > 1 ? "L'école est requise." : false })}
        >
          <option value="">—</option>
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
