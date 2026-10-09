import { createContext, useContext } from 'react'

export interface HistoryControls {
  undo: () => void
  redo: () => void
  canUndo: boolean
  canRedo: boolean
  undoLabel: string
  redoLabel: string
}

export const HistoryContext = createContext<HistoryControls | null>(null)

export function useActionHistory() {
  const history = useContext(HistoryContext)
  if (!history) throw new Error('ActionHistoryProvider absent')
  return history
}
