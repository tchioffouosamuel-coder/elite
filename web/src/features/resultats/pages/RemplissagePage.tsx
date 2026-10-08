import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueries, useQuery } from '@tanstack/react-query'
import { ArrowLeft, ListChecks } from 'lucide-react'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { fetchTrimestres, fetchTrimestresPourClasse } from '@/features/pedagogie/api'
import { SaisieSequencesPanel } from './SaisieSequencesPanel'
import { fetchRemplissage, type Remplissage } from '@/features/resultats/api'
import { fetchMesAffectationsActives } from '@/features/pedagogie/api'
import { fetchMesCompetences } from '@/features/enseignant/api'
import { NotesTab } from '@/features/notes/pages/NotesTab'
import { NotesPrimaireTab } from '@/features/primaire/pages/NotesPrimaireTab'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { triEcoleSousSystemeNiveauClasse } from '@/shared/lib/triHierarchique'

/** Vert au-delà de ce taux, rouge en dessous de la moitié — repère visuel de _smapp. */
function couleurTaux(taux: number): string {
  if (taux >= 90) return 'bg-green-500'
  if (taux >= 50) return 'bg-gold-500'
  return 'bg-red-500'
}

interface LigneClasse {
  id: number
  nom: string
  nbUnites: number | null
  responsable: string | null
  taux: number | null
  ecole?: string
  sousSysteme?: string
  niveau?: string
}

