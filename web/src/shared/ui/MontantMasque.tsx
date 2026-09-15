import { Eye, EyeOff } from 'lucide-react'
import { useUiStore } from '@/shared/store/uiStore'
import { Button } from '@/shared/ui/Button'

/**
 * Remplace un montant par des points quand la discrétion est activée —
 * même réglage partagé entre Caisse, État de synthèse, Dettes antérieures
 * et Insolvables (cf. `useUiStore.montantsMasques`).
 */
export function masquer(valeur: string, masque: boolean): string {
  return masque ? '•••••••' : valeur
}

/** Bouton œil à poser dans l'en-tête d'une page qui affiche des montants sensibles. */
export function ToggleMontantsMasques() {
  const montantsMasques = useUiStore((s) => s.montantsMasques)
  const toggleMontantsMasques = useUiStore((s) => s.toggleMontantsMasques)

  return (
    <Button
      type="button"
      variant="secondary"
      onClick={toggleMontantsMasques}
      title={montantsMasques ? 'Afficher les montants' : 'Masquer les montants'}
      aria-label={montantsMasques ? 'Afficher les montants' : 'Masquer les montants'}
    >
      {montantsMasques ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />}
    </Button>
  )
}
