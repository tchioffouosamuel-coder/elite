import { Fragment, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { BarChart3, ChevronRight, FileDown } from 'lucide-react'
import {
  fetchRecapitulatifEffectifs,
  fetchRecapitulatifSousSystemes,
  fetchTableauAges,
  type LigneEffectifs,
} from '@/features/eleves/api'
import { fetchSousSystemes } from '@/features/classes/sous-systemes/api'
import { fetchClasses, fetchSchools } from '@/features/classes/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Tabs } from '@/shared/ui/Tabs'
import { Select } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { ouvrirDocument } from '@/shared/lib/download'

const COLONNES_EFFECTIFS = [
  { cle: 'nouveaux', libelle: 'Nouveaux' },
  { cle: 'redoublants', libelle: 'Redoublants' },
  { cle: 'camerounais', libelle: 'Camerounais' },
  { cle: 'refugies', libelle: 'Réfugiés' },
  { cle: 'effectif', libelle: 'Effectif' },
] as const

/** Grille Garçons/Filles/Total × Nouveaux/Redoublants/Camerounais/Réfugiés, façon rentrée scolaire — avec son propre téléchargement : chaque carte est un document à part, pas un extrait d'un PDF général. */
function TableEffectifs({
  titre,
  garcons,
  filles,
  total,
  onTelecharger,
}: {
  titre: string
  garcons: LigneEffectifs
  filles: LigneEffectifs
  total: LigneEffectifs
  onTelecharger: () => void
}) {
  const lignes = [
    { libelle: 'Garçons', valeurs: garcons },
    { libelle: 'Filles', valeurs: filles },
    { libelle: 'Total', valeurs: total, gras: true },
  ]

  return (
    <Card>
      <div className="mb-3 flex items-center justify-between gap-2">
        <h3 className="font-display text-sm font-bold text-navy-900">{titre}</h3>
        <button
          onClick={onTelecharger}
          title="Télécharger en PDF"
          className="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-semibold text-navy-600 transition-colors hover:bg-cream-100"
        >
          <FileDown className="h-3.5 w-3.5" />
          PDF
        </button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[480px] border-collapse text-sm">
          <thead>
            <tr className="border-b border-navy-100 text-xs font-semibold uppercase tracking-wide text-navy-500">
              <th className="py-2 text-left"></th>
              {COLONNES_EFFECTIFS.map((c) => (
                <th key={c.cle} className="py-2 text-right">{c.libelle}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {lignes.map((ligne) => (
              <tr key={ligne.libelle} className={ligne.gras ? 'bg-cream-100 font-bold' : 'border-b border-navy-50'}>
                <td className="py-2 pr-3 text-left font-semibold text-navy-800">{ligne.libelle}</td>
                {COLONNES_EFFECTIFS.map((c) => (
                  <td key={c.cle} className="py-2 text-right tabular-nums">{ligne.valeurs[c.cle]}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  )
}

function OngletParEcole() {
  const [schoolId, setSchoolId] = useState<number | ''>('')
  const [classeId, setClasseId] = useState<number | ''>('')

  const { data: schools = [] } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
  // Le filtre par classe est un confort : s'il échoue (pas de permission
  // classes.view), l'onglet reste utilisable sans lui plutôt que de planter.
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), retry: false, throwOnError: false })
  const classesDisponibles = schoolId === '' ? classes : classes?.filter((c) => (c.school_id ?? c.school?.id) === schoolId)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves-recapitulatif', classeId],
    queryFn: () => fetchRecapitulatifEffectifs(classeId || null),
  })

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-end gap-2">
        {schools.length > 1 && (
          <div className="w-full sm:w-64">
            <Select
              value={schoolId}
              onChange={(e) => {
                setSchoolId(e.target.value ? Number(e.target.value) : '')
                setClasseId('')
              }}
            >
              <option value="">Toutes les écoles</option>
              {schools.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </div>
        )}
        {classesDisponibles && classesDisponibles.length > 0 && (
          <div className="w-full sm:w-64">
            <Select value={classeId} onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}>
              <option value="">Toutes les classes</option>
              {classesDisponibles.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nom}
                  {schoolId === '' ? ` — ${c.school?.name ?? ''}` : ''}
                </option>
              ))}
            </Select>
          </div>
        )}
      </div>

      {data.length === 0 ? (
        <EmptyState label="Aucune école dans le périmètre." />
      ) : (
        data.map((t) => (
          <TableEffectifs
            key={t.classe ? `classe-${t.classe.id}` : `ecole-${t.school.id}`}
            titre={t.classe ? `${t.school.name} — ${t.classe.nom}` : t.school.name}
            garcons={t.garcons}
            filles={t.filles}
            total={t.total}
            onTelecharger={() =>
              ouvrirDocument('/eleves/recapitulatif-effectifs/pdf', {
                school_id: t.school.id,
                ...(t.classe ? { classe_id: t.classe.id } : {}),
              })
            }
          />
        ))
      )}
    </div>
  )
}

function OngletParSousSysteme() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves-recapitulatif-ss'],
    queryFn: fetchRecapitulatifSousSystemes,
  })

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  const tables = data.flatMap((ecole) =>
    ecole.sous_systemes.map((ss) => ({
      cle: `${ecole.school.id}-${ss.sous_systeme?.id ?? 0}`,
      titre: `${ecole.school.name} — ${ss.sous_systeme?.nom ?? 'Sans sous-système'}`,
      schoolId: ecole.school.id,
      sousSystemeId: ss.sous_systeme?.id ?? 0,
      ...ss,
    })),
  )

  if (tables.length === 0) return <EmptyState label="Aucune donnée à afficher." />

  return (
    <div className="flex flex-col gap-4">
      {tables.map((t) => (
        <TableEffectifs
          key={t.cle}
          titre={t.titre}
          garcons={t.garcons}
          filles={t.filles}
          total={t.total}
          onTelecharger={() =>
            ouvrirDocument('/eleves/recapitulatif-sous-systemes/pdf', { school_id: t.schoolId, sous_systeme_id: t.sousSystemeId })
          }
        />
      ))}
    </div>
  )
}

