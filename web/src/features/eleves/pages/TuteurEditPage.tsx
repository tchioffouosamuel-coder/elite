import { useEffect, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Plus, Save, Star, Trash2, UserRound } from 'lucide-react'
import { http } from '@/shared/lib/http'
import { fetchEleve, type EleveTuteurInput } from '@/features/eleves/api'
import { NB_TELEPHONES_MIN, completerTelephones, type TelephoneEntry } from '@/features/eleves/lib/telephones'
import { TelephonesEditor } from '@/features/eleves/components/TelephonesEditor'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

interface TuteurFormItem {
  tuteur_id?: number
  nom_complet: string
  lien_parente: string
  profession: string
  email: string
  adresse: string
  telephones: TelephoneEntry[]
  is_principal: boolean
}

const LIENS_PARENTE = [
  { value: 'père', label: 'Père' },
  { value: 'mère', label: 'Mère' },
  { value: 'tuteur', label: 'Tuteur' },
  { value: 'autre', label: 'Autre' },
]

/**
 * Édition des tuteurs d'un élève, hors du reste de sa fiche — pour corriger
 * un numéro ou une profession sans rouvrir tout le formulaire d'inscription
 * (classe, paiement, informations administratives...). Réutilise le même
 * payload partiel que `archiveEleve` : l'API accepte `{ tuteurs: [...] }`
 * seul, sans exiger le reste de la fiche (cf. UpdateEleveRequest, règles
 * `sometimes`).
 */
export function TuteurEditPage() {
  const { id } = useParams<{ id: string }>()
  const eleveId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [tuteurs, setTuteurs] = useState<TuteurFormItem[] | null>(null)
  const [enregistrement, setEnregistrement] = useState(false)

  const { data: eleve, isLoading, isError } = useQuery({
    queryKey: ['eleve', eleveId],
    queryFn: () => fetchEleve(eleveId),
    enabled: Number.isFinite(eleveId),
  })

  useEffect(() => {
    if (!eleve || tuteurs !== null) return
    setTuteurs(
      eleve.tuteurs.map((tut) => ({
        tuteur_id: tut.id,
        nom_complet: tut.nom_complet,
        lien_parente: tut.lien_parente ?? 'père',
        profession: tut.profession ?? '',
        email: tut.email ?? '',
        adresse: tut.adresse ?? '',
        telephones: completerTelephones(tut.telephones.map((t) => ({ numero: t.numero, is_principal: t.is_principal }))),
        is_principal: tut.is_principal,
      })),
    )
  }, [eleve, tuteurs])

  const modifier = (index: number, champs: Partial<TuteurFormItem>) => {
    setTuteurs((courant) => courant!.map((t, i) => (i === index ? { ...t, ...champs } : t)))
  }

  const marquerPrincipal = (index: number) => {
    setTuteurs((courant) => courant!.map((t, i) => ({ ...t, is_principal: i === index })))
  }

  const ajouterTuteur = () => {
    setTuteurs((courant) => [
      ...(courant ?? []),
      {
        nom_complet: '',
        lien_parente: 'père',
        profession: '',
        email: '',
        adresse: '',
        telephones: Array.from({ length: NB_TELEPHONES_MIN }, (_, i) => ({ numero: '', is_principal: i === 0 })),
        is_principal: (courant?.length ?? 0) === 0,
      },
    ])
  }

  const supprimerTuteur = (index: number) => {
    setTuteurs((courant) => {
      const next = courant!.filter((_, i) => i !== index)
      if (courant![index].is_principal && next.length > 0) next[0] = { ...next[0], is_principal: true }
      return next
    })
  }

  const enregistrer = async () => {
    if (!tuteurs) return

    const payload: EleveTuteurInput[] = tuteurs.map((t) => ({
      tuteur_id: t.tuteur_id,
      nom_complet: t.nom_complet,
      telephones: t.telephones,
      profession: t.profession || undefined,
      lien_parente: t.lien_parente || undefined,
      is_principal: t.is_principal,
    }))

    setEnregistrement(true)
    try {
      // Payload volontairement partiel : `updateEleve()` exige tous les
      // champs de la fiche (typage `ElevePayload`) alors que l'API accepte
      // `{ tuteurs: [...] }` seul (cf. UpdateEleveRequest, règles `sometimes`)
      // — même pattern que `archiveEleve()`.
      await http.put(`/eleves/${eleveId}`, { tuteurs: payload })
      queryClient.invalidateQueries({ queryKey: ['eleve', eleveId] })
      queryClient.invalidateQueries({ queryKey: ['eleves'] })
      succes('Informations du tuteur mises à jour.')
      navigate(`/eleves/${eleveId}`)
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnregistrement(false)
    }
  }

  if (isLoading || tuteurs === null) return <Spinner />
  if (isError || !eleve) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={`Tuteurs de ${eleve.nom_complet}`}
        sousTitre="Modifier uniquement les informations des tuteurs, sans toucher au reste de la fiche."
        icon={UserRound}
        actions={
          <>
            <Button type="button" variant="secondary" onClick={() => navigate(`/eleves/${eleveId}`)}>
              <ArrowLeft className="h-4 w-4" />
              Retour à la fiche
            </Button>
            <Button type="button" onClick={() => void enregistrer()} disabled={enregistrement}>
              <Save className="h-4 w-4" />
              Enregistrer
            </Button>
          </>
        }
      />

      <div className="flex flex-col gap-4">
        {tuteurs.map((tuteur, index) => (
          <Card key={tuteur.tuteur_id ?? `nouveau-${index}`}>
            <div className="mb-4 flex items-center justify-between gap-3">
              <button
                type="button"
                onClick={() => marquerPrincipal(index)}
                title="Marquer comme tuteur principal"
                className="flex items-center gap-1.5 text-xs font-semibold text-navy-500 hover:text-gold-600"
              >
                <Star className={tuteur.is_principal ? 'h-4 w-4 fill-gold-400 text-gold-500' : 'h-4 w-4 text-navy-300'} />
                {tuteur.is_principal ? 'Tuteur principal' : 'Marquer comme principal'}
              </button>
              {tuteurs.length > 1 && (
                <button
                  type="button"
                  onClick={() => supprimerTuteur(index)}
                  className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-red-500 hover:bg-red-50"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  Retirer ce tuteur
                </button>
              )}
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label="Nom complet"
                value={tuteur.nom_complet}
                onChange={(e) => modifier(index, { nom_complet: e.target.value })}
              />
              <Select
                label="Lien de parenté"
                value={tuteur.lien_parente}
                onChange={(e) => modifier(index, { lien_parente: e.target.value })}
              >
                {LIENS_PARENTE.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </Select>
              <Input
                label="Profession"
                value={tuteur.profession}
                onChange={(e) => modifier(index, { profession: e.target.value })}
              />
              <Input
                label="Email"
                type="email"
                value={tuteur.email}
                onChange={(e) => modifier(index, { email: e.target.value })}
              />
              <div className="sm:col-span-2">
                <Input
                  label="Adresse"
                  value={tuteur.adresse}
                  onChange={(e) => modifier(index, { adresse: e.target.value })}
                />
              </div>
            </div>

            <div className="mt-4">
              <TelephonesEditor
                telephones={tuteur.telephones}
                onChange={(telephones) => modifier(index, { telephones })}
              />
            </div>
          </Card>
        ))}

        <Button type="button" variant="secondary" className="w-fit" onClick={ajouterTuteur}>
          <Plus className="h-4 w-4" />
          Ajouter un tuteur
        </Button>
      </div>
    </div>
  )
}
