import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Boxes, Coins, PackagePlus } from 'lucide-react'
import { enregistrerEntree, fetchEntrees, type EntreeStock } from '@/features/pointDeVente/api'
import { fetchInventaire } from '@/features/inventaire/api'
import { francs } from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import { StatCard } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Input, MontantInput, Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

function debutDuMois(): string {
  const date = new Date()
  return new Date(date.getFullYear(), date.getMonth(), 1).toISOString().slice(0, 10)
}

/**
 * Réapprovisionnement : le matériel qui entre et ce qu'il a coûté.
 *
 * Chaque entrée repondère le coût unitaire moyen de l'article — c'est ce coût
 * qui sert ensuite à mesurer la marge du comptoir, ligne à ligne.
 */
export function EntreesTab() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const can = useAuthStore((s) => s.can)

  const [du, setDu] = useState(debutDuMois())
  const [au, setAu] = useState(new Date().toISOString().slice(0, 10))
  const [formOuvert, setFormOuvert] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['pdv-entrees', du, au],
    queryFn: () => fetchEntrees({ du, au }),
  })

  const colonnes: Colonne<EntreeStock>[] = [
    {
      cle: 'date',
      entete: t('pointDeVente.date'),
      valeur: (e) => e.date_entree,
      cellule: (e) => e.date_entree,
    },
    {
      cle: 'article',
      entete: t('pointDeVente.article'),
      valeur: (e) => e.article?.nom,
      cellule: (e) => <span className="font-semibold text-navy-900">{e.article?.nom ?? '—'}</span>,
    },
    {
      cle: 'quantite',
      entete: t('pointDeVente.quantite'),
      valeur: (e) => e.quantite,
      cellule: (e) => <span className="tabular-nums">{e.quantite}</span>,
    },
    {
      cle: 'cout_unitaire',
      entete: t('pointDeVente.cout_unitaire'),
      valeur: (e) => e.cout_unitaire,
      cellule: (e) => <span className="tabular-nums">{francs(e.cout_unitaire)}</span>,
    },
    {
      cle: 'cout_total',
      entete: t('pointDeVente.cout_total'),
      valeur: (e) => e.cout_total,
      cellule: (e) => <span className="font-bold tabular-nums text-navy-900">{francs(e.cout_total)}</span>,
    },
    {
      cle: 'fournisseur',
      entete: t('pointDeVente.fournisseur'),
      valeur: (e) => e.fournisseur ?? '',
      cellule: (e) => e.fournisseur ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'par',
      entete: t('pointDeVente.enregistre_par'),
      valeur: (e) => e.enregistre_par ?? '',
      cellule: (e) => e.enregistre_par ?? '—',
      masquerMobile: true,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard label={t('pointDeVente.stat_entrees')} value={data?.totaux.effectif ?? 0} icon={PackagePlus} />
        <StatCard
          label={t('pointDeVente.stat_quantite')}
          value={data?.totaux.quantite ?? 0}
          icon={Boxes}
          accent="navy"
        />
        <StatCard
          label={t('pointDeVente.stat_cout_entrees')}
          value={francs(data?.totaux.cout ?? 0)}
          icon={Coins}
          accent="gold"
        />
      </div>

      {can('point_de_vente.manage') && (
        <div className="flex flex-wrap gap-2">
          <Button onClick={() => setFormOuvert(true)}>
            <PackagePlus className="h-4 w-4" />
            {t('pointDeVente.nouvelle_entree')}
          </Button>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data.entrees}
          cleLigne={(e) => e.id}
          placeholderRecherche={t('pointDeVente.recherche_entree')}
          messageVide={t('pointDeVente.aucune_entree')}
          largeurMin={820}
          outils={
            <>
              <div className="w-full sm:w-40">
                <Input type="date" value={du} onChange={(e) => setDu(e.target.value)} />
              </div>
              <div className="w-full sm:w-40">
                <Input type="date" value={au} onChange={(e) => setAu(e.target.value)} />
              </div>
            </>
          }
        />
      )}

      {formOuvert && (
        <EntreeFormModal
          onClose={() => setFormOuvert(false)}
          onEnregistree={() => {
            setFormOuvert(false)
            queryClient.invalidateQueries({ queryKey: ['pdv-entrees'] })
            queryClient.invalidateQueries({ queryKey: ['pdv-catalogue'] })
            queryClient.invalidateQueries({ queryKey: ['inventaire'] })
          }}
        />
      )}
    </div>
  )
}

