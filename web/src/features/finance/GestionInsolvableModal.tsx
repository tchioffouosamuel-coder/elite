import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2, Plus, CalendarClock, Percent, History } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import {
  fetchMoratoires,
  creerMoratoire,
  supprimerMoratoire,
  fetchRemises,
  creerRemise,
  supprimerRemise,
  fetchDettesAnterieures,
  creerDetteAnterieure,
  supprimerDetteAnterieure,
  francs,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Gestion en un seul endroit de ce qui peut expliquer — ou corriger — la
 * situation d'un élève insolvable : moratoire accordé, remise individuelle,
 * dette héritée d'avant le système. Regrouper les trois évite trois écrans
 * distincts pour une même décision prise au même moment, au comptoir.
 */
export function GestionInsolvableModal({
  eleveId,
  eleveNom,
  onClose,
  onChange,
}: {
  eleveId: number
  eleveNom: string
  onClose: () => void
  onChange: () => void
}) {
  return (
    <Modal title={`Situation — ${eleveNom}`} onClose={onClose}>
      <div className="flex flex-col gap-6">
        <SectionMoratoires eleveId={eleveId} onChange={onChange} />
        <SectionRemises eleveId={eleveId} onChange={onChange} />
        <SectionDettes eleveId={eleveId} onChange={onChange} />
      </div>
    </Modal>
  )
}

function SectionMoratoires({ eleveId, onChange }: { eleveId: number; onChange: () => void }) {
  const queryClient = useQueryClient()
  const [ouvert, setOuvert] = useState(false)
  const [dateDelivrance, setDateDelivrance] = useState(new Date().toISOString().slice(0, 10))
  const [dateExpiration, setDateExpiration] = useState('')
  const [motif, setMotif] = useState('')

  const { data, isLoading } = useQuery({ queryKey: ['moratoires', eleveId], queryFn: () => fetchMoratoires(eleveId) })
  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['moratoires', eleveId] })
    onChange()
  }

  const ajouter = async () => {
    if (!dateExpiration) return
    try {
      await creerMoratoire(eleveId, { date_delivrance: dateDelivrance, date_expiration: dateExpiration, motif: motif || undefined })
      succes('Moratoire accordé.')
      setOuvert(false)
      setMotif('')
      setDateExpiration('')
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const retirer = async (id: number) => {
    const ok = await confirmer({ titre: 'Retirer ce moratoire ?', message: 'Il ne protégera plus l\'élève de la liste des insolvables.', action: 'Retirer' })
    if (!ok) return
    await supprimerMoratoire(id)
    succes('Moratoire retiré.')
    rafraichir()
  }

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <h3 className="flex items-center gap-1.5 text-sm font-bold text-navy-900">
          <CalendarClock className="h-4 w-4 text-navy-500" />
          Moratoires
        </h3>
        <Button size="sm" variant="secondary" onClick={() => setOuvert((v) => !v)}>
          <Plus className="h-3.5 w-3.5" />
          Accorder
        </Button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : (
        <ul className="flex flex-col divide-y divide-navy-50 text-sm">
          {data?.length === 0 && <li className="py-2 text-navy-400">Aucun moratoire.</li>}
          {data?.map((m) => (
            <li key={m.id} className="flex items-center justify-between gap-2 py-2">
              <div>
                <span className={m.valide ? 'font-semibold text-green-600' : 'text-navy-400 line-through'}>
                  Jusqu'au {new Date(m.date_expiration).toLocaleDateString('fr-FR')}
                </span>
                {m.motif && <span className="ml-2 text-xs text-navy-400">{m.motif}</span>}
              </div>
              <button onClick={() => retirer(m.id)} className="rounded-lg p-1 text-navy-300 hover:bg-cream-100 hover:text-red-500">
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </li>
          ))}
        </ul>
      )}

      {ouvert && (
        <div className="mt-2 flex flex-col gap-2 rounded-xl bg-cream-100 p-3">
          <div className="grid grid-cols-2 gap-2">
            <Input label="Délivré le" type="date" value={dateDelivrance} onChange={(e) => setDateDelivrance(e.target.value)} />
            <Input label="Expire le" type="date" value={dateExpiration} onChange={(e) => setDateExpiration(e.target.value)} />
          </div>
          <Input label="Motif (optionnel)" value={motif} onChange={(e) => setMotif(e.target.value)} />
          <Button size="sm" onClick={ajouter} disabled={!dateExpiration}>
            Enregistrer
          </Button>
        </div>
      )}
    </div>
  )
}