export function RemplissagePage() {
  const { t } = useTranslation()
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const secondaireEcole = useAuthStore((s) => estSecondaire(s.activeSchool()?.type))
  const [classeId, setClasseId] = useState<number | ''>('')
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [matiereSelectionnee, setMatiereSelectionnee] = useState<number | null>(null)

  // Au primaire et en maternelle, une compétence peut être attribuée sans
  // matière associée : les classes doivent venir des compétences elles-mêmes.
  const { data: toutesLesClasses } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), enabled: !estEnseignant })
  const affectationsMatieres = useQuery({
    queryKey: ['mes-affectations-actives'],
    queryFn: fetchMesAffectationsActives,
    enabled: estEnseignant && secondaireEcole,
  })
  const affectationsCompetences = useQuery({
    queryKey: ['enseignant-mes-competences'],
    queryFn: fetchMesCompetences,
    enabled: estEnseignant && !secondaireEcole,
  })
  const affectationsEnseignant = secondaireEcole ? affectationsMatieres : affectationsCompetences
  const mesAffectations = affectationsEnseignant.data
  const { data: trimestres } = useQuery({
    queryKey: classeId ? ['trimestres', Number(classeId)] : ['trimestres'],
    queryFn: () => classeId ? fetchTrimestresPourClasse(Number(classeId)) : fetchTrimestres(),
  })

  const classesEnseignant = useMemo(
    () => [...new Map((mesAffectations ?? []).map((a) => [a.classe_id, { id: a.classe_id, nom: a.classe }])).values()],
    [mesAffectations],
  )
  const classes: (Classe | { id: number; nom: string })[] = estEnseignant ? classesEnseignant : (toutesLesClasses ?? [])

  // Un seul rattachement : rien à choisir, la classe est déjà connue — la
  // liste n'aurait qu'une ligne et n'apporterait rien, on ouvre directement le détail.
  useEffect(() => {
    if (estEnseignant && classeId === '' && classesEnseignant.length === 1) {
      setClasseId(classesEnseignant[0].id)
    }
  }, [estEnseignant, classesEnseignant, classeId])

  const classeActive = classeId ? Number(classeId) : null
  const trimestreActif = trimestreId ? Number(trimestreId) : undefined
  // Un enseignant reste toujours sur sa propre école (jamais en mode agrégé) :
  // pour lui seul, le type global reste un raccourci valide.
  const classeActiveObjet = classes.find((c) => c.id === classeActive) as Classe | undefined
  const secondaireClasseActive = estSecondaire(classeActiveObjet?.school?.type)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['remplissage', classeActive, trimestreId],
    queryFn: () => fetchRemplissage(classeActive!, trimestreActif, classeActiveObjet?.school?.type),
    enabled: classeActive !== null,
  })

  // Vue liste : un aperçu du remplissage de chaque classe, une requête par
  // classe (même clé de cache que la vue détail — pas de double appel en
  // rouvrant une classe déjà vue).
  const remplissageParClasse = useQueries({
    queries: classes.map((c) => ({
      queryKey: ['remplissage', c.id, trimestreId],
      queryFn: () => fetchRemplissage(c.id, trimestreActif, (c as Classe).school?.type),
      enabled: classeActive === null,
    })),
  })

  const lignesClasses: LigneClasse[] = useMemo(
    () =>
      classes.map((c, i) => {
        const unites = remplissageParClasse[i]?.data?.unites
        const taux = unites && unites.length > 0 ? unites.reduce((s, m) => s + m.taux, 0) / unites.length : null
        const responsable = !estEnseignant
          ? estSecondaire((c as Classe).school?.type)
            ? ((c as Classe).professeur_principal?.nom_complet ?? null)
            : ((c as Classe).titulaire?.nom_complet ?? null)
          : null
        const ecole = !estEnseignant ? (c as Classe).school?.name : undefined
        const sousSysteme = !estEnseignant ? (c as Classe).sous_systeme?.nom : undefined
        const niveau = !estEnseignant ? (c as Classe).niveau?.name_fr : undefined

        return {
          id: c.id,
          nom: c.nom,
          nbUnites: unites ? unites.length : null,
          responsable,
          taux,
          ecole,
          sousSysteme,
          niveau,
        }
      }),
    [classes, remplissageParClasse, estEnseignant],
  )

  const triClasses = triEcoleSousSystemeNiveauClasse<LigneClasse>({
    ecole: (l) => l.ecole,
    sousSysteme: (l) => l.sousSysteme,
    niveau: (l) => l.niveau,
    classe: (l) => l.nom,
  })

  const colonnesClasses: Colonne<LigneClasse>[] = [
    {
      cle: 'nom',
      entete: 'Classe',
      valeur: (l) => l.nom,
      cellule: (l) => <span className="font-semibold text-navy-900">{l.nom}</span>,
    },
    {
      cle: 'nbUnites',
      entete: t('resultats.elements_suivis'),
      valeur: (l) => l.nbUnites,
      cellule: (l) => <span className="tabular-nums">{l.nbUnites ?? '—'}</span>,
    },
    ...(!estEnseignant
      ? [
          {
            cle: 'responsable',
            entete: 'Responsable',
            valeur: (l: LigneClasse) => l.responsable,
            cellule: (l: LigneClasse) => l.responsable ?? '—',
          } satisfies Colonne<LigneClasse>,
        ]
      : []),
    {
      cle: 'taux',
      entete: 'Remplissage',
      valeur: (l) => l.taux,
      cellule: (l) =>
        l.taux === null ? (
          <span className="text-navy-300">…</span>
        ) : (
          <div className="flex items-center gap-2">
            <div className="h-2 w-28 flex-none overflow-hidden rounded-full bg-navy-100">
              <div className={`h-full rounded-full ${couleurTaux(l.taux)}`} style={{ width: `${Math.min(l.taux, 100)}%` }} />
            </div>
            <span className="text-xs font-semibold text-navy-700">{l.taux.toFixed(1)} %</span>
          </div>
        ),
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (l) => (
        <Button size="sm" variant="secondary" onClick={() => setClasseId(l.id)}>
          Détail
        </Button>
      ),
      className: 'text-right',
      largeur: '110px',
    },
  ]

  const libelleUnites = secondaireClasseActive ? t('matieres.title') : t('competences.title')
  const messageVideUnites = secondaireClasseActive
    ? t('progression.aucune_matiere_classe')
    : t('competences.aucune_dans_classe')

  const colonnesUnites: Colonne<Remplissage['unites'][number]>[] = [
    {
      cle: 'unite',
      entete: libelleUnites,
      valeur: (ligne) => ligne.libelle,
      cellule: (ligne) => <span className="font-medium">{ligne.libelle}</span>,
    },
    ...(estEnseignant
      ? []
      : [
        {
          cle: 'enseignant',
          entete: 'Enseignant',
          valeur: (ligne: Remplissage['unites'][number]) => ligne.enseignant,
          cellule: (ligne: Remplissage['unites'][number]) => ligne.enseignant ?? '—',
        } satisfies Colonne<Remplissage['unites'][number]>,
      ]),
    {
      cle: 'volets',
      entete: 'Total volets',
      valeur: (ligne) => ligne.volets?.length ?? null,
      cellule: (ligne) => <span className="text-center font-semibold">{ligne.volets ? ligne.volets.length : '—'}</span>,
    },
    {
      cle: 'avancement',
      entete: 'Avancement',
      cellule: (ligne) => (
        <div className="h-2 w-40 overflow-hidden rounded-full bg-navy-100">
          <div className={`h-full rounded-full ${couleurTaux(ligne.taux)}`} style={{ width: `${Math.min(ligne.taux, 100)}%` }} />
        </div>
      ),
    },
    {
      cle: 'taux',
      entete: 'Taux',
      valeur: (ligne) => ligne.taux,
      cellule: (ligne) => <span className="font-semibold">{ligne.taux.toFixed(1)} %</span>,
    },
  ]

  if (matiereSelectionnee && classeActive !== null) {
    return (
      <div className="flex flex-col gap-4">
        <SaisieSequencesPanel trimestres={trimestres ?? []} />
        {secondaireClasseActive ? (
          <NotesTab
            classeId={classeActive}
            initialMatiereId={matiereSelectionnee}
            onBack={() => setMatiereSelectionnee(null)}
          />
        ) : (
          <NotesPrimaireTab
            classeId={classeActive}
            initialMatiereId={matiereSelectionnee}
            onBack={() => setMatiereSelectionnee(null)}
          />
        )}
      </div>
    )
  }

  const classeActiveNom = classes.find((c) => c.id === classeActive)?.nom

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center gap-3">
        <ListChecks className="h-6 w-6 text-gold-500" />
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">
          État de remplissage des notes
        </h1>
      </div>

      <div className="flex flex-wrap items-end gap-3">
        {classeActive !== null && (
          <button
            onClick={() => setClasseId('')}
            className="mb-0.5 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-800"
          >
            <ArrowLeft className="h-4 w-4" />
            Retour aux classes
          </button>
        )}

        <Select
          label="Trimestre"
          value={trimestreId}
          onChange={(e) => setTrimestreId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">Trimestre actif</option>
          {trimestres?.map((t) => (
            <option key={t.id} value={t.id}>
              {t.libelle}
            </option>
          ))}
        </Select>
      </div>

      <SaisieSequencesPanel trimestres={trimestres ?? []} />

      {estEnseignant && affectationsEnseignant.isLoading ? (
        <Spinner />
      ) : estEnseignant && affectationsEnseignant.isError ? (
        <ErrorState />
      ) : estEnseignant && classesEnseignant.length === 0 ? (
        <Card>
          <EmptyState
            label={t(secondaireEcole ? 'resultats.aucune_matiere_enseignant' : 'resultats.aucune_competence_enseignant')}
          />
        </Card>
      ) : classeActive === null ? (
        <DataTable
          colonnes={colonnesClasses}
          lignes={lignesClasses}
          cleLigne={(l) => l.id}
          placeholderRecherche={t('resultats.search_classe')}
          messageVide={t('resultats.empty_classe')}
          onLigneClick={(l) => setClasseId(l.id)}
          triDefaut={triClasses}
        />
      ) : isLoading ? (
        <Spinner />
      ) : isError ? (
        <ErrorState />
      ) : !data?.unites.length ? (
        <Card>
          <EmptyState label={messageVideUnites} />
        </Card>
      ) : (
        <>
          <h2 className="-mt-2 text-sm font-semibold text-navy-600">{classeActiveNom}</h2>
          <DataTable
            colonnes={colonnesUnites}
            lignes={data.unites}
            cleLigne={(ligne) => ligne.id}
            placeholderRecherche={secondaireClasseActive ? t('matieres.search_placeholder') : t('competences.recherche')}
            messageVide={t('common.no_results')}
            onLigneClick={(ligne) => setMatiereSelectionnee(ligne.id)}
          />
        </>
      )}
    </div>
  )
}
