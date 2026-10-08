import type { ActionAudit } from "@/features/audit/api";

type Ton = "neutral" | "green" | "red" | "gold" | "blue" | "purple";

export const TON_ACTION: Record<ActionAudit, Ton> = {
  connexion: "green",
  connexion_echouee: "red",
  deconnexion: "neutral",
  consultation: "blue",
  creation: "green",
  modification: "gold",
  suppression: "red",
  import: "purple",
  export: "purple",
  impression: "purple",
  synchronisation: "neutral",
  action: "gold",
};

/** `comptes-utilisateurs` → « Comptes utilisateurs ». */
export function libelleModule(module: string | null): string {
  if (!module) return "—";
  const texte = module.replace(/[-_]/g, " ");
  return texte.charAt(0).toUpperCase() + texte.slice(1);
}

const VERBES_USAGER: Partial<Record<ActionAudit, string>> = {
  connexion: "S’est connecté",
  connexion_echouee: "A essayé de se connecter",
  deconnexion: "S’est déconnecté",
  consultation: "A consulté",
  creation: "A créé",
  modification: "A modifié",
  suppression: "A supprimé",
  import: "A importé des données dans",
  export: "A exporté des informations depuis",
  impression: "A préparé un document depuis",
  synchronisation: "A synchronisé",
  action: "A effectué une action dans",
};

const MODULES_USAGER: Record<string, string> = {
  auth: "son compte",
  eleves: "les dossiers des élèves",
  parent: "l’espace des parents",
  personnel: "la gestion du personnel",
  finances: "les finances",
  notes: "les résultats scolaires",
  absences: "le suivi des absences",
  classes: "les classes",
  audit: "le journal d’activité",
};

const ACTIONS_PARENT: Record<string, string> = {
  absences: "les absences",
  assiduite: "l’assiduité",
  sanctions: "les sanctions",
  "emploi-du-temps": "l’emploi du temps",
  "lecons-semaine": "les leçons de la semaine",
  justifications: "les justificatifs d’absence",
};

/** Résumé lisible qui évite les routes techniques et les identifiants de l’URL. */
export function descriptionUsager(
  action: ActionAudit,
  module: string | null,
  url: string,
  sujet?: string | null,
): string {
  const verbe = VERBES_USAGER[action] ?? "A effectué une action dans";
  const enfant = url.match(/\/(?:parent\/)?enfants\/\d+\/([^/?]+)/)?.[1];

  if (module === "parent" && enfant) {
    const rubrique = ACTIONS_PARENT[enfant] ?? "le dossier scolaire";
    return `${verbe} ${rubrique}${sujet ? ` de ${sujet}` : " d’un élève"}.`;
  }

  const cible = MODULES_USAGER[module ?? ""] ?? (module ? `le module ${libelleModule(module)}` : "le système");
  return `${verbe} ${cible}.`;
}

export function roleUsager(role: string | null): string | null {
  if (!role) return null;
  return role
    .replace(/[_-]/g, " ")
    .replace(/\b\w/g, (lettre) => lettre.toLocaleUpperCase("fr-FR"));
}

const CHAMPS_USAGER: Record<string, string> = {
  name: "Nom",
  nom_complet: "Nom complet",
  libelle: "Nom",
  email: "Adresse e-mail",
  telephone: "Téléphone",
  statut: "Statut",
  sexe: "Sexe",
  adresse: "Adresse",
  date_naissance: "Date de naissance",
  montant: "Montant",
  motif: "Motif",
  action: "Action",
};

const MODELES_USAGER: Record<string, string> = {
  Eleve: "Élève",
  User: "Compte utilisateur",
  School: "Établissement",
  Classe: "Classe",
  Personnel: "Personnel",
  Versement: "Versement",
};

export function libelleModeleUsager(modele: string): string {
  return MODELES_USAGER[modele] ?? libelleModule(modele);
}

export function libelleChampUsager(champ: string): string | null {
  if (champ === "id" || champ.endsWith("_id") || champ.includes("token") || champ.includes("password")) return null;
  return CHAMPS_USAGER[champ] ?? champ.replace(/_/g, " ").replace(/\b\w/g, (lettre) => lettre.toLocaleUpperCase("fr-FR"));
}

export function valeurUsager(value: unknown): string {
  if (value === null || value === undefined || value === "") return "Non renseigné";
  if (typeof value === "boolean") return value ? "Oui" : "Non";
  if (typeof value === "object") return "Informations mises à jour";
  return String(value);
}

export function formaterHorodatage(iso: string): string {
  return new Date(iso).toLocaleString("fr-FR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
}

/** Date locale au format `AAAA-MM-JJ` attendu par `<input type="date">` et l'API. */
export function dateIso(date: Date): string {
  const p = (n: number) => String(n).padStart(2, "0");
  return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`;
}
