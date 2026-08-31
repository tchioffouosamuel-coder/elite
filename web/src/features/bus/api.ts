import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

export interface School {
  id: number;
  name: string;
  code: string;
  type: string;
}

export interface BusVehicule {
  id: number;
  immatriculation: string;
  marque: string | null;
  couleur: string | null;
  capacite: number;
  statut: "actif" | "hors_service";
  chauffeur: {
    id: number;
    nom_complet: string;
    telephone: string | null;
  } | null;
  school?: School | null;
}

export interface BusVehiculePayload {
  immatriculation: string;
  marque?: string | null;
  couleur?: string | null;
  capacite?: number | null;
  chauffeur_id?: number | null;
  statut?: BusVehicule["statut"];
  school_id?: number | null;
}

export interface BusArret {
  id: number;
  nom: string;
  lieu_dit: string | null;
  ordre: number;
  heure_passage: string | null;
}

export interface BusArretPayload {
  nom: string;
  lieu_dit?: string | null;
  ordre?: number | null;
  heure_passage?: string | null;
}

export type OptionTrajet = "aller_simple" | "retour_simple" | "aller_retour";

export const LIBELLES_OPTION_TRAJET: Record<OptionTrajet, string> = {
  aller_simple: "Aller simple",
  retour_simple: "Retour simple",
  aller_retour: "Aller-retour",
};

export type StatutPaiementBus = "sans_frais" | "impaye" | "partiel" | "solde";

export interface BusTrajet {
  id: number;
  nom: string;
  description: string | null;
  tarif_aller_simple: number | null;
  tarif_retour_simple: number | null;
  tarif_aller_retour: number | null;
  vehicule: { id: number; immatriculation: string } | null;
  arrets: BusArret[];
  effectif: number | null;
  school?: School | null;
}

/** Tarif du trajet pour l'option choisie — même règle que côté serveur, pour l'aperçu avant envoi. */
export function tarifPourOption(
  trajet: BusTrajet,
  option: OptionTrajet,
): number | null {
  return option === "aller_simple"
    ? trajet.tarif_aller_simple
    : option === "retour_simple"
      ? trajet.tarif_retour_simple
      : trajet.tarif_aller_retour;
}

export interface BusTrajetDetail extends BusTrajet {
  affectations: {
    id: number;
    statut: "actif" | "suspendu";
    tarif_mensuel: number | null;
    statut_paiement: StatutPaiementBus;
    option_trajet: OptionTrajet;
    eleve: { id: number; nom_complet: string; matricule: string | null };
    arret: {
      id: number;
      nom: string;
      lieu_dit: string | null;
      heure_passage: string | null;
    } | null;
  }[];
}

export interface BusTrajetPayload {
  nom: string;
  description?: string | null;
  vehicule_id?: number | null;
  tarif_aller_simple?: number | null;
  tarif_retour_simple?: number | null;
  tarif_aller_retour?: number | null;
  school_id?: number | null;
}

export interface BusAffectation {
  id: number;
  statut: "actif" | "suspendu";
  tarif_mensuel: number | null;
  statut_paiement: StatutPaiementBus;
  option_trajet: OptionTrajet;
  eleve: {
    id: number;
    nom_complet: string;
    matricule: string | null;
    classe: string | null;
  };
  trajet: { id: number; nom: string };
  arret: {
    id: number;
    nom: string;
    lieu_dit: string | null;
    heure_passage: string | null;
  } | null;
}

/** Le tarif ne se saisit jamais : il vient du trajet, calculé côté serveur depuis l'option choisie. */
export interface BusSouscriptionPayload {
  trajet_id: number;
  arret_id?: number | null;
  annee_scolaire_id?: number | null;
  option_trajet: OptionTrajet;
}

export interface EleveTransport {
  id: number;
  nom_complet: string;
  matricule: string | null;
  classe: { id: number; nom: string } | null;
  bus: {
    affectation_id: number;
    trajet: { id: number; nom: string };
    arret: {
      id: number;
      nom: string;
      lieu_dit: string | null;
      heure_passage: string | null;
    } | null;
    option_trajet: OptionTrajet;
    tarif_mensuel: number | null;
    statut_paiement: StatutPaiementBus;
  } | null;
}

// ---- Véhicules ---------------------------------------------------------

