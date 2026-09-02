export interface TelephoneEntry {
  numero: string
  is_principal: boolean
}

/** Nombre minimal de numéros exigé par tuteur, pour rester joignable même si un contact est indisponible. */
export const NB_TELEPHONES_MIN = 3

export function telephonesParDefaut(): TelephoneEntry[] {
  return Array.from({ length: NB_TELEPHONES_MIN }, (_, i) => ({ numero: '', is_principal: i === 0 }))
}

/** Complète jusqu'à `NB_TELEPHONES_MIN` entrées (fiche existante avec moins de 3 numéros) sans jamais en retirer. */
export function completerTelephones(telephones: TelephoneEntry[]): TelephoneEntry[] {
  const liste = telephones.length > 0 ? [...telephones] : []
  while (liste.length < NB_TELEPHONES_MIN) liste.push({ numero: '', is_principal: false })
  if (!liste.some((t) => t.is_principal)) liste[0].is_principal = true
  return liste
}
