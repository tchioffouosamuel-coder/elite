import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Banknote, Building2, Calculator, FileText, RefreshCw, Users, Wallet } from 'lucide-react'
import {
  doterAmortissements,
  fetchAmortissements,
  reviserImmobilisation,
  type DotationAmortissement,
  fetchEtatSynthese,
  fetchExercices,
  fetchPrelevementsEleve,
  fetchSerieExercices,
  francs,
  regulariserPrelevements,
  type LigneEtat,
} from '@/features/finance/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Card, StatCard } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Tabs } from '@/shared/ui/Tabs'
import { erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import type { ApiError } from '@/shared/types/api'

/**
 * État de synthèse des charges et dépenses.
 *
 * L'écran rend deux lectures d'un même exercice. « Le document » reproduit le
 * classeur de l'établissement, colonne pour colonne, pour qu'il se rapproche à
 * l'œil. « Exploitation » sépare ce qui use l'exercice de la construction des
 * bâtiments et des apports du fondateur — ce que le document additionne sans
 * les distinguer, et qui change le signe du solde.
 */
export function EtatSynthesePage() {
  const user = useAuthStore((s) => s.user)
  const activeSchool = useAuthStore((s) => s.activeSchool)
  const queryClient = useQueryClient()

  // Le document est par établissement : en mode agrégé, on retient la première
  // école accessible plutôt que d'additionner deux classeurs distincts.
  const schoolId = activeSchool()?.id ?? user?.ecoles_accessibles?.[0]?.id ?? null

  const [exerciceId, setExerciceId] = useState<number | null>(null)
  const [onglet, setOnglet] = useState<'document' | 'exploitation' | 'serie'>('document')

  const { data: exercices } = useQuery({
    queryKey: ['etat-synthese-exercices', schoolId],
    queryFn: () => fetchExercices(schoolId as number),
    enabled: schoolId !== null,
  })

  useEffect(() => {
    if (exerciceId === null && exercices?.length) {
      setExerciceId(exercices.find((e) => e.is_active)?.id ?? exercices[0].id)
    }
  }, [exercices, exerciceId])

  const pret = schoolId !== null && exerciceId !== null

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['etat-synthese', schoolId, exerciceId],
    queryFn: () => fetchEtatSynthese(schoolId as number, exerciceId as number),
    enabled: pret,
  })

  if (schoolId === null) {
    return <EmptyState label="Aucun établissement accessible." />
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="État de synthèse"
        sousTitre="Charges, dépenses et produits de l'exercice, tels que les tient l'établissement."
        icon={Calculator}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <Select
              value={exerciceId ?? ''}
              onChange={(e) => setExerciceId(Number(e.target.value))}
              className="w-48"
              aria-label="Exercice"
            >
              {exercices?.map((ex) => (
                <option key={ex.id} value={ex.id}>
                  {ex.libelle}
                </option>
              ))}
            </Select>
            {/* L'état se signe et se compare au classeur : il doit sortir sur papier. */}
            <Button
              variant="secondary"
              disabled={!exerciceId}
              onClick={() =>
                ouvrirDocument('/etat-synthese/pdf', {
                  school_id: schoolId as number,
                  annee_scolaire_id: exerciceId as number,
                })
              }
            >
              <FileText className="h-4 w-4" />
              Exercice
            </Button>
            <Button
              variant="secondary"
              onClick={() => ouvrirDocument('/etat-synthese/serie/pdf', { school_id: schoolId as number })}
            >
              <FileText className="h-4 w-4" />
              Série
            </Button>
          </div>
        }
      />

      {isLoading || !pret ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState message={error?.message} />
      ) : (
        <>
          <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Effectif de l'exercice" value={data.exercice.effectif} icon={Users} accent="navy" />
            <StatCard label="Total recettes" value={francs(data.document.total_recettes)} icon={Banknote} accent="green" />
            <StatCard label="Total dépenses" value={francs(data.document.total_depenses)} icon={Wallet} accent="gold" />
            <StatCard
              label="Balance de fin d'exercice"
              value={francs(data.document.balance)}
              icon={Calculator}
              accent={data.document.balance < 0 ? 'red' : 'green'}
              hint={data.document.balance < 0 ? 'Déficit au sens du document' : 'Excédent au sens du document'}
            />
          </div>

          <Tabs
            active={onglet}
            onChange={(cle) => setOnglet(cle as typeof onglet)}
            tabs={[
              { key: 'document', label: 'Le document' },
              { key: 'exploitation', label: 'Exploitation' },
              { key: 'serie', label: 'Tous les exercices' },
            ]}
          />

          {onglet === 'document' && <VueDocument etat={data} />}
          {onglet === 'exploitation' && (
            <>
              <VueExploitation etat={data} />
              <AmortissementsCard
                schoolId={schoolId}
                exerciceId={exerciceId as number}
                onDote={() => {
                  queryClient.invalidateQueries({ queryKey: ['etat-synthese'] })
                  queryClient.invalidateQueries({ queryKey: ['amortissements'] })
                }}
              />
            </>
          )}
          {onglet === 'serie' && <VueSerie schoolId={schoolId} />}

          {onglet === 'document' && (
            <PrelevementsCard
              schoolId={schoolId}
              exerciceId={exerciceId as number}
              onRegularise={() => {
                queryClient.invalidateQueries({ queryKey: ['etat-synthese'] })
                queryClient.invalidateQueries({ queryKey: ['prelevements-eleve'] })
              }}
            />
          )}
        </>
      )}
    </div>
  )
}

