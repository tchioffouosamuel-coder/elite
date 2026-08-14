import { forwardRef, useEffect, useImperativeHandle, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchEcole, updateEcole, type EcoleProfilePayload } from '@/features/settings/api'
import { Card } from '@/shared/ui/Card'
import { Input } from '@/shared/ui/Field'
import { RichTextEditor } from '@/shared/ui/RichTextEditor'
import { Spinner } from '@/shared/ui/Feedback'

export interface EcoleProfileHandle {
  /** Persiste le formulaire ; no-op tant que le profil n'a pas fini de charger. */
  save: () => Promise<void>
}

export const EcoleProfileCard = forwardRef<EcoleProfileHandle>(function EcoleProfileCard(_props, ref) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const { data: ecole, isLoading } = useQuery({ queryKey: ['ecole'], queryFn: fetchEcole })

  const [form, setForm] = useState<EcoleProfilePayload>({ name: '' })

  useEffect(() => {
    if (ecole) {
      setForm({
        name: ecole.name,
        address: ecole.address ?? '',
        phone: ecole.phone ?? '',
        email: ecole.email ?? '',
        header_fr: ecole.header_fr ?? '',
        header_en: ecole.header_en ?? '',
      })
    }
  }, [ecole])

  // Le bouton "Enregistrer" unique de SettingsPage pilote cette carte via ref
  // — avant, elle avait son propre bouton "Enregistrer" en plus de celui de
  // la page, et il était trop facile de ne cliquer que sur l'un des deux
  // (ex. les en-têtes saisies ici mais jamais envoyées).
  useImperativeHandle(ref, () => ({
    save: async () => {
      if (!ecole) return // profil pas encore chargé : rien à sauvegarder
      await updateEcole(form)
      queryClient.invalidateQueries({ queryKey: ['ecole'] })
    },
  }))

  if (isLoading || !ecole) return <Spinner />

  return (
    <Card>
      <div className="mb-4 flex items-center justify-between">
        <h2 className="font-display text-base font-bold tracking-tight text-navy-800">{t('settings.ecole')}</h2>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Input
          label={t('settings.nom')}
          value={form.name}
          onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
        />
        <Input
          label={t('settings.adresse')}
          value={form.address ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
        />
        <Input
          label={t('settings.telephone')}
          value={form.phone ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
        />
        <Input
          label={t('settings.email')}
          type="email"
          value={form.email ?? ''}
          onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
        />
        <div className="sm:col-span-2">
          <RichTextEditor
            label={t('settings.header_fr')}
            value={form.header_fr ?? ''}
            onChange={(html) => setForm((f) => ({ ...f, header_fr: html }))}
          />
        </div>
        <div className="sm:col-span-2">
          <RichTextEditor
            label={t('settings.header_en')}
            value={form.header_en ?? ''}
            onChange={(html) => setForm((f) => ({ ...f, header_en: html }))}
          />
        </div>
      </div>
    </Card>
  )
})
