import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarOff, Plus } from 'lucide-react'
import {
  annulerRelais,
  attribuerRelais,
  fetchRelaisFlotte,
  ouvrirRelais,
  type RelaisItineraire,
} from '@/features/chauffeur/api'
import { fetchVehicules, type BusVehicule } from '@/features/bus/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Modal } from '@/shared/ui/Modal'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Tabs } from '@/shared/ui/Tabs'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const CLE = ['bus', 'remplacements'] as const

function aujourdhui(): string {
  return new Date().toISOString().slice(0, 10)
}

const LIBELLE_STATUT: Record<RelaisItineraire['statut'], string> = {
  disponible: 'Sans preneur',
  pourvu: 'Repris',
  annule: 'Annulé',
}

const TON_STATUT: Record<RelaisItineraire['statut'], 'gold' | 'green' | 'neutral'> = {
  disponible: 'gold',
  pourvu: 'green',
  annule: 'neutral',
}

/**
 * Relais d'itinéraire vus par la direction : quel bus est sans chauffeur, qui
 * le reprend, et ce qui attend encore un preneur.
 *
 * Un chauffeur empêché ouvre lui-même son relais depuis « Ma tournée » ; cette
 * page sert les cas où il ne peut pas le faire — injoignable, hospitalisé,
 * sans téléphone — et permet alors de désigner le remplaçant directement.
 */
export function BusRemplacementsPage() {
  const [filtre, setFiltre] = useState<'tous' | RelaisItineraire['statut']>('tous')
  const [ouverture, setOuverture] = useState(false)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: [...CLE, filtre],
    queryFn: () => fetchRelaisFlotte(filtre === 'tous' ? undefined : filtre),
  })

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Relais de chauffeurs"
        sousTitre="Confier l'itinéraire d'un chauffeur empêché à un autre, et suivre les reprises en cours."
        icon={CalendarOff}
        actions={
          <Button onClick={() => setOuverture(true)}>
            <Plus className="h-4 w-4" />
            Confier un itinéraire
          </Button>
        }
      />

      <Tabs
        tabs={[
          { key: 'tous', label: 'Tous' },
          { key: 'disponible', label: 'Sans preneur' },
          { key: 'pourvu', label: 'Repris' },
          { key: 'annule', label: 'Annulés' },
        ]}
        active={filtre}
        onChange={(key) => setFiltre(key as typeof filtre)}
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : data.length === 0 ? (
        <Card>
          <EmptyState label="Aucun relais enregistré pour ce filtre." />
        </Card>
      ) : (
        data.map((relais) => <CarteRelais key={relais.id} relais={relais} />)
      )}

      {ouverture && <ModaleOuverture onClose={() => setOuverture(false)} />}
    </div>
  )
}

function CarteRelais({ relais }: { relais: RelaisItineraire }) {
  const queryClient = useQueryClient()
  const [enCours, setEnCours] = useState(false)
  const [attribution, setAttribution] = useState(false)

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: CLE })

  const annuler = async () => {
    setEnCours(true)
    try {
      await annulerRelais(relais.id)
      succes('Relais annulé : le bus revient à son chauffeur titulaire.')
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="font-display text-base font-bold text-navy-900">
            {relais.immatriculation ?? 'Véhicule retiré'}
            {relais.marque && <span className="ml-2 text-xs font-normal text-navy-400">{relais.marque}</span>}
          </p>
          <p className="text-xs text-navy-400">
            du {new Date(relais.du).toLocaleDateString('fr-FR')} au {new Date(relais.au).toLocaleDateString('fr-FR')}
          </p>
          {relais.motif && <p className="mt-1 text-sm text-navy-600">{relais.motif}</p>}
          <dl className="mt-2 grid gap-1 text-xs text-navy-500 sm:grid-cols-2">
            <div>
              <dt className="inline font-semibold text-navy-400">Titulaire : </dt>
              <dd className="inline">{relais.titulaire?.nom_complet ?? 'non renseigné'}</dd>
            </div>
            <div>
              <dt className="inline font-semibold text-navy-400">Remplaçant : </dt>
              <dd className="inline">{relais.remplacant?.nom_complet ?? 'en attente'}</dd>
            </div>
          </dl>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Badge tone={TON_STATUT[relais.statut]}>{LIBELLE_STATUT[relais.statut]}</Badge>
          {relais.statut === 'disponible' && (
            <Button disabled={enCours} onClick={() => setAttribution(true)}>
              Désigner un chauffeur
            </Button>
          )}
          {relais.statut !== 'annule' && (
            <Button variant="ghost" disabled={enCours} onClick={annuler}>
              Annuler
            </Button>
          )}
        </div>
      </div>

      {attribution && (
        <ModaleAttribution
          relais={relais}
          onClose={() => setAttribution(false)}
          onFait={() => {
            setAttribution(false)
            rafraichir()
          }}
        />
      )}
    </Card>
  )
}

