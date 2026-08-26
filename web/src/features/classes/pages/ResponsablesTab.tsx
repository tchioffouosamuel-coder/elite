import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Save, UserCog } from 'lucide-react'
import { fetchClasse, updateClasse, type Classe, type Responsable } from '@/features/classes/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore, type CodeAttribution } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import type { ApiError } from '@/shared/types/api'

type ChampResponsable = 'professeur_principal_id' | 'surveillant_general_id' | 'censeur_id' | 'conseiller_orientation_id'

/**
 * Désigner un responsable ne décore pas la fiche : l'agent gagne, sur cette
 * classe précise, les prérogatives de la responsabilité. D'où `attribution`,
 * qui restreint la liste aux fonctions admises côté API — un enseignant peut
 * être désigné surveillant général d'une classe, un économe non.
 */
const RESPONSABLES: {
  champ: ChampResponsable
  attribution: CodeAttribution
  libeleKey: string
  aideKey: string
}[] = [
  {
    champ: 'professeur_principal_id',
    attribution: 'professeur_principal',
    libeleKey: 'classes.responsable_professeur_principal',
    aideKey: 'classes.responsable_professeur_principal_aide',
  },
  {
    champ: 'surveillant_general_id',
    attribution: 'surveillant_general',
    libeleKey: 'classes.responsable_surveillant_general',
    aideKey: 'classes.responsable_surveillant_general_aide',
  },
  {
    champ: 'censeur_id',
    attribution: 'censeur',
    libeleKey: 'classes.responsable_censeur',
    aideKey: 'classes.responsable_censeur_aide',
  },
  {
    champ: 'conseiller_orientation_id',
    attribution: 'conseiller_orientation',
    libeleKey: 'classes.responsable_conseiller_orientation',
    aideKey: 'classes.responsable_conseiller_orientation_aide',
  },
]

/**
 * Candidats à une responsabilité. Une requête par responsabilité plutôt qu'un
 * filtrage local : c'est l'API qui sait quelles fonctions sont éligibles, et
 * la dupliquer ici la ferait diverger le jour où le référentiel change.
 */
function useCandidats(attribution: CodeAttribution) {
  return useQuery({
    queryKey: ['personnels', 'attribution', attribution],
    queryFn: () => fetchPersonnels({ attribution, per_page: 200 }),
  })
}

function ChampResponsableSelect({
  responsable,
  valeur,
  actuel,
  desactive,
  onChange,
}: {
  responsable: (typeof RESPONSABLES)[number]
  valeur: number | ''
  actuel: Responsable | null
  desactive: boolean
  onChange: (id: number | '') => void
}) {
  const { t } = useTranslation()
  const { data: candidats, isLoading } = useCandidats(responsable.attribution)

  /*
   * Le titulaire en poste peut avoir changé de fonction depuis sa
   * désignation : sans ce repli il disparaîtrait de la liste, et le premier
   * enregistrement de la fiche le retirerait sans que personne l'ait voulu.
   */
  const options =
    actuel && !candidats?.some((p) => p.id === actuel.id) ? [...(candidats ?? []), actuel] : (candidats ?? [])

  return (
    <div className="flex flex-col gap-1">
      <Select
        label={t(responsable.libeleKey)}
        value={valeur}
        disabled={desactive || isLoading}
        onChange={(e) => onChange(e.target.value ? Number(e.target.value) : '')}
      >
        <option value="">{t('classes.responsable_non_defini')}</option>
        {options.map((p) => (
          <option key={p.id} value={p.id}>
            {p.nom_complet}
          </option>
        ))}
      </Select>
      <span className="text-[11px] leading-snug text-navy-400">{t(responsable.aideKey)}</span>
      {!isLoading && options.length === 0 && (
        <span className="text-[11px] leading-snug text-amber-600">{t('classes.responsable_aucun_eligible')}</span>
      )}
    </div>
  )
}

/** Colonne d'identifiant → relation chargée, pour retrouver le titulaire en poste. */
const CHAMP_RELATION: Record<ChampResponsable, keyof Pick<Classe, 'professeur_principal' | 'surveillant_general' | 'censeur' | 'conseiller_orientation'>> = {
  professeur_principal_id: 'professeur_principal',
  surveillant_general_id: 'surveillant_general',
  censeur_id: 'censeur',
  conseiller_orientation_id: 'conseiller_orientation',
}

export function ResponsablesTab({ classeId }: { classeId: number }) {
  const { t } = useTranslation()
  const peutGerer = useAuthStore((s) => s.can('classes.manage'))
  const queryClient = useQueryClient()

  const { data: classe, isLoading } = useQuery({ queryKey: ['classe', classeId], queryFn: () => fetchClasse(classeId) })

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
        nom: courant.nom,
        filiere: courant.filiere,
        capacite: courant.capacite,
        professeur_principal_id: form.professeur_principal_id || null,
        surveillant_general_id: form.surveillant_general_id || null,
        censeur_id: form.censeur_id || null,
        conseiller_orientation_id: form.conseiller_orientation_id || null,
        // Sans ce school_id explicite, la validation d'existence des agents
        // (StoreClasseRequest::scopedExists) retombe sur l'école ambiante du
        // compte — qui, pour un super admin en mode "toutes les écoles", peut
        // ne pas être celle de cette classe, et rejette alors même un
        // responsable parfaitement valide.
        school_id: courant.school_id ?? null,
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
          <ChampResponsableSelect
            key={responsable.champ}
            responsable={responsable}
            valeur={form[responsable.champ]}
            actuel={classe[CHAMP_RELATION[responsable.champ]]}
            desactive={!peutGerer}
            onChange={(id) => {
              setEnregistre(false)
              setForm({ ...form, [responsable.champ]: id })
            }}
          />
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
