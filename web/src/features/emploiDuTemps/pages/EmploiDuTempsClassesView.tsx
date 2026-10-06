import { useMemo, useRef, useState, useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ArrowRight, Building2, RefreshCw, Search, Trash2 } from 'lucide-react'
import { supprimerEmploisDuTemps, type ClasseEmploiDuTemps, type SuppressionEmploisDuTemps } from '../api'
import { useAuthStore, type EcoleAccessible } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { EmptyState, ErrorState, Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'

interface Props {
  classes: ClasseEmploiDuTemps[]
  isLoading: boolean
  error: { message?: string } | null
  onRetry: () => void
  onOpen: (id: number) => void
}

export function EmploiDuTempsClassesView({ classes, isLoading, error, onRetry, onOpen }: Props) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const user = useAuthStore((s) => s.user)
  const scope = useAuthStore((s) => s.activeSchoolId)
  const canDelete = useAuthStore((s) => s.can('emploi_du_temps.delete'))
  const [search, setSearch] = useState('')
  const [selection, setSelection] = useState<Set<number>>(new Set())
  const [choixEcole, setChoixEcole] = useState(false)
  const [confirming, setConfirming] = useState(false)
  const lock = useRef(false)
  const selectAll = useRef<HTMLInputElement>(null)
  const visible = useMemo(() => {
    const term = search.trim().toLocaleLowerCase()
    return classes.filter((c) => `${c.nom} ${c.school?.name ?? ''}`.toLocaleLowerCase().includes(term))
  }, [classes, search])
  const selected = classes.filter((c) => selection.has(c.id))
  const visibleSelected = visible.filter((c) => selection.has(c.id)).length
  const allChecked = visible.length > 0 && visibleSelected === visible.length
  useEffect(() => {
    if (selectAll.current) selectAll.current.indeterminate = visibleSelected > 0 && !allChecked
  }, [visibleSelected, allChecked])

  const schools = useMemo(() => {
    const byId = new Map<number, EcoleAccessible>()
    classes.forEach((c) => { if (c.school) byId.set(c.school.id, c.school) })
    user?.ecoles_accessibles?.forEach((school) => byId.set(school.id, school))
    const id = scope ?? (user?.is_super_admin ? null : user?.school_id)
    return [...byId.values()].filter((school) => id == null || school.id === id)
  }, [classes, user, scope])

  const deletion = useMutation({
    mutationFn: supprimerEmploisDuTemps,
    onSuccess: (result) => {
      setSelection(new Set())
      queryClient.invalidateQueries({ queryKey: ['emploi-du-temps'] })
      queryClient.invalidateQueries({ queryKey: ['emploi-du-temps-classes'] })
      succes(t('emploiDuTemps.creneaux_supprimes', { count: result.deleted }))
    },
    onError: (e: { message?: string }) => erreur(e.message ?? t('emploiDuTemps.deletion_failed')),
  })
  const busy = confirming || deletion.isPending

  async function supprimer(payload: SuppressionEmploisDuTemps, school?: EcoleAccessible) {
    if (lock.current || !canDelete || ('school_id' in payload && user?.perimetre_borne)) return
    lock.current = true
    setConfirming(true)
    setChoixEcole(false)
    try {
      const confirmed = await confirmer({
        titre: t('emploiDuTemps.supprimer_plannings_titre'),
        message: t(school ? 'emploiDuTemps.supprimer_ecole_confirmation' : 'emploiDuTemps.supprimer_classes_confirmation', {
          ecole: school?.name, count: selected.length,
        }),
        action: t('common.delete'),
        destructif: true,
      })
      // Une confirmation ouverte dans une école ne doit pas agir dans une autre.
      if (confirmed && useAuthStore.getState().activeSchoolId === scope) await deletion.mutateAsync(payload)
    } catch {
      // L'erreur de mutation est présentée par onError ; conserver la sélection.
    } finally {
      lock.current = false
      setConfirming(false)
    }
  }

  function toggle(id: number) {
    setSelection((current) => {
      const next = new Set(current)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  return (
    <section className="flex min-w-0 flex-col gap-4">
      <div className="flex flex-wrap items-center gap-3">
        <div className="w-full sm:max-w-sm">
          <Input icon={Search} aria-label={t('emploiDuTemps.search_classe_placeholder')}
            placeholder={t('emploiDuTemps.search_classe_placeholder')} value={search}
            onChange={(e) => setSearch(e.target.value)} disabled={busy} />
        </div>
        {canDelete && !user?.perimetre_borne && schools.length > 0 && (
          <Button variant="danger" size="sm" className="sm:ml-auto" disabled={busy || isLoading || !!error}
            onClick={() => schools.length === 1 ? void supprimer({ school_id: schools[0].id }, schools[0]) : setChoixEcole(true)}>
            <Trash2 className="h-4 w-4 shrink-0" />{t('emploiDuTemps.supprimer_ecole')}
          </Button>
        )}
      </div>
      {isLoading ? <Spinner /> : error ? (
        <div className="flex flex-col items-center gap-3">
          <ErrorState message={error.message} />
          <Button variant="secondary" onClick={onRetry}><RefreshCw className="h-4 w-4" />{t('emploiDuTemps.reessayer')}</Button>
        </div>
      ) : (
        <>
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-navy-100 pb-3">
            <label className="flex items-center gap-3 text-sm font-semibold text-navy-700">
              <input ref={selectAll} type="checkbox" className="h-4 w-4 accent-navy-700" checked={allChecked}
                disabled={busy || visible.length === 0} onChange={() => setSelection((current) => {
                  const next = new Set(current)
                  visible.forEach((c) => { if (allChecked) next.delete(c.id); else next.add(c.id) })
                  return next
                })} />
              {t('emploiDuTemps.toutes_classes')}
            </label>
            {selected.length > 0 && (
              <div className="flex flex-wrap items-center gap-3">
                <span className="text-sm text-navy-500">{t('emploiDuTemps.classes_selectionnees', { count: selected.length })}</span>
                {canDelete && <Button variant="danger" size="sm" disabled={busy}
                  onClick={() => void supprimer({ classe_ids: selected.map((c) => c.id) })}>
                  <Trash2 className="h-4 w-4" />{t('emploiDuTemps.supprimer_classes')}
                </Button>}
              </div>
            )}
          </div>
          {visible.length === 0 ? <EmptyState label={t('emploiDuTemps.empty_classes')} /> : (
            <ul className="divide-y divide-navy-100 border-y border-navy-100">
              {visible.map((classe) => (
                <li key={classe.id} className={selection.has(classe.id) ? 'flex items-center gap-3 bg-navy-50 px-3' : 'flex items-center gap-3 px-3'}>
                  <input type="checkbox" className="h-4 w-4 shrink-0 accent-navy-700" checked={selection.has(classe.id)}
                    aria-label={t('emploiDuTemps.selectionner_classe', { classe: classe.nom })} disabled={busy} onChange={() => toggle(classe.id)} />
                  <button type="button" disabled={busy} onClick={() => onOpen(classe.id)}
                    aria-label={t('emploiDuTemps.ouvrir_classe', { classe: classe.nom })}
                    className="flex min-w-0 flex-1 items-center gap-3 py-4 text-left text-navy-800 hover:text-navy-600 focus-visible:outline-2 focus-visible:outline-navy-500">
                    <span className="min-w-0 flex-1 break-words">
                      <span className="block text-sm font-semibold">{classe.nom}</span>
                      {scope === null && <span className="block text-xs text-navy-500">{classe.school?.name}</span>}
                    </span>
                    <span className="shrink-0 text-xs font-medium text-navy-500">{t('emploiDuTemps.cours_planifies', { count: classe.cours_planifies })}</span>
                    <ArrowRight className="h-4 w-4 shrink-0" />
                  </button>
                </li>
              ))}
            </ul>
          )}
        </>
      )}
      {choixEcole && <Modal title={t('emploiDuTemps.choisir_ecole_suppression')} onClose={() => setChoixEcole(false)}>
        <div role="dialog" aria-modal="true" aria-label={t('emploiDuTemps.choisir_ecole_suppression')}
          className="flex flex-col divide-y divide-navy-100">
          {schools.map((school) => <button key={school.id} type="button"
            onClick={() => void supprimer({ school_id: school.id }, school)}
            className="flex items-center gap-3 py-4 text-left text-sm font-semibold text-navy-800 hover:bg-navy-50">
            <Building2 className="h-5 w-5 shrink-0" /><span className="min-w-0 flex-1 break-words">{school.name}</span><ArrowRight className="h-4 w-4 shrink-0" />
          </button>)}
        </div>
      </Modal>}
    </section>
  )
}
