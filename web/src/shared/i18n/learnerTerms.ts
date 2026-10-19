import type { TFunction } from 'i18next'
import type { TypeEcole } from '@/shared/lib/ecole'

export type FormeApprenant =
  | 'singular'
  | 'plural'
  | 'enroll'
  | 'edit'
  | 'empty'
  | 'enrolled'
  | 'averagePerClass'
  | 'checked'

export function termeApprenant(
  t: TFunction,
  typeEcole: TypeEcole | null | undefined,
  forme: FormeApprenant,
): string {
  return t(`learnerTerms.${typeEcole ?? 'generic'}.${forme}`)
}
