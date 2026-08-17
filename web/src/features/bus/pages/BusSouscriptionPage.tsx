import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useLocation, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { ArrowLeft, Bus } from 'lucide-react'
import {
  fetchTrajets,
  fetchTrajet,
  souscrireEleve,
  souscrireLot,
  modifierAffectation,
  tarifPourOption,
  type OptionTrajet,
} from '@/features/bus/api'
import { fetchEleves, type Eleve } from '@/features/eleves/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface EtatNavigation {
  eleveIds: number[]
  eleveNoms: string[]
  /** Modification d'une souscription existante plutôt qu'une nouvelle. */
  affectationId?: number
  affectationActuelle?: { trajet_id: number; arret_id: number | null; option_trajet: OptionTrajet }
  /** Trajet déjà choisi si on arrive depuis la fiche d'un trajet précis. */
  trajetId?: number
  retour?: string
}

/**
 * Souscription au bus : un seul écran pour un élève ou pour un lot (fratrie,
 * classe entière) — le tarif ne s'y saisit jamais, il se lit sur le trajet
 * dès que l'option est choisie.
 */
export function BusSouscriptionPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const { eleveId: eleveIdParam } = useParams<{ eleveId?: string }>()
  const [serverError, setServerError] = useState<string | null>(null)

  const etat = (location.state as EtatNavigation | null) ?? null
  const [eleveChoisi, setEleveChoisi] = useState<Eleve | null>(null)
  const [rechercheEleve, setRechercheEleve] = useState('')

  const eleveIds = etat?.eleveIds ?? (eleveIdParam ? [Number(eleveIdParam)] : eleveChoisi ? [eleveChoisi.id] : [])
  const eleveNoms = etat?.eleveNoms ?? (eleveChoisi ? [eleveChoisi.nom_complet] : [])
  const modification = etat?.affectationId

  // Ni élève imposé par la navigation, ni déjà choisi ici : on part d'une
  // fiche trajet sans savoir qui affecter, il faut donc pouvoir le chercher.
  const choixEleveRequis = eleveIds.length === 0

  const { data: elevesRecherche } = useQuery({
    queryKey: ['eleves', 'bus-souscription', rechercheEleve],
    queryFn: () => fetchEleves({ search: rechercheEleve || undefined, per_page: 20 }),
    enabled: choixEleveRequis,
  })

  const { data: trajets } = useQuery({ queryKey: ['bus-trajets', 'select'], queryFn: fetchTrajets })

  const {
    register,
    handleSubmit,
    watch,
    formState: { isSubmitting, errors },
  } = useForm<{ trajet_id: number; arret_id?: number; option_trajet: OptionTrajet }>({
    defaultValues: etat?.affectationActuelle
      ? {
          trajet_id: etat.affectationActuelle.trajet_id,
          arret_id: etat.affectationActuelle.arret_id ?? undefined,
          option_trajet: etat.affectationActuelle.option_trajet,
        }
      : { trajet_id: etat?.trajetId, option_trajet: 'aller_retour' },
  })

  const trajetId = watch('trajet_id')
  const optionChoisie = watch('option_trajet')

  const { data: trajetDetail } = useQuery({
    queryKey: ['bus-trajet', trajetId],
    queryFn: () => fetchTrajet(Number(trajetId)),
    enabled: !!trajetId,
  })

  const trajetSelectionne = trajets?.find((tr) => tr.id === Number(trajetId))
  const tarifApercu = useMemo(
    () => (trajetSelectionne && optionChoisie ? tarifPourOption(trajetSelectionne, optionChoisie) : null),
    [trajetSelectionne, optionChoisie],
  )

  const retour = () => navigate(etat?.retour ?? '/bus/eleves')

  const onSubmit = async (values: { trajet_id: number; arret_id?: number; option_trajet: OptionTrajet }) => {
    setServerError(null)
    const payload = {
      trajet_id: Number(values.trajet_id),
      arret_id: values.arret_id ? Number(values.arret_id) : null,
      option_trajet: values.option_trajet,
    }

    try {
      if (modification) {
        await modifierAffectation(modification, payload)
        succes(t('bus.affectation_updated'))
      } else if (eleveIds.length === 1) {
        await souscrireEleve(eleveIds[0], payload)
        succes(t('bus.souscription_created'))
      } else {
        const { souscrits, ignores } = await souscrireLot(eleveIds, payload)
        succes(t('bus.souscriptions_lot_created', { count: souscrits }))
        if (ignores.length > 0) erreur(t('bus.souscriptions_deja_affectes', { list: ignores.join(', ') }))
      }
      retour()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  if (choixEleveRequis) {
    return (
      <div className="flex flex-col gap-5">
        <div>
          <button
            onClick={retour}
            className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
          >
            <ArrowLeft className="h-4 w-4" />
            {t('common.back')}
          </button>
          <PageHeader titre={t('bus.souscription_title')} sousTitre={t('bus.search_eleve')} icon={Bus} />
        </div>

        <Card className="max-w-xl">
          <div className="flex flex-col gap-3">
            <Input
              label={t('bus.search_eleve')}
              value={rechercheEleve}
              onChange={(e) => setRechercheEleve(e.target.value)}
              autoFocus
            />
            <div className="flex flex-col divide-y divide-navy-50">
              {(elevesRecherche?.items ?? []).map((e) => (
                <button
                  key={e.id}
                  onClick={() => setEleveChoisi(e)}
                  className="flex flex-col items-start px-1 py-2 text-left hover:bg-cream-50"
                >
                  <span className="text-sm font-semibold text-navy-900">{e.nom_complet}</span>
                  <span className="text-xs text-navy-400">{e.matricule ?? '—'} · {e.classe?.nom ?? '—'}</span>
                </button>
              ))}
              {rechercheEleve && elevesRecherche?.items.length === 0 && (
                <p className="py-3 text-sm text-navy-400">{t('common.no_results')}</p>
              )}
            </div>
          </div>
        </Card>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-5">
      <div>
        <button
          onClick={retour}
          className="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
        >
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </button>
        <PageHeader
          titre={modification ? t('bus.souscription_edit_title') : t('bus.souscription_title')}
          sousTitre={eleveNoms.length > 0 ? eleveNoms.join(', ') : t('bus.eleves_selectionnes', { count: eleveIds.length })}
          icon={Bus}
        />
      </div>

      <Card className="max-w-xl">
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
          <Select
            label={t('bus.trajets_title')}
            error={errors.trajet_id?.message}
            {...register('trajet_id', { required: t('bus.field_required') as string })}
          >
            <option value="">—</option>
            {trajets?.map((tr) => (
              <option key={tr.id} value={tr.id}>
                {tr.nom}
              </option>
            ))}
          </Select>

          <Select label={t('bus.arret_select')} {...register('arret_id')}>
            <option value="">—</option>
            {(trajetDetail?.arrets ?? []).map((arret) => (
              <option key={arret.id} value={arret.id}>
                {arret.nom}
              </option>
            ))}
          </Select>

          <Select label={t('bus.option_trajet')} {...register('option_trajet', { required: true })}>
            <option value="aller_retour">{t('bus.aller_retour')}</option>
            <option value="aller_simple">{t('bus.aller_simple')}</option>
            <option value="retour_simple">{t('bus.retour_simple')}</option>
          </Select>

          {trajetSelectionne && (
            <div className="rounded-xl bg-cream-100 px-3.5 py-2.5 text-sm text-navy-700">
              {tarifApercu ? (
                <>
                  {t('bus.tarif_mensuel')} :{' '}
                  <span className="font-semibold tabular-nums">{tarifApercu.toLocaleString('fr-FR')} FCFA</span>
                </>
              ) : (
                <span className="text-gold-700">{t('bus.no_tarif_defini')}</span>
              )}
            </div>
          )}

          {serverError && <p className="text-sm text-red-500">{serverError}</p>}

          <div className="mt-2 flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={retour}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting || !trajetId}>
              {isSubmitting ? '…' : t('common.save')}
            </Button>
          </div>
        </form>
      </Card>

      {!trajets && <Spinner />}
    </div>
  )
}
