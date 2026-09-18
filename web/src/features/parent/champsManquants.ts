/** Libellés bilingues des champs pouvant apparaître dans `ChampsManquants` — partagés entre l'alerte et la page de complétion. */
export const CHAMP_LABELS: Record<string, { fr: string; en: string }> = {
  sexe: { fr: 'Sexe', en: 'Sex' },
  date_naissance: { fr: 'Date de naissance', en: 'Date of birth' },
  lieu_naissance: { fr: 'Lieu de naissance', en: 'Place of birth' },
  adresse: { fr: 'Adresse', en: 'Address' },
  numero_acte_naissance: { fr: 'N° acte de naissance', en: 'Birth certificate no.' },
  lieu_delivrance_acte: { fr: 'Lieu de délivrance', en: 'Place of issue' },
  officier_etat_civil: { fr: "Officier d'état civil", en: 'Registrar' },
  groupe_sanguin: { fr: 'Groupe sanguin', en: 'Blood type' },
  situation_sanitaire: { fr: 'Situation sanitaire', en: 'Health situation' },
  allergies: { fr: 'Allergies', en: 'Allergies' },
  photo: { fr: 'Photo', en: 'Photo' },
  telephone: { fr: 'Téléphone', en: 'Phone' },
  email: { fr: 'Email', en: 'Email' },
  profession: { fr: 'Profession', en: 'Occupation' },
  lieu_service: { fr: 'Lieu de service', en: 'Workplace' },
}

export function libelleChamp(champ: string): string {
  const l = CHAMP_LABELS[champ]
  return l ? `${l.fr} / ${l.en}` : champ
}

/** Clé de session : une fois l'alerte écartée ("Plus tard"), elle ne réapparaît plus avant la prochaine connexion. */
export const CLE_ALERTE_MASQUEE = 'parent-champs-manquants-masque'
