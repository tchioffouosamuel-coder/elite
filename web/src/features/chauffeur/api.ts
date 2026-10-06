import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

/**
 * Espace chauffeur : sa tournée vue du siège du conducteur. Toutes ces routes
 * sont bornées côté API aux véhicules que le compte conduit effectivement ce
 * jour-là (cf. ChauffeurService), et aucune n'écrit sur une souscription, un
 * trajet ou un arrêt — ce n'est pas le métier du chauffeur.
 */

export type SensTournee = "aller" | "retour";

export interface ContactTuteur {
  nom_complet: string;
  telephone: string | null;
  lien_parente: string | null;
  principal: boolean;
}

export interface EleveTournee {
  affectation_id: number;
  eleve_id: number;
  matricule: string | null;
  nom_complet: string | null;
  sexe: string | null;
  classe: string | null;
  option_trajet: string | null;
  /** Déjà monté sur cette tournée (date + sens) : la case cochée. */
  pris: boolean;
  pris_le: string | null;
  tuteurs: ContactTuteur[];
  /** Renseignés seulement par l'annuaire de bord (`fetchMesEleves`). */
  trajet?: string | null;
  arret?: string | null;
}

export interface ArretTournee {
  id: number;
  nom: string;
  lieu_dit: string | null;
  ordre: number;
  heure_passage: string | null;
  eleves: EleveTournee[];
}

export interface TrajetTournee {
  id: number;
  nom: string;
  vehicule_id: number;
  immatriculation: string | null;
  effectif: number;
  pris: number;
  arrets: ArretTournee[];
  /** Souscriptions sans arrêt rattaché : à ne pas oublier au bord de la route. */
  eleves_sans_arret: EleveTournee[];
}

export interface Itineraire {
  date: string;
  sens: SensTournee;
  effectif: number;
  pris: number;
  trajets: TrajetTournee[];
}

export interface ChauffeurResume {
  id: number;
  nom_complet: string | null;
  telephone: string | null;
}

export interface RelaisItineraire {
  id: number;
  vehicule_id: number;
  immatriculation: string | null;
  marque: string | null;
  capacite: number | null;
  du: string;
  au: string;
  motif: string | null;
  statut: "disponible" | "pourvu" | "annule";
  titulaire: ChauffeurResume | null;
  remplacant: ChauffeurResume | null;
}

export interface BilanVehicule {
  vehicule_id: number;
  immatriculation: string;
  marque: string | null;
  capacite: number;
  statut: string;
  effectif: number;
  recettes: number;
  depenses: number;
  salaire: number;
  resultat: number;
  repris_en_remplacement: boolean;
}

export interface TableauDeBordChauffeur {
  mois: string;
  date: string;
  effectif_transporte: number;
  capacite_totale: number;
  /**
   * Rentabilité du mois : ce que les familles ont réglé *pour* ce mois-ci,
   * moins les dépenses du mois imputées au bus et le salaire du chauffeur.
   */
  rentabilite: {
    recettes: number;
    depenses: number;
    salaire: number;
    resultat: number;
  };
  vehicules: BilanVehicule[];
  tournee_du_jour: Record<SensTournee, { effectif: number; pris: number }>;
  itineraires_disponibles: RelaisItineraire[];
  mes_empechements: RelaisItineraire[];
}

export async function fetchTableauDeBordChauffeur(params?: { mois?: string; date?: string }): Promise<TableauDeBordChauffeur> {
  const { data } = await http.get<ApiResponse<TableauDeBordChauffeur>>("/chauffeur/tableau-de-bord", { params });
  return data.data;
}

export async function fetchItineraire(params: { date?: string; sens?: SensTournee }): Promise<Itineraire> {
  const { data } = await http.get<ApiResponse<Itineraire>>("/chauffeur/itineraire", { params });
  return data.data;
}

export async function fetchMesEleves(params?: { date?: string }): Promise<EleveTournee[]> {
  const { data } = await http.get<ApiResponse<EleveTournee[]>>("/chauffeur/eleves", { params });
  return data.data;
}

/** Coche (ou décoche) un enfant comme monté à son arrêt. Idempotent côté API. */
export async function pointerEleve(payload: {
  affectation_id: number;
  date?: string;
  sens?: SensTournee;
  pris: boolean;
}): Promise<{ affectation_id: number; pris: boolean; pris_le: string | null }> {
  const { data } = await http.post<ApiResponse<{ affectation_id: number; pris: boolean; pris_le: string | null }>>(
    "/chauffeur/ramassages",
    payload,
  );
  return data.data;
}

export async function declarerEmpechement(payload: {
  vehicule_id: number;
  du: string;
  au: string;
  motif?: string | null;
}): Promise<RelaisItineraire> {
  const { data } = await http.post<ApiResponse<RelaisItineraire>>("/chauffeur/empechements", payload);
  return data.data;
}

export async function reprendreItineraire(id: number): Promise<RelaisItineraire> {
  const { data } = await http.post<ApiResponse<RelaisItineraire>>(`/chauffeur/empechements/${id}/reprendre`);
  return data.data;
}

export async function annulerMonEmpechement(id: number): Promise<RelaisItineraire> {
  const { data } = await http.post<ApiResponse<RelaisItineraire>>(`/chauffeur/empechements/${id}/annuler`);
  return data.data;
}

// ---------------------------------------------------------------- Direction

/** Tous les relais de la flotte — réservé à `bus.remplacement`. */
export async function fetchRelaisFlotte(statut?: RelaisItineraire["statut"]): Promise<RelaisItineraire[]> {
  const { data } = await http.get<ApiResponse<RelaisItineraire[]>>("/bus/remplacements", {
    params: statut ? { statut } : undefined,
  });
  return data.data;
}

export async function ouvrirRelais(payload: {
  vehicule_id: number;
  du: string;
  au: string;
  motif?: string | null;
  chauffeur_remplacant_id?: number | null;
}): Promise<RelaisItineraire> {
  const { data } = await http.post<ApiResponse<RelaisItineraire>>("/bus/remplacements", payload);
  return data.data;
}

export async function attribuerRelais(id: number, chauffeurRemplacantId: number): Promise<RelaisItineraire> {
  const { data } = await http.post<ApiResponse<RelaisItineraire>>(`/bus/remplacements/${id}/attribuer`, {
    chauffeur_remplacant_id: chauffeurRemplacantId,
  });
  return data.data;
}

export async function annulerRelais(id: number): Promise<RelaisItineraire> {
  const { data } = await http.post<ApiResponse<RelaisItineraire>>(`/bus/remplacements/${id}/annuler`);
  return data.data;
}
