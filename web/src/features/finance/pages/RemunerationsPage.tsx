import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Banknote, Pencil, Users, AlertTriangle, TrendingUp, History, Copy } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchRemunerations, francs, type LigneRemuneration } from '@/features/finance/api'
import { RemunerationModal } from '@/features/finance/pages/RemunerationModal'
import { HistoriqueRemunerationModal } from '@/features/finance/pages/HistoriqueRemunerationModal'
import { CopierRemunerationModal } from '@/features/finance/pages/CopierRemunerationModal'

/**
 * Salaires du personnel.
 *
 * C'est ici que se fixe ce que gagne chaque agent ; la page « Paie » ne fait
 * qu'appliquer ces montants au mois. Un agent sans rémunération est signalé en
 * tête plutôt que noyé dans la liste : c'est lui qui bloquera la préparation
 * de la paie, et l'oubli ne se découvre autrement qu'au moment de payer.
 */
export function RemunerationsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const queryClient = useQueryClient()

  const [enEdition, setEnEdition] = useState<LigneRemuneration | null>(null)
  const [historiquePour, setHistoriquePour] = useState<LigneRemuneration | null>(null)
  const [copieOuverte, setCopieOuverte] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  const { data, isLoading, isError } = useQuery({
    queryKey: ['remunerations', activeSchoolId],
    queryFn: fetchRemunerations,
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['remunerations'] })

  const handleToggleSelect = (id: number) => {
    const newSelected = new Set(selectedIds)
    if (newSelected.has(id)) {
      newSelected.delete(id)
    } else {
      newSelected.add(id)
    }
    setSelectedIds(newSelected)
  }

  const handleSelectAll = (personnels: LigneRemuneration[]) => {
    if (selectedIds.size === personnels.length && personnels.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(personnels.map((p) => p.id)))
    }
  }

  const colonnes: Colonne<LigneRemuneration>[] = [
    ...(can('finance.paie')
      ? [
          {
            cle: 'selection',
            sticky: 'left',
            largeur: '44px',
            entete: data?.personnels ? (
              <input
                type="checkbox"
                checked={selectedIds.size === data.personnels.length && data.personnels.length > 0}
                onChange={() => handleSelectAll(data?.personnels ?? [])}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
            ) : null,
            cellule: (p: LigneRemuneration) => (
              <input
                type="checkbox"
                checked={selectedIds.has(p.id)}
                onChange={() => handleToggleSelect(p.id)}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
            ),
          } satisfies Colonne<LigneRemuneration>,
        ]
      : []),
    {
      cle: 'agent',
      entete: 'Agent',
      largeur: '210px',
      valeur: (p) => `${p.nom_complet} ${p.matricule ?? ''} ${p.fonction ?? ''}`,
      cellule: (p) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{p.nom_complet}</div>
          <div className="truncate text-xs text-navy-400">
            {p.matricule ?? '—'} · {p.fonction ?? 'Sans fonction'}
          </div>
        </div>
      ),
    },
    {
      cle: 'base',
      entete: 'Salaire de base',
      largeur: '120px',
      valeur: (p) => p.remuneration?.salaire_base ?? -1,
      cellule: (p) =>
        p.remuneration ? (
          <span className="tabular-nums">{francs(p.remuneration.salaire_base)}</span>
        ) : (
          <Badge tone="red">Non défini</Badge>
        ),
    },
    {
      cle: 'brut',
      entete: 'Brut mensuel',
      largeur: '100px',
      valeur: (p) => p.remuneration?.brut ?? -1,
      cellule: (p) => (
        <span className="font-semibold tabular-nums">{p.remuneration ? francs(p.remuneration.brut) : '—'}</span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'net',
      entete: 'Net estimé',
      largeur: '100px',
      valeur: (p) => p.remuneration?.net ?? -1,
      cellule: (p) => (
        <span className="tabular-nums text-green-600">{p.remuneration ? francs(p.remuneration.net) : '—'}</span>
      ),
    },
    {
      cle: 'cout',
      entete: 'Coût employeur',
      largeur: '120px',
      valeur: (p) => p.remuneration?.cout_employeur ?? -1,
      cellule: (p) => (
        <span className="tabular-nums text-navy-500">
          {p.remuneration ? francs(p.remuneration.cout_employeur) : '—'}
        </span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'effet',
      entete: 'Depuis le',
      largeur: '90px',
      valeur: (p) => p.remuneration?.date_effet ?? '',
      cellule: (p) => (
        <span className="text-xs tabular-nums text-navy-400">
          {p.remuneration?.date_effet?.split('-').reverse().join('/') ?? '—'}
        </span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: '',
      sticky: 'right',
      largeur: '160px',
      cellule: (p) => (
        <div className="flex justify-end gap-1.5">
          {p.remuneration && (
            <Button
              size="sm"
              variant="secondary"
              title="Historique des rémunérations"
              onClick={() => setHistoriquePour(p)}
            >
              <History className="h-3.5 w-3.5" />
            </Button>
          )}
          {can('finance.paie') && (
            <Button size="sm" onClick={() => setEnEdition(p)}>
              <Pencil className="h-3.5 w-3.5" />
              {p.remuneration ? 'Réviser' : 'Définir'}
            </Button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Salaires du personnel"
        sousTitre="Rémunération contractuelle de chaque agent — la paie mensuelle s'appuie dessus."
        icon={Banknote}
      />

      {selectedIds.size > 0 && can('finance.paie') && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{selectedIds.size} agent(s) sélectionné(s)</p>
            <div className="flex flex-wrap gap-2">
              <Button variant="secondary" onClick={() => setCopieOuverte(true)}>
                <Copy className="h-4 w-4" />
                Copier une rémunération
              </Button>
              <button
                onClick={() => setSelectedIds(new Set())}
                className="rounded-lg px-4 py-2 text-sm font-medium text-navy-600 hover:bg-navy-50 whitespace-nowrap"
              >
                Annuler
              </button>
            </div>
          </div>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
              label="Effectif"
              value={data.totaux.effectif}
              icon={Users}
              accent="navy"
              hint={`${data.totaux.definies} rémunération(s) définie(s)`}
            />
            <StatCard
              label="Sans rémunération"
              value={data.totaux.sans_remuneration}
              icon={AlertTriangle}
              accent={data.totaux.sans_remuneration > 0 ? 'red' : 'green'}
              hint="bloquent la préparation de la paie"
            />
            <StatCard label="Masse brute mensuelle" value={francs(data.totaux.masse_brute)} icon={Banknote} accent="gold" />
            <StatCard
              label="Coût employeur"
              value={francs(data.totaux.cout_employeur)}
              icon={TrendingUp}
              accent="red"
              hint="charges patronales comprises"
            />
          </div>

          {data.totaux.sans_remuneration > 0 && (
            <p className="flex items-start gap-2 rounded-xl bg-gold-50 px-3.5 py-2.5 text-sm text-gold-800 ring-1 ring-gold-100">
              <AlertTriangle className="mt-0.5 h-4 w-4 flex-none" />
              <span>
                <strong>{data.totaux.sans_remuneration} agent(s)</strong> n'ont pas de rémunération définie. Ils seront
                écartés de la préparation de la paie tant que leur salaire n'est pas fixé.
              </span>
            </p>
          )}

          <DataTable
            colonnes={colonnes}
            lignes={data.personnels}
            cleLigne={(p) => p.id}
            placeholderRecherche={t('finance.search_remuneration')}
            messageVide={t('finance.empty_remuneration')}
            largeurMin={600}
          />
        </>
      )}

      {enEdition && (
        <RemunerationModal
          personnel={enEdition}
          onClose={() => setEnEdition(null)}
          onEnregistre={() => {
            setEnEdition(null)
            rafraichir()
          }}
        />
      )}

      {historiquePour && (
        <HistoriqueRemunerationModal personnel={historiquePour} onClose={() => setHistoriquePour(null)} />
      )}

      {copieOuverte && data && (
        <CopierRemunerationModal
          cibles={data.personnels.filter((p) => selectedIds.has(p.id))}
          modeles={data.personnels.filter((p) => p.remuneration !== null)}
          onClose={() => setCopieOuverte(false)}
          onApplique={() => {
            setCopieOuverte(false)
            setSelectedIds(new Set())
            rafraichir()
          }}
        />
      )}
    </div>
  )
}
