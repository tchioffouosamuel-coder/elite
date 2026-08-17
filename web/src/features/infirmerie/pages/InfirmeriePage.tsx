import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { CalendarDays, Coins, HeartPulse, Pencil, Plus, Trash2, Users } from 'lucide-react'
import { deleteVisiteInfirmerie, fetchVisitesInfirmerie, type VisiteInfirmerie } from '@/features/infirmerie/api'
import { fetchClasses } from '@/features/classes/api'
import { fetchEleves } from '@/features/eleves/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { StatCard } from '@/shared/ui/Card'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, succes } from '@/shared/lib/alertes'

type FiltreNombre = number | ''

function debutJour(): string {
  return new Date().toISOString().slice(0, 10)
}

function maintenantLocal(): string {
  const date = new Date()
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset())
  return date.toISOString().slice(0, 16)
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
  const [classeFiltre, setClasseFiltre] = useState<FiltreNombre>('')
  const [eleveFiltre, setEleveFiltre] = useState<FiltreNombre>('')
  const [du, setDu] = useState('')
  const [au, setAu] = useState('')

  const params = useMemo(
    () => ({
      ...(classeFiltre ? { classe_id: Number(classeFiltre) } : {}),
      ...(eleveFiltre ? { eleve_id: Number(eleveFiltre) } : {}),
      ...(du ? { du } : {}),
      ...(au ? { au } : {}),
    }),
    [au, classeFiltre, du, eleveFiltre],
  )

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: eleves } = useQuery({ queryKey: ['eleves', 'infirmerie'], queryFn: () => fetchEleves({ per_page: 500 }) })
  const { data: visites, isLoading } = useQuery({
    queryKey: ['infirmerie', 'visites', params],
    queryFn: () => fetchVisitesInfirmerie(params),
  })

  const lignes = useMemo(() => visites ?? [], [visites])
  const stats = useMemo(() => {
    const elevesDistincts = new Set(lignes.map((visite) => visite.eleve.id)).size
    const coutTotal = lignes.reduce((total, visite) => total + visite.cout_soins, 0)
    const visitesAujourdhui = lignes.filter((visite) => visite.date_visite.startsWith(debutJour())).length

    return { elevesDistincts, coutTotal, visitesAujourdhui }
  }, [lignes])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['infirmerie', 'visites'] })

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
      cle: 'cout',
      entete: t('infirmerie.cout_soins'),
      valeur: (v) => v.cout_soins,
      cellule: (v) =>
        v.cout_soins > 0 ? <Badge tone="gold">{formatMontant(v.cout_soins, i18n.language)}</Badge> : <span>—</span>,
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
          <Button onClick={() => navigate('/infirmerie/nouvelle')}>
            <Plus className="h-4 w-4" />
            {t('infirmerie.add_visit')}
          </Button>
        )}
      </div>

      <div className="grid gap-3 md:grid-cols-3">
        <StatCard label={t('infirmerie.visits_total')} value={lignes.length} icon={HeartPulse} accent="red" />
        <StatCard label={t('infirmerie.students_seen')} value={stats.elevesDistincts} icon={Users} accent="navy" />
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
          outils={
            <>
              <Select value={classeFiltre} onChange={(e) => setClasseFiltre(e.target.value ? Number(e.target.value) : '')} className="w-52">
                <option value="">{t('infirmerie.all_classes')}</option>
                {classes?.map((c) => (
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
              <Input type="date" value={du} onChange={(e) => setDu(e.target.value)} className="w-40" icon={CalendarDays} />
              <Input type="date" value={au} onChange={(e) => setAu(e.target.value)} className="w-40" icon={CalendarDays} />
            </>
          }
        />
      )}
    </div>
  )
}
