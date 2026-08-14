import { ReactNode } from 'react'
import { Button } from '@/shared/ui/Button'
import { clsx } from 'clsx'

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
}: StepFormProps) {
    const canGoNext = currentStep < steps.length - 1
    const canGoPrev = currentStep > 0

    return (
        <div className="mx-auto max-w-2xl">
            {showSteps && (
                <div className="mb-8">
                    <div className="flex justify-between">
                        {steps.map((step, index) => (
                            <div key={step.id} className="flex flex-1 items-center">
                                <button
                                    type="button"
                                    onClick={() => onStepChange(index)}
                                    disabled={index > currentStep}
                                    className={clsx(
                                        'flex items-center gap-3 flex-1',
                                        'disabled:cursor-not-allowed'
                                    )}
                                >
                                    <div
                                        className={clsx(
                                            'flex h-10 w-10 items-center justify-center rounded-full font-semibold text-sm transition-colors',
                                            index < currentStep
                                                ? 'bg-green-500 text-white'
                                                : index === currentStep
                                                    ? 'bg-navy-600 text-white ring-4 ring-navy-100'
                                                    : 'bg-navy-100 text-navy-400'
                                        )}
                                    >
                                        {index < currentStep ? '✓' : index + 1}
                                    </div>
                                    <div className="text-left hidden sm:block">
                                        <div className="text-sm font-semibold text-navy-900">{step.label}</div>
                                        {step.description && <div className="text-xs text-navy-400">{step.description}</div>}
                                    </div>
                                </button>
                                {index < steps.length - 1 && (
                                    <div
                                        className={clsx(
                                            'h-1 flex-1 mx-2 rounded-full transition-colors',
                                            index < currentStep ? 'bg-green-500' : 'bg-navy-100'
                                        )}
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            <div className="rounded-xl border border-navy-100 bg-white p-6 shadow-soft mb-6">
                {children}
            </div>

            <div className="flex justify-between gap-3">
                <Button
                    type="button"
                    variant="secondary"
                    onClick={onCancel || (() => onStepChange(0))}
                    disabled={isSubmitting}
                >
                    Annuler
                </Button>
                <div className="flex gap-3">
                    {canGoPrev && (
                        <Button
                            type="button"
                            variant="secondary"
                            onClick={() => onStepChange(currentStep - 1)}
                            disabled={isSubmitting}
                        >
                            Précédent
                        </Button>
                    )}
                    {canGoNext && (
                        <Button
                            type="button"
                            onClick={() => onStepChange(currentStep + 1)}
                            disabled={isSubmitting}
                        >
                            Suivant
                        </Button>
                    )}
                    {isLastStep && (
                        <Button
                            type="button"
                            onClick={onSubmit}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Enregistrement...' : 'Enregistrer'}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    )
}
