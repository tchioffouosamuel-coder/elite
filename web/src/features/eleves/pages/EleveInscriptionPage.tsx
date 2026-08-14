import { useForm, useFieldArray, Controller } from 'react-hook-form'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useEffect, useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import { useNavigate, useParams } from 'react-router-dom'
import { StepForm } from '@/shared/ui/StepForm'
import { Input, Select } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { fetchClasses } from '@/features/classes/api'
import { createEleve, updateEleve, fetchEleves, type Eleve, type ElevePayload } from '@/features/eleves/api'
import type { ApiError } from '@/shared/types/api'
import { succes } from '@/shared/lib/alertes'

const LIEN_PARENTE_OPTIONS = [
    { value: 'père', label: 'Père' },
    { value: 'mère', label: 'Mère' },
    { value: 'tuteur', label: 'Tuteur' },
    { value: 'autre', label: 'Autre (À préciser)' },
]

interface TuteurFormData {
    nom_complet: string
    telephone?: string
    lien_parente?: string
    lien_parente_autre?: string
    is_principal?: boolean
}

export function EleveInscriptionPage() {
    const { t } = useTranslation()
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

    const {
        register,
        control,
        handleSubmit,
        reset,
        formState: { errors },
        watch,
    } = useForm<ElevePayload>({
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
            }
            : { tuteurs: [] },
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
        })
    }, [eleve, reset])

    const { fields, append, remove } = useFieldArray({ control, name: 'tuteurs' })
    const tuteurs = watch('tuteurs')

    const steps = [
        { id: 'identite', label: 'Identité', description: 'Informations de l\'élève' },
        { id: 'scolarite', label: 'Scolarité', description: 'Classe et dates' },
        { id: 'tuteurs', label: 'Tuteurs/Parents', description: 'Contacts des parents' },
        { id: 'confirmation', label: 'Confirmation', description: 'Vérification des données' },
    ]

    const onSubmit = async (values: any) => {
        setServerError(null)
        setSubmitting(true)
        try {
            // Traiter les tuteurs pour combiner lien_parente et lien_parente_autre
            const tuteurs = (values.tuteurs ?? []).map((tuteur: any) => {
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
                sexe: values.sexe,
                date_naissance: values.date_naissance,
                lieu_naissance: values.lieu_naissance,
                adresse: values.adresse,
                classe_id: values.classe_id ? Number(values.classe_id) : null,
                tuteurs,
            }

            if (eleve) {
                await updateEleve(eleve.id, payload)
            } else {
                await createEleve(payload)
            }
            queryClient.invalidateQueries({ queryKey: ['eleves'] })
            succes(eleve ? t('common.updated_successfully') : t('common.created_successfully'))
            navigate('/eleves')
        } catch (err) {
            setServerError((err as ApiError).message)
        } finally {
            setSubmitting(false)
        }
    }

    return (
        <div className="min-h-screen bg-navy-50 py-8 px-4">
            <PageHeader
                title={eleve ? t('eleves.edit') : t('eleves.add')}
                description={eleve ? 'Modifier les informations de l\'élève' : 'Inscrire un nouvel élève'}
                onBack={() => navigate('/eleves')}
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
                            {currentStep === 0 && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-semibold text-navy-900 mb-4">Identité de l'élève</h3>
                                    <Input
                                        label={t('eleves.nom_complet')}
                                        error={errors.nom_complet?.message}
                                        {...register('nom_complet', { required: 'Le nom complet est requis' })}
                                        placeholder="Nom complet"
                                    />
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Select
                                            label={t('eleves.sexe')}
                                            error={errors.sexe?.message}
                                            {...register('sexe', { required: 'Le sexe est requis' })}
                                        >
                                            <option value="">— Sélectionner —</option>
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
                                        label="Lieu de naissance"
                                        placeholder="Lieu de naissance"
                                        {...register('lieu_naissance')}
                                        error={errors.lieu_naissance?.message}
                                    />
                                    <Input
                                        label="Numéro d'acte de naissance"
                                        placeholder="Numéro d'acte de naissance"
                                        {...register('numero_acte_naissance')}
                                        error={errors.numero_acte_naissance?.message}
                                    />
                                    <Input
                                        label="Adresse"
                                        placeholder="Adresse complète"
                                        {...register('adresse')}
                                        error={errors.adresse?.message}
                                    />
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Select
                                            label="Réfugié"
                                            {...register('refugie')}
                                            error={errors.refugie?.message}
                                        >
                                            <option value="">— Non applicable —</option>
                                            <option value="Oui">Oui</option>
                                            <option value="Non">Non</option>
                                        </Select>
                                        <Select
                                            label="Déplacé interne"
                                            {...register('deplace_interne')}
                                            error={errors.deplace_interne?.message}
                                        >
                                            <option value="">— Non applicable —</option>
                                            <option value="Oui">Oui</option>
                                            <option value="Non">Non</option>
                                        </Select>
                                    </div>
                                </div>
                            )}

                            {/* Étape 2: Scolarité */}
                            {currentStep === 1 && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-semibold text-navy-900 mb-4">Informations scolaires</h3>
                                    <Select
                                        label={t('eleves.classe')}
                                        {...register('classe_id')}
                                        error={errors.classe_id?.message}
                                    >
                                        <option value="">— Sélectionner une classe —</option>
                                        {classes?.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.nom}
                                            </option>
                                        ))}
                                    </Select>
                                    <div className="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                        <p className="text-sm text-blue-800">
                                            📋 La classe pourra être modifiée ultérieurement dans les paramètres de l'élève.
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Étape 3: Tuteurs/Parents */}
                            {currentStep === 2 && (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-lg font-semibold text-navy-900">Tuteurs/Parents</h3>
                                        <button
                                            type="button"
                                            onClick={() =>
                                                append({ nom_complet: '', telephone: '', lien_parente: '', is_principal: fields.length === 0 })
                                            }
                                            className="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-navy-600 hover:text-navy-800 hover:bg-navy-50 rounded-lg transition-colors"
                                        >
                                            <Plus className="h-4 w-4" />
                                            Ajouter un tuteur
                                        </button>
                                    </div>

                                    {eleve && eleve.tuteurs.length > 0 && fields.length === 0 && (
                                        <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg mb-4">
                                            <p className="text-sm text-amber-800">
                                                Tuteurs actuels: {eleve.tuteurs.map((tut) => tut.nom_complet).join(', ')}
                                            </p>
                                            <p className="text-xs text-amber-700 mt-1">
                                                Ajouter un tuteur ci-dessous remplacera cette liste.
                                            </p>
                                        </div>
                                    )}

                                    {fields.length === 0 ? (
                                        <div className="p-6 text-center border-2 border-dashed border-navy-200 rounded-lg">
                                            <p className="text-navy-400 text-sm">Aucun tuteur ajouté. Cliquez sur « Ajouter un tuteur » pour commencer.</p>
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {fields.map((field, index) => (
                                                <Card key={field.id} className="p-4 bg-cream-50">
                                                    <div className="grid grid-cols-1 gap-3">
                                                        <Input
                                                            label="Nom complet"
                                                            placeholder="Nom complet"
                                                            {...register(`tuteurs.${index}.nom_complet` as const, { required: 'Nom complet requis' })}
                                                            error={errors.tuteurs?.[index]?.nom_complet?.message}
                                                        />

                                                        <Select
                                                            label="Lien de parenté"
                                                            {...register(`tuteurs.${index}.lien_parente` as const, { required: 'Lien de parenté requis' })}
                                                            error={errors.tuteurs?.[index]?.lien_parente?.message}
                                                        >
                                                            <option value="">— Sélectionner —</option>
                                                            {LIEN_PARENTE_OPTIONS.map((opt) => (
                                                                <option key={opt.value} value={opt.value}>
                                                                    {opt.label}
                                                                </option>
                                                            ))}
                                                        </Select>
                                                        {tuteurs?.[index]?.lien_parente === 'autre' && (
                                                            <Input
                                                                label="Préciser le lien"
                                                                placeholder="Ex: Grand-mère, oncle, cousin…"
                                                                {...register(`tuteurs.${index}.lien_parente_autre` as const, { required: 'Veuillez préciser le lien' })}
                                                                error={errors.tuteurs?.[index]?.lien_parente_autre?.message}
                                                            />
                                                        )}

                                                        <Input
                                                            label="Téléphone"
                                                            type="tel"
                                                            placeholder="Numéro de téléphone"
                                                            {...register(`tuteurs.${index}.telephone` as const)}
                                                            error={errors.tuteurs?.[index]?.telephone?.message}
                                                        />

                                                        <Input
                                                            label="Profession"
                                                            placeholder="Profession"
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
                                                                <span className="text-navy-700">Tuteur principal</span>
                                                            </label>
                                                            <button
                                                                type="button"
                                                                onClick={() => remove(index)}
                                                                className="rounded-lg p-2 text-navy-400 hover:bg-red-100 hover:text-red-500 transition-colors"
                                                                title="Supprimer ce tuteur"
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
                            {currentStep === 3 && (
                                <div className="space-y-6">
                                    <h3 className="text-lg font-semibold text-navy-900">Vérification des informations</h3>

                                    {serverError && (
                                        <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                                            <p className="text-sm text-red-700">{serverError}</p>
                                        </div>
                                    )}

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <Card className="p-4 bg-cream-50">
                                            <h4 className="text-sm font-semibold text-navy-700 mb-3">Identité</h4>
                                            <div className="space-y-2 text-sm">
                                                <div>
                                                    <span className="text-navy-400">Nom complet: </span>
                                                    <span className="font-medium text-navy-900">{watch('nom_complet')}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">Sexe: </span>
                                                    <span className="font-medium text-navy-900">
                                                        {watch('sexe') === 'M' ? 'Masculin' : watch('sexe') === 'F' ? 'Féminin' : '—'}
                                                    </span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">Naissance: </span>
                                                    <span className="font-medium text-navy-900">{watch('date_naissance') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">Lieu de naissance: </span>
                                                    <span className="font-medium text-navy-900">{watch('lieu_naissance') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">Acte de naissance: </span>
                                                    <span className="font-medium text-navy-900">{watch('numero_acte_naissance') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">Adresse: </span>
                                                    <span className="font-medium text-navy-900">{watch('adresse') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">Réfugié: </span>
                                                    <span className="font-medium text-navy-900">{watch('refugie') || '—'}</span>
                                                </div>
                                                <div>
                                                    <span className="text-navy-400">Déplacé interne: </span>
                                                    <span className="font-medium text-navy-900">{watch('deplace_interne') || '—'}</span>
                                                </div>
                                            </div>
                                        </Card>

                                        <Card className="p-4 bg-cream-50">
                                            <h4 className="text-sm font-semibold text-navy-700 mb-3">Scolarité</h4>
                                            <div className="space-y-2 text-sm">
                                                <div>
                                                    <span className="text-navy-400">Classe: </span>
                                                    <span className="font-medium text-navy-900">
                                                        {watch('classe_id')
                                                            ? classes?.find((c) => c.id === Number(watch('classe_id')))?.nom || '—'
                                                            : '—'}
                                                    </span>
                                                </div>
                                            </div>
                                        </Card>
                                    </div>

                                    {fields.length > 0 && (
                                        <Card className="p-4 bg-cream-50">
                                            <h4 className="text-sm font-semibold text-navy-700 mb-3">Tuteurs/Parents</h4>
                                            <div className="space-y-3">
                                                {fields.map((field, index) => (
                                                    <div key={field.id} className="text-sm border-t border-navy-100 pt-3 first:border-t-0 first:pt-0">
                                                        <div className="font-medium text-navy-900">
                                                            {watch(`tuteurs.${index}.nom_complet`)}
                                                            {watch(`tuteurs.${index}.is_principal`) && (
                                                                <span className="ml-2 text-xs bg-gold-100 text-gold-700 px-2 py-1 rounded-full font-semibold">
                                                                    Principal
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-navy-600 text-xs mt-1">
                                                            {watch(`tuteurs.${index}.lien_parente`) === 'autre'
                                                                ? `Autre: ${watch(`tuteurs.${index}.lien_parente_autre`)}`
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
                                        <p className="text-sm text-green-800">
                                            ✓ Vérifiez toutes les informations avant de valider l'inscription.
                                        </p>
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
