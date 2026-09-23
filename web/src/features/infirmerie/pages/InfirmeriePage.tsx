import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { CalendarDays, Coins, HeartPulse, Pencil, Plus, Trash2, Users } from 'lucide-react'
import { COLONNES_IMPORT_VISITES_INFIRMERIE, deleteVisiteInfirmerie, fetchVisitesInfirmerie, type VisiteInfirmerie } from '@/features/infirmerie/api'
import { fetchClasses, fetchSchools } from '@/features/classes/api'
import { fetchSousSystemes } from '@/features/classes/sous-systemes/api'
import { fetchEleves } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { StatCard } from '@/shared/ui/Card'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes } from '@/shared/lib/alertes'
import { triEcoleSousSystemeNiveauClasse } from '@/shared/lib/triHierarchique'

type FiltreNombre = number | ''

function debutJour(): string {
  return new Date().toISOString().slice(0, 10)
}

function iso(date: Date): string {
  return date.toISOString().slice(0, 10)
}

type Periode = '' | 'jour' | 'semaine' | 'mois' | 'trimestre' | 'annee'

/** Bornes calendaires (pas glissantes) de la période choisie, pour préremplir du/au. */
function bornesPeriode(periode: Periode): { du: string; au: string } | null {
  if (periode === '') return null

  const maintenant = new Date()
  const annee = maintenant.getFullYear()

  if (periode === 'jour') {
    return { du: iso(maintenant), au: iso(maintenant) }
  }
  if (periode === 'semaine') {
    // Semaine ISO (lundi → dimanche).
    const jourSemaine = (maintenant.getDay() + 6) % 7
    const lundi = new Date(maintenant)
    lundi.setDate(maintenant.getDate() - jourSemaine)
    const dimanche = new Date(lundi)
    dimanche.setDate(lundi.getDate() + 6)
    return { du: iso(lundi), au: iso(dimanche) }
  }
  if (periode === 'mois') {
    return { du: iso(new Date(annee, maintenant.getMonth(), 1)), au: iso(new Date(annee, maintenant.getMonth() + 1, 0)) }
  }
  if (periode === 'trimestre') {
    const debutTrimestre = Math.floor(maintenant.getMonth() / 3) * 3
    return { du: iso(new Date(annee, debutTrimestre, 1)), au: iso(new Date(annee, debutTrimestre + 3, 0)) }
  }
  return { du: iso(new Date(annee, 0, 1)), au: iso(new Date(annee, 11, 31)) }
}

function formatDateHeure(valeur: string, locale: string): string {
  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(valeur))
}

function formatMontant(montant: number, locale: string): string {
  return new Intl.NumberFormat(locale, { style: 'currency', currency: 'XAF', maximumFractionDigits: 0 }).format(montant)
}

