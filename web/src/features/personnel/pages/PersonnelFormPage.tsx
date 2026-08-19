import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { ArrowLeft, BriefcaseBusiness } from 'lucide-react'
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
  type PersonnelPayload,
} from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { estSecondaire } from '@/shared/lib/ecole'
import { useAuthStore } from '@/shared/store/authStore'
import type { ApiError } from '@/shared/types/api'

/**
 * Dossier d'un agent, saisi en quatre étapes.
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
  { id: 'recap', label: 'Récapitulatif', description: 'Vérification', champs: [] },
]

const CIVILITES = ['M.', 'Mme', 'Mlle', 'Mr', 'Mrs', 'Miss']

const SITUATIONS = [
  ['celibataire', 'Célibataire'],
  ['marie', 'Marié(e)'],
  ['divorce', 'Divorcé(e)'],
  ['veuf', 'Veuf / Veuve'],
] as const

/** Champs vides renvoyés en `null` : une chaîne vide ferait échouer `email` ou `date`. */
function nettoyer<T extends Record<string, unknown>>(valeurs: T): T {
  return Object.fromEntries(
    Object.entries(valeurs).map(([cle, valeur]) => [cle, valeur === '' ? null : valeur]),
  ) as T
}

export function PersonnelFormPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { id } = useParams()
  const personnelId = id ? Number(id) : null

  const avecDepartements = estSecondaire()
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)

  const { data: personnel, isLoading, isError } = useQuery({
    queryKey: ['personnel', personnelId],
    queryFn: () => fetchPersonnel(personnelId!),
    enabled: personnelId !== null,
  })
  const { data: departements } = useQuery({
    queryKey: ['departements', activeSchoolId],
    queryFn: fetchDepartements,
    enabled: avecDepartements,
  })
  const { data: fonctions } = useQuery({
    queryKey: ['fonctions-referentiel', activeSchoolId],
    queryFn: fetchFonctionsReferentiel,
  })
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })

  const [etape, setEtape] = useState(0)
  const [submitting, setSubmitting] = useState(false)

  const {
    register,
    handleSubmit,
    trigger,
    reset,
    watch,
    formState: { errors },
  } = useForm<PersonnelPayload>({ defaultValues: { nom_complet: '' } })

  const ecoleChoisie = watch('school_id')
  // La fonction et le département dépendent de l'école choisie : en mode
  // agrégé, `fonctions`/`departements` couvrent tout le complexe, il faut
  // filtrer plutôt que de mélanger les référentiels de plusieurs écoles.
  const fonctionsFiltrees = ecoleChoisie ? fonctions?.filter((f) => f.school_id === Number(ecoleChoisie)) : fonctions
  const departementsFiltres = ecoleChoisie ? departements?.filter((d) => d.school_id === Number(ecoleChoisie)) : departements

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
    })
  }, [personnel, reset])

  const onSubmit = async (values: PersonnelPayload) => {
    setSubmitting(true)
    try {
      const enfants = values.nombre_enfants
      const payload = nettoyer({
        ...values,
        fonction_id: Number(values.fonction_id),
        departement_id: values.departement_id ? Number(values.departement_id) : null,
        school_id: values.school_id ? Number(values.school_id) : null,
        nombre_enfants: enfants === undefined || enfants === null || (enfants as unknown) === '' ? null : Number(enfants),
      })

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
      setEtape(0)
    } finally {
      setSubmitting(false)
    }
  }

  const valeurs = watch()
  const fonctionLabel = fonctions?.find((f) => f.id === Number(valeurs.fonction_id))?.label

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
            : "Le dossier se remplit en quatre étapes ; seuls le nom et la fonction sont obligatoires."
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
          onNext={() => trigger(ETAPES[etape].champs as never)}
          onSubmit={handleSubmit(onSubmit)}
          onCancel={() => navigate('/personnel')}
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
