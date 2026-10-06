import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { Bus, CalendarOff, HandCoins, Map, Users, Wallet } from 'lucide-react'
import {
  annulerMonEmpechement,
  declarerEmpechement,
  fetchTableauDeBordChauffeur,
  reprendreItineraire,
  type BilanVehicule,
  type RelaisItineraire,
} from '@/features/chauffeur/api'
import { francs } from '@/features/finance/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Modal } from '@/shared/ui/Modal'
import { Input, Textarea } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const CLE = ['chauffeur', 'tableau-de-bord'] as const

function aujourdhui(): string {
  return new Date().toISOString().slice(0, 10)
}

/**
 * Ma tournée : l'accueil du chauffeur. Tout y part du transport — combien
 * d'enfants il conduit, ce que son bus rapporte ce mois-ci, et un accès direct
 * à son itinéraire arrêt par arrêt.
 *
 * Ce qui n'y est pas, volontairement : souscrire un élève, retoucher un trajet
 * ou un arrêt. Le chauffeur consulte et pointe, la gestion reste à l'économat
 * (cf. `bus.souscrire` et `bus_trajets.*` côté API).
 */
export function ChauffeurDashboardPage() {
  const { data, isLoading, isError, error } = useQuery({ queryKey: CLE, queryFn: () => fetchTableauDeBordChauffeur() })
  const [empechementPour, setEmpechementPour] = useState<BilanVehicule | null>(null)

  const moisCourant = data
    ? new Date(data.mois).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })
    : ''

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Ma tournée"
        sousTitre="Les enfants que vous transportez, la rentabilité de votre bus et votre itinéraire du jour."
        icon={Bus}
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
              label="Élèves transportés"
              value={data.effectif_transporte}
              accent="navy"
              icon={Users}
              hint={data.capacite_totale > 0 ? `sur ${data.capacite_totale} places` : undefined}
            />
            <StatCard
              label="Souscriptions réglées"
              value={francs(data.rentabilite.recettes)}
              accent="green"
              icon={Wallet}
              hint={`pour ${moisCourant}`}
            />
            <StatCard
              label="Dépenses + salaire"
              value={francs(data.rentabilite.depenses + data.rentabilite.salaire)}
              accent="gold"
              icon={HandCoins}
              hint={`dont ${francs(data.rentabilite.salaire)} de salaire`}
            />
            <StatCard
              label="Rentabilité du mois"
              value={francs(data.rentabilite.resultat)}
              accent={data.rentabilite.resultat < 0 ? 'red' : 'green'}
              icon={Bus}
              hint={data.rentabilite.resultat < 0 ? 'Déficitaire' : 'Bénéficiaire'}
            />
          </div>

          {/* Le geste du quotidien : l'itinéraire, arrêt par arrêt. */}
          <BoutonItineraire tournee={data.tournee_du_jour} />

          <section className="flex flex-col gap-3">
            <h2 className="font-display text-base font-bold text-navy-900">Mon bus</h2>
            {data.vehicules.length === 0 ? (
              <Card>
                <EmptyState label="Aucun véhicule ne vous est affecté pour l'instant." />
              </Card>
            ) : (
              data.vehicules.map((vehicule) => (
                <CarteVehicule
                  key={vehicule.vehicule_id}
                  vehicule={vehicule}
                  moisCourant={moisCourant}
                  onEmpechement={() => setEmpechementPour(vehicule)}
                />
              ))
            )}
          </section>

          {data.mes_empechements.length > 0 && (
            <section className="flex flex-col gap-3">
              <h2 className="font-display text-base font-bold text-navy-900">Mes empêchements</h2>
              {data.mes_empechements.map((relais) => (
                <CarteRelais key={relais.id} relais={relais} mode="mien" />
              ))}
            </section>
          )}

          {data.itineraires_disponibles.length > 0 && (
            <section className="flex flex-col gap-3">
              <h2 className="font-display text-base font-bold text-navy-900">Itinéraires à reprendre</h2>
              <p className="text-sm text-navy-400">
                Un collègue est empêché sur ces périodes : en reprendre un l'ajoute à votre tournée.
              </p>
              {data.itineraires_disponibles.map((relais) => (
                <CarteRelais key={relais.id} relais={relais} mode="offert" />
              ))}
            </section>
          )}
        </>
      )}

      {empechementPour && (
        <ModaleEmpechement vehicule={empechementPour} onClose={() => setEmpechementPour(null)} />
      )}
    </div>
  )
}

