import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Package, Plus } from 'lucide-react'
import {
  fetchMesDemandesArticles,
  soumettreDemandeArticle,
  type DonneesArticleDemande,
  type MaDemandeArticle,
  type StatutDemandeArticle,
} from '@/features/mon-espace/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TONE_DEMANDE: Record<StatutDemandeArticle, 'green' | 'gold' | 'red'> = {
  en_attente: 'gold',
  validee: 'green',
  rejetee: 'red',
}
const LIBELLE_DEMANDE: Record<StatutDemandeArticle, string> = {
  en_attente: 'En attente',
  validee: 'Validée',
  rejetee: 'Rejetée',
}
const LIBELLE_ETAT: Record<string, string> = {
  bon: 'Bon état',
  moyen: 'État moyen',
  mauvais: 'Mauvais état',
  hors_service: 'Hors service',
}
const LIBELLE_CATEGORIE: Record<string, string> = {
  mobilier: 'Mobilier',
  informatique: 'Informatique',
  pedagogique: 'Pédagogique',
  sport: 'Sport',
  medical: 'Médical',
  autre: 'Autre',
}

/**
 * Libre-service : signaler tout matériel que l'établissement a remis pour le
 * travail (médical, informatique, pédagogique...) pour qu'il rejoigne
 * l'inventaire — sans écrire directement dedans, un titulaire de
 * `inventaire.manage` valide d'abord chaque demande.
 */
export function MesArticlesInventairePage() {
  const queryClient = useQueryClient()
  const [demandeOuverte, setDemandeOuverte] = useState(false)

  const { data, isLoading, isError, error } = useQuery({ queryKey: ['mon-espace-demandes-articles'], queryFn: fetchMesDemandesArticles })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Matériel reçu"
        sousTitre="Signalez tout ce que l'établissement vous a remis pour votre travail, pour que ça rejoigne l'inventaire une fois validé."
        icon={Package}
        actions={
          <Button onClick={() => setDemandeOuverte(true)}>
            <Plus className="h-4 w-4" />
            Signaler du matériel
          </Button>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : (
        <Card>
          <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Mes demandes</h2>
          {data.length === 0 ? (
            <EmptyState label="Aucune demande transmise pour l'instant." />
          ) : (
            <div className="flex flex-col divide-y divide-navy-50">
              {data.map((d: MaDemandeArticle) => (
                <div key={d.id} className="flex flex-col gap-1 py-3 first:pt-0 last:pb-0">
                  <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm font-semibold text-navy-800">
                      {d.donnees.nom} (x{d.donnees.quantite}) — {new Date(d.created_at).toLocaleDateString('fr-FR')}
                    </p>
                    <Badge tone={TONE_DEMANDE[d.statut]}>{LIBELLE_DEMANDE[d.statut]}</Badge>
                  </div>
                  <p className="text-xs text-navy-400">
                    {LIBELLE_CATEGORIE[d.donnees.categorie] ?? d.donnees.categorie}
                    {d.donnees.etat ? ` · ${LIBELLE_ETAT[d.donnees.etat]}` : null}
                    {d.donnees.localisation ? ` · ${d.donnees.localisation}` : null}
                  </p>
                  {d.statut === 'rejetee' && d.motif_rejet && (
                    <p className="text-xs text-red-600">Motif du rejet : {d.motif_rejet}</p>
                  )}
                </div>
              ))}
            </div>
          )}
        </Card>
      )}

      {demandeOuverte && (
        <SignalerMaterielModal
          onClose={() => setDemandeOuverte(false)}
          onSaved={() => {
            setDemandeOuverte(false)
            queryClient.invalidateQueries({ queryKey: ['mon-espace-demandes-articles'] })
          }}
        />
      )}
    </div>
  )
}

function SignalerMaterielModal({ onClose, onSaved }: { onClose: () => void; onSaved: () => void }) {
  const [serverError, setServerError] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<DonneesArticleDemande>({ defaultValues: { categorie: 'autre' } })

  const onSubmit = async (values: DonneesArticleDemande) => {
    setServerError(null)
    try {
      await soumettreDemandeArticle({
        nom: values.nom,
        categorie: values.categorie,
        quantite: Number(values.quantite),
        etat: values.etat || null,
        localisation: values.localisation || null,
        notes: values.notes || null,
      })
      succes("Demande transmise, en attente de validation par l'établissement.")
      onSaved()
    } catch (e) {
      const err = e as ApiError
      setServerError(err.message)
      erreur(err.message)
    }
  }

  return (
    <Modal title="Signaler du matériel reçu" onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label="Nom du matériel"
          placeholder="Ex. Ordinateur portable, boîte de paracétamol, ballon de basket..."
          error={errors.nom?.message}
          {...register('nom', { required: 'Précisez ce que vous avez reçu.' })}
        />

        <Select label="Catégorie" {...register('categorie', { required: true })}>
          <option value="mobilier">Mobilier</option>
          <option value="informatique">Informatique</option>
          <option value="pedagogique">Pédagogique</option>
          <option value="sport">Sport</option>
          <option value="medical">Médical</option>
          <option value="autre">Autre</option>
        </Select>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Quantité"
            type="number"
            min={1}
            error={errors.quantite?.message}
            {...register('quantite', { required: 'Saisissez la quantité.', min: { value: 1, message: 'Au moins 1.' } })}
          />
          <Select label="État" {...register('etat')}>
            <option value="">Bon (par défaut)</option>
            <option value="bon">Bon état</option>
            <option value="moyen">État moyen</option>
            <option value="mauvais">Mauvais état</option>
            <option value="hors_service">Hors service</option>
          </Select>
        </div>

        <Input label="Localisation" placeholder="Ex. Infirmerie campus A" {...register('localisation')} />

        <Textarea label="Notes" placeholder="Facultatif" {...register('notes')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            Transmettre
          </Button>
        </div>
      </form>
    </Modal>
  )
}