// ------------------------------------------------------------- le document

type Etat = NonNullable<Awaited<ReturnType<typeof fetchEtatSynthese>>>

function VueDocument({ etat }: { etat: Etat }) {
  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <ColonneComptes titre="Libellés des dépenses" lignes={etat.depenses} total={etat.document.total_depenses} />
      <div className="flex flex-col gap-4">
        <ColonneComptes titre="Libellés des produits" lignes={etat.produits} total={etat.document.total_recettes} />
        <Card>
          <div className="flex items-center justify-between gap-3">
            <span className="text-sm font-bold uppercase tracking-wide text-navy-500">
              Balance de fin d'exercice
            </span>
            <span
              className={
                etat.document.balance < 0
                  ? 'font-display text-xl font-bold tabular-nums text-red-600'
                  : 'font-display text-xl font-bold tabular-nums text-green-600'
              }
            >
              {francs(etat.document.balance)}
            </span>
          </div>
          {etat.document.apport_fondateur > 0 && (
            <div className="mt-3 flex items-center justify-between gap-3 border-t border-navy-50 pt-3 text-sm">
              <span className="text-navy-500">Apport personnel du fondateur</span>
              <span className="font-semibold tabular-nums text-navy-800">{francs(etat.document.apport_fondateur)}</span>
            </div>
          )}
        </Card>
      </div>
    </div>
  )
}

