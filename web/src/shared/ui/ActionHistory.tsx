import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react'
import type { ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Redo2, Undo2 } from 'lucide-react'
import { http, getPendingWrites } from '@/shared/lib/http'
import { erreur, succes } from '@/shared/lib/alertes'
import { useAuthStore } from '@/shared/store/authStore'
import type { ApiResponse } from '@/shared/types/api'
import { newActionId } from '@/shared/lib/actionId'
import { HistoryContext, useActionHistory } from '@/shared/lib/actionHistoryContext'

interface Action {
  id: string
  libelle: string
  revision: number
  route: string
  contexte: { classeMatiereId?: string; classeCompetenceId?: string; sequence_ids?: number[] }
}

interface HistoryState {
  annuler: Action | null
  retablir: Action | null
  raison: string | null
}

export function ActionHistoryProvider({ children }: { children: ReactNode }) {
  const user = useAuthStore((state) => state.user)
  const token = useAuthStore((state) => state.token)
  const schoolId = useAuthStore((state) => state.activeSchoolId)
  const queryClient = useQueryClient()
  const [busy, setBusy] = useState(false)
  const [pendingWrites, setPendingWrites] = useState(getPendingWrites)
  const running = useRef(false)
  const queryKey = ['action-history', token, user?.id, schoolId]
  const enabled = Boolean(token && user && !user.doit_changer_mot_de_passe)
  const history = useQuery({
    queryKey,
    queryFn: async () => (await http.get<ApiResponse<HistoryState>>('/historique-actions', { silent403: true })).data.data,
    enabled,
    refetchInterval: 20_000,
    refetchOnWindowFocus: true,
    retry: false,
  })

  useEffect(() => {
    const update = () => {
      setPendingWrites(getPendingWrites())
      void queryClient.invalidateQueries({ queryKey: ['action-history'] })
    }
    window.addEventListener('app:write-finished', update)
    const pending = () => setPendingWrites(getPendingWrites())
    window.addEventListener('app:write-started', pending)
    return () => {
      window.removeEventListener('app:write-finished', update)
      window.removeEventListener('app:write-started', pending)
    }
  }, [queryClient])

  const ready = enabled && !busy && !history.isFetching && !history.isError && pendingWrites === 0
  const run = useCallback(async (redo: boolean) => {
    const action = history.data?.[redo ? 'retablir' : 'annuler']
    if (!ready || running.current || !action) return
    running.current = true
    setBusy(true)
    try {
      const response = await http.post<ApiResponse<HistoryState>>(
        `/historique-actions/${action.id}/${redo ? 'retablir' : 'annuler'}`,
        { revision: action.revision },
        { headers: { 'Idempotency-Key': newActionId() } },
      )
      const current = useAuthStore.getState()
      if (current.token !== token || current.activeSchoolId !== schoolId) return
      succes(response.data.message)
      window.dispatchEvent(new CustomEvent('app:history-applied', { detail: action }))
      await queryClient.invalidateQueries()
    } catch (error) {
      erreur((error as { message?: string }).message ?? 'Impossible de modifier cette action.')
      await queryClient.invalidateQueries({ queryKey: ['action-history'] })
    } finally {
      running.current = false
      setBusy(false)
    }
  }, [history.data, ready, queryClient, token, schoolId])

  const undo = useCallback(() => { void run(false) }, [run])
  const redo = useCallback(() => { void run(true) }, [run])
  const canUndo = ready && Boolean(history.data?.annuler)
  const canRedo = ready && Boolean(history.data?.retablir)
  const keyboardControls = useRef({ undo, redo, canUndo, canRedo })
  useLayoutEffect(() => {
    keyboardControls.current = { undo, redo, canUndo, canRedo }
  }, [undo, redo, canUndo, canRedo])

  useEffect(() => {
    const handleKey = (event: KeyboardEvent) => {
      if (event.defaultPrevented || event.repeat || event.altKey || !(event.ctrlKey || event.metaKey)) return
      const target = event.target
      if (target instanceof HTMLElement && (target.isContentEditable || target.closest('input, textarea, [contenteditable="true"], [role="textbox"]'))) return
      if (document.querySelector('[role="dialog"][aria-modal="true"]')) return
      const key = event.key.toLowerCase()
      const controls = keyboardControls.current
      if (key === 'z' && !event.shiftKey && controls.canUndo) {
        event.preventDefault()
        controls.undo()
      } else if ((key === 'y' || (key === 'z' && event.shiftKey)) && controls.canRedo) {
        event.preventDefault()
        controls.redo()
      }
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [])

  return (
    <HistoryContext.Provider value={{
      undo, redo, canUndo, canRedo,
      undoLabel: history.data?.annuler ? `Annuler : ${history.data.annuler.libelle} (Ctrl+Z)` : (history.data?.raison ?? 'Aucune action a annuler'),
      redoLabel: history.data?.retablir ? `Retablir : ${history.data.retablir.libelle} (Ctrl+Y)` : 'Aucune action a retablir',
    }}>
      {children}
    </HistoryContext.Provider>
  )
}

export function ActionHistoryButtons() {
  const history = useActionHistory()
  return (
    <div className="flex flex-none items-center gap-1">
      <button type="button" title={history.undoLabel} aria-label={history.undoLabel} disabled={!history.canUndo}
        onClick={history.undo} className="flex h-8 w-8 items-center justify-center rounded text-navy-600 hover:bg-navy-50 disabled:cursor-default disabled:opacity-30">
        <Undo2 className="h-4 w-4" />
      </button>
      <button type="button" title={history.redoLabel} aria-label={history.redoLabel} disabled={!history.canRedo}
        onClick={history.redo} className="flex h-8 w-8 items-center justify-center rounded text-navy-600 hover:bg-navy-50 disabled:cursor-default disabled:opacity-30">
        <Redo2 className="h-4 w-4" />
      </button>
    </div>
  )
}
