import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useFieldArray, useForm } from 'react-hook-form'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, BriefcaseBusiness, Plus, Trash2 } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StepForm } from '@/shared/ui/StepForm'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import {
  fetchDepartements,
  fetchFonctionsReferentiel,
  fetchPersonnel,
  createPersonnel,
  updatePersonnel,
  type PersonnelEnfant,
  type PersonnelPayload,
} from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { estSecondaire } from '@/shared/lib/ecole'
import { useAuthStore } from '@/shared/store/authStore'
import type { ApiError } from '@/shared/types/api'

/**
 * Dossier d'un agent, saisi en cinq étapes.
 *
 * La fiche portait six champs ; elle en porte vingt depuis la reprise du
 * tableau de mise en place du personnel. Tout dérouler d'un bloc rendait la
 * saisie décourageante et noyait les deux seuls champs obligatoires — le nom
 * et la fonction — au milieu du reste. Le découpage suit l'ordre dans lequel
 * un secrétariat remplit un dossier : qui est l'agent, ce qu'il fait, comment
 * le joindre, puis relecture.
 *
 * Chaque étape est validée avant la suivante : une erreur se découvre là où
 * elle se corrige, pas à l'enregistrement final.
 */

type Champ = keyof PersonnelPayload

const ETAPES: { id: string; label: string; description: string; champs: Champ[] }[] = [
  { id: 'identite', label: 'Identité', description: "État civil de l'agent", champs: ['nom_complet', 'civilite', 'sexe', 'date_naissance', 'numero_cni', 'numero_cnps'] },
  { id: 'poste', label: 'Poste', description: 'Fonction et affectation', champs: ['school_id', 'fonction_id', 'departement_id', 'affectation', 'matricule', 'date_embauche', 'date_fin'] },
  { id: 'contact', label: 'Coordonnées', description: 'Contacts et situation', champs: ['telephone', 'telephone_2', 'email', 'residence', 'departement_origine', 'situation_matrimoniale', 'nombre_enfants', 'diplome_professionnel', 'diplome_academique'] },
  { id: 'famille', label: 'Famille', description: 'Parents et enfants', champs: ['pere_nom_complet', 'pere_statut', 'pere_telephone', 'mere_nom_complet', 'mere_statut', 'mere_telephone'] },
  { id: 'recap', label: 'Récapitulatif', description: 'Vérification', champs: [] },
]

const CIVILITES = ['M.', 'Mme', 'Mlle', 'Mr', 'Mrs', 'Miss']

const STATUTS_PARENT: { value: PersonnelPayload['pere_statut']; label: string }[] = [
  { value: 'vivant', label: 'Vivant' },
  { value: 'decede', label: 'Décédé' },
]

const SEXES_ENFANT: { value: PersonnelEnfant['sexe']; label: string }[] = [
  { value: 'M', label: 'Masculin' },
  { value: 'F', label: 'Féminin' },
]

const SITUATIONS = [
  ['celibataire', 'Célibataire'],
  ['marie', 'Marié(e)'],
  ['divorce', 'Divorcé(e)'],
  ['veuf', 'Veuf / Veuve'],
] as const

function estVide(valeur: unknown): boolean {
  if (valeur === null || valeur === undefined || valeur === '') return true
  if (Array.isArray(valeur)) return valeur.every(estVide)
  if (typeof valeur === 'object') {
    return Object.values(valeur as Record<string, unknown>).every(estVide)
  }
  return false
}

/** Nettoie les chaînes vides tout en conservant les structures imbriquées. */
function nettoyer(valeur: unknown): unknown {
  if (Array.isArray(valeur)) {
    return valeur
      .map((element) => nettoyer(element))
      .filter((element) => !estVide(element))
  }

  if (valeur && typeof valeur === 'object') {
    return Object.fromEntries(
      Object.entries(valeur as Record<string, unknown>).map(([cle, valeurInterne]) => [cle, nettoyer(valeurInterne)]),
    )
  }

  return valeur === '' ? null : valeur
}