export async function fetchVehicules(): Promise<BusVehicule[]> {
  const { data } = await http.get<ApiResponse<BusVehicule[]>>("/bus/vehicules");
  return data.data;
}

/** Pas d'endpoint dédié à un seul véhicule côté API : la flotte tient en quelques lignes, filtrer côté client suffit. */
export async function fetchVehicule(
  id: number,
): Promise<BusVehicule | undefined> {
  const vehicules = await fetchVehicules();
  return vehicules.find((v) => v.id === id);
}

export async function creerVehicule(
  payload: BusVehiculePayload,
): Promise<BusVehicule> {
  const { data } = await http.post<ApiResponse<BusVehicule>>(
    "/bus/vehicules",
    payload,
  );
  return data.data;
}

export async function modifierVehicule(
  id: number,
  payload: BusVehiculePayload,
): Promise<BusVehicule> {
  const { data } = await http.put<ApiResponse<BusVehicule>>(
    `/bus/vehicules/${id}`,
    payload,
  );
  return data.data;
}

export async function supprimerVehicule(id: number): Promise<void> {
  await http.delete(`/bus/vehicules/${id}`);
}

// ---- Trajets et arrêts --------------------------------------------------

export async function fetchTrajets(): Promise<BusTrajet[]> {
  const { data } = await http.get<ApiResponse<BusTrajet[]>>("/bus/trajets");
  return data.data;
}

export async function fetchTrajet(id: number): Promise<BusTrajetDetail> {
  const { data } = await http.get<ApiResponse<BusTrajetDetail>>(
    `/bus/trajets/${id}`,
  );
  return data.data;
}

export async function creerTrajet(
  payload: BusTrajetPayload,
): Promise<BusTrajet> {
  const { data } = await http.post<ApiResponse<BusTrajet>>(
    "/bus/trajets",
    payload,
  );
  return data.data;
}

export async function modifierTrajet(
  id: number,
  payload: BusTrajetPayload,
): Promise<BusTrajet> {
  const { data } = await http.put<ApiResponse<BusTrajet>>(
    `/bus/trajets/${id}`,
    payload,
  );
  return data.data;
}

export async function supprimerTrajet(id: number): Promise<void> {
  await http.delete(`/bus/trajets/${id}`);
}

export async function ajouterArret(
  trajetId: number,
  payload: BusArretPayload,
): Promise<BusArret> {
  const { data } = await http.post<ApiResponse<BusArret>>(
    `/bus/trajets/${trajetId}/arrets`,
    payload,
  );
  return data.data;
}

export async function modifierArret(
  trajetId: number,
  arretId: number,
  payload: BusArretPayload,
): Promise<BusArret> {
  const { data } = await http.put<ApiResponse<BusArret>>(
    `/bus/trajets/${trajetId}/arrets/${arretId}`,
    payload,
  );
  return data.data;
}

export async function supprimerArret(
  trajetId: number,
  arretId: number,
): Promise<void> {
  await http.delete(`/bus/trajets/${trajetId}/arrets/${arretId}`);
}

// ---- Affectation des élèves ----------------------------------------------

export async function fetchAffectations(
  trajetId?: number,
): Promise<BusAffectation[]> {
  const { data } = await http.get<ApiResponse<BusAffectation[]>>(
    "/bus/affectations",
    {
      params: trajetId ? { trajet_id: trajetId } : undefined,
    },
  );
  return data.data;
}

/** Tous les élèves de l'école, souscription bus incluse — la vue qui remplace de deviner sur quel trajet chercher un élève. */
export async function fetchElevesTransport(
  classeId?: number,
): Promise<EleveTransport[]> {
  const { data } = await http.get<ApiResponse<EleveTransport[]>>(
    "/bus/eleves",
    {
      params: classeId ? { classe_id: classeId } : undefined,
    },
  );
  return data.data;
}

export async function souscrireEleve(
  eleveId: number,
  payload: BusSouscriptionPayload,
): Promise<BusAffectation> {
  const { data } = await http.post<ApiResponse<BusAffectation>>(
    "/bus/affectations",
    { ...payload, eleve_id: eleveId },
  );
  return data.data;
}

export async function souscrireLot(
  eleveIds: number[],
  payload: BusSouscriptionPayload,
): Promise<{ souscrits: number; ignores: string[] }> {
  const { data } = await http.post<
    ApiResponse<{ souscrits: number; ignores: string[] }>
  >("/bus/souscriptions-lot", {
    ...payload,
    eleve_ids: eleveIds,
  });
  return data.data;
}

