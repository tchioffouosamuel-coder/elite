import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Clock, MapPin, Map as MapIcon, Phone } from 'lucide-react'
import {
  fetchItineraire,
  pointerEleve,
  type ArretTournee,
  type EleveTournee,
  type SensTournee,
  type TrajetTournee,
} from '@/features/chauffeur/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Input } from '@/shared/ui/Field'
import { Tabs } from '@/shared/ui/Tabs'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function aujourdhui(): string {
  return new Date().toISOString().slice(0, 10)
}

/**
 * L'itinéraire du chauffeur : ses arrêts dans l'ordre de passage, et à chaque
 * arrêt les enfants qui y montent, à cocher au fur et à mesure du ramassage.
 *
 * Le pointage est propre à une date ET un sens : l'aller du matin et le retour
 * du soir sont deux tournées distinctes, chacune avec ses cases.
 */
export function ChauffeurItinerairePage() {
  const [date, setDate] = useState(aujourdhui())
  const [sens, setSens] = useState<SensTournee>('aller')

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['chauffeur', 'itineraire', date, sens],
    queryFn: () => fetchItineraire({ date, sens }),
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Mon itinéraire"
        sousTitre="Cochez chaque enfant au moment où il monte. Les contacts de la famille sont sous son nom."
        icon={MapIcon}
        actions={
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} aria-label="Date de la tournée" />
        }
      />

      <Tabs
        tabs={[
          { key: 'aller', label: 'Aller' },
          { key: 'retour', label: 'Retour' },
        ]}
        active={sens}
        onChange={(key) => setSens(key as SensTournee)}
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : data.trajets.length === 0 ? (
        <Card>
          <EmptyState label="Aucun trajet ne vous est affecté pour cette date." />
        </Card>
      ) : (
        <>
          <Card>
            <div className="flex flex-wrap items-center justify-between gap-3">
              <p className="text-sm text-navy-500">
                Tournée du {new Date(data.date).toLocaleDateString('fr-FR')} —{' '}
                {sens === 'aller' ? 'ramassage' : 'dépose'}
              </p>
              <p className="font-display text-lg font-bold tabular-nums text-navy-900">
                {data.pris}
                <span className="text-sm font-semibold text-navy-400"> / {data.effectif} pris en charge</span>
              </p>
            </div>
            <Jauge pris={data.pris} total={data.effectif} />
          </Card>

          {data.trajets.map((trajet) => (
            <CarteTrajet key={trajet.id} trajet={trajet} date={date} sens={sens} />
          ))}
        </>
      )}
    </div>
  )
}

function Jauge({ pris, total }: { pris: number; total: number }) {
  const part = total > 0 ? Math.round((pris / total) * 100) : 0

  return (
    <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-navy-50">
      <div
        className="h-full rounded-full bg-green-500 transition-all"
        style={{ width: `${part}%` }}
        role="progressbar"
        aria-valuenow={pris}
        aria-valuemin={0}
        aria-valuemax={total}
      />
    </div>
  )
}

function CarteTrajet({ trajet, date, sens }: { trajet: TrajetTournee; date: string; sens: SensTournee }) {
  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-display text-base font-bold text-navy-900">{trajet.nom}</p>
          <p className="text-xs text-navy-400">{trajet.immatriculation}</p>
        </div>
        <Badge tone={trajet.pris >= trajet.effectif && trajet.effectif > 0 ? 'green' : 'gold'}>
          {trajet.pris} / {trajet.effectif} pris
        </Badge>
      </div>

      <div className="mt-4 flex flex-col gap-4">
        {trajet.arrets.length === 0 && trajet.eleves_sans_arret.length === 0 && (
          <EmptyState label="Ce trajet n'a pas encore d'arrêt." />
        )}

        {trajet.arrets.map((arret) => (
          <BlocArret key={arret.id} arret={arret} date={date} sens={sens} />
        ))}

        {/* Souscriptions sans arrêt rattaché : à montrer quand même, sinon
            l'enfant resterait invisible et attendrait au bord de la route. */}
        {trajet.eleves_sans_arret.length > 0 && (
          <BlocArret
            arret={{
              id: -trajet.id,
              nom: 'Sans arrêt renseigné',
              lieu_dit: null,
              ordre: trajet.arrets.length + 1,
              heure_passage: null,
              eleves: trajet.eleves_sans_arret,
            }}
            date={date}
            sens={sens}
          />
        )}
      </div>
    </Card>
  )
}

