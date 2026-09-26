import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowDownToLine, ArrowLeft, ArrowUpFromLine, Phone, Mail, UserRound } from 'lucide-react'
import { enregistrerMouvementBanque, fetchBanque, fetchPersonnels, type BanqueMouvement } from '@/features/personnel/api'
import { francs } from '@/features/finance/api'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'

type TypeMouvementManuel = 'depot' | 'retrait'

function MouvementBanqueModal({
  banqueId,
  type,
  onClose,
  onSaved,
}: {
  banqueId: number
  type: TypeMouvementManuel
  onClose: () => void
  onSaved: () => void
}) {
  const [montant, setMontant] = useState('')
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10))
  const [libelle, setLibelle] = useState('')
  const [reference, setReference] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const depot = type === 'depot'

  const submit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    const montantNombre = Number(montant)
    if (!Number.isSafeInteger(montantNombre) || montantNombre < 1) {
      erreur('Saisissez un montant entier supérieur à zéro.')
      return
    }

    setSubmitting(true)
    try {
      await enregistrerMouvementBanque(banqueId, type, {
        montant: montantNombre,
        date: date || undefined,
        libelle: libelle.trim() || undefined,
        reference: reference.trim() || undefined,
      })
      succes(depot ? 'Dépôt enregistré.' : 'Retrait enregistré.')
      onSaved()
      onClose()
    } catch (err: any) {
      erreur(err.message || 'Impossible d’enregistrer le mouvement.')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal title={depot ? 'Dépôt bancaire' : 'Retrait bancaire'} onClose={onClose}>
      <form onSubmit={submit} className="space-y-4">
        <Input
          label="Montant (F CFA)"
          type="number"
          min="1"
          step="1"
          required
          autoFocus
          value={montant}
          onChange={(event) => setMontant(event.target.value)}
        />
        <Input label="Date" type="date" value={date} onChange={(event) => setDate(event.target.value)} />
        <Input
          label="Motif"
          placeholder={depot ? 'Ex. Solde initial, versement en caisse' : 'Ex. Retrait pour dépenses'}
          value={libelle}
          onChange={(event) => setLibelle(event.target.value)}
        />
        <Input
          label="Référence (facultatif)"
          value={reference}
          onChange={(event) => setReference(event.target.value)}
        />
        <div className="flex justify-end gap-2 pt-2">
          <Button type="button" variant="secondary" onClick={onClose}>Annuler</Button>
          <Button type="submit" disabled={submitting}>
            {submitting ? 'Enregistrement…' : depot ? 'Enregistrer le dépôt' : 'Enregistrer le retrait'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function libelleMouvement(mouvement: BanqueMouvement): string {
  if (mouvement.type === 'paie') return 'Salaire payé'
  return mouvement.type === 'depot' ? 'Dépôt' : 'Retrait'
}

export function BanqueDetailPage() {
  const { id } = useParams<{ id: string }>()
  const banqueId = Number(id)
  const queryClient = useQueryClient()
  const [typeMouvement, setTypeMouvement] = useState<TypeMouvementManuel | null>(null)

  const { data: banque, isLoading, isError } = useQuery({
    queryKey: ['banque', banqueId],
    queryFn: () => fetchBanque(banqueId),
  })

  const { data: personnels, isLoading: chargementPersonnels } = useQuery({
    queryKey: ['personnels', { banque_id: banqueId }],
    queryFn: () => fetchPersonnels({ banque_id: banqueId, per_page: 500 }),
    enabled: !!banque,
  })

  if (isLoading) return <Spinner />
  if (isError || !banque) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link
          to="/banques"
          className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700"
        >
          <ArrowLeft className="h-4 w-4" />
          Retour
        </Link>
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{banque.nom}</h1>
        <p className="text-sm text-navy-400">{banque.code || '—'}</p>
      </div>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Informations</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-0.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Nom</span>
            <span className="text-sm font-medium text-navy-800">{banque.nom}</span>
          </div>
          <div className="flex flex-col gap-0.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Code / SWIFT</span>
            <span className="text-sm font-medium text-navy-800">{banque.code || '—'}</span>
          </div>
          <div className="flex flex-col gap-0.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Solde disponible</span>
            <span className="text-xl font-bold tabular-nums text-navy-900">{francs(banque.solde)}</span>
          </div>
        </div>
        <div className="mt-5 flex flex-wrap gap-2">
          <Button onClick={() => setTypeMouvement('depot')}>
            <ArrowDownToLine className="h-4 w-4" /> Déposer
          </Button>
          <Button variant="secondary" onClick={() => setTypeMouvement('retrait')}>
            <ArrowUpFromLine className="h-4 w-4" /> Retirer
          </Button>
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Mouvements récents</h2>
        {!banque.mouvements?.length ? (
          <EmptyState label="Aucun mouvement enregistré. Le solde initial peut être saisi avec un dépôt." />
        ) : (
          <div className="flex flex-col divide-y divide-navy-100">
            {banque.mouvements.map((mouvement) => {
              const entree = mouvement.type === 'depot'
              return (
                <div key={mouvement.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                  <div className="flex min-w-0 items-center gap-3">
                    <span className={`flex h-9 w-9 flex-none items-center justify-center rounded-full ${entree ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500'}`}>
                      {entree ? <ArrowDownToLine className="h-4 w-4" /> : <ArrowUpFromLine className="h-4 w-4" />}
                    </span>
                    <div className="min-w-0">
                      <p className="truncate text-sm font-semibold text-navy-800">{mouvement.libelle || libelleMouvement(mouvement)}</p>
                      <p className="text-xs text-navy-400">
                        {libelleMouvement(mouvement)} · {new Date(mouvement.date).toLocaleDateString('fr-FR')}
                        {mouvement.reference ? ` · ${mouvement.reference}` : ''}
                      </p>
                    </div>
                  </div>
                  <span className={`font-semibold tabular-nums ${entree ? 'text-green-600' : 'text-red-500'}`}>
                    {entree ? '+' : '−'} {francs(mouvement.montant)}
                  </span>
                </div>
              )
            })}
          </div>
        )}
      </Card>

      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          Personnel domicilié
          <Badge tone="blue">{personnels?.length ?? banque.personnels_count ?? 0}</Badge>
        </h2>

        {chargementPersonnels ? (
          <Spinner />
        ) : !personnels || personnels.length === 0 ? (
          <EmptyState label="Aucun agent n'est domicilié dans cette banque pour le moment." />
        ) : (
          <div className="flex flex-col divide-y divide-navy-100">
            {personnels.map((p) => (
              <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-navy-50 ring-1 ring-navy-100">
                    <UserRound className="h-4 w-4 text-navy-400" />
                  </span>
                  <div>
                    <p className="text-sm font-semibold text-navy-800">{p.nom_complet}</p>
                    {p.numero_compte && <p className="text-xs text-navy-400">N° compte : {p.numero_compte}</p>}
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-navy-500">
                  {p.telephone && (
                    <span className="flex items-center gap-1">
                      <Phone className="h-3.5 w-3.5" />
                      {p.telephone}
                    </span>
                  )}
                  {p.email && (
                    <span className="flex items-center gap-1">
                      <Mail className="h-3.5 w-3.5" />
                      {p.email}
                    </span>
                  )}
                  <Badge tone={p.statut === 'actif' ? 'green' : 'neutral'}>{p.statut === 'actif' ? 'Actif' : 'Ex-employé'}</Badge>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>

      {typeMouvement && (
        <MouvementBanqueModal
          banqueId={banqueId}
          type={typeMouvement}
          onClose={() => setTypeMouvement(null)}
          onSaved={() => {
            queryClient.invalidateQueries({ queryKey: ['banque', banqueId] })
            queryClient.invalidateQueries({ queryKey: ['banques'] })
          }}
        />
      )}
    </div>
  )
}
