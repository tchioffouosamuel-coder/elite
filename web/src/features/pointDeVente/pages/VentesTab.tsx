import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Ban, Coins, FileDown, Receipt, TrendingUp } from 'lucide-react'
import { annulerVente, fetchVentes, type VenteFourniture } from '@/features/pointDeVente/api'
import { francs } from '@/features/finance/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { StatCard } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Input, Textarea } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/** Premier jour du mois courant, au format attendu par un champ date. */
function debutDuMois(): string {
  const date = new Date()
  return new Date(date.getFullYear(), date.getMonth(), 1).toISOString().slice(0, 10)
}

/**
 * Journal des ventes du comptoir : ce qui a été vendu, ce que ça a coûté, et
 * la marge dégagée. Une vente ne se supprime pas — son numéro de facture a été
 * remis au public — mais elle s'annule, ce qui remet le stock en rayon.
 */
export function VentesTab() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)

  const [du, setDu] = useState(debutDuMois())
  const [au, setAu] = useState(new Date().toISOString().slice(0, 10))
  const [avecAnnulees, setAvecAnnulees] = useState(false)
  const [venteAAnnuler, setVenteAAnnuler] = useState<VenteFourniture | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['pdv-ventes', du, au, avecAnnulees],
    queryFn: () => fetchVentes({ du, au, annulees: avecAnnulees || undefined }),
  })

  const colonnes: Colonne<VenteFourniture>[] = [
    {
      cle: 'facture',
      entete: t('pointDeVente.facture'),
      valeur: (v) => v.numero_facture,
      cellule: (v) => (
        <span className="flex items-center gap-2">
          <span className="font-mono text-xs font-semibold text-navy-900">{v.numero_facture}</span>
          {v.annule && <Badge tone="red">{t('pointDeVente.annulee')}</Badge>}
        </span>
      ),
    },
    {
      cle: 'date',
      entete: t('pointDeVente.date'),
      valeur: (v) => v.date_vente,
      cellule: (v) => v.date_vente,
    },
    {
      cle: 'acheteur',
      entete: t('pointDeVente.acheteur'),
      valeur: (v) => v.eleve?.nom_complet ?? v.client ?? '',
      cellule: (v) => v.eleve?.nom_complet ?? v.client ?? t('pointDeVente.client_comptoir'),
    },
    {
      cle: 'articles',
      entete: t('pointDeVente.articles'),
      valeur: (v) => v.lignes.map((l) => l.libelle).join(', '),
      cellule: (v) => (
        <span className="text-navy-500">
          {v.lignes.map((ligne) => `${ligne.quantite}× ${ligne.libelle}`).join(', ') || '—'}
        </span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'montant',
      entete: t('pointDeVente.montant'),
      valeur: (v) => v.montant,
      cellule: (v) => <span className="font-bold tabular-nums text-navy-900">{francs(v.montant)}</span>,
    },
    {
      cle: 'marge',
      entete: t('pointDeVente.marge'),
      valeur: (v) => v.marge,
      cellule: (v) => (
        <span className={v.marge >= 0 ? 'tabular-nums text-green-600' : 'tabular-nums text-red-600'}>
          {francs(v.marge)}
        </span>
      ),
      masquerMobile: true,
    },
    {
      cle: 'vendeur',
      entete: t('pointDeVente.vendeur'),
      valeur: (v) => v.vendeur ?? '',
      cellule: (v) => v.vendeur ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (v) => (
        <div className="flex items-center gap-1">
          <button
            title={t('pointDeVente.voir_facture')}
            onClick={() => ouvrirDocument(`/point-de-vente/ventes/${v.id}/facture`)}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <FileDown className="h-4 w-4" />
          </button>
          {!v.annule && can('point_de_vente.manage') && (
            <button
              title={t('pointDeVente.annuler')}
              onClick={() => setVenteAAnnuler(v)}
              className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-red-50 hover:text-red-600"
            >
              <Ban className="h-4 w-4" />
            </button>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label={t('pointDeVente.stat_ventes')}
          value={data?.totaux.effectif ?? 0}
          icon={Receipt}
          accent="navy"
        />
        <StatCard
          label={t('pointDeVente.stat_recette')}
          value={francs(data?.totaux.montant ?? 0)}
          icon={Coins}
          accent="gold"
        />
        <StatCard
          label={t('pointDeVente.stat_cout')}
          value={francs(data?.totaux.cout ?? 0)}
          icon={Coins}
          accent="navy"
        />
        <StatCard
          label={t('pointDeVente.stat_marge')}
          value={francs(data?.totaux.marge ?? 0)}
          icon={TrendingUp}
          accent={(data?.totaux.marge ?? 0) >= 0 ? 'green' : 'red'}
        />
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data.ventes}
          cleLigne={(v) => v.id}
          placeholderRecherche={t('pointDeVente.recherche_vente')}
          messageVide={t('pointDeVente.aucune_vente')}
          largeurMin={900}
          outils={
            <>
              <div className="w-full sm:w-40">
                <Input type="date" value={du} onChange={(e) => setDu(e.target.value)} />
              </div>
              <div className="w-full sm:w-40">
                <Input type="date" value={au} onChange={(e) => setAu(e.target.value)} />
              </div>
              <label className="flex items-center gap-2 whitespace-nowrap text-sm text-navy-600">
                <input
                  type="checkbox"
                  checked={avecAnnulees}
                  onChange={(e) => setAvecAnnulees(e.target.checked)}
                  className="rounded border-navy-300"
                />
                {t('pointDeVente.inclure_annulees')}
              </label>
            </>
          }
        />
      )}

      {venteAAnnuler && (
        <AnnulerVenteModal
          vente={venteAAnnuler}
          onClose={() => setVenteAAnnuler(null)}
          onAnnulee={() => {
            setVenteAAnnuler(null)
            queryClient.invalidateQueries({ queryKey: ['pdv-ventes'] })
            queryClient.invalidateQueries({ queryKey: ['pdv-catalogue'] })
            queryClient.invalidateQueries({ queryKey: ['inventaire'] })
          }}
        />
      )}
    </div>
  )
}

function AnnulerVenteModal({
  vente,
  onClose,
  onAnnulee,
}: {
  vente: VenteFourniture
  onClose: () => void
  onAnnulee: () => void
}) {
  const { t } = useTranslation()
  const [motif, setMotif] = useState('')
  const [enCours, setEnCours] = useState(false)

  const soumettre = async () => {
    setEnCours(true)
    try {
      await annulerVente(vente.id, motif.trim())
      succes(t('pointDeVente.vente_annulee'))
      onAnnulee()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal title={t('pointDeVente.annuler_titre', { numero: vente.numero_facture })} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="text-sm text-navy-600">{t('pointDeVente.annuler_message')}</p>

        <ul className="flex flex-col gap-1 rounded-xl bg-cream-100 p-3 text-sm text-navy-700">
          {vente.lignes.map((ligne) => (
            <li key={ligne.id} className="flex justify-between gap-3">
              <span>
                {ligne.quantite}× {ligne.libelle}
              </span>
              <span className="tabular-nums">{francs(ligne.total)}</span>
            </li>
          ))}
        </ul>

        <Textarea
          label={t('pointDeVente.motif')}
          rows={2}
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
          placeholder={t('pointDeVente.motif_placeholder')}
        />

        <div className="mt-1 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button variant="danger" disabled={motif.trim().length < 3 || enCours} onClick={soumettre}>
            <Ban className="h-4 w-4" />
            {t('pointDeVente.annuler')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
