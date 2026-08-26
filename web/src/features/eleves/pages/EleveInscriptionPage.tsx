import { useForm, useFieldArray } from 'react-hook-form'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, Plus, Receipt, Trash2 } from 'lucide-react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { StepForm } from '@/shared/ui/StepForm'
import { Input, Select } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Spinner } from '@/shared/ui/Feedback'
import { fetchClasses } from '@/features/classes/api'
import { createEleve, updateEleve, fetchEleves, type ElevePayload } from '@/features/eleves/api'
import {
    fetchTarifs,
    fetchDossier,
    encaisser,
    francs,
    ventilerAutomatiquement,
    MODES,
    type DossierScolarite,
    type LigneVentilation,
    type ModePaiement,
} from '@/features/finance/api'
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
        trigger,
        getValues,
        setValue,
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
    const classeSelectionnee = classes?.find((c) => c.id === classeIdSelectionnee)
    // Primaire et maternelle seulement : `niveau_scolaire` (SIL, CP, CE1…) ne se
    // renseigne que pour ces classes-là — le secondaire n'a pas de niveau
    // scolaire unique par classe, donc rien à comparer.
    const classesMemeNiveau =
        classeSelectionnee?.niveau_scolaire_id != null
            ? classes?.filter(
                  (c) =>
                      c.niveau_scolaire_id === classeSelectionnee.niveau_scolaire_id &&
                      c.sous_systeme_id === classeSelectionnee.sous_systeme_id,
              ) ?? []
            : []
    const { data: tarifs } = useQuery({
        queryKey: ['tarifs'],
        queryFn: () => fetchTarifs(),
        enabled: can('finance.encaisser'),
    })
    const montantTarif = classeIdSelectionnee
        ? (tarifs?.classes.find((c) => c.id === classeIdSelectionnee)?.montant ?? tarifs?.tarif_par_defaut ?? null)
        : null
    const etapePaiementDisponible = can('finance.encaisser') && montantTarif != null && montantTarif > 0

    // L'élève doit exister avant d'ouvrir son dossier de scolarité : dès qu'on
    // avance vers l'étape paiement, il est enregistré immédiatement (cf.
    // `handleNext`) — exactement comme la page d'encaissement, qui n'opère
    // que sur un élève déjà persisté.
    const [eleveIdCree, setEleveIdCree] = useState<number | undefined>(undefined)
    const eleveIdActif = eleveIdCree ?? eleve?.id
    const [dossier, setDossier] = useState<DossierScolarite | null>(null)
    const [allocations, setAllocations] = useState<number[]>([])
    const [allocationsModifiees, setAllocationsModifiees] = useState(false)

    const rubriques = dossier?.rubriques ?? []
    const montantPaiement = Number(watch('paiement_montant') || 0)
    const resteApresPaiement = (dossier?.reste_a_payer ?? 0) - montantPaiement

    // La répartition suggérée suit le montant saisi tant que l'utilisateur n'a
    // pas encore touché aux champs — dès qu'il en modifie un, on ne l'écrase
    // plus pour ne pas effacer son ajustement (même logique qu'EncaissementPage).
    useEffect(() => {
        if (allocationsModifiees || rubriques.length === 0) return
        setAllocations(ventilerAutomatiquement(rubriques, montantPaiement))
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [montantPaiement, rubriques.length])

    const totalAlloue = useMemo(() => allocations.reduce((s, v) => s + (v || 0), 0), [allocations])
    const ventilationValide = rubriques.length === 0 || montantPaiement === 0 || totalAlloue === montantPaiement

    const construirePayload = (values: EleveFormValues): ElevePayload => {
        const tuteursPayload = (values.tuteurs ?? []).map((tuteur) => {
            const { lien_parente_autre, ...rest } = tuteur
            let lien_parente = rest.lien_parente

            if (lien_parente === 'autre' && lien_parente_autre) {
                lien_parente = `autre: ${lien_parente_autre}`
            }

            return { ...rest, lien_parente }
        })

        return {
            nom_complet: values.nom_complet,
            sexe: values.sexe as 'M' | 'F',
            date_naissance: values.date_naissance,
            lieu_naissance: values.lieu_naissance,
            adresse: values.adresse,
            classe_id: values.classe_id ? Number(values.classe_id) : null,
            tuteurs: tuteursPayload,
        }
    }

    /**
     * Garde de passage à l'étape suivante. Deux rôles : valider l'identité
     * avant de quitter cette étape (l'élève va être enregistré juste après),
     * et — en entrant dans l'étape paiement — enregistrer l'élève puis ouvrir
     * son dossier de scolarité pour afficher de vrais totaux et une vraie
     * répartition par rubrique, plutôt qu'un simple champ montant.
     */
    const handleNext = async (): Promise<boolean> => {
        if (steps[currentStep]?.id === 'identite') {
            const valide = await trigger(['nom_complet', 'sexe'])
            if (!valide) return false
        }

        if (steps[currentStep + 1]?.id === 'paiement') {
            setServerError(null)
            setSubmitting(true)
            try {
                const payload = construirePayload(getValues())
                const cible = eleveIdActif ? await updateEleve(eleveIdActif, payload) : await createEleve(payload)
                setEleveIdCree(cible.id)
                setDossier(await fetchDossier(cible.id))
                setAllocationsModifiees(false)
            } catch (err) {
                setServerError((err as ApiError).message)
                setSubmitting(false)
                return false
            }
            setSubmitting(false)
        }

        return true
    }

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

        if (dossier && montantPaiement > 0 && !ventilationValide) {
            setServerError(
                t('eleves.inscription.paiement_repartition_erreur', { alloue: francs(totalAlloue), montant: francs(montantPaiement) }),
            )
            return
        }

        setSubmitting(true)
        try {
            const payload = construirePayload(values)

            if (eleveIdActif) {
                await updateEleve(eleveIdActif, payload)
            } else {
                await createEleve(payload)
            }

            if (dossier && montantPaiement > 0) {
                const lignes: LigneVentilation[] | undefined =
                    rubriques.length > 0
                        ? rubriques
                            .map((r, i) => ({
                                affectation: r.cle,
                                dossier_frais_annexe_id: r.dossier_frais_annexe_id,
                                libelle: r.libelle,
                                montant: allocations[i] || 0,
                            }))
                            .filter((l) => l.montant > 0)
                        : undefined

                const { numero_recu, versement_id } = await encaisser(dossier.id, {
                    montant: montantPaiement,
                    mode: values.paiement_mode || 'especes',
                    date_versement: values.paiement_date || undefined,
                    reference_externe: values.paiement_reference || undefined,
                    note: values.paiement_note || undefined,
                    lignes,
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
                            onNext={handleNext}
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

                                    {classesMemeNiveau.length > 1 && (
                                        <div className="flex flex-col gap-2 pt-2">
                                            <div>
                                                <span className="text-sm font-semibold text-navy-800">
                                                    {t('eleves.inscription.effectifs_niveau_title')}
                                                </span>
                                                <p className="text-xs text-navy-400">{t('eleves.inscription.effectifs_niveau_hint')}</p>
                                            </div>

                                            <div className="overflow-x-auto rounded-xl border border-navy-100">
                                                <table className="w-full min-w-[420px] text-xs">
                                                    <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                                                        <tr>
                                                            <th className="px-2.5 py-2 text-left">{t('eleves.inscription.effectifs_niveau_classe')}</th>
                                                            <th className="px-2.5 py-2 text-right">{t('eleves.inscription.effectifs_niveau_garcons')}</th>
                                                            <th className="px-2.5 py-2 text-right">{t('eleves.inscription.effectifs_niveau_filles')}</th>
                                                            <th className="px-2.5 py-2 text-right">{t('eleves.inscription.effectifs_niveau_total')}</th>
                                                            <th className="px-2.5 py-2 text-right" />
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-navy-50">
                                                        {classesMemeNiveau.map((c) => {
                                                            const estSelectionnee = c.id === classeIdSelectionnee
                                                            return (
                                                                <tr key={c.id} className={estSelectionnee ? 'bg-navy-50/60' : undefined}>
                                                                    <td className="px-2.5 py-1.5 font-medium text-navy-800">{c.nom}</td>
                                                                    <td className="px-2.5 py-1.5 text-right tabular-nums text-navy-600">{c.garcons ?? 0}</td>
                                                                    <td className="px-2.5 py-1.5 text-right tabular-nums text-navy-600">{c.filles ?? 0}</td>
                                                                    <td className="px-2.5 py-1.5 text-right tabular-nums font-semibold text-navy-800">{c.effectif ?? 0}</td>
                                                                    <td className="px-2.5 py-1.5 text-right">
                                                                        {estSelectionnee ? (
                                                                            <span className="text-[11px] font-semibold text-navy-500">
                                                                                {t('eleves.inscription.effectifs_niveau_selectionnee')}
                                                                            </span>
                                                                        ) : (
                                                                            <button
                                                                                type="button"
                                                                                onClick={() =>
                                                                                    setValue('classe_id', c.id, { shouldDirty: true })
                                                                                }
                                                                                className="rounded-lg px-2 py-1 text-[11px] font-semibold text-navy-600 hover:bg-navy-100"
                                                                            >
                                                                                {t('eleves.inscription.effectifs_niveau_choisir')}
                                                                            </button>
                                                                        )}
                                                                    </td>
                                                                </tr>
                                                            )
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Étape facultative : Paiement — n'apparaît que si un tarif existe déjà pour la classe choisie. Même formulaire que la caisse (EncaissementPage) : totaux réels, répartition par rubrique. */}
                            {steps[currentStep]?.id === 'paiement' && dossier && (
                                <div className="space-y-4">
                                    <h3 className="text-lg font-semibold text-navy-900 mb-4">{t('eleves.inscription.paiement_title')}</h3>

                                    <dl className="grid grid-cols-3 gap-2 rounded-xl bg-cream-100 p-3 text-center">
                                        {[
                                            [t('eleves.inscription.paiement_total_du'), francs(dossier.total_du), 'text-navy-700'],
                                            [t('eleves.inscription.paiement_deja_verse'), francs(dossier.total_paye), 'text-green-600'],
                                            [t('eleves.inscription.paiement_reste'), francs(dossier.reste_a_payer), 'text-red-500'],
                                        ].map(([libelle, valeur, couleur]) => (
                                            <div key={libelle}>
                                                <dt className="text-[11px] uppercase tracking-wide text-navy-400">{libelle}</dt>
                                                <dd className={`text-sm font-bold tabular-nums ${couleur}`}>{valeur}</dd>
                                            </div>
                                        ))}
                                    </dl>

                                    <Input
                                        label={t('eleves.inscription.paiement_montant_label')}
                                        type="number"
                                        min={0}
                                        placeholder={t('eleves.inscription.paiement_montant_placeholder')}
                                        error={errors.paiement_montant?.message}
                                        {...register('paiement_montant')}
                                    />

                                    {montantPaiement > 0 && (
                                        <p className="-mt-2 text-xs text-navy-500">
                                            {resteApresPaiement > 0 ? (
                                                <>{t('eleves.inscription.paiement_reste_apres')} <span className="font-semibold">{francs(resteApresPaiement)}</span></>
                                            ) : resteApresPaiement === 0 ? (
                                                <span className="font-semibold text-green-600">{t('eleves.inscription.paiement_solde')}</span>
                                            ) : (
                                                <span className="font-semibold text-blue-600">
                                                    {t('eleves.inscription.paiement_avance', { montant: francs(-resteApresPaiement) })}
                                                </span>
                                            )}
                                        </p>
                                    )}

                                    {rubriques.length > 0 && (
                                        <div className="flex flex-col gap-2">
                                            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                                                {t('eleves.inscription.paiement_repartition_titre')}
                                            </span>

                                            <div className="overflow-x-auto rounded-xl border border-navy-100">
                                                <table className="w-full min-w-[440px] text-xs">
                                                    <thead className="bg-cream-50 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                                                        <tr>
                                                            <th className="px-2.5 py-2 text-left">{t('eleves.inscription.paiement_rubrique')}</th>
                                                            <th className="px-2.5 py-2 text-right">{t('eleves.inscription.paiement_paye')}</th>
                                                            <th className="px-2.5 py-2 text-right">{t('eleves.inscription.paiement_reste')}</th>
                                                            <th className="px-2.5 py-2 text-right">{t('eleves.inscription.paiement_montant_alloue')}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-navy-50">
                                                        {rubriques.map((r, i) => (
                                                            <tr key={`${r.cle}:${r.dossier_frais_annexe_id ?? ''}`}>
                                                                <td className="px-2.5 py-1.5 font-medium text-navy-800">{r.libelle}</td>
                                                                <td className="px-2.5 py-1.5 text-right tabular-nums text-green-600">{francs(r.montant_paye)}</td>
                                                                <td className="px-2.5 py-1.5 text-right tabular-nums text-red-500">{francs(r.reste)}</td>
                                                                <td className="px-2.5 py-1.5 text-right">
                                                                    <input
                                                                        type="number"
                                                                        min={0}
                                                                        value={allocations[i] ?? 0}
                                                                        onChange={(e) => {
                                                                            setAllocationsModifiees(true)
                                                                            setAllocations((a) => {
                                                                                const suivant = [...a]
                                                                                suivant[i] = Number(e.target.value) || 0
                                                                                return suivant
                                                                            })
                                                                        }}
                                                                        className="w-24 rounded-lg border border-navy-200 px-1.5 py-1 text-right text-xs tabular-nums shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
                                                                    />
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                    <tfoot>
                                                        <tr className="border-t border-navy-100 bg-cream-50/70 font-semibold">
                                                            <td className="px-2.5 py-1.5 text-navy-700">{t('eleves.inscription.paiement_total_alloue')}</td>
                                                            <td />
                                                            <td />
                                                            <td className={`px-2.5 py-1.5 text-right tabular-nums ${totalAlloue === montantPaiement ? 'text-green-600' : 'text-red-500'}`}>
                                                                {francs(totalAlloue)}
                                                            </td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>

                                            {totalAlloue !== montantPaiement && montantPaiement > 0 && (
                                                <p className="text-xs font-semibold text-red-500">
                                                    {t('eleves.inscription.paiement_repartition_erreur', { alloue: francs(totalAlloue), montant: francs(montantPaiement) })}
                                                </p>
                                            )}
                                        </div>
                                    )}

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
                                        {montantPaiement > 0 ? t('eleves.inscription.paiement_recu_hint') : t('eleves.inscription.paiement_skip_hint')}
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