function resumeParent(nom: string | null | undefined, statut: PersonnelPayload['pere_statut'] | null | undefined, telephone: string | null | undefined) {
  if (!nom && !statut && !telephone) return null

  const morceaux = [
    nom,
    statut === 'vivant' ? 'vivant' : statut === 'decede' ? 'décédé' : null,
    statut === 'vivant' && telephone ? telephone : null,
  ].filter(Boolean)

  return morceaux.join(' · ')
}

function resumeEnfant(enfant: PersonnelEnfant) {
  const morceaux = [
    enfant.nom_complet,
    enfant.sexe === 'M' ? 'M' : enfant.sexe === 'F' ? 'F' : null,
    enfant.date_naissance,
  ].filter(Boolean)

  return morceaux.join(' · ')
}

function etapePourErreurs(erreurs?: Record<string, unknown> | null): number {
  const cles = Object.keys(erreurs ?? {})

  if (cles.some((cle) => cle.startsWith('pere_') || cle.startsWith('mere_') || cle.startsWith('enfants.'))) {
    return 3
  }
  if (cles.some((cle) => ['telephone', 'telephone_2', 'email', 'residence', 'departement_origine', 'situation_matrimoniale', 'nombre_enfants', 'diplome_professionnel', 'diplome_academique'].includes(cle))) {
    return 2
  }
  if (cles.some((cle) => ['school_id', 'fonction_id', 'departement_id', 'affectation', 'matricule', 'date_embauche', 'date_fin'].includes(cle))) {
    return 1
  }
  return 0
}

