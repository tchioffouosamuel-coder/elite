import { useState } from 'react'
import { useForm, useFieldArray } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'
import { StepForm } from '@/shared/ui/StepForm'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import {
  fetchEcolesDisponibles,
  fetchClassesDisponibles,
  soumettrePreinscription,
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

/** Préinscription d'un nouvel enfant, dont le compte connecté deviendra le tuteur. */
export function ParentPreinscriptionNouveauPage() {
  const navigate = useNavigate()
  const [currentStep, setCurrentStep] = useState(0)
  const [submitting, setSubmitting] = useState(false)
  const [serverError, setServerError] = useState<string | null>(null)

  const { data: ecoles } = useQuery({ queryKey: ['parent-ecoles-disponibles'], queryFn: fetchEcolesDisponibles })

  const {
    register,
    control,
    handleSubmit,
    trigger,
    watch,
    formState: { errors },
  } = useForm<FormValues>({ defaultValues: { tuteurs: [{ nom_complet: '', is_principal: true }], aptitude: 'apte' } })

  const { fields, append, remove } = useFieldArray({ control, name: 'tuteurs' })
  const schoolId = watch('school_id') ? Number(watch('school_id')) : undefined

  const { data: classes, isLoading: classesEnChargement } = useQuery({
    queryKey: ['parent-classes-disponibles', schoolId],
    queryFn: () => fetchClassesDisponibles(schoolId!),
    enabled: !!schoolId,
  })

  const steps = [
    { id: 'ecole', label: 'École', description: 'Établissement et classe visés' },
    { id: 'identite', label: 'Identité', description: "Informations de l'enfant" },
    { id: 'acte_sante', label: 'État civil & santé', description: 'Acte de naissance, fiche sanitaire' },
    { id: 'tuteurs', label: 'Parents', description: 'Vos coordonnées' },
    { id: 'confirmation', label: 'Confirmation', description: "Envoi à l'établissement" },
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
      await soumettrePreinscription({
        type: 'nouveau',
        school_id: Number(values.school_id),
        donnees_eleve: {
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
        },
        donnees_tuteurs: values.tuteurs,
      })
      succes("Préinscription transmise, en attente de validation par l'établissement.")
      navigate('/parent/preinscriptions')
    } catch (err) {
      setServerError((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div>
      <Link to="/parent" className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
        <ArrowLeft className="h-4 w-4" />
        Mes enfants
      </Link>
      <PageHeader titre="Inscrire un enfant" sousTitre="Nouvelle demande d'inscription, à valider par l'établissement." />

      <div className="mt-6">
        <form onSubmit={handleSubmit(onSubmit)}>
          <StepForm
            steps={steps}
            currentStep={currentStep}
            onStepChange={setCurrentStep}
            isLastStep={currentStep === steps.length - 1}
            isSubmitting={submitting}
            onSubmit={handleSubmit(onSubmit)}
            onCancel={() => navigate('/parent')}
            onNext={handleNext}
          >
            {steps[currentStep]?.id === 'ecole' && (
              <div className="space-y-4">
                <Select label="Établissement" error={errors.school_id?.message} {...register('school_id', { required: 'Requis.' })}>
                  <option value="">Sélectionner…</option>
                  {ecoles?.map((e) => (
                    <option key={e.id} value={e.id}>
                      {e.name}
                    </option>
                  ))}
                </Select>
                {schoolId && (
                  <Select label="Classe souhaitée" error={errors.classe_id?.message} {...register('classe_id', { required: 'Requis.' })}>
                    <option value="">{classesEnChargement ? 'Chargement…' : 'Sélectionner…'}</option>
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
                <Input label="Nom complet de l'enfant" error={errors.nom_complet?.message} {...register('nom_complet', { required: 'Requis.' })} />
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Select label="Sexe" error={errors.sexe?.message} {...register('sexe', { required: 'Requis.' })}>
                    <option value="">Sélectionner…</option>
                    <option value="F">Féminin</option>
                    <option value="M">Masculin</option>
                  </Select>
                  <Input label="Date de naissance" type="date" error={errors.date_naissance?.message} {...register('date_naissance', { required: 'Requis.' })} />
                </div>
                <Input label="Lieu de naissance" {...register('lieu_naissance')} />
                <Input label="Adresse" {...register('adresse')} />
              </div>
            )}

            {steps[currentStep]?.id === 'acte_sante' && (
              <div className="space-y-4">
                <h3 className="text-sm font-bold uppercase tracking-wide text-navy-500">Acte de naissance</h3>
                <Input label="N° acte de naissance" {...register('numero_acte_naissance')} />
                <Input label="Lieu de délivrance" {...register('lieu_delivrance_acte')} />
                <Input label="Nom de l'officier d'état civil" {...register('officier_etat_civil')} />

                <h3 className="mt-6 text-sm font-bold uppercase tracking-wide text-navy-500">Fiche sanitaire</h3>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Input label="Groupe sanguin" placeholder="Ex. O+" {...register('groupe_sanguin')} />
                  <Select label="Aptitude" {...register('aptitude')}>
                    <option value="apte">Apte</option>
                    <option value="inapte">Inapte</option>
                  </Select>
                </div>
                <Textarea label="Allergies" {...register('allergies')} />
                <Textarea label="Situation sanitaire" {...register('situation_sanitaire')} />
              </div>
            )}

            {steps[currentStep]?.id === 'tuteurs' && (
              <TuteursFieldArray fields={fields} append={append} remove={remove} register={register} errors={errors} />
            )}

            {steps[currentStep]?.id === 'confirmation' && (
              <div className="space-y-4">
                {serverError && <p className="rounded-xl bg-red-50 px-3.5 py-2.5 text-sm text-red-700">{serverError}</p>}
                <p className="rounded-xl bg-green-50 px-3.5 py-2.5 text-sm text-green-800">
                  Votre demande d'inscription sera transmise à l'établissement pour validation.
                </p>
              </div>
            )}
          </StepForm>
        </form>
      </div>
    </div>
  )
}