/**
 * Le bouton central de l'écran : l'itinéraire du jour. Il porte l'avancement
 * des deux tournées pour que le chauffeur sache, sans l'ouvrir, où il en est.
 */
function BoutonItineraire({ tournee }: { tournee: Record<'aller' | 'retour', { effectif: number; pris: number }> }) {
  return (
    <Link
      to="/chauffeur/itineraire"
      className="group flex flex-col gap-3 rounded-2xl bg-linear-to-br from-navy-800 to-navy-900 p-5 text-white shadow-lifted transition hover:from-navy-700 hover:to-navy-800 focus:outline-none focus:ring-2 focus:ring-gold-400 sm:flex-row sm:items-center sm:justify-between"
    >
      <div className="flex items-center gap-4">
        <span className="flex h-12 w-12 flex-none items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
          <Map className="h-6 w-6 text-gold-300" />
        </span>
        <div>
          <p className="font-display text-lg font-bold tracking-tight">Mon itinéraire</p>
          <p className="text-sm text-white/70">
            Les arrêts dans l'ordre, et à chaque arrêt les enfants à prendre.
          </p>
        </div>
      </div>
      <div className="flex gap-6 sm:gap-8">
        {(['aller', 'retour'] as const).map((sens) => (
          <div key={sens}>
            <p className="text-[0.625rem] font-semibold uppercase tracking-wide text-white/50">{sens}</p>
            <p className="font-display text-xl font-bold tabular-nums">
              {tournee[sens].pris}
              <span className="text-sm font-semibold text-white/60"> / {tournee[sens].effectif}</span>
            </p>
          </div>
        ))}
      </div>
    </Link>
  )
}

function CarteVehicule({
  vehicule,
  moisCourant,
  onEmpechement,
}: {
  vehicule: BilanVehicule
  moisCourant: string
  onEmpechement: () => void
}) {
  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-display text-base font-bold text-navy-900">{vehicule.immatriculation}</p>
          <p className="text-xs text-navy-400">
            {vehicule.marque ?? 'Véhicule'} · {vehicule.effectif} élève{vehicule.effectif > 1 ? 's' : ''} sur{' '}
            {vehicule.capacite} places
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {vehicule.repris_en_remplacement && <Badge tone="blue">Repris d'un collègue</Badge>}
          <Badge tone={vehicule.statut === 'actif' ? 'green' : 'neutral'}>
            {vehicule.statut === 'actif' ? 'En service' : 'Hors service'}
          </Badge>
        </div>
      </div>

      <div className="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <Chiffre label={`Réglé pour ${moisCourant}`} valeur={francs(vehicule.recettes)} />
        <Chiffre label="Dépenses du mois" valeur={francs(vehicule.depenses)} />
        <Chiffre label="Salaire" valeur={francs(vehicule.salaire)} />
        <Chiffre
          label="Résultat"
          valeur={francs(vehicule.resultat)}
          ton={vehicule.resultat < 0 ? 'text-red-600' : 'text-green-600'}
        />
      </div>

      {/* Confier son itinéraire n'a de sens que sur son propre bus : sur un
          véhicule déjà repris à un collègue, le relais appartient au titulaire. */}
      {!vehicule.repris_en_remplacement && (
        <div className="mt-4 flex justify-end">
          <Button variant="ghost" onClick={onEmpechement}>
            <CalendarOff className="h-4 w-4" />
            Je suis empêché
          </Button>
        </div>
      )}
    </Card>
  )
}

function Chiffre({ label, valeur, ton }: { label: string; valeur: string; ton?: string }) {
  return (
    <div>
      <p className="text-[0.625rem] font-semibold uppercase leading-tight tracking-wide text-navy-400">{label}</p>
      <p className={`tabular-nums font-semibold ${ton ?? 'text-navy-900'}`}>{valeur}</p>
    </div>
  )
}

