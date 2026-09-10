import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Wallet, Receipt, Ban, Users, TrendingUp, AlertTriangle, ListFilter, History, Settings2 } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchClasses } from '@/features/classes/api'
import { fetchSituation, annulerVersement, francs, type DossierScolarite, type StatutPaiement } from '@/features/finance/api'
import { GestionRemiseModal } from '@/features/finance/GestionRemiseModal'
import type { ApiError } from '@/shared/types/api'

const STATUTS: { valeur: StatutPaiement | ''; libelle: string }[] = [
  { valeur: '', libelle: 'Tous les élèves' },
  { valeur: 'impaye', libelle: "N'ont rien versé" },
  { valeur: 'partiel', libelle: 'Paiement partiel' },
  { valeur: 'solde', libelle: 'Soldés' },
  { valeur: 'avance', libelle: 'En avance' },
]

const TONS: Record<StatutPaiement, 'red' | 'gold' | 'green' | 'blue' | 'neutral'> = {
  impaye: 'red',
  partiel: 'gold',
  solde: 'green',
  avance: 'blue',
  sans_frais: 'neutral',
}

const LIBELLES: Record<StatutPaiement, string> = {
  impaye: 'Impayé',
  partiel: 'Partiel',
  solde: 'Soldé',
  avance: 'Avance',
  sans_frais: 'Sans frais',
}

/**
 * Comptoir de la caisse : situation de recouvrement, liste des insolvables et
 * encaissement.
 *
 * Le filtre par statut n'est pas un simple confort : « n'ont rien versé » et
 * « paiement partiel » sont deux listes de relance différentes, et c'est la
 * question que pose un économe en début de trimestre.
 */
