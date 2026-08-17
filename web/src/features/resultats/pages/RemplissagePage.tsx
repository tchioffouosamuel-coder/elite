import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueries, useQuery } from '@tanstack/react-query'
import { ArrowLeft, ListChecks } from 'lucide-react'
import { fetchClasses, type Classe } from '@/features/classes/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { fetchRemplissage, type Remplissage } from '@/features/resultats/api'
import { fetchMesAffectationsActives } from '@/features/pedagogie/api'
import { NotesTab } from '@/features/notes/pages/NotesTab'
import { NotesPrimaireTab } from '@/features/primaire/pages/NotesPrimaireTab'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'

/** Vert au-delà de ce taux, rouge en dessous de la moitié — repère visuel de _smapp. */
function couleurTaux(taux: number): string {
  if (taux >= 90) return 'bg-green-500'
  if (taux >= 50) return 'bg-gold-500'
  return 'bg-red-500'
}

interface LigneClasse {
  id: number
  nom: string
  nbMatieres: number | null
  responsable: string | null
  taux: number | null
}

export function RemplissagePage() {
  const { t } = useTranslation()
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const secondaire = estSecondaire()
  const [classeId, setClasseId] = useState<number | ''>('')
  const [trimestreId, setTrimestreId] = useState<number | ''>('')
  const [matiereSelectionnee, setMatiereSelectionnee] = useState<number | null>(null)

  // Un enseignant ne doit choisir que parmi les classes où il intervient
  // (titulaire ou matière affectée) — pas la liste complète de l'établissement,
  // qui n'aurait pour lui aucun sens et exposerait des classes hors de son périmètre.
  const { data: toutesLesClasses } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), enabled: !estEnseignant })
  const { data: mesAffectations } = useQuery({
    queryKey: ['mes-affectations-actives'],
    queryFn: fetchMesAffectationsActives,
    enabled: estEnseignant,
  })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })

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

  const { data, isLoading } = useQuery({
    queryKey: ['remplissage', classeActive, trimestreId],
    queryFn: () => fetchRemplissage(classeActive!, trimestreActif),
    enabled: classeActive !== null,
  })

  // Vue liste : un aperçu du remplissage de chaque classe, une requête par
  // classe (même clé de cache que la vue détail — pas de double appel en
  // rouvrant une classe déjà vue).
  const remplissageParClasse = useQueries({
    queries: classes.map((c) => ({
      queryKey: ['remplissage', c.id, trimestreId],
      queryFn: () => fetchRemplissage(c.id, trimestreActif),
      enabled: classeActive === null,
    })),
  })

  const lignesClasses: LigneClasse[] = useMemo(
    () =>
      classes.map((c, i) => {
        const matieres = remplissageParClasse[i]?.data?.matieres
        const taux = matieres && matieres.length > 0 ? matieres.reduce((s, m) => s + m.taux, 0) / matieres.length : null
        const responsable = !estEnseignant
          ? secondaire
            ? ((c as Classe).professeur_principal?.nom_complet ?? null)
            : ((c as Classe).titulaire?.nom_complet ?? null)
          : null

        return { id: c.id, nom: c.nom, nbMatieres: matieres ? matieres.length : null, responsable, taux }
      }),
    [classes, remplissageParClasse, estEnseignant, secondaire],
  )

  const colonnesClasses: Colonne<LigneClasse>[] = [
    {
      cle: 'nom',
      entete: 'Classe',
      valeur: (l) => l.nom,
      cellule: (l) => <span className="font-semibold text-navy-900">{l.nom}</span>,
    },
    {
      cle: 'nbMatieres',
      entete: 'Matières',
      valeur: (l) => l.nbMatieres,
      cellule: (l) => <span className="tabular-nums">{l.nbMatieres ?? '—'}</span>,
    },
    ...(!estEnseignant
      ? [
          {
            cle: 'responsable',
            entete: secondaire ? 'Professeur principal' : 'Enseignant',
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

  const colonnesMatieres: Colonne<Remplissage['matieres'][number]>[] = [
    {
      cle: 'matiere',
      entete: 'Matière',
      valeur: (ligne) => ligne.matiere,
      cellule: (ligne) => <span className="font-medium">{ligne.matiere}</span>,
    },
    ...(estEnseignant
      ? []
      : [
        {
          cle: 'enseignant',
          entete: 'Enseignant',
          valeur: (ligne: Remplissage['matieres'][number]) => ligne.enseignant,
          cellule: (ligne: Remplissage['matieres'][number]) => ligne.enseignant ?? '—',
        } satisfies Colonne<Remplissage['matieres'][number]>,
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
        {secondaire ? (
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

      {estEnseignant && classesEnseignant.length === 0 ? (
        <Card>
          <EmptyState label="Aucune matière ne vous est affectée pour le moment." />
        </Card>
      ) : classeActive === null ? (
        <DataTable
          colonnes={colonnesClasses}
          lignes={lignesClasses}
          cleLigne={(l) => l.id}
          placeholderRecherche={t('resultats.search_classe')}
          messageVide={t('resultats.empty_classe')}
          onLigneClick={(l) => setClasseId(l.id)}
        />
      ) : isLoading ? (
        <Spinner />
      ) : !data?.matieres.length ? (
        <Card>
          <EmptyState label={t('progression.aucune_matiere_classe')} />
        </Card>
      ) : (
        <>
          <h2 className="-mt-2 text-sm font-semibold text-navy-600">{classeActiveNom}</h2>
          <DataTable
            colonnes={colonnesMatieres}
            lignes={data.matieres}
            cleLigne={(ligne) => ligne.classe_matiere_id}
            placeholderRecherche={t('matieres.search_placeholder')}
            messageVide={t('progression.aucune_matiere_classe')}
            onLigneClick={(ligne) => setMatiereSelectionnee(ligne.classe_matiere_id)}
          />
        </>
      )}
    </div>
  )
}
