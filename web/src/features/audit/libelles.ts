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
