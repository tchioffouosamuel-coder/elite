import { useEffect, useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { StepForm } from '@/shared/ui/StepForm'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner } from '@/shared/ui/Feedback'
import {
  fetchEcolesDisponibles,
  fetchClassesDisponibles,
  fetchMaPreinscription,
  soumettrePreinscription,
  modifierPreinscription,
  type TuteurPayload,
} from '@/features/parent/api'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { TuteursFieldArray } from './ParentPreinscriptionExistantPage'

interface FormValues {
  school_id: number | ''
  classe_id: number | ''
  nom_complet: string
  sexe: 'M' | 'F' | ''
  date_naissance: string
  lieu_naissance: string
  adresse: string
  numero_acte_naissance: string
  lieu_delivrance_acte: string
  officier_etat_civil: string
  groupe_sanguin: string
  situation_sanitaire: string
  aptitude: 'apte' | 'inapte'
  allergies: string
  tuteurs: TuteurPayload[]
}

/** Préinscription d'un nouvel enfant, dont le compte connecté deviendra le tuteur — ou correction d'une demande déjà déposée (`?id=`). */
export function ParentPreinscriptionNouveauPage() {
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const preinscriptionId = searchParams.get('id') ? Number(searchParams.get('id')) : undefined
  const modeEdition = !!preinscriptionId

  const [currentStep, setCurrentStep] = useState(0)
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const { data: ecoles } = useQuery({ queryKey: ['parent-ecoles-disponibles'], queryFn: fetchEcolesDisponibles })
  const { data: preinscriptionDetail, isLoading: preinscriptionEnChargement } = useQuery({
    queryKey: ['parent-preinscription', preinscriptionId],
    queryFn: () => fetchMaPreinscription(preinscriptionId!),
    enabled: modeEdition,
  })

  const {
    register,
    control,
    handleSubmit,
    reset,
    trigger,
    watch,
    formState: { errors },
  } = useForm<FormValues>({ defaultValues: { tuteurs: [{ nom_complet: '', is_principal: true }], aptitude: 'apte' } })

  useEffect(() => {
    if (!preinscriptionDetail) return
    const d = preinscriptionDetail.donnees_eleve
    reset({
      school_id: preinscriptionDetail.school?.id ?? '',
      classe_id: d.classe_id ?? '',
      nom_complet: d.nom_complet,
      sexe: d.sexe,
      date_naissance: d.date_naissance ?? '',
      lieu_naissance: d.lieu_naissance ?? '',
      adresse: d.adresse ?? '',
      numero_acte_naissance: d.numero_acte_naissance ?? '',
      lieu_delivrance_acte: d.lieu_delivrance_acte ?? '',
      officier_etat_civil: d.officier_etat_civil ?? '',
      groupe_sanguin: d.groupe_sanguin ?? '',
      situation_sanitaire: d.situation_sanitaire ?? '',
      aptitude: d.aptitude ?? 'apte',
      allergies: d.allergies ?? '',
      tuteurs: preinscriptionDetail.donnees_tuteurs,
    })
  }, [preinscriptionDetail, reset])

  const { fields, append, remove } = useFieldArray({ control, name: 'tuteurs' })
  const schoolId = watch('school_id') ? Number(watch('school_id')) : undefined

  const { data: classes, isLoading: classesEnChargement } = useQuery({
    queryKey: ['parent-classes-disponibles', schoolId],
    queryFn: () => fetchClassesDisponibles(schoolId!),
    enabled: !!schoolId,
  })

  const steps = [
    { id: 'ecole', label: 'École / School', description: 'Établissement et classe visés / Target school and class' },
    { id: 'identite', label: 'Identité / Identity', description: "Informations de l'enfant / Child's information" },
    { id: 'acte_sante', label: 'État civil & santé / Civil status & health', description: 'Acte de naissance, fiche sanitaire / Birth certificate, health record' },
    { id: 'tuteurs', label: 'Parents / Parents', description: 'Vos coordonnées / Your contact details' },
    { id: 'confirmation', label: 'Confirmation / Confirmation', description: "Envoi à l'établissement / Sent to the school" },
  ]

  const handleNext = async (): Promise<boolean> => {
    if (steps[currentStep]?.id === 'ecole') return trigger(['school_id', 'classe_id'])
    if (steps[currentStep]?.id === 'identite') return trigger(['nom_complet', 'sexe', 'date_naissance'])
    return true
  }

  const onSubmit = async (values: FormValues) => {
    setServerError(null)
    setSubmitting(true)
    try {
      const donneesEleve = {
        nom_complet: values.nom_complet,
        sexe: values.sexe as 'M' | 'F',
        date_naissance: values.date_naissance,
        lieu_naissance: values.lieu_naissance || undefined,
        adresse: values.adresse || undefined,
        classe_id: Number(values.classe_id),
        numero_acte_naissance: values.numero_acte_naissance || undefined,
        lieu_delivrance_acte: values.lieu_delivrance_acte || undefined,
        officier_etat_civil: values.officier_etat_civil || undefined,
        groupe_sanguin: values.groupe_sanguin || undefined,
        situation_sanitaire: values.situation_sanitaire || undefined,
        aptitude: values.aptitude,
        allergies: values.allergies || undefined,
      }

      if (modeEdition && preinscriptionId) {
        await modifierPreinscription(preinscriptionId, { donnees_eleve: donneesEleve, donnees_tuteurs: values.tuteurs })
        succes('Préinscription mise à jour. / Pre-registration updated.')
      } else {
        await soumettrePreinscription({
          type: 'nouveau',
          school_id: Number(values.school_id),
          donnees_eleve: donneesEleve,
          donnees_tuteurs: values.tuteurs,
        })
        succes("Préinscription transmise, en attente de validation par l'établissement. / Pre-registration submitted, awaiting school approval.")
      }
      navigate('/parent/preinscriptions')
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (modeEdition && (preinscriptionEnChargement || !preinscriptionDetail)) return <Spinner />

  return (
    <div>
      <Link to="/parent" className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
        <ArrowLeft className="h-4 w-4" />
        Mes enfants / My children
      </Link>
      <PageHeader
        titre={modeEdition ? 'Modifier ma préinscription / Edit my pre-registration' : 'Inscrire un enfant / Register a child'}
        sousTitre={
          modeEdition
            ? "Cette demande est encore en attente : vous pouvez la corriger avant qu'elle ne soit traitée. / This request is still pending: you can correct it before it's processed."
            : "Nouvelle demande d'inscription, à valider par l'établissement. / New registration request, to be approved by the school."
        }
      />

      <div className="mt-6">
        <form onSubmit={handleSubmit(onSubmit)}>
          <StepForm
            steps={steps}
            currentStep={currentStep}
            onStepChange={setCurrentStep}
            isLastStep={currentStep === steps.length - 1}
            isSubmitting={submitting}
            onSubmit={handleSubmit(onSubmit)}
            onCancel={() => navigate(modeEdition ? '/parent/preinscriptions' : '/parent')}
            onNext={handleNext}
          >
            {steps[currentStep]?.id === 'ecole' && (
              <div className="space-y-4">
                {modeEdition && (
                  <p className="rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-500">
                    L'établissement et la classe visés ne peuvent plus changer une fois la demande déposée. / The target
                    school and class can no longer change once the request has been submitted.
                  </p>
                )}
                <Select
                  label="Établissement / School"
                  error={errors.school_id?.message}
                  disabled={modeEdition}
                  {...register('school_id', { required: 'Requis. / Required.' })}
                >
                  <option value="">Sélectionner… / Select…</option>
                  {ecoles?.map((e) => (
                    <option key={e.id} value={e.id}>
                      {e.name}
                    </option>
                  ))}
                  {/* L'école de la demande peut ne plus être dans la liste courante des
                      écoles disponibles (ex. devenue inactive) : on l'ajoute au besoin
                      pour que le select ait toujours une option correspondant à la valeur. */}
                  {modeEdition && preinscriptionDetail?.school && !ecoles?.some((e) => e.id === preinscriptionDetail.school?.id) && (
                    <option value={preinscriptionDetail.school.id}>{preinscriptionDetail.school.name}</option>
                  )}
                </Select>
                {schoolId && (
                  <Select
                    label="Classe souhaitée / Desired class"
                    error={errors.classe_id?.message}
                    disabled={modeEdition}
                    {...register('classe_id', { required: 'Requis. / Required.' })}
                  >
                    <option value="">{classesEnChargement ? 'Chargement… / Loading…' : 'Sélectionner… / Select…'}</option>
                    {classes?.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.nom}
                      </option>
                    ))}
                  </Select>
                )}
              </div>
            )}

            {steps[currentStep]?.id === 'identite' && (
              <div className="space-y-4">
                <Input
                  label="Nom complet de l'enfant / Child's full name"
                  error={errors.nom_complet?.message}
                  {...register('nom_complet', { required: 'Requis. / Required.' })}
                />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Select label="Sexe / Sex" error={errors.sexe?.message} {...register('sexe', { required: 'Requis. / Required.' })}>
                    <option value="">Sélectionner… / Select…</option>
                    <option value="F">Féminin / Female</option>
                    <option value="M">Masculin / Male</option>
                  </Select>
                  <Input
                    label="Date de naissance / Date of birth"
                    type="date"
                    error={errors.date_naissance?.message}
                    {...register('date_naissance', { required: 'Requis. / Required.' })}
                  />
                </div>
                <Input label="Lieu de naissance / Place of birth" {...register('lieu_naissance')} />
                <Input label="Adresse / Address" {...register('adresse')} />
              </div>
            )}

            {steps[currentStep]?.id === 'acte_sante' && (
              <div className="space-y-4">
                <h3 className="text-sm font-bold uppercase tracking-wide text-navy-500">Acte de naissance / Birth certificate</h3>
                <Input label="N° acte de naissance / Birth certificate no." {...register('numero_acte_naissance')} />
                <Input label="Lieu de délivrance / Place of issue" {...register('lieu_delivrance_acte')} />
                <Input label="Nom de l'officier d'état civil / Registrar's name" {...register('officier_etat_civil')} />

                <h3 className="mt-6 text-sm font-bold uppercase tracking-wide text-navy-500">Fiche sanitaire / Health record</h3>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Input label="Groupe sanguin / Blood type" placeholder="Ex. O+" {...register('groupe_sanguin')} />
                  <Select label="Aptitude / Fitness" {...register('aptitude')}>
                    <option value="apte">Apte / Fit</option>
                    <option value="inapte">Inapte / Unfit</option>
                  </Select>
                </div>
                <Textarea label="Allergies / Allergies" {...register('allergies')} />
                <Textarea label="Situation sanitaire / Health situation" {...register('situation_sanitaire')} />
              </div>
            )}

            {steps[currentStep]?.id === 'tuteurs' && (
              <TuteursFieldArray fields={fields} append={append} remove={remove} register={register} errors={errors} />
            )}

            {steps[currentStep]?.id === 'confirmation' && (
              <div className="space-y-4">
                {serverError && <p className="rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{serverError}</p>}
                <p className="rounded-xl bg-green-50 px-3.5 py-2.5 text-sm text-green-800">
                  {modeEdition
                    ? "Votre correction sera enregistrée sur la demande déjà en attente. / Your correction will be saved on the request already pending."
                    : "Votre demande d'inscription sera transmise à l'établissement pour validation. / Your registration request will be sent to the school for approval."}
                </p>
              </div>
            )}
          </StepForm>
        </form>
      </div>
    </div>
  )
}