/**
 * Les chauffeurs de l'établissement — filtrés sur la fonction du référentiel
 * et non sur un privilège : c'est la fonction qui fait le métier (cf.
 * User::estChauffeur côté API).
 */
function useChauffeurs() {
  return useQuery({
    queryKey: ['personnels', 'chauffeurs'],
    queryFn: () => fetchPersonnels({ fonction_label: 'Chauffeur', statut: 'actif', per_page: 200 }),
  })
}

function ModaleOuverture({ onClose }: { onClose: () => void }) {
  const queryClient = useQueryClient()
  const vehicules = useQuery({ queryKey: ['bus', 'vehicules'], queryFn: fetchVehicules })
  const chauffeurs = useChauffeurs()

  const [vehiculeId, setVehiculeId] = useState('')
  const [du, setDu] = useState(aujourdhui())
  const [au, setAu] = useState(aujourdhui())
  const [motif, setMotif] = useState('')
  const [remplacantId, setRemplacantId] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const envoyer = async () => {
    setEnvoi(true)
    try {
      await ouvrirRelais({
        vehicule_id: Number(vehiculeId),
        du,
        au,
        motif: motif.trim() || null,
        chauffeur_remplacant_id: remplacantId ? Number(remplacantId) : null,
      })
      succes(
        remplacantId
          ? 'Itinéraire confié au chauffeur désigné.'
          : 'Itinéraire rendu disponible aux autres chauffeurs.',
      )
      queryClient.invalidateQueries({ queryKey: CLE })
      onClose()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title="Confier un itinéraire" onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="text-sm text-navy-500">
          Laissez le remplaçant vide pour simplement rendre l'itinéraire disponible : le premier chauffeur à le
          reprendre depuis son accueil l'emporte.
        </p>

        <Select label="Bus" value={vehiculeId} onChange={(e) => setVehiculeId(e.target.value)}>
          <option value="">Choisir un bus…</option>
          {(vehicules.data ?? []).map((vehicule: BusVehicule) => (
            <option key={vehicule.id} value={vehicule.id}>
              {vehicule.immatriculation}
              {vehicule.chauffeur ? ` — ${vehicule.chauffeur.nom_complet}` : ' — sans chauffeur'}
            </option>
          ))}
        </Select>

        <div className="grid grid-cols-2 gap-3">
          <Input label="Du" type="date" value={du} onChange={(e) => setDu(e.target.value)} />
          <Input label="Au" type="date" value={au} min={du} onChange={(e) => setAu(e.target.value)} />
        </div>

        <Select
          label="Remplaçant (facultatif)"
          value={remplacantId}
          onChange={(e) => setRemplacantId(e.target.value)}
        >
          <option value="">Laisser disponible</option>
          {(chauffeurs.data ?? []).map((agent) => (
            <option key={agent.id} value={agent.id}>
              {agent.nom_complet}
            </option>
          ))}
        </Select>

        <Textarea
          label="Motif (facultatif)"
          placeholder="Chauffeur injoignable, congé maladie…"
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
        />

        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose} disabled={envoi}>
            Annuler
          </Button>
          <Button onClick={envoyer} disabled={envoi || !vehiculeId || !du || !au}>
            Enregistrer
          </Button>
        </div>
      </div>
    </Modal>
  )
}

function ModaleAttribution({
  relais,
  onClose,
  onFait,
}: {
  relais: RelaisItineraire
  onClose: () => void
  onFait: () => void
}) {
  const chauffeurs = useChauffeurs()
  const [remplacantId, setRemplacantId] = useState('')
  const [envoi, setEnvoi] = useState(false)

  const envoyer = async () => {
    setEnvoi(true)
    try {
      await attribuerRelais(relais.id, Number(remplacantId))
      succes('Itinéraire confié au chauffeur désigné.')
      onFait()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={`Désigner un chauffeur pour ${relais.immatriculation ?? 'ce bus'}`} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Select label="Chauffeur" value={remplacantId} onChange={(e) => setRemplacantId(e.target.value)}>
          <option value="">Choisir un chauffeur…</option>
          {(chauffeurs.data ?? [])
            // Le titulaire ne peut pas se remplacer lui-même : l'API le refuse,
            // autant ne pas le proposer.
            .filter((agent) => agent.id !== relais.titulaire?.id)
            .map((agent) => (
              <option key={agent.id} value={agent.id}>
                {agent.nom_complet}
              </option>
            ))}
        </Select>

        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose} disabled={envoi}>
            Annuler
          </Button>
          <Button onClick={envoyer} disabled={envoi || !remplacantId}>
            Confier
          </Button>
        </div>
      </div>
    </Modal>
  )
}
