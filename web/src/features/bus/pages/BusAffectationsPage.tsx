import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router-dom'
import { MapPin, Pencil, Trash2, UserPlus, Users } from 'lucide-react'
import {
  affecterEleve,
  fetchAffectations,
  fetchTrajet,
  fetchTrajets,
  modifierAffectation,
  retirerAffectation,
  type BusAffectation,
  type BusTrajet,
  type OptionTrajet,
} from '@/features/bus/api'
import { fetchEleves, type Eleve } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Élèves affectés au transport, tous trajets confondus — le seul endroit où
 * chercher « où prend le bus cet élève » sans deviner sur quel trajet le
 * trouver. Affecter un élève depuis la fiche d'un trajet reste possible,
 * mais suppose de déjà savoir lequel ; ici la recherche fait le travail.
 */
export function BusAffectationsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const [trajetFiltre, setTrajetFiltre] = useState<number | ''>('')
  const [showForm, setShowForm] = useState(false)
  const [affectationEnEdition, setAffectationEnEdition] = useState<BusAffectation | null>(null)

  const { data: trajets } = useQuery({ queryKey: ['bus-trajets', 'select'], queryFn: fetchTrajets })
  const { data: affectations, isLoading } = useQuery({
    queryKey: ['bus-affectations', trajetFiltre],
    queryFn: () => fetchAffectations(trajetFiltre || undefined),
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['bus-affectations'] })
    queryClient.invalidateQueries({ queryKey: ['bus-trajets'] })
    queryClient.invalidateQueries({ queryKey: ['bus-trajet'] })
  }

  const colonnes: Colonne<BusAffectation>[] = [
    {
      cle: 'eleve',
      entete: t('bus.eleve'),
      valeur: (a) => `${a.eleve.nom_complet} ${a.eleve.matricule ?? ''}`,
      cellule: (a) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{a.eleve.nom_complet}</div>
          <div className="truncate text-xs text-navy-400">
            {a.eleve.matricule ?? '—'} · {a.eleve.classe ?? '—'}
          </div>
        </div>
      ),
    },
    {
      cle: 'trajet',
      entete: t('bus.trajets_title'),
      valeur: (a) => a.trajet.nom,
      cellule: (a) => (
        <Link to={`/bus/trajets/${a.trajet.id}`} className="text-navy-700 hover:text-gold-600 hover:underline">
          {a.trajet.nom}
        </Link>
      ),
    },
    {
      cle: 'arret',
      entete: t('bus.arret_select'),
      valeur: (a) => a.arret?.nom,
      cellule: (a) =>
        a.arret ? (
          <span className="inline-flex items-center gap-1 text-navy-600">
            <MapPin className="h-3.5 w-3.5 text-navy-300" />
            {a.arret.nom}
          </span>
        ) : (
          '—'
        ),
      masquerMobile: true,
    },
    {
      cle: 'option',
      entete: t('bus.option_trajet'),
      valeur: (a) => a.option_trajet,
      cellule: (a) => t(`bus.${a.option_trajet}`),
      masquerMobile: true,
    },
    {
      cle: 'tarif',
      entete: t('bus.tarif_mensuel'),
      valeur: (a) => a.tarif_mensuel ?? -1,
      cellule: (a) => (a.tarif_mensuel ? `${a.tarif_mensuel.toLocaleString('fr-FR')} FCFA` : '—'),
    },
    {
      cle: 'statut',
      entete: t('bus.statut'),
      valeur: (a) => a.statut,
      cellule: (a) => <Badge tone={a.statut === 'actif' ? 'green' : 'neutral'}>{t(`bus.${a.statut}`)}</Badge>,
    },
    ...(can('bus.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (a: BusAffectation) => (
              <div className="flex items-center justify-end gap-1">
                <button
                  title={t('common.edit')}
                  onClick={() => {
                    setAffectationEnEdition(a)
                    setShowForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title={t('bus.affectation_remove')}
                  onClick={async () => {
                    if (!(await confirmerSuppression(a.eleve.nom_complet))) return
                    try {
                      await retirerAffectation(a.id)
                      invalidate()
                      succes(t('bus.affectation_deleted'))
                    } catch (err) {
                      erreur((err as ApiError).message)
                    }
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<BusAffectation>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('bus.affectations')}
        sousTitre={t('bus.affectations_subtitle')}
        icon={Users}
        actions={
          can('bus.manage') && (
            <Button
              onClick={() => {
                setAffectationEnEdition(null)
                setShowForm(true)
              }}
            >
              <UserPlus className="h-4 w-4" />
              {t('bus.affectation_add')}
            </Button>
          )
        }
      />

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={affectations ?? []}
          cleLigne={(a) => a.id}
          placeholderRecherche={t('bus.search_eleve')}
          messageVide={t('bus.empty_affectations')}
          largeurMin={860}
          outils={
            <Select
              value={trajetFiltre}
              onChange={(e) => setTrajetFiltre(e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">{t('bus.all_trajets')}</option>
              {trajets?.map((tr) => (
                <option key={tr.id} value={tr.id}>
                  {tr.nom}
                </option>
              ))}
            </Select>
          }
        />
      )}

      {showForm && (
        <AffectationFormModal
          trajets={trajets ?? []}
          affectation={affectationEnEdition}
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            setAffectationEnEdition(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

/**
 * Sert à la fois la création (trajet à choisir, élève cherché par nom) et la
 * modification (trajet et élève déjà fixés, seuls arrêt/option/tarif/statut
 * bougent) — deux écrans distincts pour la même poignée de champs auraient
 * fait doublon sans rien clarifier.
 */
function AffectationFormModal({
  trajets,
  affectation,
  onClose,
  onSaved,
}: {
  trajets: BusTrajet[]
  affectation: BusAffectation | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const [rechercheEleve, setRechercheEleve] = useState('')
  const [trajetId, setTrajetId] = useState<number | ''>(affectation?.trajet.id ?? '')

  const { data: eleves } = useQuery({
    queryKey: ['eleves', 'bus-affectation', rechercheEleve],
    queryFn: () => fetchEleves({ search: rechercheEleve || undefined, per_page: 30 }),
    enabled: !affectation,
  })

  const { data: trajetDetail } = useQuery({
    queryKey: ['bus-trajet', trajetId],
    queryFn: () => fetchTrajet(Number(trajetId)),
    enabled: trajetId !== '',
  })

  const elevesDejaAffectes = useMemo(
    () => new Set((trajetDetail?.affectations ?? []).map((a) => a.eleve.id)),
    [trajetDetail],
  )

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<{
    eleve_id: number
    arret_id?: number
    tarif_mensuel?: number
    option_trajet: OptionTrajet
    statut?: BusAffectation['statut']
  }>({
    defaultValues: affectation
      ? {
          arret_id: affectation.arret?.id,
          tarif_mensuel: affectation.tarif_mensuel ?? undefined,
          option_trajet: affectation.option_trajet,
          statut: affectation.statut,
        }
      : { option_trajet: 'aller_retour' },
  })

  const onSubmit = async (values: {
    eleve_id: number
    arret_id?: number
    tarif_mensuel?: number
    option_trajet: OptionTrajet
    statut?: BusAffectation['statut']
  }) => {
    setServerError(null)
    try {
      if (affectation) {
        await modifierAffectation(affectation.id, {
          arret_id: values.arret_id ? Number(values.arret_id) : null,
          tarif_mensuel: values.tarif_mensuel ? Number(values.tarif_mensuel) : null,
          option_trajet: values.option_trajet,
          statut: values.statut,
        })
        succes(t('bus.affectation_updated'))
      } else {
        await affecterEleve({
          eleve_id: Number(values.eleve_id),
          trajet_id: Number(trajetId),
          arret_id: values.arret_id ? Number(values.arret_id) : null,
          tarif_mensuel: values.tarif_mensuel ? Number(values.tarif_mensuel) : null,
          option_trajet: values.option_trajet,
        })
        succes(t('bus.affectation_created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={affectation ? t('bus.affectation_edit') : t('bus.affectation_add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        {affectation ? (
          <p className="rounded-xl bg-cream-100 px-3.5 py-2.5 text-sm text-navy-700">
            <span className="font-semibold">{affectation.eleve.nom_complet}</span> — {affectation.trajet.nom}
          </p>
        ) : (
          <>
            <Select
              label={t('bus.trajets_title')}
              value={trajetId}
              onChange={(e) => setTrajetId(e.target.value ? Number(e.target.value) : '')}
            >
              <option value="">—</option>
              {trajets.map((tr) => (
                <option key={tr.id} value={tr.id}>
                  {tr.nom}
                </option>
              ))}
            </Select>

            <Input
              label={t('bus.search_eleve')}
              value={rechercheEleve}
              onChange={(e) => setRechercheEleve(e.target.value)}
            />

            <Select
              label={t('bus.eleve')}
              error={errors.eleve_id?.message}
              {...register('eleve_id', { required: t('bus.field_required') as string })}
            >
              <option value="">—</option>
              {eleves?.items
                .filter((e: Eleve) => !elevesDejaAffectes.has(e.id))
                .map((e: Eleve) => (
                  <option key={e.id} value={e.id}>
                    {e.nom_complet}
                  </option>
                ))}
            </Select>
          </>
        )}

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

        <Input label={t('bus.tarif_mensuel')} type="number" min={0} {...register('tarif_mensuel')} />

        {affectation && (
          <Select label={t('bus.statut')} {...register('statut')}>
            <option value="actif">{t('bus.actif')}</option>
            <option value="suspendu">{t('bus.suspendu')}</option>
          </Select>
        )}

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting || (!affectation && trajetId === '')}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
