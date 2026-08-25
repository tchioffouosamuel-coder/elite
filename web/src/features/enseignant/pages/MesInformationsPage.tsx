import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { UserRound, Pencil, X, Phone, Mail, MapPin } from 'lucide-react'
import { fetchMesInformations, mettreAJourMesInformations, fetchMaRemuneration } from '@/features/enseignant/api'
import type { DossierPersonnel } from '@/features/personnel/api'
import { GAINS, francs } from '@/features/finance/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function Champ({ label, valeur }: { label: string; valeur: string | number | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</span>
      <span className="text-sm font-medium text-navy-800">{valeur === 0 ? '0' : valeur || '—'}</span>
    </div>
  )
}

type ChampsModifiables = Pick<
  DossierPersonnel,
  | 'telephone'
  | 'telephone_2'
  | 'email'
  | 'residence'
  | 'situation_matrimoniale'
  | 'nombre_enfants'
  | 'diplome_academique'
  | 'diplome_professionnel'
  | 'pere_nom_complet'
  | 'pere_telephone'
  | 'mere_nom_complet'
  | 'mere_telephone'
>

/** Ma fiche personnel : identité et carrière en lecture seule, contact/famille/diplômes modifiables, rémunération en lecture seule. */
export function MesInformationsPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [modeEdition, setModeEdition] = useState(false)

  const { data: personnel, isLoading, isError } = useQuery({ queryKey: ['enseignant-mes-informations'], queryFn: fetchMesInformations })
  const { data: remuneration } = useQuery({ queryKey: ['enseignant-ma-remuneration'], queryFn: fetchMaRemuneration })

  const {
    register,
    handleSubmit,
    reset,
    formState: { isSubmitting },
  } = useForm<ChampsModifiables>()

  useEffect(() => {
    if (personnel) reset(personnel)
  }, [personnel, reset])

  if (isLoading) return <Spinner />
  if (isError || !personnel) return <ErrorState />

  const onSubmit = async (values: ChampsModifiables) => {
    try {
      await mettreAJourMesInformations(values)
      succes('Informations mises à jour.')
      setModeEdition(false)
      queryClient.invalidateQueries({ queryKey: ['enseignant-mes-informations'] })
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Mes informations"
        sousTitre="Ma fiche personnelle et ma rémunération."
        icon={UserRound}
        actions={
          <Button variant={modeEdition ? 'secondary' : 'primary'} onClick={() => setModeEdition((v) => !v)}>
            {modeEdition ? (
              <>
                <X className="h-4 w-4" />
                {t('common.cancel')}
              </>
            ) : (
              <>
                <Pencil className="h-4 w-4" />
                {t('common.edit')}
              </>
            )}
          </Button>
        }
      />

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Identité</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Champ label="Matricule" valeur={personnel.matricule} />
          <Champ label="Fonction" valeur={personnel.fonction} />
          <Champ label="Sexe" valeur={personnel.sexe === 'F' ? 'Féminin' : personnel.sexe === 'M' ? 'Masculin' : null} />
          <Champ label="Date de naissance" valeur={personnel.date_naissance} />
          <Champ label="N° CNI" valeur={personnel.numero_cni} />
          <Champ label="N° CNPS" valeur={personnel.numero_cnps} />
        </div>
      </Card>

      {modeEdition ? (
        <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-5">
          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Contact</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Input label="Téléphone" {...register('telephone')} />
              <Input label="Téléphone secondaire" {...register('telephone_2')} />
              <Input label="Email" type="email" {...register('email')} />
              <Textarea label="Résidence" {...register('residence')} />
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Carrière</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Champ label="Date d'embauche" valeur={personnel.date_embauche} />
              <Champ label="Fin de contrat" valeur={personnel.date_fin} />
              <Champ label="Départ à la retraite" valeur={personnel.date_retraite} />
              <Champ label="Affectation" valeur={personnel.affectation} />
              <Input label="Diplôme académique" {...register('diplome_academique')} />
              <Input label="Diplôme professionnel" {...register('diplome_professionnel')} />
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Famille</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Select label="Situation matrimoniale" {...register('situation_matrimoniale')}>
                <option value="">—</option>
                <option value="celibataire">Célibataire</option>
                <option value="marie">Marié(e)</option>
                <option value="divorce">Divorcé(e)</option>
                <option value="veuf">Veuf/Veuve</option>
              </Select>
              <Input label="Nombre d'enfants" type="number" min={0} {...register('nombre_enfants')} />
              <Input label="Nom du père" {...register('pere_nom_complet')} />
              <Input label="Téléphone du père" {...register('pere_telephone')} />
              <Input label="Nom de la mère" {...register('mere_nom_complet')} />
              <Input label="Téléphone de la mère" {...register('mere_telephone')} />
            </div>
          </Card>

          <div className="flex justify-end gap-2">
            <Button type="button" variant="secondary" onClick={() => setModeEdition(false)}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {t('common.save')}
            </Button>
          </div>
        </form>
      ) : (
        <>
          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Contact</h2>
            <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm text-navy-600">
              <span className="flex items-center gap-1.5">
                <Phone className="h-4 w-4 text-navy-300" />
                {[personnel.telephone, personnel.telephone_2].filter(Boolean).join(' · ') || '—'}
              </span>
              <span className="flex items-center gap-1.5">
                <Mail className="h-4 w-4 text-navy-300" />
                {personnel.email || '—'}
              </span>
              <span className="flex items-center gap-1.5">
                <MapPin className="h-4 w-4 text-navy-300" />
                {personnel.residence || '—'}
              </span>
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Carrière</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Champ label="Date d'embauche" valeur={personnel.date_embauche} />
              <Champ label="Fin de contrat" valeur={personnel.date_fin} />
              <Champ label="Départ à la retraite" valeur={personnel.date_retraite} />
              <Champ label="Affectation" valeur={personnel.affectation} />
              <Champ label="Diplôme académique" valeur={personnel.diplome_academique} />
              <Champ label="Diplôme professionnel" valeur={personnel.diplome_professionnel} />
            </div>
          </Card>

          <Card>
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Famille</h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Champ label="Situation matrimoniale" valeur={personnel.situation_matrimoniale} />
              <Champ label="Nombre d'enfants" valeur={personnel.nombre_enfants} />
              <Champ label="Père" valeur={personnel.pere_nom_complet} />
              <Champ label="Mère" valeur={personnel.mere_nom_complet} />
            </div>
          </Card>
        </>
      )}

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Rémunération</h2>
        {!remuneration ? (
          <EmptyState label="Aucune rémunération enregistrée pour l'instant." />
        ) : (
          <>
            <p className="mb-4 text-xs text-navy-400">
              Effet au {remuneration.date_effet} · <span className="capitalize">{remuneration.mode}</span>
            </p>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
              {GAINS.map((gain) => (
                <Champ key={gain.champ} label={gain.libelle} valeur={francs(remuneration[gain.champ] ?? 0)} />
              ))}
            </div>
            <div className="mt-4 grid grid-cols-2 gap-4 border-t border-navy-50 pt-4 sm:grid-cols-4">
              <Champ label="Brut" valeur={francs(remuneration.brut)} />
              <Champ label="Charges salariales" valeur={francs(remuneration.charges_salariales)} />
              <Champ label="Net" valeur={francs(remuneration.net)} />
              <Champ label="Coût employeur" valeur={francs(remuneration.cout_employeur)} />
            </div>
          </>
        )}
      </Card>
    </div>
  )
}
