import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { Boxes, Package, Pencil, Plus, Trash2, Wallet } from 'lucide-react'
import {
  creerArticle,
  fetchInventaire,
  modifierArticle,
  supprimerArticle,
  type ArticleInventaire,
  type ArticleInventairePayload,
  type CategorieArticle,
  type EtatArticle,
} from '@/features/inventaire/api'
import { francs } from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { StatCard } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const CATEGORIES: CategorieArticle[] = ['mobilier', 'informatique', 'pedagogique', 'sport', 'autre']
const ETATS: EtatArticle[] = ['bon', 'moyen', 'mauvais', 'hors_service']

const TONE_ETAT: Record<EtatArticle, 'green' | 'gold' | 'red' | 'neutral'> = {
  bon: 'green',
  moyen: 'gold',
  mauvais: 'red',
  hors_service: 'neutral',
}

export function InventairePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [categorie, setCategorie] = useState<CategorieArticle | ''>('')
  const [etat, setEtat] = useState<EtatArticle | ''>('')
  const [showForm, setShowForm] = useState(false)
  const [articleEnEdition, setArticleEnEdition] = useState<ArticleInventaire | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['inventaire', categorie, etat],
    queryFn: () => fetchInventaire({ categorie: categorie || undefined, etat: etat || undefined }),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['inventaire'] })

  const colonnes: Colonne<ArticleInventaire>[] = [
    {
      cle: 'nom',
      entete: t('inventaire.article_col'),
      valeur: (a) => a.nom,
      cellule: (a) => <span className="font-semibold text-navy-900">{a.nom}</span>,
    },
    {
      cle: 'categorie',
      entete: t('inventaire.categorie_col'),
      valeur: (a) => t(`inventaire.categorie_${a.categorie}`),
      cellule: (a) => t(`inventaire.categorie_${a.categorie}`),
    },
    {
      cle: 'quantite',
      entete: t('inventaire.quantite_col'),
      valeur: (a) => a.quantite,
      cellule: (a) => <span className="tabular-nums">{a.quantite}</span>,
    },
    {
      cle: 'etat',
      entete: t('inventaire.etat_col'),
      valeur: (a) => a.etat,
      cellule: (a) => <Badge tone={TONE_ETAT[a.etat]}>{t(`inventaire.etat_${a.etat}`)}</Badge>,
    },
    {
      cle: 'localisation',
      entete: t('inventaire.localisation_col'),
      valeur: (a) => a.localisation,
      cellule: (a) => a.localisation ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'valeur',
      entete: t('inventaire.valeur_totale_col'),
      valeur: (a) => a.valeur_totale,
      cellule: (a) => (a.valeur_totale > 0 ? <span className="tabular-nums">{francs(a.valeur_totale)}</span> : '—'),
      masquerMobile: true,
    },
    ...(can('inventaire.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (a: ArticleInventaire) => (
              <div className="flex items-center gap-1">
                <button
                  title={t('common.edit')}
                  onClick={() => {
                    setArticleEnEdition(a)
                    setShowForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title={t('common.delete')}
                  onClick={async () => {
                    if (!(await confirmerSuppression(a.nom))) return
                    try {
                      await supprimerArticle(a.id)
                      invalidate()
                      succes(t('inventaire.deleted'))
                    } catch (err) {
                      erreur((err as ApiError).message)
                    }
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<ArticleInventaire>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('inventaire.title')}
        sousTitre={t('inventaire.subtitle')}
        icon={Boxes}
        actions={
          can('inventaire.manage') && (
            <Button
              onClick={() => {
                setArticleEnEdition(null)
                setShowForm(true)
              }}
            >
              <Plus className="h-4 w-4" />
              {t('inventaire.add')}
            </Button>
          )
        }
      />

      {data && (
        <div className="grid gap-3 sm:grid-cols-3">
          <StatCard label={t('inventaire.stat_articles_distincts')} value={data.stats.effectif_articles} icon={Package} accent="navy" />
          <StatCard label={t('inventaire.stat_quantite_totale')} value={data.stats.quantite_totale} icon={Boxes} accent="gold" />
          <StatCard label={t('inventaire.valeur_totale_col')} value={francs(data.stats.valeur_totale)} icon={Wallet} accent="green" />
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data?.articles ?? []}
          cleLigne={(a) => a.id}
          placeholderRecherche={t('inventaire.search_placeholder')}
          messageVide={t('inventaire.empty')}
          largeurMin={760}
          outils={
            <div className="flex flex-wrap gap-2">
              <Select value={categorie} onChange={(e) => setCategorie(e.target.value as CategorieArticle | '')}>
                <option value="">{t('inventaire.all_categories')}</option>
                {CATEGORIES.map((valeur) => (
                  <option key={valeur} value={valeur}>
                    {t(`inventaire.categorie_${valeur}`)}
                  </option>
                ))}
              </Select>
              <Select value={etat} onChange={(e) => setEtat(e.target.value as EtatArticle | '')}>
                <option value="">{t('inventaire.all_etats')}</option>
                {ETATS.map((valeur) => (
                  <option key={valeur} value={valeur}>
                    {t(`inventaire.etat_${valeur}`)}
                  </option>
                ))}
              </Select>
            </div>
          }
        />
      )}

      {showForm && (
        <ArticleFormModal
          article={articleEnEdition}
          onClose={() => setShowForm(false)}
          onSaved={() => {
            setShowForm(false)
            setArticleEnEdition(null)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

function ArticleFormModal({
  article,
  onClose,
  onSaved,
}: {
  article: ArticleInventaire | null
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { isSubmitting, errors },
  } = useForm<ArticleInventairePayload>({
    defaultValues: article
      ? {
          nom: article.nom,
          categorie: article.categorie,
          quantite: article.quantite,
          etat: article.etat,
          localisation: article.localisation ?? '',
          valeur_unitaire: article.valeur_unitaire ?? undefined,
          date_acquisition: article.date_acquisition ?? '',
          notes: article.notes ?? '',
        }
      : { categorie: 'mobilier', quantite: 1, etat: 'bon' },
  })

  const onSubmit = async (values: ArticleInventairePayload) => {
    setServerError(null)
    const payload: ArticleInventairePayload = {
      ...values,
      quantite: Number(values.quantite),
      valeur_unitaire: values.valeur_unitaire ? Number(values.valeur_unitaire) : null,
      date_acquisition: values.date_acquisition || null,
    }

    try {
      if (article) {
        await modifierArticle(article.id, payload)
        succes(t('inventaire.updated'))
      } else {
        await creerArticle(payload)
        succes(t('inventaire.created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={article ? t('inventaire.edit_title') : t('inventaire.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label={t('inventaire.nom_label')}
          error={errors.nom?.message}
          {...register('nom', { required: t('bus.field_required') as string })}
        />

        <div className="grid grid-cols-2 gap-3">
          <Select label={t('inventaire.categorie_col')} {...register('categorie', { required: true })}>
            {CATEGORIES.map((valeur) => (
              <option key={valeur} value={valeur}>
                {t(`inventaire.categorie_${valeur}`)}
              </option>
            ))}
          </Select>
          <Select label={t('inventaire.etat_col')} {...register('etat', { required: true })}>
            {ETATS.map((valeur) => (
              <option key={valeur} value={valeur}>
                {t(`inventaire.etat_${valeur}`)}
              </option>
            ))}
          </Select>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('inventaire.quantite_col')}
            type="number"
            min={1}
            error={errors.quantite?.message}
            {...register('quantite', { required: true, min: { value: 1, message: t('inventaire.quantite_min') } })}
          />
          <Input label={t('inventaire.valeur_unitaire_label')} type="number" min={0} {...register('valeur_unitaire')} />
        </div>

        <Input label={t('inventaire.localisation_col')} placeholder={t('inventaire.localisation_placeholder')} {...register('localisation')} />
        <Input label={t('inventaire.date_acquisition_label')} type="date" {...register('date_acquisition')} />
        <Input label={t('inventaire.notes_label')} placeholder={t('inventaire.notes_placeholder')} {...register('notes')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