export function CaissePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [classeId, setClasseId] = useState<number | ''>('')
  const [statut, setStatut] = useState<StatutPaiement | ''>('')
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [remiseEleve, setRemiseEleve] = useState<{ id: number; nom: string } | null>(null)

  const { data: classes } = useQuery({
    queryKey: ['classes', activeSchoolId],
    queryFn: () => fetchClasses(),
  })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['scolarite-situation', activeSchoolId, classeId, statut],
    queryFn: () => fetchSituation({ classe_id: classeId || null, statut: statut || null }),
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] })

  const toggleSelect = (eleveId: number) => {
    setSelectedIds((actuels) => {
      const prochain = new Set(actuels)
      if (prochain.has(eleveId)) {
        prochain.delete(eleveId)
      } else {
        prochain.add(eleveId)
      }
      return prochain
    })
  }

  const toggleSelectAll = (dossiers: DossierScolarite[]) => {
    if (selectedIds.size === dossiers.length && dossiers.length > 0) {
      setSelectedIds(new Set())
      return
    }

    setSelectedIds(new Set(dossiers.map((d) => d.eleve.id)))
  }

  const annuler = async (dossier: DossierScolarite) => {
    const dernier = dossier.versements?.filter((v) => !v.annule).at(-1)
    if (!dernier) return

    const ok = await confirmer({
      titre: `Annuler le reçu ${dernier.numero_recu} ?`,
      message: `${francs(dernier.montant)} seront retirés du solde de ${dossier.eleve.nom_complet}. Le reçu reste au registre, marqué annulé.`,
      action: 'Annuler le reçu',
    })
    if (!ok) return

    try {
      await annulerVersement(dernier.id, 'Annulation depuis la caisse')
      succes(t('finance.receipt_cancelled'))
      rafraichir()
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    }
  }

  const colonnes: Colonne<DossierScolarite>[] = [
    {
      cle: 'selection',
      entete: data?.dossiers ? (
        <input
          type="checkbox"
          checked={selectedIds.size === data.dossiers.length && data.dossiers.length > 0}
          onClick={(event) => event.stopPropagation()}
          onChange={() => toggleSelectAll(data.dossiers)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ) : null,
      cellule: (d) => (
        <input
          type="checkbox"
          checked={selectedIds.has(d.eleve.id)}
          onClick={(event) => event.stopPropagation()}
          onChange={() => toggleSelect(d.eleve.id)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ),
      largeur: '48px',
      sticky: 'left',
    },
    {
      cle: 'eleve',
      entete: 'Élève',
      valeur: (d) => `${d.eleve.nom_complet} ${d.eleve.matricule ?? ''}`,
      cellule: (d) => (
        <div className="min-w-0">
          <div className="break-words font-semibold leading-tight text-navy-900" title={d.eleve.nom_complet}>
            {d.eleve.nom_complet}
          </div>
          <div className="text-xs text-navy-400">
            {d.eleve.matricule ?? '—'} · {d.eleve.classe ?? 'Sans classe'}
          </div>
        </div>
      ),
    },
    {
      cle: 'du',
      entete: 'Dû',
      valeur: (d) => d.total_du,
      cellule: (d) => <span className="tabular-nums">{francs(d.total_du)}</span>,
      masquerMobile: true,
    },
    {
      cle: 'remise',
      entete: 'Remise',
      valeur: (d) => d.remise,
      cellule: (d) => (
        <div className="flex items-center gap-2 whitespace-nowrap">
          <span className={d.remise > 0 ? 'tabular-nums text-amber-600' : 'tabular-nums text-navy-300'}>
            {d.remise > 0 ? francs(d.remise) : '—'}
          </span>
          {d.remise > 0 && can('finance.manage') && (
            <button
              type="button"
              title="Modifier ou supprimer la remise"
              onClick={(event) => {
                event.stopPropagation()
                setRemiseEleve({ id: d.eleve.id, nom: d.eleve.nom_complet })
              }}
              className="rounded-lg p-1 text-navy-400 hover:bg-cream-100 hover:text-navy-800"
            >
              <Settings2 className="h-3.5 w-3.5" />
            </button>
          )}
        </div>
      ),
      masquerMobile: true,
    },
    {
      cle: 'paye',
      entete: 'Versé',
      valeur: (d) => d.total_paye,
      cellule: (d) => <span className="tabular-nums text-green-600">{francs(d.total_paye)}</span>,
    },
    {
      cle: 'reste',
      entete: 'Reste',
      valeur: (d) => d.reste_a_payer,
      cellule: (d) => (
        <span className={d.reste_a_payer > 0 ? 'font-semibold tabular-nums text-red-500' : 'tabular-nums text-navy-300'}>
          {d.reste_a_payer > 0 ? francs(d.reste_a_payer) : '—'}
        </span>
      ),
    },
    {
      cle: 'statut',
      entete: 'Statut',
      valeur: (d) => d.statut_paiement,
      cellule: (d) => <Badge tone={TONS[d.statut_paiement]}>{LIBELLES[d.statut_paiement]}</Badge>,
    },
    {
      cle: 'contact',
      entete: 'Contact',
      valeur: (d) => d.eleve.contact ?? '',
      cellule: (d) => <span className="text-xs text-navy-500">{d.eleve.contact ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: '',
      largeur: '310px',
      sticky: 'right',
      className: 'whitespace-nowrap',
      cellule: (d) => {
        if (selectedIds.size > 0) return <div className="w-0" />

        const dernier = d.versements?.filter((v) => !v.annule).at(-1)

        return (
          <div className="flex min-w-max justify-end gap-1.5">
            {can('finance.encaisser') && (
              <Button size="sm" onClick={() => navigate(`/caisse/encaisser/${d.eleve.id}`)}>
                <Wallet className="h-3.5 w-3.5" />
                Encaisser
              </Button>
            )}
            {dernier && (
              <Button
                size="sm"
                variant="secondary"
                title={`Reçu ${dernier.numero_recu}`}
                onClick={() => ouvrirDocument(`/versements/${dernier.id}/recu`)}
              >
                <Receipt className="h-3.5 w-3.5" />
              </Button>
            )}
            {dernier && can('finance.annuler') && (
              <Button size="sm" variant="danger" title="Annuler le dernier reçu" onClick={() => annuler(d)}>
                <Ban className="h-3.5 w-3.5" />
              </Button>
            )}
          </div>
        )
      },
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Caisse — frais de scolarité"
        sousTitre="Encaissements, reçus et suivi du recouvrement."
        icon={Wallet}
        actions={
          <>
            <Button variant="secondary" onClick={() => navigate('/caisse/dettes-anterieures')}>
              <History className="h-4 w-4" />
              Dettes antérieures
            </Button>
            <Button variant="secondary" onClick={() => navigate('/caisse/insolvables')}>
              <ListFilter className="h-4 w-4" />
              Insolvables
            </Button>
          </>
        }
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
          {selectedIds.size > 0 && (
            <div className="flex items-center justify-between rounded-2xl border border-navy-200 bg-navy-50 px-4 py-2.5 text-sm text-navy-700">
              <span>
                {selectedIds.size} élève{selectedIds.size > 1 ? 's' : ''} sélectionné{selectedIds.size > 1 ? 's' : ''}
              </span>
              <Button size="sm" variant="secondary" onClick={() => setSelectedIds(new Set())}>
                Tout déselectionner
              </Button>
            </div>
          )}
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Attendu" value={francs(data.totaux.attendu)} icon={Users} accent="navy" />
            <StatCard
              label="Recouvré"
              value={francs(data.totaux.recouvre)}
              icon={TrendingUp}
              accent="green"
              hint={`${data.totaux.taux_recouvrement} % du dû`}
            />
            <StatCard label="Reste à recouvrer" value={francs(data.totaux.reste)} icon={AlertTriangle} accent="red" />
            <StatCard
              label="Insolvables"
              value={data.totaux.insolvables}
              icon={AlertTriangle}
              accent="gold"
              hint={`sur ${data.totaux.effectif} élève(s)`}
            />
          </div>

          <DataTable
            colonnes={colonnes}
            lignes={data.dossiers}
            parPage={0}
            cleLigne={(d) => d.eleve.id}
            placeholderRecherche={t('finance.search_caisse')}
            messageVide={t('finance.empty_caisse')}
            largeurMin={1250}
            outils={
              <div className="flex flex-wrap gap-2">
                <Select value={statut} onChange={(e) => setStatut(e.target.value as StatutPaiement | '')}>
                  {STATUTS.map((s) => (
                    <option key={s.valeur} value={s.valeur}>
                      {s.libelle}
                    </option>
                  ))}
                </Select>
                <Select value={classeId} onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}>
                  <option value="">Toutes les classes</option>
                  {classes?.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.nom}
                    </option>
                  ))}
                </Select>
              </div>
            }
          />
        </>
      )}
      {remiseEleve && (
        <GestionRemiseModal
          eleveId={remiseEleve.id}
          eleveNom={remiseEleve.nom}
          onClose={() => setRemiseEleve(null)}
          onChange={rafraichir}
        />
      )}
    </div>
  )
}