export async function modifierAffectation(
  id: number,
  payload: {
    arret_id?: number | null;
    statut?: BusAffectation["statut"];
    option_trajet?: OptionTrajet;
  },
): Promise<BusAffectation> {
  const { data } = await http.put<ApiResponse<BusAffectation>>(
    `/bus/affectations/${id}`,
    payload,
  );
  return data.data;
}

export async function retirerAffectation(id: number): Promise<void> {
  await http.delete(`/bus/affectations/${id}`);
}

// ---- Paiement mensuel -----------------------------------------------------

export type ModePaiementBus = "especes" | "mobile_money" | "virement" | "cheque" | "depot_bancaire";

export const MODES_PAIEMENT_BUS: { valeur: ModePaiementBus; libelle: string }[] = [
  { valeur: "especes", libelle: "Espèces" },
  { valeur: "mobile_money", libelle: "Mobile Money" },
  { valeur: "virement", libelle: "Virement" },
  { valeur: "cheque", libelle: "Chèque" },
  { valeur: "depot_bancaire", libelle: "Dépôt bancaire" },
];

/** Un mois de la souscription — dû, réglé et statut, indépendamment de la scolarité. */
export interface MoisPaiementBus {
  mois: string;
  du: number;
  paye: number;
  reste: number;
  statut: StatutPaiementBus;
}

export interface VersementBus {
  id: number;
  numero_recu: string;
  mois: string;
  date_versement: string;
  montant: number;
  mode: ModePaiementBus;
  annule: boolean;
}

export interface SituationPaiementBus {
  affectation: {
    id: number;
    tarif_mensuel: number | null;
    eleve: { id: number; nom_complet: string; matricule: string | null; classe: string | null };
    trajet: string;
  };
  situation_mensuelle: MoisPaiementBus[];
  total_du: number;
  total_paye: number;
  reste_a_payer: number;
  statut_paiement: StatutPaiementBus;
  versements: VersementBus[];
}

export async function fetchSituationPaiementBus(affectationId: number): Promise<SituationPaiementBus> {
  const { data } = await http.get<ApiResponse<SituationPaiementBus>>(`/bus/affectations/${affectationId}/versements`);
  return data.data;
}

export interface EncaissementBusPayload {
  mois: string;
  montant: number;
  date_versement?: string;
  mode?: ModePaiementBus;
  reference_externe?: string;
  note?: string;
}

export async function encaisserBus(
  affectationId: number,
  payload: EncaissementBusPayload,
): Promise<{ versement_id: number; numero_recu: string }> {
  const { data } = await http.post<ApiResponse<{ versement_id: number; numero_recu: string }>>(
    `/bus/affectations/${affectationId}/versements`,
    payload,
  );
  return data.data;
}

export async function annulerVersementBus(versementId: number, motif: string): Promise<void> {
  await http.post(`/bus/versements/${versementId}/annuler`, { motif });
}

export interface VerificationVersementBus {
  numero_recu: string;
  eleve: { nom_complet: string; matricule: string | null };
  classe: string | null;
  ecole: string | null;
  trajet: string;
  mois: string;
  montant: number;
  date_versement: string;
  mode: ModePaiementBus;
  annule: boolean;
}

export async function fetchVerificationVersementBus(versementId: number, signature: string): Promise<VerificationVersementBus> {
  const { data } = await http.get<ApiResponse<VerificationVersementBus>>(
    `/verification-versement-bus/${versementId}/${signature}`,
  );
  return data.data;
}

// ---- Notifications ------------------------------------------------------

export type TypeNotificationBus =
  | "retard"
  | "incident"
  | "changement_itineraire"
  | "autre";

export const LIBELLES_TYPE_NOTIFICATION: Record<TypeNotificationBus, string> = {
  retard: "Retard",
  incident: "Incident",
  changement_itineraire: "Changement d'itinéraire",
  autre: "Autre",
};

export async function notifierParents(
  trajetId: number,
  payload: { type: TypeNotificationBus; detail: string },
): Promise<{ envoyes: number }> {
  const { data } = await http.post<ApiResponse<{ envoyes: number }>>(
    `/bus/trajets/${trajetId}/notifier`,
    payload,
  );
  return data.data;
}