function OngletAges() {
  // Lignes dépliées, par âge. Plusieurs peuvent l'être en même temps : on
  // compare volontiers deux tranches côte à côte.
  const [ouverts, setOuverts] = useState<Set<string>>(new Set())
  const [schoolId, setSchoolId] = useState<number | ''>('')
  const [sousSystemeId, setSousSystemeId] = useState<number | ''>('')
  const [classeId, setClasseId] = useState<number | ''>('')

  const { data: schools = [] } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })

  // Ces deux filtres sont un confort, pas un pré-requis : s'ils échouent
  // (permission classes.manage/classes.view absente), l'écran reste
  // utilisable, simplement sans eux.
  const { data: sousSystemes } = useQuery({
    queryKey: ['sous-systemes'],
    queryFn: fetchSousSystemes,
    retry: false,
    throwOnError: false,
  })
  const sousSystemesDisponibles = schoolId === '' ? sousSystemes : sousSystemes?.filter((ss) => ss.school?.id === schoolId)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), retry: false, throwOnError: false })
  const classesDisponibles = schoolId === '' ? classes : classes?.filter((c) => (c.school_id ?? c.school?.id) === schoolId)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves-tableau-ages', schoolId, sousSystemeId, classeId],
    queryFn: () => fetchTableauAges({ school_id: schoolId || null, sous_systeme_id: sousSystemeId || null, classe_id: classeId || null }),
  })

  const totaux = data?.reduce(
    (acc, l) => ({ garcons: acc.garcons + l.garcons, filles: acc.filles + l.filles, total: acc.total + l.total }),
    { garcons: 0, filles: 0, total: 0 },
  )

  const basculerAge = (age: string) =>
    setOuverts((actuels) => {
      const suivant = new Set(actuels)

      if (suivant.has(age)) {
        suivant.delete(age)
      } else {
        suivant.add(age)
      }

      return suivant
    })

  const pdfParams: Record<string, number> = {}
  if (schoolId) pdfParams.school_id = schoolId
  if (classeId) pdfParams.classe_id = classeId
  else if (sousSystemeId) pdfParams.sous_systeme_id = sousSystemeId

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center justify-end gap-2">
        {schools.length > 1 && (
          <div className="w-full sm:w-64">
            <Select
              value={schoolId}
              onChange={(e) => {
                setSchoolId(e.target.value ? Number(e.target.value) : '')
                setSousSystemeId('')
                setClasseId('')
              }}
            >
              <option value="">Toutes les écoles</option>
              {schools.map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </div>
        )}
        {classesDisponibles && classesDisponibles.length > 0 && (
          <div className="w-full sm:w-64">
            <Select
              value={classeId}
              onChange={(e) => {
                setClasseId(e.target.value ? Number(e.target.value) : '')
                setSousSystemeId('')
              }}
            >
              <option value="">Toutes les classes</option>
              {classesDisponibles.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nom}
                  {schoolId === '' ? ` — ${c.school?.name ?? ''}` : ''}
                </option>
              ))}
            </Select>
          </div>
        )}
        {!classeId && sousSystemesDisponibles && sousSystemesDisponibles.length > 0 && (
          <div className="w-full sm:w-64">
            <Select value={sousSystemeId} onChange={(e) => setSousSystemeId(e.target.value ? Number(e.target.value) : '')}>
              <option value="">Tous les sous-systèmes</option>
              {sousSystemesDisponibles.map((ss) => (
                <option key={ss.id} value={ss.id}>
                  {ss.nom}{schoolId === '' ? ` — ${ss.school?.name}` : ''}
                </option>
              ))}
            </Select>
          </div>
        )}
        <button
          onClick={() => ouvrirDocument('/eleves/tableau-ages/pdf', Object.keys(pdfParams).length ? pdfParams : undefined)}
          title="Télécharger en PDF"
          className="flex items-center gap-1.5 rounded-lg border border-navy-200 bg-white px-3 py-1.5 text-xs font-semibold text-navy-600 shadow-soft transition-colors hover:border-navy-300 hover:bg-cream-50"
        >
          <FileDown className="h-3.5 w-3.5" />
          PDF
        </button>
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.length === 0 ? (
        <EmptyState label="Aucun élève avec une date de naissance renseignée." />
      ) : (
        <Card>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[420px] border-collapse text-sm">
              <thead>
                <tr className="border-b border-navy-100 text-xs font-semibold uppercase tracking-wide text-navy-500">
                  <th className="py-2 text-left">Âge exact (ans.mois)</th>
                  <th className="py-2 text-right">Garçons</th>
                  <th className="py-2 text-right">Filles</th>
                  <th className="py-2 text-right">Effectif</th>
                </tr>
              </thead>
              <tbody>
                {data.map((ligne) => {
                  const ouvert = ouverts.has(ligne.age)

                  return (
                    <Fragment key={ligne.age}>
                      <tr
                        className="cursor-pointer border-b border-navy-50 hover:bg-cream-50"
                        onClick={() => basculerAge(ligne.age)}
                      >
                        <td className="py-2 text-left font-semibold text-navy-800">
                          <span className="flex items-center gap-1.5">
                            <ChevronRight
                              className={`h-4 w-4 flex-none text-navy-400 transition-transform ${ouvert ? 'rotate-90' : ''}`}
                            />
                            {ligne.age}
                          </span>
                        </td>
                        <td className="py-2 text-right tabular-nums">{ligne.garcons}</td>
                        <td className="py-2 text-right tabular-nums">{ligne.filles}</td>
                        <td className="py-2 text-right tabular-nums">{ligne.total}</td>
                      </tr>

                      {ouvert && (
                        <tr className="border-b border-navy-50 bg-cream-50/60">
                          <td colSpan={4} className="px-3 py-2">
                            {ligne.eleves.length === 0 ? (
                              <p className="text-xs text-navy-400">Aucun élève à détailler pour cet âge.</p>
                            ) : (
                              <ul className="flex flex-col divide-y divide-navy-100/70">
                                {ligne.eleves.map((eleve) => (
                                  <li
                                    key={eleve.id}
                                    className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-1.5 text-xs"
                                  >
                                    <span className="font-medium text-navy-800">{eleve.nom_complet}</span>
                                    <span className="flex flex-wrap items-center gap-x-3 text-navy-500">
                                      <span className="font-mono">{eleve.matricule ?? '—'}</span>
                                      <span>{eleve.classe ?? '—'}</span>
                                      <span>{eleve.sexe === 'F' ? 'Fille' : 'Garçon'}</span>
                                      <span className="tabular-nums">{eleve.date_naissance ?? '—'}</span>
                                    </span>
                                  </li>
                                ))}
                              </ul>
                            )}
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  )
                })}
                {totaux && (
                  <tr className="bg-cream-100 font-bold">
                    <td className="py-2 text-left">Total</td>
                    <td className="py-2 text-right tabular-nums">{totaux.garcons}</td>
                    <td className="py-2 text-right tabular-nums">{totaux.filles}</td>
                    <td className="py-2 text-right tabular-nums">{totaux.total}</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </Card>
      )}
    </div>
  )
}

/** Rapports d'effectifs de la page Élèves : récapitulatif rentrée scolaire et pyramide des âges. */
export function EleveRapportsPage() {
  const [onglet, setOnglet] = useState('ecole')

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre="Rapports d'effectifs" sousTitre="Récapitulatif de rentrée et pyramide des âges." icon={BarChart3} />

      <Tabs
        tabs={[
          { key: 'ecole', label: 'Par école' },
          { key: 'sous-systeme', label: 'Par sous-système' },
          { key: 'ages', label: 'Pyramide des âges' },
        ]}
        active={onglet}
        onChange={setOnglet}
      />

      {onglet === 'ecole' && <OngletParEcole />}
      {onglet === 'sous-systeme' && <OngletParSousSysteme />}
      {onglet === 'ages' && <OngletAges />}
    </div>
  )
}