function EntreeFormModal({ onClose, onEnregistree }: { onClose: () => void; onEnregistree: () => void }) {
  const { t } = useTranslation()
  const [articleId, setArticleId] = useState<number | ''>('')
  const [quantite, setQuantite] = useState('')
  const [coutUnitaire, setCoutUnitaire] = useState<number | null>(null)
  const [fournisseur, setFournisseur] = useState('')
  const [reference, setReference] = useState('')
  const [dateEntree, setDateEntree] = useState(new Date().toISOString().slice(0, 10))
  const [comptabiliser, setComptabiliser] = useState(false)
  const [enCours, setEnCours] = useState(false)

  // Le réassort porte sur n'importe quel article de l'inventaire, pas seulement
  // sur ceux déjà mis en vente : on approvisionne avant de fixer un prix.
  const { data } = useQuery({ queryKey: ['inventaire'], queryFn: () => fetchInventaire() })
  const articles = data?.articles ?? []

  const coutTotal = (Number(quantite) || 0) * (coutUnitaire ?? 0)
  const valide = articleId !== '' && Number(quantite) > 0 && coutUnitaire !== null

  const soumettre = async () => {
    if (!valide) return

    setEnCours(true)
    try {
      await enregistrerEntree({
        article_id: Number(articleId),
        quantite: Number(quantite),
        cout_unitaire: coutUnitaire ?? 0,
        fournisseur: fournisseur.trim() || null,
        reference: reference.trim() || null,
        date_entree: dateEntree,
        comptabiliser,
      })
      succes(t('pointDeVente.entree_enregistree'))
      onEnregistree()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  return (
    <Modal title={t('pointDeVente.nouvelle_entree')} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <Select
          label={t('pointDeVente.article')}
          value={articleId}
          onChange={(e) => setArticleId(e.target.value ? Number(e.target.value) : '')}
        >
          <option value="">—</option>
          {articles.map((article) => (
            <option key={article.id} value={article.id}>
              {article.nom} ({t('pointDeVente.en_stock', { count: article.quantite })})
            </option>
          ))}
        </Select>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('pointDeVente.quantite')}
            type="number"
            min={1}
            value={quantite}
            onChange={(e) => setQuantite(e.target.value)}
          />
          <MontantInput
            label={t('pointDeVente.cout_unitaire')}
            value={coutUnitaire}
            onChange={setCoutUnitaire}
          />
        </div>

        {coutTotal > 0 && (
          <p className="rounded-lg bg-cream-100 px-3 py-2 text-sm text-navy-600">
            {t('pointDeVente.cout_total')} :{' '}
            <span className="font-bold text-navy-900">{francs(coutTotal)}</span>
          </p>
        )}

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('pointDeVente.fournisseur')}
            value={fournisseur}
            onChange={(e) => setFournisseur(e.target.value)}
          />
          <Input
            label={t('pointDeVente.reference')}
            value={reference}
            onChange={(e) => setReference(e.target.value)}
          />
        </div>

        <Input
          label={t('pointDeVente.date')}
          type="date"
          value={dateEntree}
          onChange={(e) => setDateEntree(e.target.value)}
        />

        <label className="flex cursor-pointer items-start gap-2 rounded-lg bg-cream-100 p-3 text-sm text-navy-700">
          <input
            type="checkbox"
            checked={comptabiliser}
            onChange={(e) => setComptabiliser(e.target.checked)}
            className="mt-0.5 h-4 w-4 rounded border-navy-300"
          />
          <span>
            {t('pointDeVente.comptabiliser')}
            <span className="mt-0.5 block text-xs text-navy-400">{t('pointDeVente.comptabiliser_aide')}</span>
          </span>
        </label>

        <div className="mt-1 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button disabled={!valide || enCours} onClick={soumettre}>
            <PackagePlus className="h-4 w-4" />
            {t('common.save')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
