import { useForm, useFieldArray } from 'react-hook-form'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useEffect, useState } from 'react'
import { ArrowLeft, Plus, Receipt, Trash2 } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { StepForm } from '@/shared/ui/StepForm'
import { Input, Select } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Spinner } from '@/shared/ui/Feedback'
import { fetchClasses } from '@/features/classes/api'
import { createEleve, updateEleve, fetchEleves, type ElevePayload } from '@/features/eleves/api'
import { fetchTarifs, fetchDossier, encaisser, francs, MODES, type ModePaiement } from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import type { ApiError } from '@/shared/types/api'
import { succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'

function useLienParenteOptions() {
    const { t } = useTranslation()
    return [
        { value: 'père', label: t('eleves.inscription.lien_pere') },
        { value: 'mère', label: t('eleves.inscription.lien_mere') },
        { value: 'tuteur', label: t('eleves.inscription.lien_tuteur') },
        { value: 'autre', label: t('eleves.inscription.lien_autre') },
    ]
}

interface TuteurFormData {
    nom_complet: string
    telephone?: string
    profession?: string
    lien_parente?: string
    // Précision libre saisie quand `lien_parente` vaut "autre" — combinée avec
    // lui à la soumission ("autre: <précision>"), jamais envoyée telle quelle.
    lien_parente_autre?: string
    is_principal?: boolean
}

/**
 * Type du formulaire, distinct de `ElevePayload` : le champ `lien_parente_autre`
 * n'existe que côté saisie (fusionné dans `lien_parente` à la soumission), et
 * `refugie`/`deplace_interne` transitent par `''` tant que le select n'a pas
 * été choisi — deux états que l'API elle-même n'accepte pas.
 */
interface EleveFormValues {
    nom_complet: string
    sexe: 'M' | 'F' | ''
    date_naissance?: string
    lieu_naissance?: string
    numero_acte_naissance?: string
    adresse?: string
    refugie?: 'Oui' | 'Non' | ''
    deplace_interne?: 'Oui' | 'Non' | ''
    classe_id?: number
    tuteurs: TuteurFormData[]
    // Étape facultative : ne déclenche un encaissement que si un montant est saisi.
    paiement_montant?: number
    paiement_mode?: ModePaiement
    paiement_date?: string
    paiement_reference?: string
    paiement_note?: string
}

export function EleveInscriptionPage() {
    const { t } = useTranslation()
    const LIEN_PARENTE_OPTIONS = useLienParenteOptions()
    const navigate = useNavigate()
    const { id } = useParams<{ id: string }>()
    const eleveId = id ? Number(id) : undefined
    const queryClient = useQueryClient()
    const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })

    // Récupérer l'élève si on est en édition
    const { data: elevesData, isLoading } = useQuery({
        queryKey: ['eleves', eleveId],
        queryFn: () => fetchEleves({ per_page: 1000 }),
        enabled: !!eleveId,
    })

    const eleve = eleveId ? elevesData?.items?.find(e => e.id === eleveId) : undefined
    const [currentStep, setCurrentStep] = useState(0)
    const [serverError, setServerError] = useState<string | null>(null)
    const [submitting, setSubmitting] = useState(false)

    // Valeurs par défaut de l'étape paiement facultative : espèces, aujourd'hui.
    const paiementDefaults = { paiement_mode: 'especes' as ModePaiement, paiement_date: new Date().toISOString().slice(0, 10) }

    const {
        register,
        control,
        handleSubmit,
        reset,
        formState: { errors },
        watch,
    } = useForm<EleveFormValues>({
        defaultValues: eleve
            ? {
                nom_complet: eleve.nom_complet,
                sexe: eleve.sexe,
                date_naissance: eleve.date_naissance ?? '',
                lieu_naissance: eleve.lieu_naissance ?? '',
                numero_acte_naissance: eleve.numero_acte_naissance ?? '',
                adresse: eleve.adresse ?? '',
                refugie: eleve.refugie ?? '',
                deplace_interne: eleve.deplace_interne ?? '',
                classe_id: eleve.classe?.id,
                tuteurs: eleve.tuteurs.map((t) => ({
                    nom_complet: t.nom_complet,
                    telephone: t.telephone ?? '',
                    profession: t.profession ?? '',
                    lien_parente: t.lien_parente ?? '',
                    is_principal: t.is_principal,
                })),
                ...paiementDefaults,
            }
            : { tuteurs: [], ...paiementDefaults },
    })

    // `defaultValues` n'est lu qu'au montage, or l'élève arrive après la requête :
    // sans ce reset, le formulaire d'édition s'ouvrirait vide (élève et tuteurs).
    useEffect(() => {
        if (!eleve) return
        reset({
            nom_complet: eleve.nom_complet,
            sexe: eleve.sexe,
            date_naissance: eleve.date_naissance ?? '',
            lieu_naissance: eleve.lieu_naissance ?? '',
            numero_acte_naissance: eleve.numero_acte_naissance ?? '',
            adresse: eleve.adresse ?? '',
            refugie: eleve.refugie ?? '',
            deplace_interne: eleve.deplace_interne ?? '',
            classe_id: eleve.classe?.id,
            tuteurs: eleve.tuteurs.map((t) => ({
                nom_complet: t.nom_complet,
                telephone: t.telephone ?? '',
                profession: t.profession ?? '',
                lien_parente: t.lien_parente ?? '',
                is_principal: t.is_principal,
            })),
            ...paiementDefaults,
        })
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [eleve, reset])

    const { fields, append, remove } = useFieldArray({ control, name: 'tuteurs' })
    const tuteurs = watch('tuteurs')
    const can = useAuthStore((s) => s.can)

    // Un tarif existe déjà pour la classe choisie : proposer l'encaissement
    // immédiat plutôt que de renvoyer l'utilisateur vers la caisse ensuite.
    const classeIdSelectionnee = watch('classe_id') ? Number(watch('classe_id')) : undefined
    const { data: tarifs } = useQuery({
        queryKey: ['tarifs'],
        queryFn: fetchTarifs,
        enabled: can('finance.encaisser'),
    })
    const montantTarif = classeIdSelectionnee
        ? (tarifs?.classes.find((c) => c.id === classeIdSelectionnee)?.montant ?? tarifs?.tarif_par_defaut ?? null)
        : null
    const etapePaiementDisponible = can('finance.encaisser') && montantTarif != null && montantTarif > 0

    const steps = [
        { id: 'identite', label: t('eleves.inscription.step_identite_label'), description: t('eleves.inscription.step_identite_description') },
        { id: 'scolarite', label: t('eleves.inscription.step_scolarite_label'), description: t('eleves.inscription.step_scolarite_description') },
        ...(etapePaiementDisponible
            ? [{ id: 'paiement', label: t('eleves.inscription.step_paiement_label'), description: t('eleves.inscription.step_paiement_description') }]
            : []),
        { id: 'tuteurs', label: t('eleves.inscription.step_tuteurs_label'), description: t('eleves.inscription.step_tuteurs_description') },
        { id: 'confirmation', label: t('eleves.inscription.step_confirmation_label'), description: t('eleves.inscription.step_confirmation_description') },
    ]

    const onSubmit = async (values: EleveFormValues) => {
        setServerError(null)
        setSubmitting(true)
        try {
            // Traiter les tuteurs pour combiner lien_parente et lien_parente_autre
            const tuteurs = (values.tuteurs ?? []).map((tuteur) => {
                const { lien_parente_autre, ...rest } = tuteur
                let lien_parente = rest.lien_parente

                if (lien_parente === 'autre' && lien_parente_autre) {
                    // Formatter comme "autre: [précision]"
                    lien_parente = `autre: ${lien_parente_autre}`
                }

                return {
                    ...rest,
                    lien_parente,
                }
            })

            const payload: ElevePayload = {
                nom_complet: values.nom_complet,
                sexe: values.sexe as 'M' | 'F',
                date_naissance: values.date_naissance,
                lieu_naissance: values.lieu_naissance,
                adresse: values.adresse,
                classe_id: values.classe_id ? Number(values.classe_id) : null,
                tuteurs,
            }

            const cible = eleve ? await updateEleve(eleve.id, payload) : await createEleve(payload)

            const montantPaiement = Number(values.paiement_montant) || 0
            if (etapePaiementDisponible && montantPaiement > 0) {
                const dossier = await fetchDossier(cible.id)
                const { numero_recu, versement_id } = await encaisser(dossier.id, {
                    montant: montantPaiement,
                    mode: values.paiement_mode || 'especes',
                    date_versement: values.paiement_date || undefined,
                    reference_externe: values.paiement_reference || undefined,
                    note: values.paiement_note || undefined,
                })
                ouvrirDocument(`/versements/${versement_id}/recu`)
                succes(t('finance.receipt_recorded', { numero: numero_recu }))
            } else {
                succes(eleve ? t('common.updated_successfully') : t('common.created_successfully'))
            }

            queryClient.invalidateQueries({ queryKey: ['eleves'] })
            navigate('/eleves')
        } catch (err) {
            setServerError((err as ApiError).message)
        } finally {
            setSubmitting(false)
        }
    }

    return (
        <div className="min-h-screen bg-navy-50 py-8 px-4">
            <Link to="/eleves" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
                <ArrowLeft className="h-4 w-4" />
                {t('common.back')}
            </Link>
            <PageHeader
                titre={eleve ? t('eleves.edit') : t('eleves.add')}
                sousTitre={eleve ? t('eleves.inscription.page_description_edit') : t('eleves.inscription.page_description_create')}
            />

            {isLoading && eleveId ? (
                <div className="mt-6"><Spinner /></div>
            ) : (
                <div className="mt-6">
                    <form onSubmit={handleSubmit(onSubmit)}>
                        <StepForm
                            steps={steps}
                            currentStep={currentStep}
                            onStepChange={setCurrentStep}
                            isLastStep={currentStep === steps.length - 1}
                            isSubmitting={submitting}
                            onSubmit={handleSubmit(onSubmit)}
                            onCancel={() => navigate('/eleves')}
                            showSteps={true}
                        >
                            {/* Étape 1: Identité */}
                            {steps[currentStep]?.id === 'identite' && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-semibold text-navy-900 mb-4">{t('eleves.inscription.identite_title')}</h3>
                                    <Input
                                        label={t('eleves.nom_complet')}
                                        error={errors.nom_complet?.message}
                                        {...register('nom_complet', { required: t('eleves.inscription.nom_complet_required') })}
                                        placeholder={t('eleves.inscription.nom_complet_placeholder')}
                                    />
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Select
                                            label={t('eleves.sexe')}
                                            error={errors.sexe?.message}
                                            {...register('sexe', { required: t('eleves.inscription.sexe_required') })}
                                        >
                                            <option value="">{t('eleves.inscription.select_placeholder')}</option>
                                            <option value="F">{t('eleves.feminin')}</option>
                                            <option value="M">{t('eleves.masculin')}</option>
                                        </Select>
                                        <Input
                                            label={t('eleves.date_naissance')}
                                            type="date"
                                            {...register('date_naissance')}
                                            error={errors.date_naissance?.message}
                                        />
                                    </div>
                                    <Input
                                        label={t('eleves.inscription.lieu_naissance_placeholder')}
                                        placeholder={t('eleves.inscription.lieu_naissance_placeholder')}
                                        {...register('lieu_naissance')}
                                        error={errors.lieu_naissance?.message}
                                    />
                                    <Input
                                        label={t('eleves.inscription.numero_acte_naissance')}
                                        placeholder={t('eleves.inscription.numero_acte_naissance')}
                                        {...register('numero_acte_naissance')}
                                        error={errors.numero_acte_naissance?.message}
                                    />
                                    <Input
                                        label={t('eleves.inscription.adresse')}
                                        placeholder={t('eleves.inscription.adresse_placeholder')}
                                        {...register('adresse')}
                                        error={errors.adresse?.message}
                                    />
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Select
                                            label={t('eleves.inscription.refugie')}
                                            {...register('refugie')}
                                            error={errors.refugie?.message}
                                        >
                                            <option value="">{t('eleves.inscription.non_applicable_placeholder')}</option>
                                            <option value="Oui">{t('eleves.inscription.oui')}</option>
                                            <option value="Non">{t('eleves.inscription.non')}</option>
                                        </Select>
                                        <Select
                                            label={t('eleves.inscription.deplace_interne')}
                                            {...register('deplace_interne')}
                                            error={errors.deplace_interne?.message}
                                        >
                                            <option value="">{t('eleves.inscription.non_applicable_placeholder')}</option>
                                            <option value="Oui">{t('eleves.inscription.oui')}</option>
                                            <option value="Non">{t('eleves.inscription.non')}</option>
                                        </Select>
                                    </div>
                                </div>
                            )}

                            {/* Étape 2: Scolarité */}
                            {steps[currentStep]?.id === 'scolarite' && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-semibold text-navy-900 mb-4">{t('eleves.inscription.scolarite_title')}</h3>
                                    <Select
                                        label={t('eleves.classe')}
                                        {...register('classe_id')}
                                        error={errors.classe_id?.message}
                                    >
                                        <option value="">{t('eleves.inscription.select_classe_placeholder')}</option>
                                        {classes?.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.nom}
                                            </option>
                                        ))}
                                    </Select>
                                    <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p className="text-sm text-blue-800">{t('eleves.inscription.classe_hint')}</p>
                                    </div>
                                </div>
                            )}

                            {/* Étape facultative : Paiement — n'apparaît que si un tarif existe déjà pour la classe choisie */}
                            {steps[currentStep]?.id === 'paiement' && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-semibold text-navy-900 mb-4">{t('eleves.inscription.paiement_title')}</h3>
                                    <div className="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p className="text-sm text-blue-800">
                                            {t('eleves.inscription.paiement_hint', { montant: francs(montantTarif ?? 0) })}
                                        </p>
                                    </div>
                                    <Input
                                        label={t('eleves.inscription.paiement_montant_label')}
                                        type="number"
                                        min={0}
                                        placeholder={t('eleves.inscription.paiement_montant_placeholder')}
                                        error={errors.paiement_montant?.message}
                                        {...register('paiement_montant')}
                                    />
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Select label={t('eleves.inscription.paiement_mode_label')} {...register('paiement_mode')}>
                                            {MODES.map((m) => (
                                                <option key={m.valeur} value={m.valeur}>
                                                    {m.libelle}
                                                </option>
                                            ))}
                                        </Select>
                                        <Input
                                            label={t('eleves.inscription.paiement_date_label')}
                                            type="date"
                                            {...register('paiement_date')}
                                        />
                                    </div>
                                    <Input
                                        label={t('eleves.inscription.paiement_reference_label')}
                                        placeholder={t('eleves.inscription.paiement_facultatif')}
                                        {...register('paiement_reference')}
                                    />
                                    <Input
                                        label={t('eleves.inscription.paiement_note_label')}
                                        placeholder={t('eleves.inscription.paiement_facultatif')}
                                        {...register('paiement_note')}
                                    />
                                    <p className="flex items-start gap-2 rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
                                        <Receipt className="mt-0.5 h-3.5 w-3.5 flex-none" />
                                        {t('eleves.inscription.paiement_recu_hint')}
                                    </p>
                                </div>
                            )}

                            {/* Étape 3: Tuteurs/Parents */}
                            {steps[currentStep]?.id === 'tuteurs' && (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-lg font-semibold text-navy-900">{t('eleves.inscription.tuteurs_title')}</h3>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                append({ nom_complet: '', telephone: '', lien_parente: '', is_principal: fields.length === 0 })
                                            }
                                            className="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-navy-600 hover:text-navy-800 hover:bg-navy-50 rounded-lg transition-colors"
                                        >
                                            <Plus className="h-4 w-4" />
                                            {t('eleves.inscription.ajouter_tuteur')}
                                        </button>
                                    </div>

                                    {eleve && eleve.tuteurs.length > 0 && fields.length === 0 && (
                                        <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg mb-4">
                                            <p className="text-sm text-amber-800">
                                                {t('eleves.inscription.tuteurs_actuels', { noms: eleve.tuteurs.map((tut) => tut.nom_complet).join(', ') })}
                                            </p>
                                            <p className="text-xs text-amber-700 mt-1">{t('eleves.inscription.tuteurs_remplacement_hint')}</p>
                                        </div>
                                    )}

                                    {fields.length === 0 ? (
                                        <div className="p-6 text-center border-2 border-dashed border-navy-200 rounded-lg">
                                            <p className="text-navy-400 text-sm">{t('eleves.inscription.aucun_tuteur')}</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {fields.map((field, index) => (
                                                <Card key={field.id} className="p-4 bg-cream-50">
                                                    <div className="grid grid-cols-1 gap-3">
                                                        <Input
                                                            label={t('eleves.inscription.champ_nom_complet')}
                                                            placeholder={t('eleves.inscription.champ_nom_complet')}
                                                            {...register(`tuteurs.${index}.nom_complet` as const, { required: t('eleves.inscription.tuteur_nom_complet_required') })}
                                                            error={errors.tuteurs?.[index]?.nom_complet?.message}
                                                        />

                                                        <Select
                                                            label={t('eleves.inscription.lien_parente')}
                                                            {...register(`tuteurs.${index}.lien_parente` as const, { required: t('eleves.inscription.lien_parente_required') })}
                                                            error={errors.tuteurs?.[index]?.lien_parente?.message}
                                                        >
                                                            <option value="">{t('eleves.inscription.select_placeholder')}</option>
                                                            {LIEN_PARENTE_OPTIONS.map((opt) => (
                                                                <option key={opt.value} value={opt.value}>
                                                                    {opt.label}
                                                                </option>
                                                            ))}
                                                        </Select>
                                                        {tuteurs?.[index]?.lien_parente === 'autre' && (
                                                            <Input
                                                                label={t('eleves.inscription.preciser_lien')}
                                                                placeholder={t('eleves.inscription.preciser_lien_placeholder')}
                                                                {...register(`tuteurs.${index}.lien_parente_autre` as const, { required: t('eleves.inscription.preciser_lien_required') })}
                                                                error={errors.tuteurs?.[index]?.lien_parente_autre?.message}
                                                            />
                                                        )}

                                                        <Input
                                                            label={t('eleves.tuteur_telephone')}
                                                            type="tel"
                                                            placeholder={t('eleves.inscription.telephone_placeholder')}
                                                            {...register(`tuteurs.${index}.telephone` as const)}
                                                            error={errors.tuteurs?.[index]?.telephone?.message}
                                                        />

                                                        <Input
                                                            label={t('eleves.inscription.profession')}
                                                            placeholder={t('eleves.inscription.profession')}
                                                            {...register(`tuteurs.${index}.profession` as const)}
                                                            error={errors.tuteurs?.[index]?.profession?.message}
                                                        />

                                                        <div className="flex items-center justify-between pt-2 border-t border-navy-100">
                                                            <label className="flex items-center gap-2 text-sm">
                                                                <input
                                                                    type="checkbox"
                                                                    {...register(`tuteurs.${index}.is_principal` as const)}
                                                                    className="rounded border-navy-300"
                                                                />
                                                                <span className="text-navy-700">{t('eleves.inscription.tuteur_principal')}</span>
                                                            </label>
                                                            <button
                                                                type="button"
                                                                onClick={() => remove(index)}
                                                                className="rounded-lg p-2 text-navy-400 hover:bg-red-100 hover:text-red-500 transition-colors"
                                                                title={t('eleves.inscription.supprimer_tuteur')}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </Card>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Étape 4: Confirmation */}
                            {steps[currentStep]?.id === 'confirmation' && (
                                <div className="space-y-6">
                                    <h3 className="text-lg font-semibold text-navy-900">{t('eleves.inscription.confirmation_title')}</h3>

                                    {serverError && (
                                        <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                                            <p className="text-sm text-red-700">{serverError}</p>
                                        </div>
                                    )}

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Card className="p-4 bg-cream-50">
                                            <h4 className="text-sm font-semibold text-navy-700 mb-3">{t('eleves.inscription.confirmation_identite_title')}</h4>
                                            <div className="space-y-2 text-sm">
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_nom_complet')}: </span>
                                                    <span className="font-medium text-navy-900">{watch('nom_complet')}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_sexe')}: </span>
                                                    <span className="font-medium text-navy-900">
                                                        {watch('sexe') === 'M' ? t('eleves.masculin') : watch('sexe') === 'F' ? t('eleves.feminin') : '—'}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_naissance')}: </span>
                                                    <span className="font-medium text-navy-900">{watch('date_naissance') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_lieu_naissance')}: </span>
                                                    <span className="font-medium text-navy-900">{watch('lieu_naissance') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_acte_naissance')}: </span>
                                                    <span className="font-medium text-navy-900">{watch('numero_acte_naissance') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_adresse')}: </span>
                                                    <span className="font-medium text-navy-900">{watch('adresse') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_refugie')}: </span>
                                                    <span className="font-medium text-navy-900">{watch('refugie') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_deplace_interne')}: </span>
                                                    <span className="font-medium text-navy-900">{watch('deplace_interne') || '—'}</span>
                                                </div>
                                            </div>
                                        </Card>

                                        <Card className="p-4 bg-cream-50">
                                            <h4 className="text-sm font-semibold text-navy-700 mb-3">{t('eleves.inscription.confirmation_scolarite_title')}</h4>
                                            <div className="space-y-2 text-sm">
                                                <div>
                                                    <span className="text-navy-400">{t('eleves.inscription.champ_classe')}: </span>
                                                    <span className="font-medium text-navy-900">
                                                        {watch('classe_id')
                                                            ? classes?.find((c) => c.id === Number(watch('classe_id')))?.nom || '—'
                                                            : '—'}
                                                    </span>
                                                </div>
                                                {etapePaiementDisponible && (
                                                    <div>
                                                        <span className="text-navy-400">{t('eleves.inscription.champ_paiement')}: </span>
                                                        <span className="font-medium text-navy-900">
                                                            {Number(watch('paiement_montant')) > 0
                                                                ? francs(Number(watch('paiement_montant')))
                                                                : t('eleves.inscription.paiement_aucun')}
                                                        </span>
                                                    </div>
                                                )}
                                            </div>
                                        </Card>
                                    </div>

                                    {fields.length > 0 && (
                                        <Card className="p-4 bg-cream-50">
                                            <h4 className="text-sm font-semibold text-navy-700 mb-3">{t('eleves.inscription.confirmation_tuteurs_title')}</h4>
                                            <div className="space-y-3">
                                                {fields.map((field, index) => (
                                                    <div key={field.id} className="text-sm border-t border-navy-100 pt-3 first:border-t-0 first:pt-0">
                                                        <div className="font-medium text-navy-900">
                                                            {watch(`tuteurs.${index}.nom_complet`)}
                                                            {watch(`tuteurs.${index}.is_principal`) && (
                                                                <span className="ml-2 text-xs bg-gold-100 text-gold-700 px-2 py-1 rounded-full font-semibold">
                                                                    {t('eleves.principal')}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-navy-600 text-xs mt-1">
                                                            {watch(`tuteurs.${index}.lien_parente`) === 'autre'
                                                                ? t('eleves.inscription.lien_autre_valeur', { valeur: watch(`tuteurs.${index}.lien_parente_autre`) })
                                                                : LIEN_PARENTE_OPTIONS.find((o) => o.value === watch(`tuteurs.${index}.lien_parente`))
                                                                    ?.label || '—'}
                                                        </div>
                                                        {watch(`tuteurs.${index}.profession`) && (
                                                            <div className="text-navy-600 text-xs">
                                                                {watch(`tuteurs.${index}.profession`)}
                                                            </div>
                                                        )}
                                                        {watch(`tuteurs.${index}.telephone`) && (
                                                            <div className="text-navy-600 text-xs">
                                                                {watch(`tuteurs.${index}.telephone`)}
                                                            </div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </Card>
                                    )}

                                    <div className="p-4 bg-green-50 border border-green-200 rounded-lg">
                                        <p className="text-sm text-green-800">{t('eleves.inscription.confirmation_footer')}</p>
                                    </div>
                                </div>
                            )}
                        </StepForm>
                    </form>
                </div>
            )}
        </div>
    )
}
