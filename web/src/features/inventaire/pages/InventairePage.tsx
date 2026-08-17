import { useState } from 'react'
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

const LIBELLES_CATEGORIE: Record<CategorieArticle, string> = {
  mobilier: 'Mobilier',
  informatique: 'Informatique',
  pedagogique: 'Pédagogique',
  sport: 'Sport',
  autre: 'Autre',
}

const LIBELLES_ETAT: Record<EtatArticle, string> = {
  bon: 'Bon',
  moyen: 'Moyen',
  mauvais: 'Mauvais',
  hors_service: 'Hors service',
}

const TONE_ETAT: Record<EtatArticle, 'green' | 'gold' | 'red' | 'neutral'> = {
  bon: 'green',
  moyen: 'gold',
  mauvais: 'red',
  hors_service: 'neutral',
}

export function InventairePage() {
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
      entete: 'Article',
      valeur: (a) => a.nom,
      cellule: (a) => <span className="font-semibold text-navy-900">{a.nom}</span>,
    },
    {
      cle: 'categorie',
      entete: 'Catégorie',
      valeur: (a) => LIBELLES_CATEGORIE[a.categorie],
      cellule: (a) => LIBELLES_CATEGORIE[a.categorie],
    },
    {
      cle: 'quantite',
      entete: 'Quantité',
      valeur: (a) => a.quantite,
      cellule: (a) => <span className="tabular-nums">{a.quantite}</span>,
    },
    {
      cle: 'etat',
      entete: 'État',
      valeur: (a) => a.etat,
      cellule: (a) => <Badge tone={TONE_ETAT[a.etat]}>{LIBELLES_ETAT[a.etat]}</Badge>,
    },
    {
      cle: 'localisation',
      entete: 'Localisation',
      valeur: (a) => a.localisation,
      cellule: (a) => a.localisation ?? '—',
      masquerMobile: true,
    },
    {
      cle: 'valeur',
      entete: 'Valeur totale',
      valeur: (a) => a.valeur_totale,
      cellule: (a) => (a.valeur_totale > 0 ? <span className="tabular-nums">{francs(a.valeur_totale)}</span> : '—'),
      masquerMobile: true,
    },
    ...(can('inventaire.manage')
      ? [
          {
            cle: 'actions',
            entete: 'Actions',
            cellule: (a: ArticleInventaire) => (
              <div className="flex items-center gap-1">
                <button
                  title="Modifier"
                  onClick={() => {
                    setArticleEnEdition(a)
                    setShowForm(true)
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  title="Supprimer"
                  onClick={async () => {
                    if (!(await confirmerSuppression(a.nom))) return
                    try {
                      await supprimerArticle(a.id)
                      invalidate()
                      succes('Article supprimé.')
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
        titre="Inventaire"
        sousTitre="Mobilier, matériel informatique et pédagogique de l'établissement."
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
              Ajouter un article
            </Button>
          )
        }
      />

      {data && (
        <div className="grid gap-3 sm:grid-cols-3">
          <StatCard label="Articles distincts" value={data.stats.effectif_articles} icon={Package} accent="navy" />
          <StatCard label="Quantité totale" value={data.stats.quantite_totale} icon={Boxes} accent="gold" />
          <StatCard label="Valeur totale" value={francs(data.stats.valeur_totale)} icon={Wallet} accent="green" />
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data?.articles ?? []}
          cleLigne={(a) => a.id}
          placeholderRecherche="Rechercher un article, une localisation…"
          messageVide="Aucun article enregistré."
          largeurMin={760}
          outils={
            <div className="flex flex-wrap gap-2">
              <Select value={categorie} onChange={(e) => setCategorie(e.target.value as CategorieArticle | '')}>
                <option value="">Toutes les catégories</option>
                {Object.entries(LIBELLES_CATEGORIE).map(([valeur, libelle]) => (
                  <option key={valeur} value={valeur}>
                    {libelle}
                  </option>
                ))}
              </Select>
              <Select value={etat} onChange={(e) => setEtat(e.target.value as EtatArticle | '')}>
                <option value="">Tous les états</option>
                {Object.entries(LIBELLES_ETAT).map(([valeur, libelle]) => (
                  <option key={valeur} value={valeur}>
                    {libelle}
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
        succes('Article mis à jour.')
      } else {
        await creerArticle(payload)
        succes('Article ajouté.')
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={article ? "Modifier l'article" : 'Ajouter un article'} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input
          label="Nom"
          error={errors.nom?.message}
          {...register('nom', { required: 'Ce champ est requis.' })}
        />

        <div className="grid grid-cols-2 gap-3">
          <Select label="Catégorie" {...register('categorie', { required: true })}>
            {Object.entries(LIBELLES_CATEGORIE).map(([valeur, libelle]) => (
              <option key={valeur} value={valeur}>
                {libelle}
              </option>
            ))}
          </Select>
          <Select label="État" {...register('etat', { required: true })}>
            {Object.entries(LIBELLES_ETAT).map(([valeur, libelle]) => (
              <option key={valeur} value={valeur}>
                {libelle}
              </option>
            ))}
          </Select>
        </div>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label="Quantité"
            type="number"
            min={1}
            error={errors.quantite?.message}
            {...register('quantite', { required: true, min: { value: 1, message: 'La quantité doit être au moins 1.' } })}
          />
          <Input label="Valeur unitaire (F CFA)" type="number" min={0} {...register('valeur_unitaire')} />
        </div>

        <Input label="Localisation" placeholder="Ex. : Salle CM2-A, Bibliothèque…" {...register('localisation')} />
        <Input label="Date d'acquisition" type="date" {...register('date_acquisition')} />
        <Input label="Notes" placeholder="Facultatif" {...register('notes')} />

        {serverError && <p className="text-sm text-red-500">{serverError}</p>}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            Annuler
          </Button>
          <Button type="submit" disabled={isSubmitting}>
            Enregistrer
          </Button>
        </div>
      </form>
    </Modal>
  )
}
