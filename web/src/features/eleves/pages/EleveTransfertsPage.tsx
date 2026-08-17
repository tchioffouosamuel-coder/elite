import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Repeat } from 'lucide-react'
import { fetchEleves, batchChangerClasseEleves, batchTransfererEleveEcole } from '@/features/eleves/api'
import { fetchClasses, fetchClassesForSchool } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, succes, erreur } from '@/shared/lib/alertes'
import type { Eleve } from '@/features/eleves/api'
import type { ApiError } from '@/shared/types/api'

type Mode = 'classe' | 'ecole'

export function EleveTransfertsPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const isSuperAdmin = useAuthStore((s) => s.user?.is_super_admin ?? false)
  const ecoles = useAuthStore((s) => s.user?.ecoles_accessibles ?? []).filter((e) => e.id !== activeSchoolId)

  const [mode, setMode] = useState<Mode>('classe')
  const [classeFiltreId, setClasseFiltreId] = useState<number | ''>('')
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  // Destination.
  const [classeDestId, setClasseDestId] = useState<number | ''>('')
  const [ecoleDestId, setEcoleDestId] = useState<number | ''>('')
  const [classeDestEcoleId, setClasseDestEcoleId] = useState<number | ''>('')
  const [submitting, setSubmitting] = useState(false)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['eleves', 'transferts', classeFiltreId],
    queryFn: () => fetchEleves({ per_page: 1000, classe_id: classeFiltreId ? Number(classeFiltreId) : undefined }),
  })

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: classesDestEcole } = useQuery({
    queryKey: ['classes-ecole', ecoleDestId],
    queryFn: () => fetchClassesForSchool(Number(ecoleDestId)),
    enabled: mode === 'ecole' && !!ecoleDestId,
  })

  const handleToggleSelect = (id: number) => {
    const next = new Set(selectedIds)
    next.has(id) ? next.delete(id) : next.add(id)
    setSelectedIds(next)
  }

  const handleSelectAll = (eleves: Eleve[]) => {
    if (selectedIds.size === eleves.length && eleves.length > 0) {
      setSelectedIds(new Set())
    } else {
      setSelectedIds(new Set(eleves.map((e) => e.id)))
    }
  }

  const destinationValide = mode === 'classe' ? !!classeDestId : !!ecoleDestId && !!classeDestEcoleId

  const handleTransferer = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0 || !destinationValide) return

    const confirme = await confirmer({
      titre: t('eleves.transfer_confirm_title', { count: ids.length }),
      message: mode === 'classe' ? t('eleves.transfer_message_classe') : t('eleves.transfer_message_ecole'),
      action: t('eleves.transferer'),
      destructif: false,
    })
    if (!confirme) return

    setSubmitting(true)
    try {
      const resultat =
        mode === 'classe'
          ? await batchChangerClasseEleves(ids, Number(classeDestId))
          : await batchTransfererEleveEcole(ids, Number(ecoleDestId), Number(classeDestEcoleId))

      succes(t('eleves.transferred', { transferes: resultat.transferes }))
      setSelectedIds(new Set())
      queryClient.invalidateQueries({ queryKey: ['eleves'] })
      queryClient.invalidateQueries({ queryKey: ['classes'] })
      navigate('/eleves')
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  const colonnes: Colonne<Eleve>[] = [
    {
      cle: 'selection',
      entete: data?.items ? (
        <input
          type="checkbox"
          checked={selectedIds.size === data.items.length && data.items.length > 0}
          onChange={() => handleSelectAll(data?.items ?? [])}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ) : null,
      cellule: (e) => (
        <input
          type="checkbox"
          checked={selectedIds.has(e.id)}
          onChange={() => handleToggleSelect(e.id)}
          className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      ),
    },
    {
      cle: 'matricule',
      entete: t('eleves.matricule'),
      valeur: (e) => e.matricule,
      cellule: (e) => <span className="font-mono text-xs">{e.matricule ?? '—'}</span>,
    },
    {
      cle: 'nom',
      entete: t('eleves.nom_complet'),
      valeur: (e) => e.nom_complet,
      cellule: (e) => <span className="font-semibold text-navy-900">{e.nom_complet}</span>,
    },
    {
      cle: 'classe',
      entete: t('eleves.classe_actuelle'),
      valeur: (e) => e.classe?.nom,
      cellule: (e) => e.classe?.nom ?? '—',
    },
    {
      cle: 'statut',
      entete: t('eleves.statut'),
      valeur: (e) => e.statut,
      cellule: (e) => <Badge tone={e.statut === 'actif' ? 'green' : 'neutral'}>{t(`eleves.${e.statut}`)}</Badge>,
      masquerMobile: true,
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/eleves" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          {t('common.back')}
        </Link>
        <div className="flex items-center gap-3">
          <span className="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-linear-to-br from-gold-50 to-gold-100 shadow-soft ring-1 ring-gold-100">
            <Repeat className="h-5 w-5 text-gold-600" />
          </span>
          <h1 className="font-display text-xl font-bold tracking-tight text-navy-900 sm:text-2xl">{t('nav.transferts')}</h1>
        </div>
      </div>

      <Card className="flex flex-col gap-4">
        <div className="flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() => setMode('classe')}
            className={`rounded-xl px-4 py-2 text-sm font-semibold transition-colors ${mode === 'classe' ? 'bg-navy-800 text-cream-50 shadow-soft' : 'bg-cream-100 text-navy-600 hover:bg-cream-200'
              }`}
          >
            {t('eleves.vers_classe')}
          </button>
          {isSuperAdmin && (
            <button
              type="button"
              onClick={() => setMode('ecole')}
              className={`rounded-xl px-4 py-2 text-sm font-semibold transition-colors ${mode === 'ecole' ? 'bg-navy-800 text-cream-50 shadow-soft' : 'bg-cream-100 text-navy-600 hover:bg-cream-200'
                }`}
            >
              {t('eleves.vers_ecole')}
            </button>
          )}
        </div>

        {mode === 'classe' ? (
          <Select label={t('eleves.classe_destination')} value={classeDestId} onChange={(e) => setClasseDestId(e.target.value ? Number(e.target.value) : '')}>
            <option value="">—</option>
            {classes?.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </Select>
        ) : (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Select
              label={t('eleves.ecole_destination')}
              value={ecoleDestId}
              onChange={(e) => {
                setEcoleDestId(e.target.value ? Number(e.target.value) : '')
                setClasseDestEcoleId('')
              }}
            >
              <option value="">—</option>
              {ecoles.map((ecole) => (
                <option key={ecole.id} value={ecole.id}>
                  {ecole.name}
                </option>
              ))}
            </Select>
            <Select
              label={t('eleves.classe_destination')}
              value={classeDestEcoleId}
              onChange={(e) => setClasseDestEcoleId(e.target.value ? Number(e.target.value) : '')}
              disabled={!ecoleDestId}
            >
              <option value="">—</option>
              {classesDestEcole?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nom}
                </option>
              ))}
            </Select>
          </div>
        )}
      </Card>

      <Card className="flex flex-col gap-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <Select
            label={t('eleves.filtrer_classe_actuelle')}
            value={classeFiltreId}
            onChange={(e) => setClasseFiltreId(e.target.value ? Number(e.target.value) : '')}
            className="max-w-xs"
          >
            <option value="">{t('eleves.toutes_classes')}</option>
            {classes?.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </Select>

          <Button onClick={handleTransferer} disabled={submitting || selectedIds.size === 0 || !destinationValide}>
            <Repeat className="h-4 w-4" />
            {t('eleves.transferer_count', { count: selectedIds.size })}
          </Button>
        </div>

        {isLoading ? (
          <Spinner />
        ) : isError || !data ? (
          <ErrorState />
        ) : (
          <DataTable
            colonnes={colonnes}
            lignes={data.items}
            cleLigne={(e) => e.id}
            placeholderRecherche={t('eleves.transferts_search_placeholder')}
            messageVide={t('eleves.empty_filtre')}
            largeurMin={700}
          />
        )}
      </Card>
    </div>
  )
}
