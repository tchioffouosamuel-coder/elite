import { type ReactNode } from 'react'
import { Check } from 'lucide-react'
import { clsx } from 'clsx'
import { Button } from '@/shared/ui/Button'

interface Step {
  id: string
  label: string
  description?: string
}

interface StepFormProps {
  steps: Step[]
  currentStep: number
  onStepChange: (step: number) => void
  children: ReactNode
  isLastStep?: boolean
  isSubmitting?: boolean
  onSubmit?: () => void
  onCancel?: () => void
  showSteps?: boolean
  /**
   * Garde de passage à l'étape suivante : renvoyer `false` bloque la
   * navigation. Sert à valider les champs de l'étape courante avant d'avancer
   * — sans elle, une erreur de saisie ne se découvre qu'à l'enregistrement,
   * plusieurs écrans plus loin.
   */
  onNext?: () => boolean | Promise<boolean>
  /**
   * Retire la largeur maximale et l'encadré : la mise en page vient alors du
   * conteneur (une modale, par exemple), qui a déjà les siens.
   */
  compact?: boolean
}

export function StepForm({
  steps,
  currentStep,
  onStepChange,
  children,
  isLastStep = false,
  isSubmitting = false,
  onSubmit,
  onCancel,
  showSteps = true,
  onNext,
  compact = false,
}: StepFormProps) {
  const canGoNext = currentStep < steps.length - 1
  const canGoPrev = currentStep > 0

  const suivant = async () => {
    if (onNext && !(await onNext())) return
    onStepChange(currentStep + 1)
  }

  return (
    <div className={clsx(!compact && 'mx-auto max-w-2xl')}>
      {showSteps && (
        <div className={clsx('flex', compact ? 'mb-5' : 'mb-8')}>
          {steps.map((step, index) => (
            <div key={step.id} className="flex flex-1 items-center">
              <button
                type="button"
                onClick={() => onStepChange(index)}
                // Revenir en arrière est libre, sauter en avant ne l'est pas :
                // l'étape suivante peut dépendre de champs pas encore validés.
                disabled={index > currentStep}
                className="flex min-w-0 flex-1 items-center gap-2.5 text-left disabled:cursor-not-allowed"
              >
                <span
                  className={clsx(
                    'flex flex-none items-center justify-center rounded-full text-sm font-semibold transition-colors',
                    compact ? 'h-8 w-8 text-xs' : 'h-10 w-10',
                    index < currentStep
                      ? 'bg-green-500 text-white'
                      : index === currentStep
                        ? 'bg-navy-700 text-cream-50 ring-4 ring-navy-100'
                        : 'bg-navy-100 text-navy-400',
                  )}
                >
                  {index < currentStep ? <Check className="h-4 w-4" /> : index + 1}
                </span>
                <span className="hidden min-w-0 lg:block">
                  <span className="block truncate text-sm font-semibold text-navy-900">{step.label}</span>
                  {step.description && (
                    <span className="block truncate text-xs text-navy-400">{step.description}</span>
                  )}
                </span>
              </button>
              {index < steps.length - 1 && (
                <span
                  className={clsx(
                    'mx-2 h-1 flex-1 rounded-full transition-colors',
                    index < currentStep ? 'bg-green-500' : 'bg-navy-100',
                  )}
                />
              )}
            </div>
          ))}
        </div>
      )}

      <div className={clsx(compact ? 'mb-5' : 'mb-6 rounded-xl border border-navy-100 bg-white/75 p-6 shadow-soft')}>
        {children}
      </div>

      <div className="flex justify-between gap-3">
        <Button type="button" variant="secondary" onClick={onCancel ?? (() => onStepChange(0))} disabled={isSubmitting}>
          Annuler
        </Button>
        <div className="flex gap-3">
          {canGoPrev && (
            <Button type="button" variant="secondary" onClick={() => onStepChange(currentStep - 1)} disabled={isSubmitting}>
              Précédent
            </Button>
          )}
          {canGoNext && (
            <Button type="button" onClick={suivant} disabled={isSubmitting}>
              Suivant
            </Button>
          )}
          {isLastStep && (
            <Button type="button" onClick={onSubmit} disabled={isSubmitting}>
              {isSubmitting ? 'Enregistrement…' : 'Enregistrer'}
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}