function SectionRemises({ eleveId, onChange }: { eleveId: number; onChange: () => void }) {
  const queryClient = useQueryClient()
  const [ouvert, setOuvert] = useState(false)
  const [montant, setMontant] = useState<number | null>(null)
  const [motif, setMotif] = useState('')

  const { data, isLoading } = useQuery({ queryKey: ['remises', eleveId], queryFn: () => fetchRemises(eleveId) })
  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['remises', eleveId] })
    queryClient.invalidateQueries({ queryKey: ['finance-insolvables'] })
    queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] })
    onChange()
  }

  const ajouter = async () => {
    if (!montant) return
    try {
      await creerRemise(eleveId, { montant, motif: motif || undefined })
      succes('Remise accordée.')
      setOuvert(false)
      setMotif('')
      setMontant(null)
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const retirer = async (id: number) => {
    const ok = await confirmer({ titre: 'Retirer cette remise ?', message: 'Le montant dû sera recalculé sans elle.', action: 'Retirer' })
    if (!ok) return
    await supprimerRemise(id)
    succes('Remise retirée.')
    rafraichir()
  }

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <h3 className="flex items-center gap-1.5 text-sm font-bold text-navy-900">
          <Percent className="h-4 w-4 text-navy-500" />
          Remises individuelles
        </h3>
        <Button size="sm" variant="secondary" onClick={() => setOuvert((v) => !v)}>
          <Plus className="h-3.5 w-3.5" />
          Accorder
        </Button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : (
        <ul className="flex flex-col divide-y divide-navy-50 text-sm">
          {data?.length === 0 && <li className="py-2 text-navy-400">Aucune remise.</li>}
          {data?.map((r) => (
            <li key={r.id} className="flex items-center justify-between gap-2 py-2">
              <div>
                <span className="font-semibold text-navy-800">{francs(r.montant)}</span>
                <span className="ml-2 text-xs text-navy-400">{r.annee_scolaire}{r.motif ? ` · ${r.motif}` : ''}</span>
              </div>
              <button onClick={() => retirer(r.id)} className="rounded-lg p-1 text-navy-300 hover:bg-cream-100 hover:text-red-500">
                <Trash2 className="h-3.5 w-3.5" />
              </button>
            </li>
          ))}
        </ul>
      )}

      {ouvert && (
        <div className="mt-2 flex flex-col gap-2 rounded-xl bg-cream-100 p-3">
          <MontantInput label="Montant de la remise (F CFA)" value={montant} onChange={setMontant} />
          <Input label="Motif (optionnel)" value={motif} onChange={(e) => setMotif(e.target.value)} />
          <Button size="sm" onClick={ajouter} disabled={!montant}>
            Enregistrer
          </Button>
        </div>
      )}
    </div>
  )
}

function SectionDettes({ eleveId, onChange }: { eleveId: number; onChange: () => void }) {
  const queryClient = useQueryClient()
  const [ouvert, setOuvert] = useState(false)
  const [montant, setMontant] = useState<number | null>(null)
  const [motif, setMotif] = useState('')

  const { data, isLoading } = useQuery({ queryKey: ['dettes-anterieures', eleveId], queryFn: () => fetchDettesAnterieures(eleveId) })
  const rafraichir = () => {
    queryClient.invalidateQueries({ queryKey: ['dettes-anterieures', eleveId] })
    queryClient.invalidateQueries({ queryKey: ['finance-insolvables'] })
    queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] })
    onChange()
  }

  const ajouter = async () => {
    if (!montant) return
    try {
      await creerDetteAnterieure(eleveId, { montant, motif: motif || undefined })
      succes('Dette antérieure enregistrée.')
      setOuvert(false)
      setMotif('')
      setMontant(null)
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  const retirer = async (id: number) => {
    try {
      await supprimerDetteAnterieure(id)
      succes('Dette retirée.')
      rafraichir()
    } catch (e) {
      erreur((e as ApiError).message)
    }
  }

  return (
    <div>
      <div className="mb-2 flex items-center justify-between">
        <h3 className="flex items-center gap-1.5 text-sm font-bold text-navy-900">
          <History className="h-4 w-4 text-navy-500" />
          Dettes des années antérieures
        </h3>
        <Button size="sm" variant="secondary" onClick={() => setOuvert((v) => !v)}>
          <Plus className="h-3.5 w-3.5" />
          Ajouter
        </Button>
      </div>
      <p className="mb-2 text-xs text-navy-400">
        Une dette héritée d'avant l'usage de ce système. Elle s'ajoute automatiquement au dossier de l'élève dès qu'il en a un pour l'année active.
      </p>

      {isLoading ? (
        <Spinner />
      ) : (
        <ul className="flex flex-col divide-y divide-navy-50 text-sm">
          {data?.length === 0 && <li className="py-2 text-navy-400">Aucune dette antérieure.</li>}
          {data?.map((d) => (
            <li key={d.id} className="flex items-center justify-between gap-2 py-2">
              <div>
                <span className="font-semibold text-navy-800">{francs(d.montant)}</span>
                <span className="ml-2 text-xs text-navy-400">
                  {d.imputee ? 'Imputée au dossier' : 'En attente d\'un dossier'}
                  {d.motif ? ` · ${d.motif}` : ''}
                </span>
              </div>
              {!d.imputee && (
                <button onClick={() => retirer(d.id)} className="rounded-lg p-1 text-navy-300 hover:bg-cream-100 hover:text-red-500">
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              )}
            </li>
          ))}
        </ul>
      )}

      {ouvert && (
        <div className="mt-2 flex flex-col gap-2 rounded-xl bg-cream-100 p-3">
          <MontantInput label="Montant de la dette (F CFA)" value={montant} onChange={setMontant} />
          <Input label="Motif (optionnel)" value={motif} onChange={(e) => setMotif(e.target.value)} />
          <Button size="sm" onClick={ajouter} disabled={!montant}>
            Enregistrer
          </Button>
        </div>
      )}
    </div>
  )
}
