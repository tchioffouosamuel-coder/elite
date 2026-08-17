import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Save, UserCog } from 'lucide-react'
import { fetchClasse, updateClasse, type Classe } from '@/features/classes/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

type ChampResponsable = 'professeur_principal_id' | 'surveillant_general_id' | 'censeur_id' | 'conseiller_orientation_id'

const RESPONSABLES: { champ: ChampResponsable; libeleKey: string; aideKey: string }[] = [
  {
    champ: 'professeur_principal_id',
    libeleKey: 'classes.responsable_professeur_principal',
    aideKey: 'classes.responsable_professeur_principal_aide',
  },
  {
    champ: 'surveillant_general_id',
    libeleKey: 'classes.responsable_surveillant_general',
    aideKey: 'classes.responsable_surveillant_general_aide',
  },
  { champ: 'censeur_id', libeleKey: 'classes.responsable_censeur', aideKey: 'classes.responsable_censeur_aide' },
  {
    champ: 'conseiller_orientation_id',
    libeleKey: 'classes.responsable_conseiller_orientation',
    aideKey: 'classes.responsable_conseiller_orientation_aide',
  },
]

export function ResponsablesTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  const peutGerer = useAuthStore((s) => s.can('classes.manage'))
  const queryClient = useQueryClient()

  const { data: classe, isLoading } = useQuery({ queryKey: ['classe', classeId], queryFn: () => fetchClasse(classeId) })
  const { data: personnels } = useQuery({ queryKey: ['personnels'], queryFn: () => fetchPersonnels({ per_page: 200 }) })

  const [form, setForm] = useState<Record<ChampResponsable, number | ''>>({
    professeur_principal_id: '',
    surveillant_general_id: '',
    censeur_id: '',
    conseiller_orientation_id: '',
  })
  const [erreur, setErreur] = useState<string | null>(null)
  const [enregistre, setEnregistre] = useState(false)

  useEffect(() => {
    if (classe) {
      setForm({
        professeur_principal_id: classe.professeur_principal_id ?? '',
        surveillant_general_id: classe.surveillant_general_id ?? '',
        censeur_id: classe.censeur_id ?? '',
        conseiller_orientation_id: classe.conseiller_orientation_id ?? '',
      })
    }
  }, [classe])

  const enregistrement = useMutation({
    mutationFn: (courant: Classe) =>
      updateClasse(classeId, {
        niveau_id: courant.niveau_id,
        annee_scolaire_id: courant.annee_scolaire_id,
        nom: courant.nom,
        filiere: courant.filiere,
        capacite: courant.capacite,
        professeur_principal_id: form.professeur_principal_id || null,
        surveillant_general_id: form.surveillant_general_id || null,
        censeur_id: form.censeur_id || null,
        conseiller_orientation_id: form.conseiller_orientation_id || null,
      }),
    onSuccess: () => {
      setErreur(null)
      setEnregistre(true)
      queryClient.invalidateQueries({ queryKey: ['classe', classeId] })
    },
    onError: (e: ApiError) => {
      setEnregistre(false)
      setErreur(e.message)
    },
  })

  if (isLoading || !classe) return <Spinner />

  return (
    <Card className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <UserCog className="h-4 w-4 text-gold-500" />
        <h2 className="font-display text-base font-bold text-navy-900">{t('classes.responsables_title')}</h2>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        {RESPONSABLES.map((responsable) => (
          <div key={responsable.champ} className="flex flex-col gap-1">
            <Select
              label={t(responsable.libeleKey)}
              value={form[responsable.champ]}
              disabled={!peutGerer}
              onChange={(e) => {
                setEnregistre(false)
                setForm({ ...form, [responsable.champ]: e.target.value ? Number(e.target.value) : '' })
              }}
            >
              <option value="">{t('classes.responsable_non_defini')}</option>
              {personnels?.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.nom_complet}
                </option>
              ))}
            </Select>
            <span className="text-[11px] leading-snug text-navy-400">{t(responsable.aideKey)}</span>
          </div>
        ))}
      </div>

      {erreur && <p className="text-sm text-red-500">{erreur}</p>}
      {enregistre && <p className="text-sm text-green-600">{t('classes.responsables_saved')}</p>}

      {peutGerer && (
        <div className="flex justify-end">
          <Button onClick={() => enregistrement.mutate(classe)} disabled={enregistrement.isPending}>
            <Save className="h-4 w-4" />
            {t('common.save')}
          </Button>
        </div>
      )}
    </Card>
  )
}
