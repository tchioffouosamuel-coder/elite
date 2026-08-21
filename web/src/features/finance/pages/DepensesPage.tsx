import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ReceiptText, Plus, Ban, Paperclip, CheckCircle2, Wallet, TrendingDown, FileText } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card, StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input, Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { annulerDepense, fetchDepenses, francs, payerDepense, type Depense } from '@/features/finance/api'
import { DepenseFormModal } from '@/features/finance/pages/DepenseFormModal'
import type { ApiError } from '@/shared/types/api'

const TONS = { payee: 'green', engagee: 'gold', annulee: 'red' } as const
const LIBELLES = { payee: 'Payée', engagee: 'Engagée', annulee: 'Annulée' } as const
const SOURCES = { caisse: 'Caisse', revenu_personnel: 'Revenu personnel' } as const

/**
 * Suivi des dépenses.
 *
 * La ventilation par compte est affichée à côté de la liste : c'est elle qui
 * répond à « où part l'argent », alors que la liste répond à « qu'a-t-on payé
 * ce mois-ci ». Les deux questions se posent en même temps.
 */
export function DepensesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const queryClient = useQueryClient()

  const [du, setDu] = useState('')
  const [au, setAu] = useState('')
  const [statut, setStatut] = useState('')
  const [formOuvert, setFormOuvert] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['depenses', activeSchoolId, du, au, statut],
    queryFn: () => fetchDepenses({ du: du || null, au: au || null, statut: statut || null }),
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['depenses'] })

  const agir = async (action: () => Promise<void>, message: string) => {
    try {
      await action()
      succes(message)
      rafraichir()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  const annuler = async (depense: Depense) => {
    const ok = await confirmer({
      titre: 'Annuler cette dépense ?',
      message: `${depense.libelle} — ${francs(depense.montant)}. La dépense reste au registre et son écriture est contrepassée.`,
      action: 'Annuler la dépense',
    })
    if (ok) await agir(() => annulerDepense(depense.id, 'Annulation depuis le suivi'), 'Dépense annulée.')
  }

  const colonnes: Colonne<Depense>[] = [
    {
      cle: 'date',
      entete: 'Date',
      valeur: (d) => d.date_depense,
      cellule: (d) => <span className="tabular-nums text-xs">{d.date_depense?.split('-').reverse().join('/')}</span>,
    },
    {
      cle: 'libelle',
      entete: 'Libellé',
      valeur: (d) => `${d.libelle} ${d.beneficiaire ?? ''} ${d.reference_facture ?? ''}`,
      cellule: (d) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{d.libelle}</div>
          <div className="text-xs text-navy-400">
            {d.beneficiaire ?? '—'}
            {d.reference_facture ? ` · ${d.reference_facture}` : ''}
          </div>
        </div>
      ),
    },
    {
      cle: 'compte',
      entete: 'Compte',
      valeur: (d) => d.compte?.code ?? '',
      cellule: (d) =>
        d.compte ? (
          <span className="text-xs">
            <span className="font-semibold">{d.compte.code}</span> · {d.compte.libelle}
          </span>
        ) : (
          <span className="text-xs text-navy-300">Non imputé</span>
        ),
      masquerMobile: true,
    },
    {
      cle: 'source',
      entete: 'Source',
      valeur: (d) => d.source,
      cellule: (d) => <span className="text-xs">{SOURCES[d.source]}</span>,
      masquerMobile: true,
    },
    {
      cle: 'montant',
      entete: 'Montant',
      valeur: (d) => d.montant,
      cellule: (d) => (
        <span className={d.statut === 'annulee' ? 'tabular-nums text-navy-300 line-through' : 'font-semibold tabular-nums'}>
          {francs(d.montant)}
        </span>
      ),
    },
    {
      cle: 'statut',
      entete: 'Statut',
      valeur: (d) => d.statut,
      cellule: (d) => <Badge tone={TONS[d.statut]}>{LIBELLES[d.statut]}</Badge>,
    },
    {
      cle: 'actions',
      entete: '',
      cellule: (d) => (
        <div className="flex justify-end gap-1.5">
          {d.justificatif_url && (
            <Button
              size="sm"
              variant="secondary"
              title="Voir le justificatif"
              onClick={() => window.open(d.justificatif_url!, '_blank')}
            >
              <Paperclip className="h-3.5 w-3.5" />
            </Button>
          )}
          {d.statut === 'engagee' && can('finance.depenses') && (
            <Button size="sm" title="Marquer payée" onClick={() => agir(() => payerDepense(d.id), 'Règlement enregistré.')}>
              <CheckCircle2 className="h-3.5 w-3.5" />
            </Button>
          )}
          {d.statut !== 'annulee' && can('finance.depenses') && (
            <Button size="sm" variant="danger" title="Annuler" onClick={() => annuler(d)}>
              <Ban className="h-3.5 w-3.5" />
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Dépenses"
        sousTitre="Saisie, justificatifs et ventilation par poste comptable."
        icon={ReceiptText}
        actions={
          <>
            {can('finance.rapports') && (
              <Button
                variant="secondary"
                onClick={() => ouvrirDocument('/depenses/bilan/pdf', { du: du || undefined, au: au || undefined })}
              >
                <FileText className="h-4 w-4" />
                Bilan PDF
              </Button>
            )}
            {can('finance.depenses') && (
              <Button onClick={() => setFormOuvert(true)}>
                <Plus className="h-4 w-4" />
                Nouvelle dépense
              </Button>
            )}
          </>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-3">
            <StatCard label="Total de la période" value={francs(data.totaux.total)} icon={TrendingDown} accent="red" />
            <StatCard label="Payé" value={francs(data.totaux.paye)} icon={Wallet} accent="navy" />
            <StatCard
              label="Engagé, non réglé"
              value={francs(data.totaux.engage)}
              icon={CheckCircle2}
              accent="gold"
              hint={`${data.totaux.nombre} dépense(s)`}
            />
          </div>

          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <DataTable
              colonnes={colonnes}
              lignes={data.depenses}
              cleLigne={(d) => d.id}
              placeholderRecherche={t('finance.search_depense')}
              messageVide={t('finance.empty_depense')}
              largeurMin={860}
              outils={
                <div className="flex flex-wrap items-end gap-2">
                  <Input label="Du" type="date" value={du} onChange={(e) => setDu(e.target.value)} />
                  <Input label="Au" type="date" value={au} onChange={(e) => setAu(e.target.value)} />
                  <Select value={statut} onChange={(e) => setStatut(e.target.value)}>
                    <option value="">Tous statuts</option>
                    <option value="payee">Payées</option>
                    <option value="engagee">Engagées</option>
                    <option value="annulee">Annulées</option>
                  </Select>
                </div>
              }
            />

            <Card className="h-fit">
              <h2 className="mb-3 border-b border-navy-100/70 pb-2 font-display text-sm font-bold text-navy-900">
                Ventilation par poste
              </h2>
              {data.par_compte.length === 0 ? (
                <p className="text-sm text-navy-400">Rien à ventiler.</p>
              ) : (
                <ul className="flex flex-col gap-2.5">
                  {data.par_compte.map((poste) => {
                    const part = data.totaux.total > 0 ? (poste.montant * 100) / data.totaux.total : 0

                    return (
                      <li key={poste.code}>
                        <div className="flex items-baseline justify-between gap-2 text-sm">
                          <span className="min-w-0 truncate text-navy-700">
                            <span className="font-semibold">{poste.code}</span> {poste.libelle}
                          </span>
                          <span className="flex-none tabular-nums font-semibold">{francs(poste.montant)}</span>
                        </div>
                        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-cream-100">
                          <div className="h-full rounded-full bg-navy-500" style={{ width: `${part}%` }} />
                        </div>
                      </li>
                    )
                  })}
                </ul>
              )}
            </Card>
          </div>
        </>
      )}

      {formOuvert && (
        <DepenseFormModal
          onClose={() => setFormOuvert(false)}
          onEnregistre={() => {
            setFormOuvert(false)
            rafraichir()
          }}
        />
      )}
    </div>
  )
}