export function InfirmeriePage() {
  const { t, i18n } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [ecoleFiltre, setEcoleFiltre] = useState<FiltreNombre>('')
  const [sousSystemeFiltre, setSousSystemeFiltre] = useState<FiltreNombre>('')
  const [classeFiltre, setClasseFiltre] = useState<FiltreNombre>('')
  const [eleveFiltre, setEleveFiltre] = useState<FiltreNombre>('')
  const [periode, setPeriode] = useState<Periode>('')
  const [du, setDu] = useState('')
  const [au, setAu] = useState('')

  const changerPeriode = (valeur: Periode) => {
    setPeriode(valeur)
    const bornes = bornesPeriode(valeur)
    setDu(bornes?.du ?? '')
    setAu(bornes?.au ?? '')
  }

  const params = useMemo(
    () => ({
      ...(ecoleFiltre ? { school_id: Number(ecoleFiltre) } : {}),
      ...(sousSystemeFiltre ? { sous_systeme_id: Number(sousSystemeFiltre) } : {}),
      ...(classeFiltre ? { classe_id: Number(classeFiltre) } : {}),
      ...(eleveFiltre ? { eleve_id: Number(eleveFiltre) } : {}),
      ...(du ? { du } : {}),
      ...(au ? { au } : {}),
    }),
    [au, classeFiltre, du, ecoleFiltre, eleveFiltre, sousSystemeFiltre],
  )

  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: () => fetchSchools() })
  const { data: sousSystemes } = useQuery({ queryKey: ['sous-systemes'], queryFn: fetchSousSystemes })
  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: eleves } = useQuery({ queryKey: ['eleves', 'infirmerie'], queryFn: () => fetchEleves({ per_page: 500 }) })

  const sousSystemesFiltres = ecoleFiltre
    ? sousSystemes?.filter((s) => s.school_id === Number(ecoleFiltre))
    : sousSystemes
  const classesFiltrees = ecoleFiltre ? classes?.filter((c) => c.school_id === Number(ecoleFiltre)) : classes

  const { data: visites, isLoading } = useQuery({
    queryKey: ['infirmerie', 'visites', params],
    queryFn: () => fetchVisitesInfirmerie(params),
  })

  const lignes = useMemo(() => visites ?? [], [visites])
  const stats = useMemo(() => {
    const parEleve = new Map(lignes.map((visite) => [visite.eleve.id, visite.eleve]))
    const elevesUniques = [...parEleve.values()]
    const garcons = elevesUniques.filter((e) => e.sexe === 'M').length
    const filles = elevesUniques.filter((e) => e.sexe === 'F').length
    const coutTotal = lignes.reduce((total, visite) => total + visite.cout_total, 0)
    const visitesAujourdhui = lignes.filter((visite) => visite.date_visite.startsWith(debutJour())).length

    return { elevesDistincts: elevesUniques.length, garcons, filles, coutTotal, visitesAujourdhui }
  }, [lignes])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['infirmerie', 'visites'] })

  const triVisites = triEcoleSousSystemeNiveauClasse<VisiteInfirmerie>({
    ecole: (v) => v.school?.name,
    classe: (v) => v.classe?.nom,
  })

  const colonnes: Colonne<VisiteInfirmerie>[] = [
    {
      cle: 'date',
      entete: t('infirmerie.date_visite'),
      valeur: (v) => v.date_visite,
      cellule: (v) => <span className="font-semibold text-navy-900">{formatDateHeure(v.date_visite, i18n.language)}</span>,
      largeur: '170px',
    },
    {
      cle: 'eleve',
      entete: t('eleves.nom_complet'),
      valeur: (v) => v.eleve.nom_complet,
      cellule: (v) => (
        <div className="flex min-w-0 flex-col">
          <span className="truncate font-semibold text-navy-900">{v.eleve.nom_complet}</span>
          <span className="truncate text-xs text-navy-400">{v.classe?.nom ?? '—'}</span>
        </div>
      ),
      largeur: '220px',
    },
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (v) => v.school?.name,
      cellule: (v) => <span className="text-navy-600">{v.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'raison',
      entete: t('infirmerie.raison'),
      valeur: (v) => v.raison,
      cellule: (v) => <span className="block truncate">{v.raison}</span>,
    },
    {
      cle: 'soins',
      entete: t('infirmerie.soins_prodiges'),
      valeur: (v) => v.soins_prodiges,
      cellule: (v) => <span className="block truncate">{v.soins_prodiges}</span>,
      masquerMobile: true,
    },
    {
      cle: 'type',
      entete: t('infirmerie.type_col'),
      valeur: (v) => v.type_traitement,
      cellule: (v) => (
        <Badge tone={v.type_traitement === 'interne' ? 'neutral' : v.type_traitement === 'externe' ? 'red' : 'purple'}>
          {t(`infirmerie.type_${v.type_traitement}`)}
        </Badge>
      ),
      largeur: '130px',
      masquerMobile: true,
    },
    {
      cle: 'cout',
      entete: t('infirmerie.cout_total'),
      valeur: (v) => v.cout_total,
      cellule: (v) =>
        v.cout_total > 0 ? <Badge tone="gold">{formatMontant(v.cout_total, i18n.language)}</Badge> : <span>—</span>,
      largeur: '140px',
    },
    ...(can('infirmerie.manage')
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (v: VisiteInfirmerie) => (
              <div className="flex justify-end gap-1">
                <button
                  type="button"
                  title={t('common.edit')}
                  onClick={() => navigate(`/infirmerie/${v.id}/edit`)}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                >
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  type="button"
                  title={t('common.delete')}
                  onClick={async () => {
                    if (!(await confirmerSuppression(t('infirmerie.delete_target', { eleve: v.eleve.nom_complet })))) return
                    await deleteVisiteInfirmerie(v.id)
                    invalidate()
                    succes(t('infirmerie.deleted'))
                  }}
                  className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
            className: 'text-right',
            largeur: '110px',
          } satisfies Colonne<VisiteInfirmerie>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('infirmerie.title')}</h1>
          <p className="mt-1 max-w-2xl text-sm text-navy-500">{t('infirmerie.subtitle')}</p>
        </div>
        {can('infirmerie.manage') && (
          <div className="flex flex-wrap items-center gap-2">
            <ImportExportBar
              titreImport={t('infirmerie.import_title')}
              importUrl="/infirmerie/visites/import"
              exportUrl="/infirmerie/visites/export"
              modeleUrl="/infirmerie/visites/modele"
              colonnes={COLONNES_IMPORT_VISITES_INFIRMERIE}
              nomFichier="visites-infirmerie"
              onImported={invalidate}
            />
            <Button onClick={() => navigate('/infirmerie/nouvelle')}>
              <Plus className="h-4 w-4" />
              {t('infirmerie.add_visit')}
            </Button>
          </div>
        )}
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        <StatCard label={t('infirmerie.visits_total')} value={lignes.length} icon={HeartPulse} accent="red" />
        <StatCard
          label={t('infirmerie.students_seen')}
          value={stats.elevesDistincts}
          hint={t('infirmerie.students_seen_detail', { garcons: stats.garcons, filles: stats.filles })}
          icon={Users}
          accent="navy"
        />
        <StatCard
          label={t('infirmerie.care_cost_total')}
          value={formatMontant(stats.coutTotal, i18n.language)}
          hint={t('infirmerie.today_count', { count: stats.visitesAujourdhui })}
          icon={Coins}
          accent="gold"
        />
      </div>

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={lignes}
          cleLigne={(v) => v.id}
          placeholderRecherche={t('infirmerie.search_placeholder')}
          messageVide={t('infirmerie.empty')}
          largeurMin={980}
          triDefaut={triVisites}
          outils={
            <>
              <Select
                value={ecoleFiltre}
                onChange={(e) => {
                  setEcoleFiltre(e.target.value ? Number(e.target.value) : '')
                  setSousSystemeFiltre('')
                  setClasseFiltre('')
                }}
                className="w-52"
              >
                <option value="">{t('infirmerie.all_schools')}</option>
                {schools?.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </Select>
              <Select value={sousSystemeFiltre} onChange={(e) => setSousSystemeFiltre(e.target.value ? Number(e.target.value) : '')} className="w-48">
                <option value="">{t('infirmerie.all_sous_systemes')}</option>
                {sousSystemesFiltres?.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.nom}
                  </option>
                ))}
              </Select>
              <Select value={classeFiltre} onChange={(e) => setClasseFiltre(e.target.value ? Number(e.target.value) : '')} className="w-52">
                <option value="">{t('infirmerie.all_classes')}</option>
                {classesFiltrees?.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.nom}
                  </option>
                ))}
              </Select>
              <Select value={eleveFiltre} onChange={(e) => setEleveFiltre(e.target.value ? Number(e.target.value) : '')} className="w-64">
                <option value="">{t('infirmerie.all_students')}</option>
                {eleves?.items.map((e) => (
                  <option key={e.id} value={e.id}>
                    {e.nom_complet} — {e.classe?.nom ?? '—'}
                  </option>
                ))}
              </Select>
              <Select value={periode} onChange={(e) => changerPeriode(e.target.value as Periode)} className="w-40">
                <option value="">{t('infirmerie.periode_custom')}</option>
                <option value="jour">{t('infirmerie.periode_jour')}</option>
                <option value="semaine">{t('infirmerie.periode_semaine')}</option>
                <option value="mois">{t('infirmerie.periode_mois')}</option>
                <option value="trimestre">{t('infirmerie.periode_trimestre')}</option>
                <option value="annee">{t('infirmerie.periode_annee')}</option>
              </Select>
              <Input
                type="date"
                value={du}
                onChange={(e) => {
                  setDu(e.target.value)
                  setPeriode('')
                }}
                className="w-40"
                icon={CalendarDays}
              />
              <Input
                type="date"
                value={au}
                onChange={(e) => {
                  setAu(e.target.value)
                  setPeriode('')
                }}
                className="w-40"
                icon={CalendarDays}
              />
            </>
          }
        />
      )}
    </div>
  )
}