function ColonneComptes({ titre, lignes, total }: { titre: string; lignes: LigneEtat[]; total: number }) {
  // Les comptes non mouvementés restent affichés : le document les liste tous,
  // et c'est cette grille constante qui rend les exercices comparables.
  return (
    <Card>
      <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-navy-500">{titre}</h2>
      <div className="overflow-x-auto">
        <table className="w-full text-sm tabular-nums">
          <tbody className="divide-y divide-navy-50">
            {lignes.map((ligne) => (
              <tr key={ligne.code} className={ligne.montant === 0 ? 'text-navy-300' : ''}>
                <td className="w-14 py-1.5 pr-2 font-mono text-xs text-navy-400">{ligne.code}</td>
                <td className="py-1.5 pr-3">
                  {ligne.libelle}
                  {ligne.assiette === 'par_eleve' && (
                    <span className="ml-1.5 text-[10px] font-semibold uppercase tracking-wide text-gold-700">
                      {ligne.montant_unitaire} F/élève
                    </span>
                  )}
                  {ligne.nature !== 'exploitation' && (
                    <span className="ml-1.5 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                      {ligne.nature}
                    </span>
                  )}
                </td>
                <td className="py-1.5 text-right font-medium">{ligne.montant === 0 ? '—' : francs(ligne.montant)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr className="border-t-2 border-navy-800">
              <td colSpan={2} className="py-2 text-sm font-bold uppercase tracking-wide text-navy-800">
                Total
              </td>
              <td className="py-2 text-right font-display text-base font-bold text-navy-900">{francs(total)}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </Card>
  )
}

// ---------------------------------------------------------- l'exploitation

function VueExploitation({ etat }: { etat: Etat }) {
  const { analytique, document } = etat
  const ecart = analytique.resultat_exploitation - document.balance

  return (
    <div className="flex flex-col gap-4">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Résultat d'exploitation"
          value={francs(analytique.resultat_exploitation)}
          icon={Calculator}
          accent={analytique.resultat_exploitation < 0 ? 'red' : 'green'}
          hint="Produits moins charges de l'année"
        />
        <StatCard
          label="Investissement"
          value={francs(analytique.investissement)}
          icon={Building2}
          accent="navy"
          hint="Construction : un actif, pas une charge"
        />
        <StatCard
          label="Mouvements de capital"
          value={francs(analytique.capital)}
          icon={Wallet}
          accent="gold"
          hint="Dépôts et apports de l'exploitant"
        />
        <StatCard
          label="Balance du document"
          value={francs(document.balance)}
          icon={Banknote}
          accent={document.balance < 0 ? 'red' : 'green'}
          hint="Les trois additionnés"
        />
      </div>

      <Card>
        <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-navy-500">Du document au résultat</h2>
        <div className="flex flex-col divide-y divide-navy-50 text-sm">
          <Ligne libelle="Balance de fin d'exercice (document)" montant={document.balance} />
          <Ligne libelle="+ Construction reclassée en investissement" montant={analytique.investissement} />
          <Ligne libelle="+ Dépôts et apports de l'exploitant" montant={analytique.capital} />
          <div className="flex items-center justify-between gap-3 border-t-2 border-navy-800 py-2.5">
            <span className="font-bold uppercase tracking-wide text-navy-800">Résultat d'exploitation</span>
            <span
              className={
                analytique.resultat_exploitation < 0
                  ? 'font-display text-lg font-bold tabular-nums text-red-600'
                  : 'font-display text-lg font-bold tabular-nums text-green-600'
              }
            >
              {francs(analytique.resultat_exploitation)}
            </span>
          </div>
        </div>

        {ecart !== 0 && (
          <p className="mt-3 rounded-lg bg-cream-100 px-3 py-2 text-xs text-navy-500">
            L'écart de <span className="font-semibold text-navy-800">{francs(ecart)}</span> entre les deux lectures ne
            vient pas de l'activité : c'est ce que l'établissement a bâti et déposé cette année, que le document porte en
            dépense.
          </p>
        )}
      </Card>
    </div>
  )
}

function Ligne({ libelle, montant }: { libelle: string; montant: number }) {
  return (
    <div className="flex items-center justify-between gap-3 py-2">
      <span className="text-navy-600">{libelle}</span>
      <span className="font-semibold tabular-nums text-navy-800">{francs(montant)}</span>
    </div>
  )
}

// ------------------------------------------------------------ la série

function VueSerie({ schoolId }: { schoolId: number }) {
  const { data, isLoading } = useQuery({
    queryKey: ['etat-synthese-serie', schoolId],
    queryFn: () => fetchSerieExercices(schoolId),
  })

  if (isLoading) return <Spinner />
  if (!data?.length) return <EmptyState label="Aucun exercice enregistré." />

  return (
    <Card>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[46rem] text-sm tabular-nums">
          <thead>
            <tr className="border-b border-navy-100 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
              <th className="py-2 pr-3 text-left">Exercice</th>
              <th className="py-2 pr-3 text-right">Effectif</th>
              <th className="py-2 pr-3 text-right">Recettes</th>
              <th className="py-2 pr-3 text-right">Dépenses</th>
              <th className="py-2 pr-3 text-right">Balance</th>
              <th className="py-2 pr-3 text-right">Investissement</th>
              <th className="py-2 text-right">Résultat exploitation</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-navy-50">
            {data.map((ex) => (
              <tr key={ex.annee_scolaire_id}>
                <td className="py-2 pr-3 font-semibold text-navy-800">{ex.libelle}</td>
                <td className="py-2 pr-3 text-right text-navy-500">{ex.effectif}</td>
                <td className="py-2 pr-3 text-right">{francs(ex.total_recettes)}</td>
                <td className="py-2 pr-3 text-right">{francs(ex.total_depenses)}</td>
                <td className={ex.balance < 0 ? 'py-2 pr-3 text-right font-semibold text-red-600' : 'py-2 pr-3 text-right font-semibold text-green-600'}>
                  {francs(ex.balance)}
                </td>
                <td className="py-2 pr-3 text-right text-navy-500">{francs(ex.investissement)}</td>
                <td
                  className={
                    ex.resultat_exploitation < 0
                      ? 'py-2 text-right font-semibold text-red-600'
                      : 'py-2 text-right font-semibold text-green-600'
                  }
                >
                  {francs(ex.resultat_exploitation)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}

// ------------------------------------------------- prélèvements par élève

function PrelevementsCard({
  schoolId,
  exerciceId,
  onRegularise,
}: {
  schoolId: number
  exerciceId: number
  onRegularise: () => void
}) {
  const can = useAuthStore((s) => s.can)
  const [enCours, setEnCours] = useState(false)

  const { data } = useQuery({
    queryKey: ['prelevements-eleve', schoolId, exerciceId],
    queryFn: () => fetchPrelevementsEleve(schoolId, exerciceId),
  })

  const aRegulariser = useMemo(() => (data ?? []).some((l) => l.ecart !== 0), [data])

  const regulariser = async () => {
    setEnCours(true)
    try {
      const { message } = await regulariserPrelevements(schoolId, exerciceId)
      succes(message || 'Prélèvements régularisés.')
      onRegularise()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  if (!data?.length) return null

  return (
    <Card>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-bold uppercase tracking-wide text-navy-500">Prélèvements sur effectif</h2>
          <p className="mt-0.5 text-xs text-navy-400">
            Ces trois charges ne s'arbitrent pas : elles suivent l'effectif au tarif du compte.
          </p>
        </div>
        {can('finance.depenses') && aRegulariser && (
          <Button onClick={regulariser} disabled={enCours}>
            <RefreshCw className="h-4 w-4" />
            Régulariser
          </Button>
        )}
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[34rem] text-sm tabular-nums">
          <thead>
            <tr className="border-b border-navy-100 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
              <th className="py-2 pr-3 text-left">Compte</th>
              <th className="py-2 pr-3 text-right">Effectif</th>
              <th className="py-2 pr-3 text-right">Tarif</th>
              <th className="py-2 pr-3 text-right">Dû</th>
              <th className="py-2 pr-3 text-right">Enregistré</th>
              <th className="py-2 text-right">Écart</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-navy-50">
            {data.map((ligne) => (
              <tr key={ligne.code}>
                <td className="py-2 pr-3">
                  <span className="font-mono text-xs text-navy-400">{ligne.code}</span>{' '}
                  <span className="text-navy-700">{ligne.libelle}</span>
                </td>
                <td className="py-2 pr-3 text-right text-navy-500">{ligne.effectif}</td>
                <td className="py-2 pr-3 text-right text-navy-500">{francs(ligne.montant_unitaire)}</td>
                <td className="py-2 pr-3 text-right font-semibold text-navy-800">{francs(ligne.du)}</td>
                <td className="py-2 pr-3 text-right text-navy-500">{francs(ligne.enregistre)}</td>
                <td className={ligne.ecart === 0 ? 'py-2 text-right text-navy-300' : 'py-2 text-right font-semibold text-gold-700'}>
                  {ligne.ecart === 0 ? 'à jour' : francs(ligne.ecart)}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}

// ---------------------------------------------------------- amortissements

/**
 * Le compte 624 mêle construction et réfection : vingt ans conviennent à un
 * bâtiment, pas à une toiture. La durée se corrige donc bien par bien, sans
 * toucher aux annuités déjà passées.
 */
function DureeAmortissement({
  ligne,
  modifiable,
  onRevise,
}: {
  ligne: DotationAmortissement
  modifiable: boolean
  onRevise: () => void
}) {
  const [edition, setEdition] = useState(false)
  const [duree, setDuree] = useState(String(ligne.duree_annees))

  const enregistrer = async () => {
    const valeur = Number(duree)
    if (!valeur || valeur === ligne.duree_annees) {
      setEdition(false)
      return
    }

    try {
      await reviserImmobilisation(ligne.immobilisation_id, { duree_annees: valeur })
      succes('Durée mise à jour.')
      setEdition(false)
      onRevise()
    } catch (e) {
      erreur((e as ApiError).message)
      setDuree(String(ligne.duree_annees))
    }
  }

  if (!modifiable) return <span className="text-navy-500">{ligne.duree_annees} ans</span>

  if (!edition) {
    return (
      <button
        type="button"
        onClick={() => setEdition(true)}
        className="rounded px-1.5 py-0.5 text-navy-600 underline decoration-dotted underline-offset-2 hover:bg-navy-50"
      >
        {ligne.duree_annees} ans
      </button>
    )
  }

  return (
    <input
      autoFocus
      type="number"
      min={1}
      max={60}
      value={duree}
      onChange={(e) => setDuree(e.target.value)}
      onBlur={enregistrer}
      onKeyDown={(e) => {
        if (e.key === 'Enter') enregistrer()
        if (e.key === 'Escape') {
          setDuree(String(ligne.duree_annees))
          setEdition(false)
        }
      }}
      className="w-16 rounded border border-navy-200 px-1.5 py-0.5 text-right tabular-nums"
    />
  )
}

function AmortissementsCard({
  schoolId,
  exerciceId,
  onDote,
}: {
  schoolId: number
  exerciceId: number
  onDote: () => void
}) {
  const can = useAuthStore((s) => s.can)
  const [enCours, setEnCours] = useState(false)

  const { data } = useQuery({
    queryKey: ['amortissements', schoolId, exerciceId],
    queryFn: () => fetchAmortissements(schoolId, exerciceId),
  })

  const aDoter = useMemo(() => (data ?? []).some((l) => !l.deja_dote && l.dotation > 0), [data])

  const doter = async () => {
    setEnCours(true)
    try {
      const { message } = await doterAmortissements(schoolId, exerciceId)
      succes(message || 'Dotations enregistrées.')
      onDote()
    } catch (e) {
      erreur((e as ApiError).message)
    } finally {
      setEnCours(false)
    }
  }

  if (!data?.length) return null

  return (
    <Card>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-sm font-bold uppercase tracking-wide text-navy-500">Amortissements de l'exercice</h2>
          <p className="mt-0.5 text-xs text-navy-400">
            Ce que la construction rend au résultat cette année, étalé sur la durée du bien.
          </p>
        </div>
        {can('finance.depenses') && aDoter && (
          <Button onClick={doter} disabled={enCours}>
            <Building2 className="h-4 w-4" />
            Passer les dotations
          </Button>
        )}
      </div>

      <div className="overflow-x-auto">
        <table className="w-full min-w-[38rem] text-sm tabular-nums">
          <thead>
            <tr className="border-b border-navy-100 text-[10px] font-semibold uppercase tracking-wide text-navy-400">
              <th className="py-2 pr-3 text-left">Bien</th>
              <th className="py-2 pr-3 text-right">Valeur</th>
              <th className="py-2 pr-3 text-right">Déjà amorti</th>
              <th className="py-2 pr-3 text-right">Reste à étaler</th>
              <th className="py-2 pr-3 text-right">Durée</th>
              <th className="py-2 text-right">Dotation</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-navy-50">
            {data.map((ligne) => (
              <tr key={ligne.immobilisation_id}>
                <td className="py-2 pr-3 text-navy-700">{ligne.libelle}</td>
                <td className="py-2 pr-3 text-right">{francs(ligne.montant)}</td>
                <td className="py-2 pr-3 text-right text-navy-500">{francs(ligne.cumul)}</td>
                <td className="py-2 pr-3 text-right text-navy-500">{francs(ligne.valeur_residuelle)}</td>
                <td className="py-2 pr-3 text-right">
                  <DureeAmortissement ligne={ligne} modifiable={can('finance.depenses')} onRevise={onDote} />
                </td>
                <td
                  className={
                    ligne.deja_dote
                      ? 'py-2 text-right font-semibold text-green-600'
                      : 'py-2 text-right font-semibold text-gold-700'
                  }
                >
                  {francs(ligne.dotation)}
                  <span className="ml-1.5 text-[10px] font-medium uppercase tracking-wide text-navy-400">
                    {ligne.deja_dote ? 'passée' : 'à passer'}
                  </span>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}
