import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Tags, Plus, Save, Trash2, Info } from 'lucide-react'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Input } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import { useAuthStore } from '@/shared/store/authStore'
import {
  creerFraisAnnexe,
  definirTarif,
  desactiverFraisAnnexe,
  fetchTarifs,
  francs,
  modifierFraisAnnexe,
  supprimerTarif,
} from '@/features/finance/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Paramétrage des tarifs.
 *
 * L'écran insiste sur un point contre-intuitif : modifier un tarif ne touche
 * pas aux dossiers déjà ouverts, qui ont recopié le montant à l'inscription.
 * Le nombre de dossiers concernés est affiché par classe, faute de quoi un
 * gestionnaire croirait avoir corrigé une erreur qui persistera.
 */
export function TarifsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const queryClient = useQueryClient()
  const modifiable = can('finance.manage')

  const [brouillons, setBrouillons] = useState<Record<string, string>>({})
  const [nouveauFrais, setNouveauFrais] = useState({ libelle: '', montant: '', obligatoire: false })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['tarifs', activeSchoolId],
    queryFn: () => fetchTarifs(),
  })

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['tarifs'] })

  const mutation = useMutation({
    mutationFn: async (action: () => Promise<void>) => action(),
    onSuccess: () => rafraichir(),
    onError: (e: ApiError) => {
      if (e.status !== 403) erreur(e.message)
    },
  })

  const agir = (action: () => Promise<void>, message: string) =>
    mutation.mutate(
      async () => {
        await action()
        succes(message)
      },
      { onError: (e: ApiError) => e.status !== 403 && erreur(e.message) },
    )

  const enregistrerTarif = (classeId: number | null) => {
    const cle = String(classeId ?? 'defaut')
    const valeur = brouillons[cle]
    if (valeur === undefined || valeur === '') return

    agir(() => definirTarif(classeId, Number(valeur)), t('finance.tariff_recorded'))
    setBrouillons((b) => {
      const suivant = { ...b }
      delete suivant[cle]
      return suivant
    })
  }

  const retirerTarif = async (classeId: number, nom: string) => {
    const ok = await confirmer({
      titre: `Retirer le tarif de ${nom} ?`,
      message: 'La classe suivra le tarif par défaut de l\'établissement.',
      action: 'Retirer',
    })
    if (ok) agir(() => supprimerTarif(classeId), t('finance.tariff_removed'))
  }

  const ajouterFrais = () => {
    if (!nouveauFrais.libelle || !nouveauFrais.montant) return

    agir(
      () => creerFraisAnnexe({ ...nouveauFrais, montant: Number(nouveauFrais.montant) }),
      t('finance.additional_fee_added'),
    )
    setNouveauFrais({ libelle: '', montant: '', obligatoire: false })
  }

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const valeurChamp = (cle: string, defaut: number | null | undefined) =>
    brouillons[cle] ?? (defaut != null ? String(defaut) : '')

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Tarifs et frais annexes"
        sousTitre={`Grille de l'année ${data.annee_scolaire.libelle}.`}
        icon={Tags}
      />

      <p className="flex items-start gap-2 rounded-xl bg-gold-50 px-3.5 py-2.5 text-sm text-gold-800 ring-1 ring-gold-100">
        <Info className="mt-0.5 h-4 w-4 flex-none" />
        <span>
          Un tarif modifié ici ne s'applique qu'aux <strong>nouveaux dossiers</strong>. Ceux déjà ouverts conservent
          le montant annoncé à la famille — le nombre de dossiers concernés est indiqué par classe.
        </span>
      </p>

      {/*
        La colonne des frais annexes porte, sur une même ligne, un libellé, un
        montant, un état et deux actions : à 22 rem tout se tassait. On lui
        laisse la place, et la grille ne se scinde qu'à partir de `xl` — en
        dessous, deux colonnes étroites valaient moins que deux pleines largeurs.
      */}
      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_30rem]">
        <Card>
          <h2 className="mb-3 border-b border-navy-100/70 pb-2 font-display text-sm font-bold text-navy-900">
            Scolarité par classe
          </h2>

          <div className="mb-4 flex flex-wrap items-end gap-2 rounded-xl bg-cream-100 p-3">
            <Input
              label="Tarif par défaut de l'établissement"
              type="number"
              min={0}
              disabled={!modifiable}
              value={valeurChamp('defaut', data.tarif_par_defaut)}
              onChange={(e) => setBrouillons((b) => ({ ...b, defaut: e.target.value }))}
            />
            {modifiable && (
              <Button onClick={() => enregistrerTarif(null)} disabled={brouillons.defaut === undefined}>
                <Save className="h-4 w-4" />
                Enregistrer
              </Button>
            )}
          </div>

          <ul className="flex flex-col divide-y divide-navy-50">
            {data.classes.map((classe) => {
              const cle = String(classe.id)
              const modifie = brouillons[cle] !== undefined

              return (
                <li key={classe.id} className="flex flex-wrap items-center gap-3 py-2.5">
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold text-navy-900">{classe.nom}</div>
                    <div className="text-xs text-navy-400">
                      {classe.montant == null ? (
                        <>Suit le tarif par défaut</>
                      ) : (
                        <>Tarif propre</>
                      )}
                      {classe.dossiers_ouverts > 0 && (
                        <> · {classe.dossiers_ouverts} dossier(s) déjà ouvert(s)</>
                      )}
                    </div>
                  </div>

                  <div className="flex items-center gap-1.5">
                    <input
                      type="number"
                      min={0}
                      disabled={!modifiable}
                      placeholder={data.tarif_par_defaut != null ? String(data.tarif_par_defaut) : '—'}
                      value={valeurChamp(cle, classe.montant)}
                      onChange={(e) => setBrouillons((b) => ({ ...b, [cle]: e.target.value }))}
                      className="w-32 rounded-xl border border-navy-200 bg-white px-3 py-1.5 text-right text-sm tabular-nums shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100 disabled:bg-cream-100"
                    />
                    {modifiable && modifie && (
                      <Button size="sm" onClick={() => enregistrerTarif(classe.id)}>
                        <Save className="h-3.5 w-3.5" />
                      </Button>
                    )}
                    {modifiable && classe.montant != null && !modifie && (
                      <Button
                        size="sm"
                        variant="secondary"
                        title="Revenir au tarif par défaut"
                        onClick={() => retirerTarif(classe.id, classe.nom)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    )}
                  </div>
                </li>
              )
            })}
          </ul>
        </Card>

        <Card className="h-fit">
          <h2 className="mb-3 border-b border-navy-100/70 pb-2 font-display text-sm font-bold text-navy-900">
            Frais annexes
          </h2>

          <ul className="mb-4 flex flex-col divide-y divide-navy-50">
            {data.frais_annexes.length === 0 && (
              <li className="py-2 text-sm text-navy-400">Aucun frais annexe défini.</li>
            )}
            {data.frais_annexes.map((frais) => (
              <li key={frais.id} className="flex items-center gap-2 py-2.5">
                <div className="min-w-0 flex-1">
                  <div className="truncate text-sm font-semibold text-navy-900">{frais.libelle}</div>
                  <div className="flex flex-wrap items-center gap-1.5 text-xs text-navy-400">
                    {/*
                      Dans un conteneur flex, un montant laissé en texte nu
                      devient un élément anonyme que la compression casse mot à
                      mot — « 5 / 000 / F ». Un span insécable règle la coupure.
                    */}
                    <span className="whitespace-nowrap tabular-nums">{francs(frais.montant)}</span>
                    {/*
                      L'état courant est toujours affiché, y compris quand il
                      vaut « facultatif » : sans cela, seul le bouton d'action
                      restait à lire, et son libellé désigne l'état visé.
                    */}
                    <Badge tone={frais.obligatoire ? 'gold' : 'neutral'}>
                      {frais.obligatoire ? 'Obligatoire' : 'Facultatif'}
                    </Badge>
                    {!frais.is_active && <Badge tone="neutral">Désactivé</Badge>}
                  </div>
                </div>
                {modifiable && frais.is_active && (
                  // `flex-none` : les actions gardent leur largeur, c'est le
                  // libellé qui cède s'il faut tronquer.
                  <div className="flex flex-none items-center gap-1.5">
                    <Button
                      size="sm"
                      variant="secondary"
                      title={frais.obligatoire ? 'Rendre facultatif' : 'Rendre obligatoire'}
                      onClick={() =>
                        agir(
                          () => modifierFraisAnnexe(frais.id, { obligatoire: !frais.obligatoire }),
                          'Frais annexe mis à jour.',
                        )
                      }
                    >
                      {/* Un verbe : le bouton dit ce qu'il fait, pas un état. */}
                      {frais.obligatoire ? 'Rendre facultatif' : 'Rendre obligatoire'}
                    </Button>
                    <Button
                      size="sm"
                      variant="danger"
                      title="Désactiver"
                      onClick={() => agir(() => desactiverFraisAnnexe(frais.id), 'Frais annexe désactivé.')}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  </div>
                )}
              </li>
            ))}
          </ul>

          {modifiable && (
            <div className="flex flex-col gap-2 rounded-xl bg-cream-100 p-3">
              <Input
                label="Nouveau frais"
                placeholder="Tenue scolaire, transport…"
                value={nouveauFrais.libelle}
                onChange={(e) => setNouveauFrais((f) => ({ ...f, libelle: e.target.value }))}
              />
              <Input
                label="Montant (F CFA)"
                type="number"
                min={0}
                value={nouveauFrais.montant}
                onChange={(e) => setNouveauFrais((f) => ({ ...f, montant: e.target.value }))}
              />
              {/*
                Le même mot qu'en liste — « obligatoire » — suivi de ce qu'il
                emporte. Nommer la case autrement laissait le lecteur sans lien
                entre ce qu'il coche ici et ce qu'il lit au-dessus.
              */}
              <label className="flex items-start gap-2 text-sm text-navy-700">
                <input
                  type="checkbox"
                  checked={nouveauFrais.obligatoire}
                  onChange={(e) => setNouveauFrais((f) => ({ ...f, obligatoire: e.target.checked }))}
                  className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300 text-navy-700 focus:ring-navy-200"
                />
                <span>
                  <span className="font-semibold">Frais obligatoire</span>
                  <span className="block text-xs text-navy-400">
                    Rattaché d'office au dossier de chaque nouvel élève. Sinon, il s'ajoute au cas par cas.
                  </span>
                </span>
              </label>
              <Button onClick={ajouterFrais} disabled={!nouveauFrais.libelle || !nouveauFrais.montant}>
                <Plus className="h-4 w-4" />
                Ajouter
              </Button>
            </div>
          )}
        </Card>
      </div>
    </div>
  )
}