function BlocArret({ arret, date, sens }: { arret: ArretTournee; date: string; sens: SensTournee }) {
  const pris = arret.eleves.filter((e) => e.pris).length

  return (
    <section className="rounded-xl border border-navy-100/70 bg-navy-50/30">
      <header className="flex flex-wrap items-center justify-between gap-2 border-b border-navy-100/70 px-4 py-3">
        <div className="flex min-w-0 items-center gap-2">
          <span className="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-white text-xs font-bold tabular-nums text-navy-600 ring-1 ring-navy-100">
            {arret.ordre}
          </span>
          <div className="min-w-0">
            <p className="flex items-center gap-1.5 truncate font-semibold text-navy-900">
              <MapPin className="h-3.5 w-3.5 flex-none text-gold-500" />
              {arret.nom}
            </p>
            {arret.lieu_dit && <p className="truncate text-xs text-navy-400">{arret.lieu_dit}</p>}
          </div>
        </div>
        <div className="flex items-center gap-3">
          {arret.heure_passage && (
            <span className="flex items-center gap-1 text-xs tabular-nums text-navy-500">
              <Clock className="h-3.5 w-3.5" />
              {arret.heure_passage.slice(0, 5)}
            </span>
          )}
          <Badge tone={arret.eleves.length > 0 && pris >= arret.eleves.length ? 'green' : 'neutral'}>
            {pris} / {arret.eleves.length}
          </Badge>
        </div>
      </header>

      {arret.eleves.length === 0 ? (
        <p className="px-4 py-3 text-sm text-navy-400">Aucun enfant à cet arrêt.</p>
      ) : (
        <ul className="divide-y divide-navy-100/70">
          {arret.eleves.map((eleve) => (
            <LigneEleve key={eleve.affectation_id} eleve={eleve} date={date} sens={sens} />
          ))}
        </ul>
      )}
    </section>
  )
}

function LigneEleve({ eleve, date, sens }: { eleve: EleveTournee; date: string; sens: SensTournee }) {
  const queryClient = useQueryClient()
  const [enCours, setEnCours] = useState(false)

  const basculer = async () => {
    setEnCours(true)
    try {
      await pointerEleve({ affectation_id: eleve.affectation_id, date, sens, pris: !eleve.pris })
      queryClient.invalidateQueries({ queryKey: ['chauffeur', 'itineraire'] })
      queryClient.invalidateQueries({ queryKey: ['chauffeur', 'tableau-de-bord'] })
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <li className="flex items-start gap-3 px-4 py-3">
      <input
        type="checkbox"
        checked={eleve.pris}
        disabled={enCours}
        onChange={basculer}
        aria-label={`${eleve.nom_complet} pris en charge`}
        className="mt-0.5 h-5 w-5 flex-none cursor-pointer rounded border-navy-200 text-green-600 focus:ring-green-400"
      />
      <div className="min-w-0 flex-1">
        <p className={`truncate font-semibold ${eleve.pris ? 'text-navy-400 line-through' : 'text-navy-900'}`}>
          {eleve.nom_complet}
        </p>
        <p className="truncate text-xs text-navy-400">
          {[eleve.classe, eleve.matricule].filter(Boolean).join(' · ')}
          {eleve.pris_le && ` · pris à ${eleve.pris_le}`}
        </p>

        {/* Le contact de la famille, à portée de pouce : c'est tout ce que le
            chauffeur a à connaître du dossier de l'enfant. */}
        {eleve.tuteurs.length > 0 && (
          <ul className="mt-1.5 flex flex-wrap gap-x-4 gap-y-1">
            {eleve.tuteurs.map((tuteur, index) => (
              <li key={`${tuteur.nom_complet}-${index}`} className="text-xs text-navy-500">
                <span className="font-medium">{tuteur.nom_complet}</span>
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
      </div>
    </li>
  )
}
