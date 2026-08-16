import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Wallet, Receipt, Ban, Users, TrendingUp, AlertTriangle } from 'lucide-react'
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
import {
  fetchDossier,
  fetchSituation,
  annulerVersement,
  francs,
  type DossierScolarite,
  type StatutPaiement,
} from '@/features/finance/api'
import { EncaissementModal } from '@/features/finance/pages/EncaissementModal'
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
  const queryClient = useQueryClient()

  const [classeId, setClasseId] = useState<number | ''>('')
  const [statut, setStatut] = useState<StatutPaiement | ''>('')
  const [dossierActif, setDossierActif] = useState<DossierScolarite | null>(null)
  const [ouverture, setOuverture] = useState<number | null>(null)

  const { data: classes } = useQuery({
    queryKey: ['classes', activeSchoolId],
    queryFn: () => fetchClasses(),
  })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['scolarite-situation', activeSchoolId, classeId, statut],
    queryFn: () => fetchSituation({ classe_id: classeId || null, statut: statut || null }),
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['scolarite-situation'] })

  /**
   * Un eleve sans dossier en porte un projete (`id` nul) : on l'ouvre au
   * moment d'encaisser, pas a l'affichage de la liste. Consulter la caisse
   * ne doit pas creer 269 dossiers a des familles qui n'ont rien verse.
   */
  const ouvrirEtEncaisser = async (dossier: DossierScolarite) => {
    if (dossier.id !== null) {
      setDossierActif(dossier)
      return
    }

    try {
      setOuverture(dossier.eleve.id)
      setDossierActif(await fetchDossier(dossier.eleve.id))
    } catch (e) {
      const err = e as ApiError
      if (err.status !== 403) erreur(err.message)
    } finally {
      setOuverture(null)
    }
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
      cle: 'eleve',
      entete: 'Élève',
      valeur: (d) => `${d.eleve.nom_complet} ${d.eleve.matricule ?? ''}`,
      cellule: (d) => (
        <div className="min-w-0">
          <div className="truncate font-semibold text-navy-900">{d.eleve.nom_complet}</div>
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
      cellule: (d) => {
        const dernier = d.versements?.filter((v) => !v.annule).at(-1)

        return (
          <div className="flex justify-end gap-1.5">
            {can('finance.encaisser') && (
              <Button size="sm" disabled={ouverture === d.eleve.id} onClick={() => ouvrirEtEncaisser(d)}>
                <Wallet className="h-3.5 w-3.5" />
                {ouverture === d.eleve.id ? 'Ouverture…' : 'Encaisser'}
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
      />

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <>
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
            cleLigne={(d) => d.eleve.id}
            placeholderRecherche="Rechercher un nom, un matricule…"
            messageVide="Aucun dossier pour ce filtre."
            largeurMin={900}
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

      {dossierActif && (
        <EncaissementModal
          dossier={dossierActif}
          onClose={() => setDossierActif(null)}
          onEncaisse={() => {
            setDossierActif(null)
            rafraichir()
          }}
        />
      )}
    </div>
  )
}
