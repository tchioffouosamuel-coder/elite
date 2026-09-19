import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, GitMerge, Phone } from 'lucide-react'
import {
  fetchDoublonsTuteurs,
  fusionnerDoublonTuteur,
  type GroupeDoublonTuteur,
  type MembreDoublonTuteur,
} from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'

/**
 * Doublons de comptes parent — deux fiches Tuteur avec le même numéro de
 * téléphone, dans la même école. La création d'un tuteur compare le numéro
 * BRUT plutôt que sa forme normalisée (cf. `Telephone::normaliser` côté API),
 * donc deux saisies du même numéro sous des formes différentes
 * ("659732002" vs "0659732002") créent deux fiches au lieu de réutiliser la
 * première.
 *
 * Fusion manuelle, pas automatique : un même numéro partagé entre deux
 * écoles du même complexe est un cas légitime (parent avec des enfants dans
 * plusieurs établissements) et n'apparaît jamais ici — le groupement se fait
 * par école, cf. `TuteurController::doublons`.
 */
export function TuteursDoublonsPage() {
  const navigate = useNavigate()
  const can = useAuthStore((state) => state.can)
  const queryClient = useQueryClient()
  const [enCours, setEnCours] = useState<string | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['tuteurs', 'doublons'],
    queryFn: fetchDoublonsTuteurs,
  })

  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['tuteurs'] })
  }

  const fusionner = async (groupe: GroupeDoublonTuteur, conservee: MembreDoublonTuteur) => {
    const autres = groupe.membres.filter((m) => m.id !== conservee.id)

    const confirme = await confirmer({
      titre: `Garder la fiche de ${conservee.nom_complet} ?`,
      message: `Les enfants, l'accès parent et l'historique (préinscriptions, justifications d'absence...) de ${autres.length > 1 ? 'toutes les autres fiches' : "l'autre fiche"} seront rattachés à celle-ci, qui sera seule conservée.`,
      action: 'Fusionner',
    })
    if (!confirme) return

    setEnCours(`${groupe.telephone}:${conservee.id}`)
    try {
      for (const autre of autres) {
        await fusionnerDoublonTuteur(conservee.id, autre.id)
      }
      rafraichir()
      succes(`${autres.length} fiche(s) fusionnée(s) dans ${conservee.nom_complet}.`)
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnCours(null)
    }
  }

  if (!can('eleves.manage')) {
    return <ErrorState />
  }

  const groupes = data ?? []
  const totalFiches = groupes.reduce((total, g) => total + g.membres.length, 0)

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Doublons de comptes parent"
        sousTitre="Fiches tuteur partageant le même numéro de téléphone, dans la même école. Choisissez laquelle garder pour chaque groupe."
        icon={GitMerge}
        actions={
          <Button type="button" variant="secondary" onClick={() => navigate('/comptes-parents')}>
            <ArrowLeft className="h-4 w-4" />
            Retour aux comptes parents
          </Button>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : groupes.length === 0 ? (
        <Card>
          <EmptyState label="Aucun doublon apparent n'a été trouvé." />
        </Card>
      ) : (
        <div className="flex flex-col gap-4">
          <div className="flex items-center gap-2 text-sm text-navy-600">
            <Phone className="h-4 w-4" />
            {groupes.length} groupe(s) de doublons, {totalFiches} fiche(s) à vérifier
          </div>

          {groupes.map((groupe) => (
            <Card key={`${groupe.ecole ?? ''}:${groupe.telephone}`}>
              <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-navy-100 pb-3">
                <div>
                  <h2 className="font-bold text-navy-900">{groupe.telephone}</h2>
                  <p className="text-xs text-navy-500">{groupe.ecole ?? 'École non renseignée'}</p>
                </div>
                <Badge tone="gold">{groupe.membres.length} fiches</Badge>
              </div>

              <div className="flex flex-col divide-y divide-navy-100">
                {groupe.membres.map((membre) => (
                  <div
                    key={membre.id}
                    className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                  >
                    <div className="min-w-0">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="font-semibold text-navy-900">{membre.nom_complet}</p>
                        {membre.a_compte && <Badge tone="green">Accès portail</Badge>}
                      </div>
                      <p className="mt-0.5 text-xs text-navy-500">
                        {membre.telephone ?? '—'}
                        {' · '}
                        {membre.enfants.length > 0
                          ? `Enfant(s) : ${membre.enfants.map((e) => e.nom_complet).join(', ')}`
                          : 'Aucun enfant rattaché'}
                        {' · Créée le '}
                        {membre.created_at ?? '—'}
                      </p>
                    </div>
                    <Button
                      size="sm"
                      variant="secondary"
                      disabled={enCours !== null}
                      onClick={() => void fusionner(groupe, membre)}
                    >
                      <GitMerge className="h-3.5 w-3.5" />
                      {enCours === `${groupe.telephone}:${membre.id}` ? 'Fusion…' : 'Garder celle-ci'}
                    </Button>
                  </div>
                ))}
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
