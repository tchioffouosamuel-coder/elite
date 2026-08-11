import { forwardRef, useEffect, useImperativeHandle, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchEcole, updateEcole, type EcoleProfilePayload } from '@/features/settings/api'
import { fetchNiveaux } from '@/features/classes/api'
import { Card } from '@/shared/ui/Card'
import { Input } from '@/shared/ui/Field'
import { RichTextEditor } from '@/shared/ui/RichTextEditor'
import { Spinner } from '@/shared/ui/Feedback'

export interface EcoleProfileHandle {
  /** Persiste le formulaire ; no-op tant que le profil n'a pas fini de charger. */
  save: () => Promise<void>
}

export const EcoleProfileCard = forwardRef<EcoleProfileHandle>(function EcoleProfileCard(_props, ref) {
  const { t, i18n } = useTranslation()
  const isFr = i18n.language === 'fr'
  const queryClient = useQueryClient()

  const { data: ecole, isLoading } = useQuery({ queryKey: ['ecole'], queryFn: fetchEcole })
  const { data: niveaux } = useQuery({ queryKey: ['niveaux'], queryFn: fetchNiveaux })

  const [form, setForm] = useState<EcoleProfilePayload>({ name: '', niveau_ids: [] })

  useEffect(() => {
    if (ecole) {
      setForm({
        name: ecole.name,
        address: ecole.address ?? '',
        phone: ecole.phone ?? '',
        email: ecole.email ?? '',
        header_fr: ecole.header_fr ?? '',
        header_en: ecole.header_en ?? '',
        niveau_ids: ecole.niveau_ids,
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

  const toggleNiveau = (id: number) => {
    setForm((f) => ({
      ...f,
      niveau_ids: f.niveau_ids.includes(id) ? f.niveau_ids.filter((n) => n !== id) : [...f.niveau_ids, id],
    }))
  }

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

      <div className="mt-5 border-t border-navy-100 pt-4">
        <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('settings.niveaux_operes')}</span>
        <p className="mb-3 mt-1 text-xs text-navy-400">{t('settings.niveaux_hint')}</p>
        <div className="flex flex-wrap gap-3">
          {niveaux?.map((n) => (
            <label
              key={n.id}
              className="flex cursor-pointer items-center gap-2 rounded-xl border border-navy-200 bg-white px-3.5 py-2 text-sm shadow-soft"
            >
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-navy-300"
                checked={form.niveau_ids.includes(n.id)}
                onChange={() => toggleNiveau(n.id)}
              />
              {isFr ? n.name_fr : n.name_en}
            </label>
          ))}
        </div>
      </div>
    </Card>
  )
})
