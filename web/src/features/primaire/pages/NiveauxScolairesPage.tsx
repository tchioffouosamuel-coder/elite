import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Eye, Layers, Pencil, Plus, Trash2, X } from 'lucide-react'
import {
  fetchNiveauxScolaires,
  createNiveauScolaire,
  updateNiveauScolaire,
  deleteNiveauScolaire,
  type NiveauScolaire,
} from '@/features/primaire/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { fetchSchools } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Niveaux d'enseignement du primaire et de la maternelle. Chaque niveau est
 * confié à un animateur : c'est l'équivalent du département et de son chef au
 * secondaire, mais rattaché aux classes d'un même degré (SIL, CP, CE1…).
 */
export function NiveauxScolairesPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading, isError } = useQuery({ queryKey: ['niveaux-scolaires'], queryFn: fetchNiveauxScolaires })
  const { data: personnels } = useQuery({
    queryKey: ['personnels', { page: 1, per_page: 100 }],
    queryFn: () => fetchPersonnels({ per_page: 100 }),
  })
  const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })

  const [code, setCode] = useState('')
  const [libelle, setLibelle] = useState('')
  const [schoolId, setSchoolId] = useState('')
  const [editingId, setEditingId] = useState<number | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['niveaux-scolaires'] })

  const resetForm = () => {
    setCode('')
    setLibelle('')
    setSchoolId('')
    setEditingId(null)
  }

  const handleEdit = (niveau: NiveauScolaire) => {
    setEditingId(niveau.id)
    setCode(niveau.code)
    setLibelle(niveau.libelle)
    setSchoolId(niveau.school_id ? String(niveau.school_id) : '')
  }

  const handleSubmitForm = async () => {
    if (!code.trim() || !libelle.trim()) return
    setSubmitting(true)
    try {
      if (editingId) {
        const niveau = data?.find((n) => n.id === editingId)
        await updateNiveauScolaire(editingId, {
          code: code.trim(),
          libelle: libelle.trim(),
          ordre: niveau?.ordre,
          animateur_personnel_id: niveau?.animateur_personnel_id ?? null,
        })
        succes(t('niveaux.updated'))
      } else {
        await createNiveauScolaire({
          code: code.trim(),
          libelle: libelle.trim(),
          ordre: (data?.length ?? 0) + 1,
          school_id: schoolId ? Number(schoolId) : undefined,
        })
        succes(t('niveaux.created'))
      }
      resetForm()
      invalider()
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  const handleAnimateur = async (niveauId: number, animateurId: string) => {
    const niveau = data?.find((n) => n.id === niveauId)
    if (!niveau) return

    try {
      await updateNiveauScolaire(niveauId, {
        code: niveau.code,
        libelle: niveau.libelle,
        ordre: niveau.ordre,
        animateur_personnel_id: animateurId ? Number(animateurId) : null,
      })
      invalider()
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const handleDelete = async (niveau: NiveauScolaire) => {
    const ok = await confirmerSuppression(niveau.code)
    if (!ok) return

    try {
      await deleteNiveauScolaire(niveau.id)
      invalider()
      succes(t('niveaux.deleted'))
    } catch (err) {
      erreur((err as ApiError).message ?? t('niveaux.delete_error'))
    }
  }

  const handleToggleSelect = (id: number) => {
    const next = new Set(selectedIds)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setSelectedIds(next)
  }

  const handleSelectAll = () => {
    if (!data) return
    setSelectedIds(selectedIds.size === data.length ? new Set() : new Set(data.map((n) => n.id)))
  }

  const handleBatchDelete = async () => {
    const ids = Array.from(selectedIds)
    if (ids.length === 0) return

    const ok = await confirmer({
      titre: t('niveaux.delete_batch_title', { count: ids.length }),
      message: t('alerts.irreversible'),
      action: t('common.delete'),
    })
    if (!ok) return

    try {
      await Promise.all(ids.map((id) => deleteNiveauScolaire(id)))
      setSelectedIds(new Set())
      invalider()
      succes(t('niveaux.batch_deleted', { count: ids.length }))
    } catch (err) {
      erreur((err as ApiError).message ?? t('niveaux.delete_error'))
    }
  }

  const colonnes: Colonne<NiveauScolaire>[] = [
    ...(can('pedagogie.manage')
      ? [
        {
          cle: 'selection',
          entete: data ? (
            <input
              type="checkbox"
              checked={selectedIds.size === data.length && data.length > 0}
              onChange={handleSelectAll}
              className="h-4 w-4 rounded border-navy-300"
            />
          ) : null,
          valeur: () => '',
          cellule: (n: NiveauScolaire) => (
            <input
              type="checkbox"
              checked={selectedIds.has(n.id)}
              onChange={() => handleToggleSelect(n.id)}
              className="h-4 w-4 rounded border-navy-300"
            />
          ),
          largeur: '44px',
        } satisfies Colonne<NiveauScolaire>,
      ]
      : []),
    {
      cle: 'code',
      entete: t('niveaux.code'),
      valeur: (n) => n.code,
      cellule: (n) => <span className="font-mono text-xs font-semibold text-navy-900">{n.code}</span>,
    },
    {
      cle: 'libelle',
      entete: t('niveaux.libelle'),
      valeur: (n) => n.libelle,
      cellule: (n) => <span className="font-medium text-navy-800">{n.libelle}</span>,
    },
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (n) => n.school?.name,
      cellule: (n) => <span className="text-navy-600">{n.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'classes',
      entete: t('niveaux.classes'),
      valeur: (n) => n.nb_classes ?? 0,
      cellule: (n) => (
        <button
          onClick={() => navigate(`/niveaux/${n.id}`)}
          className="font-medium text-navy-700 hover:text-gold-600 hover:underline"
        >
          {n.nb_classes ?? 0}
        </button>
      ),
    },
    {
      cle: 'animateur',
      entete: t('niveaux.animateur'),
      valeur: (n) => n.animateur?.nom_complet,
      cellule: (n) =>
        can('pedagogie.manage') ? (
          <Select
            value={n.animateur_personnel_id ?? ''}
            onChange={(e) => handleAnimateur(n.id, e.target.value)}
            onClick={(e) => e.stopPropagation()}
            className="w-full max-w-56 rounded-lg border border-navy-200 bg-white px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-4 focus:ring-navy-100"
          >
            <option value="">—</option>
            {personnels?.map((p) => (
              <option key={p.id} value={p.id}>
                {p.nom_complet}
              </option>
            ))}
          </Select>
        ) : (
          (n.animateur?.nom_complet ?? '—')
        ),
      masquerMobile: true,
    },
    {
      cle: 'actions',
      entete: t('common.actions'),
      cellule: (n) => (
        <div className="flex items-center gap-1">
          <button
            title={t('niveaux.view')}
            onClick={(e) => {
              e.stopPropagation()
              navigate(`/niveaux/${n.id}`)
            }}
            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
          >
            <Eye className="h-4 w-4" />
          </button>
          {can('pedagogie.manage') && (
            <>
              <button
                title={t('common.edit')}
                onClick={(e) => {
                  e.stopPropagation()
                  handleEdit(n)
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
              >
                <Pencil className="h-4 w-4" />
              </button>
              <button
                title={t('common.delete')}
                onClick={(e) => {
                  e.stopPropagation()
                  handleDelete(n)
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </>
          )}
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('niveaux.title')} sousTitre={t('niveaux.hint')} icon={Layers} />

      {can('pedagogie.manage') && (
        <div className="flex max-w-3xl flex-wrap items-end gap-2">
          {(schools?.length ?? 0) > 1 && !editingId && (
            <div className="w-56 flex-none">
              <Select label={t('classes.ecole')} value={schoolId} onChange={(e) => setSchoolId(e.target.value)}>
                <option value="">—</option>
                {schools?.map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </div>
          )}
          <div className="w-28 flex-none">
            <Input label={t('niveaux.code')} value={code} onChange={(e) => setCode(e.target.value)} placeholder="CE1" />
          </div>
          <div className="min-w-64 flex-1">
            <Input
              label={t('niveaux.libelle')}
              value={libelle}
              onChange={(e) => setLibelle(e.target.value)}
              placeholder="Cours Élémentaire 1"
            />
          </div>
          <Button onClick={handleSubmitForm} disabled={submitting}>
            {editingId ? <Pencil className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
            {editingId ? t('niveaux.edit') : t('niveaux.add')}
          </Button>
          {editingId && (
            <button
              type="button"
              onClick={resetForm}
              title={t('niveaux.cancel_edit')}
              className="flex h-10 w-10 flex-none items-center justify-center rounded-xl text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <X className="h-4 w-4" />
            </button>
          )}
        </div>
      )}

      {selectedIds.size > 0 && can('pedagogie.manage') && (
        <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
          <div className="flex flex-wrap items-center justify-between gap-4">
            <p className="font-medium text-navy-900">{t('niveaux.selected_count', { count: selectedIds.size })}</p>
            <div className="flex flex-wrap gap-2">
              <Button variant="danger" onClick={handleBatchDelete}>
                <Trash2 className="h-4 w-4" />
                {t('common.delete')}
              </Button>
              <button
                onClick={() => setSelectedIds(new Set())}
                className="rounded-lg px-4 py-2 text-sm font-medium text-navy-600 hover:bg-navy-50 whitespace-nowrap"
              >
                {t('common.cancel')}
              </button>
            </div>
          </div>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data}
          cleLigne={(n) => n.id}
          placeholderRecherche={t('niveaux.search_placeholder')}
          messageVide={t('niveaux.empty')}
          largeurMin={780}
        />
      )}
    </div>
  )
}
