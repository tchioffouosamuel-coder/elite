import { useEffect, useMemo, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams, useSearchParams, Link } from 'react-router-dom'
import { clsx } from 'clsx'
import { ArrowLeft, ChevronDown, HeartPulse, History, Plus, Stethoscope, Trash2 } from 'lucide-react'
import {
  createMalaiseReferentiel,
  createVisiteInfirmerie,
  deleteMalaiseReferentiel,
  fetchMalaisesReferentiel,
  fetchVisitesInfirmerie,
  updateVisiteInfirmerie,
  type MalaiseReferentiel,
  type TypeTraitement,
  type VisiteInfirmeriePayload,
} from '@/features/infirmerie/api'
import { fetchEleves } from '@/features/eleves/api'
import { fetchInventaire } from '@/features/inventaire/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select, FieldWrapper } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { succes, erreur, confirmerSuppression } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface MaterielFormValue {
  inventaire_article_id: number | ''
  quantite: number | string
}

interface VisiteFormValues {
  eleve_id: number
  date_visite: string
  raison: string
  soins_prodiges: string
  type_traitement: TypeTraitement
  structure_externe?: string | null
  cout_soins?: number | string | null
  materiels: MaterielFormValue[]
  autre_materiel?: string | null
  cout_autre_materiel?: number | string | null
  observations?: string | null
}

const champTexte =
  'w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm text-navy-900 shadow-soft transition-colors placeholder:text-navy-300 focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100'

function maintenantLocal(): string {
  const date = new Date()
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 16)
}

function formatDateHeure(valeur: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(valeur))
}

function formatMontant(montant: number, locale: string): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: 'XAF', maximumFractionDigits: 0 }).format(montant)
}