function CarteRelais({ relais, mode }: { relais: RelaisItineraire; mode: 'mien' | 'offert' }) {
  const queryClient = useQueryClient()
  const [enCours, setEnCours] = useState(false)

  const agir = async (action: () => Promise<unknown>, message: string) => {
    setEnCours(true)
    try {
      await action()
      succes(message)
      queryClient.invalidateQueries({ queryKey: CLE })
      queryClient.invalidateQueries({ queryKey: ['chauffeur', 'itineraire'] })
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  const periode = `du ${new Date(relais.du).toLocaleDateString('fr-FR')} au ${new Date(relais.au).toLocaleDateString('fr-FR')}`

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="font-display text-base font-bold text-navy-900">
            {relais.immatriculation ?? 'Véhicule retiré'}
            {relais.marque && <span className="ml-2 text-xs font-normal text-navy-400">{relais.marque}</span>}
          </p>
          <p className="text-xs text-navy-400">{periode}</p>
          {relais.motif && <p className="mt-1 text-sm text-navy-600">{relais.motif}</p>}
          {mode === 'offert' && relais.titulaire && (
            <p className="mt-1 text-xs text-navy-400">
              Chauffeur titulaire : {relais.titulaire.nom_complet}
              {relais.titulaire.telephone && ` · ${relais.titulaire.telephone}`}
            </p>
          )}
          {mode === 'mien' && relais.remplacant && (
            <p className="mt-1 text-xs text-navy-400">
              Repris par {relais.remplacant.nom_complet}
              {relais.remplacant.telephone && ` · ${relais.remplacant.telephone}`}
            </p>
          )}
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={relais.statut === 'pourvu' ? 'green' : relais.statut === 'disponible' ? 'gold' : 'neutral'}>
            {relais.statut === 'pourvu' ? 'Repris' : relais.statut === 'disponible' ? 'Sans preneur' : 'Annulé'}
          </Badge>
          {mode === 'offert' ? (
            <Button
              disabled={enCours}
              onClick={() => agir(() => reprendreItineraire(relais.id), 'Itinéraire repris : il rejoint votre tournée.')}
            >
              Reprendre
            </Button>
          ) : (
            <Button
              variant="ghost"
              disabled={enCours}
              onClick={() => agir(() => annulerMonEmpechement(relais.id), 'Empêchement levé : votre bus vous revient.')}
            >
              Lever l'empêchement
            </Button>
          )}
        </div>
      </div>
    </Card>
  )
}

/** « Je suis empêché » : la période, et le bus repart à un collègue. */
function ModaleEmpechement({ vehicule, onClose }: { vehicule: BilanVehicule; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [du, setDu] = useState(aujourdhui())
  const [au, setAu] = useState(aujourdhui())
  const [motif, setMotif] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const envoyer = async () => {
    setEnvoi(true)
    try {
      await declarerEmpechement({ vehicule_id: vehicule.vehicule_id, du, au, motif: motif.trim() || null })
      succes('Itinéraire rendu disponible : un autre chauffeur peut le reprendre.')
      queryClient.invalidateQueries({ queryKey: CLE })
      onClose()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={`Confier l'itinéraire de ${vehicule.immatriculation}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="text-sm text-navy-500">
          Sur la période choisie, votre itinéraire quitte votre tournée et devient disponible : un collègue pourra le
          reprendre, ou la direction le lui confier.
        </p>
        <div className="grid grid-cols-2 gap-3">
          <Input label="Du" type="date" value={du} onChange={(e) => setDu(e.target.value)} />
          <Input label="Au" type="date" value={au} min={du} onChange={(e) => setAu(e.target.value)} />
        </div>
        <Textarea
          label="Motif (facultatif)"
          placeholder="Maladie, mission, panne…"
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
        />
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose} disabled={envoi}>
            Annuler
          </Button>
          <Button onClick={envoyer} disabled={envoi || !du || !au}>
            Rendre disponible
          </Button>
        </div>
      </div>
    </Modal>
  )
}
