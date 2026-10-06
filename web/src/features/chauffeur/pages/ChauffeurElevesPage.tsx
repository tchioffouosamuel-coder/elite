import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Phone, Users } from 'lucide-react'
import { fetchMesEleves, type EleveTournee } from '@/features/chauffeur/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Input } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { Table, Thead, Th, Td, Tr } from '@/shared/ui/Table'

function normaliser(valeur: string): string {
  return valeur
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
}

/**
 * L'annuaire de bord : tous les enfants que le chauffeur transporte, avec le
 * contact de leurs tuteurs — pour joindre une famille sans dérouler
 * l'itinéraire arrêt par arrêt.
 *
 * Lecture seule : le chauffeur ne souscrit personne et ne change ni trajet ni
 * arrêt (cf. ChauffeurEspaceController, qui n'expose aucune route pour ça).
 */
export function ChauffeurElevesPage() {
  const [recherche, setRecherche] = useState('')
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['chauffeur', 'eleves'],
    queryFn: () => fetchMesEleves(),
  })

  const requete = normaliser(recherche.trim())
  const eleves = useMemo(() => {
    if (!data) return []
    if (requete === '') return data

    return data.filter((eleve) =>
      [eleve.nom_complet, eleve.matricule, eleve.classe, eleve.trajet, eleve.arret]
        .concat(eleve.tuteurs.flatMap((t) => [t.nom_complet, t.telephone]))
        .some((champ) => champ && normaliser(champ).includes(requete)),
    )
  }, [data, requete])

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Mes élèves transportés"
        sousTitre="Les enfants de votre bus et le contact de leurs parents."
        icon={Users}
        actions={
          <Input
            placeholder="Nom, classe, arrêt, téléphone…"
            value={recherche}
            onChange={(e) => setRecherche(e.target.value)}
            aria-label="Rechercher un élève"
          />
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : eleves.length === 0 ? (
        <Card>
          <EmptyState
            label={
              data.length === 0
                ? "Aucun élève n'est affecté à votre bus pour l'instant."
                : 'Aucun élève ne correspond à cette recherche.'
            }
          />
        </Card>
      ) : (
        <Card>
          <p className="mb-3 text-sm text-navy-400">
            {eleves.length} élève{eleves.length > 1 ? 's' : ''}
            {requete !== '' && ` sur ${data.length}`}
          </p>
          <Table>
            <Thead>
              <tr>
                <Th>Élève</Th>
                <Th>Classe</Th>
                <Th>Trajet / arrêt</Th>
                <Th>Contacts famille</Th>
              </tr>
            </Thead>
            <tbody>
              {eleves.map((eleve) => (
                <LigneEleve key={eleve.affectation_id} eleve={eleve} />
              ))}
            </tbody>
          </Table>
        </Card>
      )}
    </div>
  )
}

function LigneEleve({ eleve }: { eleve: EleveTournee }) {
  return (
    <Tr>
      <Td>
        <span className="font-semibold text-navy-900">{eleve.nom_complet}</span>
        {eleve.matricule && <span className="block text-xs text-navy-400">{eleve.matricule}</span>}
      </Td>
      <Td>{eleve.classe ?? '—'}</Td>
      <Td>
        <span className="text-navy-700">{eleve.trajet ?? '—'}</span>
        {eleve.arret && <span className="block text-xs text-navy-400">{eleve.arret}</span>}
      </Td>
      <Td>
        {eleve.tuteurs.length === 0 ? (
          <span className="text-navy-300">Aucun contact enregistré</span>
        ) : (
          <ul className="flex flex-col gap-1">
            {eleve.tuteurs.map((tuteur, index) => (
              <li key={`${tuteur.nom_complet}-${index}`} className="text-xs">
                <span className="font-medium text-navy-700">{tuteur.nom_complet}</span>
                {tuteur.lien_parente && <span className="text-navy-400"> ({tuteur.lien_parente})</span>}
                {tuteur.telephone && (
                  <a
                    href={`tel:${tuteur.telephone}`}
                    className="ml-1.5 inline-flex items-center gap-1 font-semibold text-navy-700 hover:text-gold-600"
                  >
                    <Phone className="h-3 w-3" />
                    {tuteur.telephone}
                  </a>
                )}
              </li>
            ))}
          </ul>
        )}
      </Td>
    </Tr>
  )
}