export function VisiteInfirmerieFormPage() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { id } = useParams<{ id: string }>()
  const visiteId = id ? Number(id) : undefined
  // Ouvert depuis la fiche d'un élève : l'élève est déjà connu et l'écran doit
  // ramener à sa fiche, pas au registre de l'infirmerie.
  const [searchParams] = useSearchParams()
  const elevePreselectionne = searchParams.get('eleve_id') ? Number(searchParams.get('eleve_id')) : undefined
  const urlRetour = searchParams.get('retour') ?? '/infirmerie'

  const { data: visites, isLoading } = useQuery({
    queryKey: ['infirmerie', 'visites', {}],
    queryFn: () => fetchVisitesInfirmerie(),
  })
  const { data: eleves } = useQuery({ queryKey: ['eleves', 'infirmerie-form'], queryFn: () => fetchEleves({ per_page: 500 }) })

  const visite = visiteId ? visites?.find((v) => v.id === visiteId) : undefined

  const [serverError, setServerError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [selectedMalaiseIds, setSelectedMalaiseIds] = useState<Set<number>>(new Set())
  const [nouveauMalaise, setNouveauMalaise] = useState('')
  const [ajoutMalaiseEnCours, setAjoutMalaiseEnCours] = useState(false)
  const [malaisesOuvert, setMalaisesOuvert] = useState(true)

  const { register, handleSubmit, control, watch, reset } = useForm<VisiteFormValues>({
    defaultValues: visite
      ? {
          eleve_id: visite.eleve.id,
          date_visite: visite.date_visite,
          raison: visite.raison,
          soins_prodiges: visite.soins_prodiges,
          type_traitement: visite.type_traitement,
          structure_externe: visite.structure_externe ?? '',
          cout_soins: visite.cout_soins,
          materiels: visite.materiels
            .filter((m) => m.inventaire_article_id !== null)
            .map((m) => ({ inventaire_article_id: m.inventaire_article_id as number, quantite: m.quantite })),
          autre_materiel: visite.autre_materiel ?? '',
          cout_autre_materiel: visite.cout_autre_materiel,
          observations: visite.observations ?? '',
        }
      : {
          ...(elevePreselectionne ? { eleve_id: elevePreselectionne } : {}),
          date_visite: maintenantLocal(),
          type_traitement: 'interne',
          cout_soins: 0,
          materiels: [],
          cout_autre_materiel: 0,
          observations: '',
        },
  })
  const { fields, append, remove } = useFieldArray({ control, name: 'materiels' })

  useEffect(() => {
    if (!visite) return
    reset({
      eleve_id: visite.eleve.id,
      date_visite: visite.date_visite,
      raison: visite.raison,
      soins_prodiges: visite.soins_prodiges,
      type_traitement: visite.type_traitement,
      structure_externe: visite.structure_externe ?? '',
      cout_soins: visite.cout_soins,
      materiels: visite.materiels
        .filter((m) => m.inventaire_article_id !== null)
        .map((m) => ({ inventaire_article_id: m.inventaire_article_id as number, quantite: m.quantite })),
      autre_materiel: visite.autre_materiel ?? '',
      cout_autre_materiel: visite.cout_autre_materiel,
      observations: visite.observations ?? '',
    })
    setSelectedMalaiseIds(new Set(visite.malaises.map((m) => m.id)))
  }, [visite, reset])

  const eleveId = watch('eleve_id')
  const typeTraitement = watch('type_traitement')
  const materielsValues = watch('materiels')
  const coutSoins = watch('cout_soins')
  const coutAutreMateriel = watch('cout_autre_materiel')

  const eleveSelectionne = eleves?.items.find((e) => e.id === Number(eleveId))
  // Scopée à l'école de l'élève choisi : en mode agrégé (super admin, toutes
  // les écoles), les référentiels et l'inventaire des 3 écoles seraient sinon
  // mélangés (chacune avec ses propres doublons de « Fièvre », « Toux »...).
  const eleveSchoolId = eleveSelectionne?.school?.id

  const { data: malaises } = useQuery({
    queryKey: ['infirmerie', 'malaises', eleveSchoolId],
    queryFn: () => fetchMalaisesReferentiel(eleveSchoolId),
    enabled: !!eleveSchoolId,
  })
  const { data: inventaireData } = useQuery({
    queryKey: ['inventaire', 'infirmerie-form', eleveSchoolId],
    queryFn: () => fetchInventaire(undefined, eleveSchoolId),
    enabled: !!eleveSchoolId,
  })
  const articles = useMemo(() => inventaireData?.articles ?? [], [inventaireData])

  const { data: historique } = useQuery({
    queryKey: ['infirmerie', 'historique', eleveId],
    queryFn: () => fetchVisitesInfirmerie({ eleve_id: Number(eleveId) }),
    enabled: !!eleveId,
  })
  const historiqueAffiche = (historique ?? []).filter((v) => v.id !== visiteId)

  /*
   * Volontairement recalculé à chaque rendu, sans useMemo : react-hook-form
   * mute son tableau de valeurs sur place, si bien que la référence de
   * `materielsValues` ne change pas quand une ligne est modifiée. Mémoïsé, le
   * total restait figé à sa première valeur — la ligne affichait 300 FCFA et
   * le total 0. Sur quelques lignes, la somme ne coûte rien.
   */
  const coutMateriels = (materielsValues ?? []).reduce((total, ligne) => {
    const article = articles.find((a) => a.id === Number(ligne.inventaire_article_id))
    if (!article) return total
    return total + (Number(ligne.quantite) || 0) * (article.valeur_unitaire ?? 0)
  }, 0)

  const coutTotal = (Number(coutSoins) || 0) + coutMateriels + (Number(coutAutreMateriel) || 0)

  const invalidateMalaises = () => queryClient.invalidateQueries({ queryKey: ['infirmerie', 'malaises'] })

  const toggleMalaise = (malaiseId: number) => {
    setSelectedMalaiseIds((prev) => {
      const next = new Set(prev)
      if (next.has(malaiseId)) next.delete(malaiseId)
      else next.add(malaiseId)
      return next
    })
  }

  const handleAjouterMalaise = async () => {
    const label = nouveauMalaise.trim()
    if (!label) return
    setAjoutMalaiseEnCours(true)
    try {
      const cree = await createMalaiseReferentiel({ label_fr: label, school_id: eleveSchoolId })
      invalidateMalaises()
      setSelectedMalaiseIds((prev) => new Set(prev).add(cree.id))
      setNouveauMalaise('')
      succes(t('infirmerie.malaise_created'))
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setAjoutMalaiseEnCours(false)
    }
  }

  const handleSupprimerMalaise = async (malaise: MalaiseReferentiel) => {
    if (!(await confirmerSuppression(t('infirmerie.malaise_delete_confirm', { label: malaise.label_fr })))) return
    try {
      await deleteMalaiseReferentiel(malaise.id)
      invalidateMalaises()
      setSelectedMalaiseIds((prev) => {
        const next = new Set(prev)
        next.delete(malaise.id)
        return next
      })
      succes(t('infirmerie.malaise_deleted'))
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const onSubmit = async (values: VisiteFormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const payload: VisiteInfirmeriePayload = {
        eleve_id: Number(values.eleve_id),
        date_visite: values.date_visite,
        raison: values.raison,
        malaise_ids: Array.from(selectedMalaiseIds),
        soins_prodiges: values.soins_prodiges,
        type_traitement: values.type_traitement,
        structure_externe: values.type_traitement === 'interne' ? null : values.structure_externe?.trim() || null,
        cout_soins: values.cout_soins === '' || values.cout_soins == null ? 0 : Number(values.cout_soins),
        materiels: (values.materiels ?? [])
          .filter((m) => m.inventaire_article_id !== '' && Number(m.quantite) > 0)
          .map((m) => ({ inventaire_article_id: Number(m.inventaire_article_id), quantite: Number(m.quantite) })),
        autre_materiel: values.autre_materiel?.trim() || null,
        cout_autre_materiel: values.cout_autre_materiel === '' || values.cout_autre_materiel == null ? 0 : Number(values.cout_autre_materiel),
        observations: values.observations?.trim() || null,
      }

      if (visite) {
        await updateVisiteInfirmerie(visite.id, payload)
        succes(t('infirmerie.updated'))
      } else {
        await createVisiteInfirmerie(payload)
        succes(t('infirmerie.created'))
      }

      queryClient.invalidateQueries({ queryKey: ['infirmerie', 'visites'] })
      queryClient.invalidateQueries({ queryKey: ['inventaire'] })
      queryClient.invalidateQueries({ queryKey: ['infirmerie-visites'] })
      navigate(urlRetour)
    } catch (err) {
      setServerError((err as ApiError).message)
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (visiteId && isLoading) return <Spinner />
  if (visiteId && !isLoading && !visite) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to={urlRetour} className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Link>
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 shadow-soft ring-1 ring-gold-100">
            <HeartPulse className="h-5 w-5 text-gold-600" />
          </span>
          <h1 className="font-display text-xl font-bold tracking-tight text-navy-900 sm:text-2xl">
            {visite ? t('infirmerie.edit_visit') : t('infirmerie.add_visit')}
          </h1>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-[2fr_1fr] lg:items-start">
        <Card className="p-5">
          <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
            <Select label={t('eleves.title')} {...register('eleve_id', { required: true })}>
              <option value="">—</option>
              {eleves?.items.map((e) => (
                <option key={e.id} value={e.id}>
                  {e.nom_complet} — {e.classe?.nom ?? '—'}
                </option>
              ))}
            </Select>

            <Input label={t('infirmerie.date_visite')} type="datetime-local" {...register('date_visite', { required: true })} />

            <FieldWrapper label={t('infirmerie.raison')}>
              <textarea rows={3} className={champTexte} {...register('raison', { required: true })} />
            </FieldWrapper>

            <div className="flex flex-col gap-1.5">
              <button
                type="button"
                onClick={() => setMalaisesOuvert((o) => !o)}
                className="flex items-center justify-between gap-2 text-left"
              >
                <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('infirmerie.malaises_label')}</span>
                <span className="flex items-center gap-1.5 text-xs text-navy-400">
                  {selectedMalaiseIds.size > 0 && <Badge tone="blue">{selectedMalaiseIds.size}</Badge>}
                  <ChevronDown className={clsx('h-4 w-4 transition-transform', malaisesOuvert && 'rotate-180')} />
                </span>
              </button>

              {malaisesOuvert &&
                (!eleveSchoolId ? (
                  <p className="text-xs text-navy-400">{t('infirmerie.select_eleve_prompt')}</p>
                ) : (
                  <>
                    <div className="flex flex-wrap gap-2">
                      {(malaises ?? []).map((m) => {
                        const selectionne = selectedMalaiseIds.has(m.id)
                        return (
                          <span key={m.id} className="inline-flex items-center gap-1">
                            <button
                              type="button"
                              onClick={() => toggleMalaise(m.id)}
                              className={clsx(
                                'rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset transition-colors',
                                selectionne
                                  ? 'bg-navy-700 text-white ring-navy-700'
                                  : 'bg-cream-50 text-navy-600 ring-navy-100 hover:bg-cream-100',
                              )}
                            >
                              {i18n.language === 'en' && m.label_en ? m.label_en : m.label_fr}
                            </button>
                            <button
                              type="button"
                              title={t('common.delete')}
                              onClick={() => handleSupprimerMalaise(m)}
                              className="rounded-full p-1 text-navy-300 hover:text-red-500"
                            >
                              <Trash2 className="h-3 w-3" />
                            </button>
                          </span>
                        )
                      })}
                      {(malaises ?? []).length === 0 && <p className="text-xs text-navy-400">{t('infirmerie.malaise_empty')}</p>}
                    </div>
                    <div className="mt-2 flex gap-2">
                      <input
                        value={nouveauMalaise}
                        onChange={(e) => setNouveauMalaise(e.target.value)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') {
                            e.preventDefault()
                            handleAjouterMalaise()
                          }
                        }}
                        placeholder={t('infirmerie.malaise_add_placeholder')}
                        className={champTexte}
                      />
                      <Button
                        type="button"
                        variant="secondary"
                        disabled={ajoutMalaiseEnCours || !nouveauMalaise.trim()}
                        onClick={handleAjouterMalaise}
                      >
                        <Plus className="h-4 w-4" />
                        {t('infirmerie.malaise_add')}
                      </Button>
                    </div>
                  </>
                ))}
            </div>

            <FieldWrapper label={t('infirmerie.soins_prodiges')}>
              <textarea rows={3} className={champTexte} {...register('soins_prodiges', { required: true })} />
            </FieldWrapper>

            <FieldWrapper label={t('infirmerie.type_traitement')}>
              <div className="flex flex-wrap gap-4">
                {(['interne', 'externe', 'mixte'] as TypeTraitement[]).map((type) => (
                  <label key={type} className="flex items-center gap-1.5 text-sm text-navy-700">
                    <input type="radio" value={type} className="h-4 w-4 text-gold-600" {...register('type_traitement', { required: true })} />
                    {t(`infirmerie.type_${type}`)}
                  </label>
                ))}
              </div>
            </FieldWrapper>

            {typeTraitement !== 'interne' && (
              <Input
                label={t('infirmerie.structure_externe')}
                placeholder={t('infirmerie.structure_externe_placeholder')}
                {...register('structure_externe', { required: true })}
              />
            )}

            <FieldWrapper label={t('infirmerie.materiels_label')}>
              <div className="flex flex-col gap-2">
                {fields.length === 0 && <p className="text-xs text-navy-400">{t('infirmerie.materiel_empty')}</p>}
                {fields.map((field, index) => {
                  const articleId = materielsValues?.[index]?.inventaire_article_id
                  const article = articles.find((a) => a.id === Number(articleId))
                  const quantite = Number(materielsValues?.[index]?.quantite) || 0
                  const cout = article ? quantite * (article.valeur_unitaire ?? 0) : 0

                  return (
                    <div key={field.id} className="grid grid-cols-[2fr_80px_110px_auto] items-end gap-2">
                      <Select {...register(`materiels.${index}.inventaire_article_id` as const, { required: true })}>
                        <option value="">{t('infirmerie.materiel_select_article')}</option>
                        {articles.map((a) => (
                          <option key={a.id} value={a.id}>
                            {a.nom} ({t('infirmerie.materiel_stock', { quantite: a.quantite })})
                          </option>
                        ))}
                      </Select>
                      <Input type="number" min={1} {...register(`materiels.${index}.quantite` as const, { required: true, min: 1 })} />
                      <span className="rounded-xl border border-navy-100 bg-cream-50 px-3 py-2.5 text-center text-sm font-semibold text-navy-700">
                        {formatMontant(cout, i18n.language)}
                      </span>
                      <button
                        type="button"
                        onClick={() => remove(index)}
                        className="rounded-lg p-2 text-navy-400 hover:bg-cream-100 hover:text-red-500"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  )
                })}
                <button
                  type="button"
                  onClick={() => append({ inventaire_article_id: '', quantite: 1 })}
                  className="flex items-center gap-1 self-start text-xs font-semibold text-navy-600 hover:text-navy-800"
                >
                  <Plus className="h-3.5 w-3.5" />
                  {t('infirmerie.materiel_add')}
                </button>
              </div>
            </FieldWrapper>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label={t('infirmerie.autre_materiel')}
                placeholder={t('infirmerie.autre_materiel_placeholder')}
                {...register('autre_materiel')}
              />
              <Input label={t('infirmerie.cout_autre_materiel')} type="number" min={0} step={1} {...register('cout_autre_materiel')} />
            </div>

            <Input label={t('infirmerie.cout_soins')} type="number" min={0} step={1} {...register('cout_soins')} />

            <div className="rounded-xl border border-navy-100 bg-navy-50 px-4 py-3">
              <div className="flex items-center justify-between text-sm">
                <span className="text-navy-500">{t('infirmerie.cout_materiels')}</span>
                <span className="font-semibold text-navy-800">{formatMontant(coutMateriels, i18n.language)}</span>
              </div>
              <div className="mt-1 flex items-center justify-between text-base">
                <span className="font-bold text-navy-900">{t('infirmerie.cout_total')}</span>
                <span className="font-bold text-gold-600">{formatMontant(coutTotal, i18n.language)}</span>
              </div>
            </div>

            <FieldWrapper label={t('infirmerie.observations')}>
              <textarea rows={3} className={champTexte} {...register('observations')} />
            </FieldWrapper>

            {serverError && <p className="text-sm text-red-500">{serverError}</p>}

            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => navigate('/infirmerie')}>
                {t('common.cancel')}
              </Button>
              <Button type="submit" disabled={submitting}>
                {t('common.save')}
              </Button>
            </div>
          </form>
        </Card>

        <div className="flex flex-col gap-5">
          <Card className="p-5">
            <div className="mb-3 flex items-center gap-2">
              <Stethoscope className="h-4 w-4 text-gold-600" />
              <h2 className="font-display text-sm font-bold text-navy-900">{t('infirmerie.fiche_sanitaire')}</h2>
            </div>
            {!eleveSelectionne ? (
              <p className="text-sm text-navy-400">{t('infirmerie.select_eleve_prompt')}</p>
            ) : (
              <div className="flex flex-col gap-3 text-sm">
                <div className="flex items-center justify-between">
                  <span className="text-navy-400">{t('infirmerie.groupe_sanguin')}</span>
                  <span className="font-semibold text-navy-900">{eleveSelectionne.groupe_sanguin || t('infirmerie.non_renseigne')}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-navy-400">{t('infirmerie.aptitude')}</span>
                  <Badge tone={eleveSelectionne.aptitude === 'inapte' ? 'red' : 'green'}>
                    {eleveSelectionne.aptitude === 'inapte' ? t('infirmerie.aptitude_inapte') : t('infirmerie.aptitude_apte')}
                  </Badge>
                </div>
                <div>
                  <span className="mb-0.5 block text-navy-400">{t('infirmerie.allergies')}</span>
                  <span className="font-medium text-navy-800">{eleveSelectionne.allergies || t('infirmerie.non_renseigne')}</span>
                </div>
                <div>
                  <span className="mb-0.5 block text-navy-400">{t('infirmerie.situation_sanitaire')}</span>
                  <span className="font-medium text-navy-800">{eleveSelectionne.situation_sanitaire || t('infirmerie.non_renseigne')}</span>
                </div>
              </div>
            )}
          </Card>

          <Card className="p-5">
            <div className="mb-3 flex items-center gap-2">
              <History className="h-4 w-4 text-navy-600" />
              <h2 className="font-display text-sm font-bold text-navy-900">{t('infirmerie.historique_label')}</h2>
            </div>
            {!eleveId ? (
              <p className="text-sm text-navy-400">{t('infirmerie.select_eleve_prompt')}</p>
            ) : historiqueAffiche.length === 0 ? (
              <p className="text-sm text-navy-400">{t('infirmerie.historique_empty')}</p>
            ) : (
              <ul className="flex max-h-72 flex-col gap-2 overflow-y-auto">
                {historiqueAffiche.map((v) => (
                  <li key={v.id} className="rounded-lg border border-navy-100 p-2.5 text-xs">
                    <div className="flex items-center justify-between font-semibold text-navy-800">
                      <span>{formatDateHeure(v.date_visite, i18n.language)}</span>
                      <span>{v.cout_total > 0 ? formatMontant(v.cout_total, i18n.language) : '—'}</span>
                    </div>
                    <p className="mt-0.5 truncate text-navy-500">{v.raison}</p>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>
    </div>
  )
}