export function PersonnelFormPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { id } = useParams()
  const personnelId = id ? Number(id) : null

  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)

  const { data: personnel, isLoading, isError } = useQuery({
    queryKey: ['personnel', personnelId],
    queryFn: () => fetchPersonnel(personnelId!),
    enabled: personnelId !== null,
  })
  // Chargés systématiquement (pas seulement pour le secondaire) : en mode
  // agrégé, savoir si CETTE fiche relève du secondaire dépend de l'école
  // choisie plus bas dans le formulaire, pas connue avant ce point.
  const { data: departements } = useQuery({ queryKey: ['departements', activeSchoolId], queryFn: fetchDepartements })
  const { data: fonctions } = useQuery({
    queryKey: ['fonctions-referentiel', activeSchoolId],
    queryFn: fetchFonctionsReferentiel,
  })
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })

  const [etape, setEtape] = useState(0)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    control,
    handleSubmit,
    trigger,
    reset,
    watch,
    formState: { errors },
  } = useForm<PersonnelPayload>({ defaultValues: { nom_complet: '', enfants: [], pere_statut: '', mere_statut: '' } })

  const { fields: enfants, append, remove } = useFieldArray({ control, name: 'enfants' })

  const ecoleChoisie = watch('school_id')
  // La fonction et le département dépendent de l'école choisie : en mode
  // agrégé, `fonctions`/`departements` couvrent tout le complexe, il faut
  // filtrer plutôt que de mélanger les référentiels de plusieurs écoles.
  const fonctionsFiltrees = ecoleChoisie ? fonctions?.filter((f) => f.school_id === Number(ecoleChoisie)) : fonctions
  const departementsFiltres = ecoleChoisie ? departements?.filter((d) => d.school_id === Number(ecoleChoisie)) : departements
  const typeEcoleFormulaire = ecoleChoisie
    ? schools?.find((s) => s.id === Number(ecoleChoisie))?.type
    : personnel?.school?.type
  const avecDepartements = estSecondaire(typeEcoleFormulaire)

  // La fiche à modifier arrive après le premier rendu : le formulaire est
  // recalé quand elle est là, pas construit à vide puis laissé tel quel.
  useEffect(() => {
    if (!personnel) return

    reset({
      nom_complet: personnel.nom_complet,
      school_id: personnel.school_id,
      fonction_id: personnel.fonction_id,
      departement_id: personnel.departement?.id ?? undefined,
      matricule: personnel.matricule ?? '',
      affectation: personnel.affectation ?? '',
      civilite: personnel.civilite ?? '',
      sexe: personnel.sexe ?? undefined,
      date_naissance: personnel.date_naissance ?? '',
      numero_cni: personnel.numero_cni ?? '',
      numero_cnps: personnel.numero_cnps ?? '',
      departement_origine: personnel.departement_origine ?? '',
      residence: personnel.residence ?? '',
      telephone: personnel.telephone ?? '',
      telephone_2: personnel.telephone_2 ?? '',
      situation_matrimoniale: personnel.situation_matrimoniale ?? undefined,
      nombre_enfants: personnel.nombre_enfants ?? undefined,
      diplome_professionnel: personnel.diplome_professionnel ?? '',
      diplome_academique: personnel.diplome_academique ?? '',
      email: personnel.email ?? '',
      date_embauche: personnel.date_embauche ?? '',
      date_fin: personnel.date_fin ?? '',
      pere_nom_complet: personnel.pere_nom_complet ?? '',
      pere_statut: personnel.pere_statut ?? '',
      pere_telephone: personnel.pere_telephone ?? '',
      mere_nom_complet: personnel.mere_nom_complet ?? '',
      mere_statut: personnel.mere_statut ?? '',
      mere_telephone: personnel.mere_telephone ?? '',
      enfants: personnel.enfants ?? [],
    })
  }, [personnel, reset])

  const onSubmit = async (values: PersonnelPayload) => {
    setSubmitting(true)
    try {
      const payload = nettoyer({
        ...values,
        fonction_id: Number(values.fonction_id),
        departement_id: values.departement_id ? Number(values.departement_id) : null,
        school_id: values.school_id ? Number(values.school_id) : null,
        nombre_enfants: values.nombre_enfants === undefined || values.nombre_enfants === null
          ? null
          : Number(values.nombre_enfants),
      }) as PersonnelPayload

      if (personnel) {
        await updatePersonnel(personnel.id, payload)
      } else {
        await createPersonnel(payload)
      }

      await queryClient.invalidateQueries({ queryKey: ['personnels'] })
      succes(personnel ? t('common.updated_successfully') : t('common.created_successfully'))
      navigate('/personnel')
    } catch (err) {
      // Un refus d'autorisation est déjà présenté par l'intercepteur HTTP.
      const e = err as ApiError
      if (e.status !== 403) erreur(e.message)

      // L'API peut refuser un champ d'une étape précédente : on ramène
      // l'utilisateur là où la correction se fait.
      setEtape(etapePourErreurs(e.errors ?? undefined))
    } finally {
      setSubmitting(false)
    }
  }

  const valeurs = watch()
  const enfantsSaisis = (valeurs.enfants ?? []).filter((enfant) => !estVide(enfant))
  const fonctionLabel = fonctions?.find((f) => f.id === Number(valeurs.fonction_id))?.label
  const handleNext = async (): Promise<boolean> => {
    if (ETAPES[etape]?.id === 'famille') {
      const champsEnfants = enfants.map((_, index) => [
        `enfants.${index}.nom_complet` as const,
        `enfants.${index}.sexe` as const,
        `enfants.${index}.date_naissance` as const,
      ]).flat()

      return trigger([
        ...ETAPES[etape].champs,
        ...champsEnfants,
      ] as never)
    }

    return trigger(ETAPES[etape].champs as never)
  }

  const recapitulatif: [string, string | number | null | undefined][] = [
    [t('personnel.nom_complet'), [valeurs.civilite, valeurs.nom_complet].filter(Boolean).join(' ')],
    [t('personnel.fonction'), fonctionLabel],
    ['Affectation', valeurs.affectation],
    [t('personnel.matricule'), valeurs.matricule],
    ['Sexe', valeurs.sexe === 'M' ? 'Masculin' : valeurs.sexe === 'F' ? 'Féminin' : null],
    ['Date de naissance', valeurs.date_naissance],
    ['N° CNI', valeurs.numero_cni],
    ['N° CNPS', valeurs.numero_cnps],
    [t('personnel.telephone'), [valeurs.telephone, valeurs.telephone_2].filter(Boolean).join(' · ')],
    [t('personnel.email'), valeurs.email],
    ['Résidence', valeurs.residence],
    ["Département d'origine", valeurs.departement_origine],
    ['Situation', SITUATIONS.find(([cle]) => cle === valeurs.situation_matrimoniale)?.[1]],
    ['Enfants de moins de 21 ans', valeurs.nombre_enfants],
    ['Père', resumeParent(valeurs.pere_nom_complet, valeurs.pere_statut, valeurs.pere_telephone)],
    ['Mère', resumeParent(valeurs.mere_nom_complet, valeurs.mere_statut, valeurs.mere_telephone)],
    ['Enfants', enfantsSaisis.length === 0 ? null : enfantsSaisis.map(resumeEnfant).join(' · ')],
    ['Diplôme professionnel', valeurs.diplome_professionnel],
    ['Diplôme académique', valeurs.diplome_academique],
    ["Date d'embauche", valeurs.date_embauche],
    ['Fin de contrat', valeurs.date_fin],
  ]

  if (personnelId !== null && isLoading) return <Spinner />
  if (personnelId !== null && isError) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={personnel ? t('personnel.edit') : t('personnel.add')}
        sousTitre={
          personnel
            ? `Dossier de ${personnel.nom_complet}`
            : "Le dossier se remplit en cinq étapes ; seuls le nom et la fonction sont obligatoires."
        }
        icon={BriefcaseBusiness}
        actions={
          <Button variant="secondary" onClick={() => navigate('/personnel')}>
            <ArrowLeft className="h-4 w-4" />
            Retour à la liste
          </Button>
        }
      />

      <form onSubmit={handleSubmit(onSubmit)}>
        <StepForm
          steps={ETAPES}
          currentStep={etape}
          onStepChange={setEtape}
          isLastStep={etape === ETAPES.length - 1}
          isSubmitting={submitting}
          onNext={handleNext}
          onSubmit={handleSubmit(onSubmit)}
          onCancel={() => navigate('/personnel')}
          wide
        >
          {etape === 0 && (
            <div className="flex flex-col gap-4">
              <h3 className="font-display text-base font-bold text-navy-900">Identité de l'agent</h3>
              <div className="grid gap-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
                <Select label="Civilité" {...register('civilite')}>
                  <option value="">—</option>
                  {CIVILITES.map((c) => (
                    <option key={c} value={c}>
                      {c}
                    </option>
                  ))}
                </Select>
                <Input
                  label={t('personnel.nom_complet')}
                  error={errors.nom_complet?.message}
                  {...register('nom_complet', { required: 'Le nom est obligatoire.' })}
                />
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <Select label="Sexe" {...register('sexe')}>
                  <option value="">—</option>
                  <option value="M">Masculin</option>
                  <option value="F">Féminin</option>
                </Select>
                <Input label="Date de naissance" type="date" {...register('date_naissance')} />
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <Input label="N° CNI" {...register('numero_cni')} />
                <Input label="N° CNPS" {...register('numero_cnps')} />
              </div>
            </div>
          )}

          {etape === 1 && (
            <div className="flex flex-col gap-4">
              <h3 className="font-display text-base font-bold text-navy-900">Poste et affectation</h3>
              {!personnel && (schools?.length ?? 0) > 1 && (
                <Select
                  label={`${t('classes.ecole')} *`}
                  error={errors.school_id?.message}
                  {...register('school_id', { required: "L'école est requise." })}
                >
                  <option value="">—</option>
                  {schools?.map((s) => (
                    <option key={s.id} value={s.id}>
                      {s.name}
                    </option>
                  ))}
                </Select>
              )}
              <Select
                label={t('personnel.fonction')}
                error={errors.fonction_id?.message}
                {...register('fonction_id', { required: 'La fonction est obligatoire.' })}
              >
                <option value="">—</option>
                {fonctionsFiltrees?.map((fonction) => (
                  <option key={fonction.id} value={fonction.id}>
                    {fonction.label}
                  </option>
                ))}
              </Select>
              <p className="-mt-2 text-xs text-navy-400">
                La fonction porte les privilèges dont héritera le compte de l'agent.
              </p>

              {!personnel && (
                <p className="rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
                  Un accès de connexion sera ouvert automatiquement à l'enregistrement, sous la forme{' '}
                  <code className="font-semibold">prenom.nom@elite.school</code> et avec le mot de passe par défaut de
                  l'établissement. L'agent n'aura que les privilèges de la fonction choisie ci-dessus.
                </p>
              )}

              {avecDepartements && (
                <Select label={t('personnel.departement')} {...register('departement_id')}>
                  <option value="">—</option>
                  {departementsFiltres?.map((d) => (
                    <option key={d.id} value={d.id}>
                      {d.nom}
                    </option>
                  ))}
                </Select>
              )}

              <div className="grid gap-3 sm:grid-cols-2">
                <Input label="Affectation" placeholder="Nursery 1-A, Bus driver…" {...register('affectation')} />
                <Input label={t('personnel.matricule')} {...register('matricule')} />
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <Input label="Date d'embauche" type="date" {...register('date_embauche')} />
                <Input
                  label="Fin de contrat"
                  type="date"
                  error={errors.date_fin?.message}
                  {...register('date_fin', {
                    validate: (valeur, champs) =>
                      !valeur || !champs.date_embauche || valeur >= champs.date_embauche
                        ? true
                        : "La fin de contrat précède la date d'embauche.",
                  })}
                />
              </div>
              {valeurs.date_fin && (
                <p className="rounded-xl bg-cream-100 px-3 py-2 text-xs text-navy-500">
                  Une date de fin marque l'agent comme sorti des effectifs.
                </p>
              )}
            </div>
          )}

          {etape === 2 && (
            <div className="flex flex-col gap-4">
              <h3 className="font-display text-base font-bold text-navy-900">Coordonnées et situation</h3>
              <div className="grid gap-3 sm:grid-cols-2">
                <Input label={t('personnel.telephone')} {...register('telephone')} />
                <Input label="Second téléphone" {...register('telephone_2')} />
              </div>
              <Input
                label={t('personnel.email')}
                type="email"
                disabled={!!personnel}
                error={errors.email?.message}
                {...register('email', { disabled: !!personnel })}
              />
              <div className="grid gap-3 sm:grid-cols-2">
                <Input label="Résidence" {...register('residence')} />
                <Input label="Département d'origine" {...register('departement_origine')} />
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <Select label="Situation matrimoniale" {...register('situation_matrimoniale')}>
                  <option value="">—</option>
                  {SITUATIONS.map(([cle, libelle]) => (
                    <option key={cle} value={cle}>
                      {libelle}
                    </option>
                  ))}
                </Select>
                <Input
                  label="Enfants de moins de 21 ans"
                  type="number"
                  min={0}
                  max={30}
                  error={errors.nombre_enfants?.message}
                  {...register('nombre_enfants', { min: { value: 0, message: 'Nombre invalide.' } })}
                />
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <Input label="Diplôme professionnel" placeholder="CAPIEMP…" {...register('diplome_professionnel')} />
                <Input label="Diplôme académique" placeholder="A-LEVEL, Licence…" {...register('diplome_academique')} />
              </div>
            </div>
          )}

          {etape === 3 && (
            <div className="flex flex-col gap-4">
              <h3 className="font-display text-base font-bold text-navy-900">Famille de l'agent</h3>
              <div className="grid gap-4 lg:grid-cols-2">
                <section className="rounded-xl border border-navy-100 bg-white p-4">
                  <h4 className="text-sm font-bold text-navy-900">Père</h4>
                  <div className="mt-3 flex flex-col gap-3">
                    <Input label="Nom complet" error={errors.pere_nom_complet?.message} {...register('pere_nom_complet')} />
                    <Select label="Statut" {...register('pere_statut')}>
                      <option value="">—</option>
                      {STATUTS_PARENT.map((statut) => (
                        <option key={statut.value ?? ''} value={statut.value ?? ''}>
                          {statut.label}
                        </option>
                      ))}
                    </Select>
                    <Input
                      label="Téléphone"
                      placeholder="Obligatoire si vivant"
                      error={errors.pere_telephone?.message}
                      {...register('pere_telephone')}
                    />
                  </div>
                </section>

                <section className="rounded-xl border border-navy-100 bg-white p-4">
                  <h4 className="text-sm font-bold text-navy-900">Mère</h4>
                  <div className="mt-3 flex flex-col gap-3">
                    <Input label="Nom complet" error={errors.mere_nom_complet?.message} {...register('mere_nom_complet')} />
                    <Select label="Statut" {...register('mere_statut')}>
                      <option value="">—</option>
                      {STATUTS_PARENT.map((statut) => (
                        <option key={statut.value ?? ''} value={statut.value ?? ''}>
                          {statut.label}
                        </option>
                      ))}
                    </Select>
                    <Input
                      label="Téléphone"
                      placeholder="Obligatoire si vivante"
                      error={errors.mere_telephone?.message}
                      {...register('mere_telephone')}
                    />
                  </div>
                </section>
              </div>

              <section className="rounded-xl border border-dashed border-navy-200 p-4">
                <div className="mb-3 flex items-center justify-between gap-3">
                  <div>
                    <h4 className="text-sm font-bold text-navy-900">Enfants</h4>
                    <p className="text-xs text-navy-400">Ajoutez un enfant par ligne avec son sexe et sa date de naissance.</p>
                  </div>
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => append({ nom_complet: '', sexe: '', date_naissance: '' })}
                  >
                    <Plus className="h-4 w-4" />
                    Ajouter
                  </Button>
                </div>

                {enfants.length === 0 ? (
                  <p className="rounded-lg bg-cream-100 px-3 py-2 text-sm text-navy-500">
                    Aucun enfant saisi pour le moment.
                  </p>
                ) : (
                  <div className="flex flex-col gap-3">
                    {enfants.map((enfant, index) => (
                      <div key={enfant.id} className="grid gap-3 rounded-lg border border-navy-100 p-3 lg:grid-cols-[minmax(0,2fr)_12rem_12rem_auto]">
                        <Input
                          label="Nom complet"
                          error={errors.enfants?.[index]?.nom_complet?.message}
                          {...register(`enfants.${index}.nom_complet` as const, {
                            validate: (valeur, champs) =>
                              valeur || champs.sexe || champs.date_naissance
                                ? valeur?.trim()
                                  ? true
                                  : "Le nom de l'enfant est obligatoire."
                                : true,
                          })}
                        />
                        <Select
                          label="Sexe"
                          error={errors.enfants?.[index]?.sexe?.message}
                          {...register(`enfants.${index}.sexe` as const, {
                            validate: (valeur, champs) =>
                              valeur || champs.nom_complet || champs.date_naissance
                                ? valeur === 'M' || valeur === 'F'
                                  ? true
                                  : "Le sexe de l'enfant est obligatoire."
                                : true,
                          })}
                        >
                          <option value="">—</option>
                          {SEXES_ENFANT.map((sexe) => (
                            <option key={sexe.value ?? ''} value={sexe.value ?? ''}>
                              {sexe.label}
                            </option>
                          ))}
                        </Select>
                        <Input
                          label="Date de naissance"
                          type="date"
                          error={errors.enfants?.[index]?.date_naissance?.message}
                          {...register(`enfants.${index}.date_naissance` as const, {
                            validate: (valeur, champs) =>
                              valeur || champs.nom_complet || champs.sexe
                                ? valeur
                                  ? true
                                  : "La date de naissance de l'enfant est obligatoire."
                                : true,
                          })}
                        />
                        <div className="flex items-end justify-end">
                          <button
                            type="button"
                            onClick={() => remove(index)}
                            className="rounded-lg p-2 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
                            aria-label={`Supprimer l'enfant ${index + 1}`}
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </section>
            </div>
          )}

          {etape === 4 && (
            <div className="flex flex-col gap-4">
              <h3 className="font-display text-base font-bold text-navy-900">Vérification avant enregistrement</h3>
              <dl className="grid gap-x-8 sm:grid-cols-2">
                {recapitulatif.map(([libelle, valeur]) => (
                  <div key={libelle} className="flex justify-between gap-3 border-b border-navy-50 py-2">
                    <dt className="text-sm text-navy-400">{libelle}</dt>
                    <dd className="min-w-0 truncate text-right text-sm font-medium text-navy-900">
                      {valeur === '' || valeur === null || valeur === undefined ? '—' : valeur}
                    </dd>
                  </div>
                ))}
              </dl>
            </div>
          )}
        </StepForm>
      </form>
    </div>
  )
}
