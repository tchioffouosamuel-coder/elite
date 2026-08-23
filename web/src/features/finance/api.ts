import { http } from "@/shared/lib/http";
import type { ApiResponse } from "@/shared/types/api";

/** Les montants circulent en francs CFA entiers : la devise n'a pas de subdivision. */
export type ModePaiement =
  | "especes"
  | "mobile_money"
  | "virement"
  | "cheque"
  | "depot_bancaire";

export const MODES: { valeur: ModePaiement; libelle: string }[] = [
  { valeur: "especes", libelle: "Espèces" },
  { valeur: "mobile_money", libelle: "Mobile Money" },
  { valeur: "virement", libelle: "Virement" },
  { valeur: "cheque", libelle: "Chèque" },
  { valeur: "depot_bancaire", libelle: "Dépôt bancaire" },
];

export type StatutPaiement =
  | "impaye"
  | "partiel"
  | "solde"
  | "avance"
  | "sans_frais";

// ---------------------------------------------------------------- Scolarité

/** Un poste du dû — scolarité, un frais annexe, le bus… — avec ce qui est déjà réglé dessus. */
export interface RubriqueScolarite {
  cle: "report_dette" | "scolarite" | "frais_annexe" | "bus";
  dossier_frais_annexe_id: number | null;
  libelle: string;
  montant_du: number;
  montant_paye: number;
  reste: number;
}

export interface DossierScolarite {
  id: number;
  eleve: {
    id: number;
    matricule: string | null;
    nom_complet: string;
    classe: string | null;
    classe_id: number | null;
    contact?: string | null;
  };
  montant_scolarite: number;
  remise: number;
  report_dette: number;
  frais_annexes?: { id: number; libelle: string; montant: number }[];
  bus?: {
    trajet: string | null;
    option_trajet: string | null;
    montant: number;
  } | null;
  /** Décomposition du dû poste par poste — présente uniquement sur la fiche d'un seul dossier. */
  rubriques?: RubriqueScolarite[];
  total_du: number;
  total_paye: number;
  reste_a_payer: number;
  avance: number;
  statut_paiement: StatutPaiement;
  taux_recouvrement: number;
  versements?: {
    id: number;
    numero_recu: string;
    date_versement: string;
    montant: number;
    mode: ModePaiement;
    annule: boolean;
  }[];
  observation: string | null;
}

export interface TotauxScolarite {
  effectif: number;
  attendu: number;
  recouvre: number;
  reste: number;
  avances: number;
  taux_recouvrement: number;
  insolvables: number;
}

export async function fetchSituation(params: {
  classe_id?: number | null;
  statut?: string | null;
}): Promise<{ dossiers: DossierScolarite[]; totaux: TotauxScolarite }> {
  const { data } = await http.get<
    ApiResponse<{ dossiers: DossierScolarite[]; totaux: TotauxScolarite }>
  >("/scolarite/situation", { params });
  return data.data;
}

export async function fetchDossier(eleveId: number): Promise<DossierScolarite> {
  const { data } = await http.get<ApiResponse<DossierScolarite>>(
    `/eleves/${eleveId}/scolarite`,
  );
  return data.data;
}

export interface LigneVentilation {
  affectation: RubriqueScolarite["cle"];
  dossier_frais_annexe_id?: number | null;
  libelle?: string;
  montant: number;
}

/** Reproduit côté client l'ordre de ventilation automatique du serveur (report → scolarité → frais annexes → bus), pour préremplir la répartition. */
export function ventilerAutomatiquement(
  rubriques: RubriqueScolarite[],
  montant: number,
): number[] {
  let restant = montant;
  return rubriques.map((r) => {
    if (restant <= 0) return 0;
    const part = Math.min(restant, r.reste);
    restant -= part;
    return part;
  });
}

export async function encaisser(
  dossierId: number,
  payload: {
    montant: number;
    mode: ModePaiement;
    date_versement?: string;
    reference_externe?: string;
    note?: string;
    lignes?: LigneVentilation[];
  },
): Promise<{ versement_id: number; numero_recu: string }> {
  const { data } = await http.post<
    ApiResponse<{ versement_id: number; numero_recu: string }>
  >(`/scolarite/dossiers/${dossierId}/versements`, payload);
  return data.data;
}

export async function annulerVersement(
  versementId: number,
  motif: string,
): Promise<void> {
  await http.post(`/versements/${versementId}/annuler`, { motif });
}

export interface VerificationVersement {
  numero_recu: string;
  eleve: { nom_complet: string; matricule: string | null };
  classe: string | null;
  ecole: string;
  montant: number;
  date_versement: string;
  mode: ModePaiement;
  annule: boolean;
}

/** Page publique de vérification (QR code du reçu) : aucune authentification requise. */
export async function fetchVerificationVersement(
  versementId: number,
  signature: string,
): Promise<VerificationVersement> {
  const { data } = await http.get<ApiResponse<VerificationVersement>>(
    `/verification-versement/${versementId}/${signature}`,
  );
  return data.data;
}

// ---------------------------------------------------------------- Dépenses

export interface Depense {
  id: number;
  date_depense: string;
  libelle: string;
  montant: number;
  source: "caisse" | "revenu_personnel";
  mode: ModePaiement;
  statut: "engagee" | "payee" | "annulee";
  beneficiaire: string | null;
  reference_facture: string | null;
  responsable: string | null;
  compte: { id: number; code: string; libelle: string } | null;
  justificatif_url: string | null;
  saisi_par: string | null;
  motif_annulation: string | null;
  vehicule_id: number | null;
}

export interface CompteComptable {
  id: number;
  code: string;
  libelle: string;
  classe: number;
  sens: "debit" | "credit";
}

export async function fetchDepenses(params: {
  du?: string | null;
  au?: string | null;
  statut?: string | null;
  vehicule_id?: number | null;
}): Promise<{
  depenses: Depense[];
  par_compte: {
    code: string;
    libelle: string;
    nombre: number;
    montant: number;
  }[];
  totaux: {
    nombre: number;
    engage: number;
    paye: number;
    total: number;
    annule: number;
  };
}> {
  const { data } = await http.get<ApiResponse<never>>("/depenses", { params });
  return data.data as never;
}

export async function fetchComptes(): Promise<CompteComptable[]> {
  const { data } = await http.get<ApiResponse<CompteComptable[]>>(
    "/comptes-comptables",
  );
  return data.data;
}

/** `FormData` et non JSON : la dépense peut porter son justificatif scanné. */
export async function creerDepense(
  champs: Record<string, string | number | Blob | undefined>,
): Promise<Depense> {
  const formulaire = new FormData();
  Object.entries(champs).forEach(([cle, valeur]) => {
    if (valeur !== undefined && valeur !== "")
      formulaire.append(cle, valeur as string | Blob);
  });

  const { data } = await http.post<ApiResponse<Depense>>(
    "/depenses",
    formulaire,
    {
      headers: { "Content-Type": "multipart/form-data" },
    },
  );
  return data.data;
}

export async function annulerDepense(id: number, motif: string): Promise<void> {
  await http.post(`/depenses/${id}/annuler`, { motif });
}

export async function payerDepense(
  id: number,
  mode?: ModePaiement,
): Promise<void> {
  await http.post(`/depenses/${id}/payer`, mode ? { mode } : {});
}

// -------------------------------------------------------------------- Paie

export interface BulletinPaie {
  id: number;
  numero: string;
  personnel: {
    id: number;
    nom_complet: string;
    matricule: string | null;
    fonction: string | null;
  };
  periode: string;
  jours_ouvrables: number;
  jours_travailles: number;
  salaire_brut: number;
  net_taxable: number;
  charges_salariales: number;
  charges_patronales: number;
  total_deductions: number;
  net_a_payer: number;
  cout_employeur: number;
  statut: "brouillon" | "valide" | "paye";
  mode_paiement: ModePaiement | null;
  date_paiement: string | null;
  emarge: boolean;
}

export interface TotauxPaie {
  effectif: number;
  brut: number;
  charges_salariales: number;
  charges_patronales: number;
  net_a_payer: number;
  cout_employeur: number;
  regles: number;
  emarges: number;
}

export async function fetchPaie(params: {
  annee: number;
  mois: number;
}): Promise<{
  periode: { annee: number; mois: number };
  totaux: TotauxPaie;
  bulletins: BulletinPaie[];
}> {
  const { data } = await http.get<ApiResponse<never>>("/paie", { params });
  return data.data as never;
}

/** Un agent que le lot n'a pas su préparer — et pourquoi. */
export interface AgentIgnore {
  personnel_id: number;
  nom_complet: string;
  // heures_requises : vacataire du technique, ses heures du mois manquent —
  // se règle avec preparerBulletinAgent(). sans_remuneration : aucune
  // rémunération définie, à corriger dans sa fiche.
  motif: "heures_requises" | "sans_remuneration";
  message: string;
}

export async function preparerPaie(
  params: { annee: number; mois: number },
  payload: { jours_ouvrables?: number; jours_travailles?: number },
): Promise<{ prepares: number; ignores: AgentIgnore[] }> {
  const { data } = await http.post<
    ApiResponse<{ prepares: number; ignores: AgentIgnore[] }>
  >("/paie/preparer", payload, { params });
  return data.data;
}

/** Prépare le bulletin d'un seul agent — c'est ici qu'un vacataire déclare ses heures du mois. */
export async function preparerBulletinAgent(
  personnelId: number,
  params: { annee: number; mois: number },
  payload: { heures?: number },
): Promise<BulletinPaie> {
  const { data } = await http.post<ApiResponse<BulletinPaie>>(
    `/paie/personnels/${personnelId}/preparer`,
    payload,
    { params },
  );
  return data.data;
}

export async function arreterBulletin(id: number): Promise<void> {
  await http.post(`/paie/bulletins/${id}/arreter`);
}

export async function arreterBulletins(
  ids: number[],
): Promise<{ arretes: number }> {
  const { data } = await http.post<ApiResponse<{ arretes: number }>>(
    "/paie/bulletins/arreter-lot",
    { ids },
  );
  return data.data;
}

export async function payerBulletin(
  id: number,
  mode: ModePaiement,
  date_paiement?: string,
): Promise<void> {
  await http.post(`/paie/bulletins/${id}/payer`, { mode, date_paiement });
}

export async function payerBulletins(
  ids: number[],
  mode: ModePaiement,
  date_paiement?: string,
): Promise<{ regles: number }> {
  const { data } = await http.post<ApiResponse<{ regles: number }>>(
    "/paie/bulletins/payer-lot",
    {
      ids,
      mode,
      date_paiement,
    },
  );
  return data.data;
}

export async function emargerBulletin(
  id: number,
  reference?: string,
): Promise<void> {
  await http.post(`/paie/bulletins/${id}/emarger`, { reference });
}

// ------------------------------------------------------- Avances sur salaire

export type ModeRemboursement = "retenue_salaire" | "versement_direct";
export type StatutAvance = "en_cours" | "partielle" | "remboursee" | "annulee";

export interface RemboursementAvance {
  id: number;
  montant: number;
  date_remboursement: string;
  mode: ModeRemboursement;
  note: string | null;
}

export interface AvanceSalaire {
  id: number;
  personnel: {
    id: number;
    nom_complet: string;
    matricule: string | null;
    fonction: string | null;
  };
  school?: { id: number; name: string; code: string; type: string } | null;
  montant: number;
  nombre_mois: number | null;
  mensualite: number | null;
  date_avance: string;
  motif: string | null;
  montant_rembourse: number;
  solde: number;
  statut: StatutAvance;
  annule: boolean;
  motif_annulation: string | null;
  remboursements: RemboursementAvance[];
}

export interface TotauxAvances {
  effectif: number;
  total_accorde: number;
  total_rembourse: number;
  total_restant: number;
}

export async function fetchAvancesSalaire(params?: {
  personnel_id?: number;
  statut?: StatutAvance;
}): Promise<{
  avances: AvanceSalaire[];
  totaux: TotauxAvances;
}> {
  const { data } = await http.get<
    ApiResponse<{ avances: AvanceSalaire[]; totaux: TotauxAvances }>
  >("/avances-salaire", { params });
  return data.data;
}

/** Bornes de remboursement d'un employé : salaire brut en cours et mensualité maximale (50%). */
export interface PlafondAvance {
  salaire_brut: number | null;
  plafond_mensualite: number | null;
}

export async function fetchPlafondAvance(
  personnelId: number,
): Promise<PlafondAvance> {
  const { data } = await http.get<ApiResponse<PlafondAvance>>(
    "/avances-salaire/plafond",
    {
      params: { personnel_id: personnelId },
    },
  );
  return data.data;
}

export async function accorderAvance(payload: {
  personnel_id: number;
  montant: number;
  nombre_mois: number;
  date_avance: string;
  motif?: string | null;
}): Promise<AvanceSalaire> {
  const { data } = await http.post<ApiResponse<AvanceSalaire>>(
    "/avances-salaire",
    payload,
  );
  return data.data;
}

export async function rembourserAvance(
  id: number,
  payload: {
    montant: number;
    date_remboursement?: string;
    mode?: ModeRemboursement;
    note?: string | null;
  },
): Promise<AvanceSalaire> {
  const { data } = await http.post<ApiResponse<AvanceSalaire>>(
    `/avances-salaire/${id}/remboursements`,
    payload,
  );
  return data.data;
}

export async function annulerAvance(
  id: number,
  motif: string,
): Promise<AvanceSalaire> {
  const { data } = await http.post<ApiResponse<AvanceSalaire>>(
    `/avances-salaire/${id}/annuler`,
    { motif },
  );
  return data.data;
}

// ------------------------------------------- Demandes d'avance (personnel)

export type StatutDemandeAvance = "en_attente" | "validee" | "rejetee";

export interface DemandeAvanceSalaire {
  id: number;
  statut: StatutDemandeAvance;
  personnel: {
    id: number;
    nom_complet: string;
    matricule: string | null;
    fonction: string | null;
  } | null;
  montant: number;
  nombre_mois: number;
  /** Échéancier qui sera appliqué à la validation, et la borne des 50% du brut. */
  mensualite: number;
  plafond_mensualite: number | null;
  motif: string | null;
  motif_rejet: string | null;
  avance_salaire_id: number | null;
  created_at: string;
  traite_le: string | null;
}

export async function fetchDemandesAvanceSalaire(
  statut?: StatutDemandeAvance | "",
): Promise<DemandeAvanceSalaire[]> {
  const { data } = await http.get<ApiResponse<DemandeAvanceSalaire[]>>(
    "/demandes-avance-salaire",
    { params: { statut: statut || undefined } },
  );
  return data.data;
}

export async function validerDemandeAvance(
  id: number,
): Promise<DemandeAvanceSalaire> {
  const { data } = await http.post<ApiResponse<DemandeAvanceSalaire>>(
    `/demandes-avance-salaire/${id}/valider`,
  );
  return data.data;
}

export async function rejeterDemandeAvance(
  id: number,
  motif: string,
): Promise<DemandeAvanceSalaire> {
  const { data } = await http.post<ApiResponse<DemandeAvanceSalaire>>(
    `/demandes-avance-salaire/${id}/rejeter`,
    { motif },
  );
  return data.data;
}

// ----------------------------------------------------------- Rémunérations

/** Les six gains du bulletin, dans leur ordre d'affichage. */
export const GAINS = [
  { champ: "salaire_base", libelle: "Salaire de base", exonere: false },
  { champ: "prime_anciennete", libelle: "Ancienneté", exonere: false },
  { champ: "prime_communication", libelle: "Communication", exonere: true },
  { champ: "prime_transport", libelle: "Transport", exonere: true },
  { champ: "prime_recherche", libelle: "Recherche & leçon", exonere: false },
  { champ: "prime_performance", libelle: "Performance", exonere: false },
] as const;

export type ChampGain = (typeof GAINS)[number]["champ"];

/** Salaire mensuel négocié, ou vacation payée à l'heure enseignée. */
export type ModeRemuneration = "mensuel" | "horaire";

export interface Remuneration extends Record<ChampGain, number> {
  id: number;
  date_effet: string;
  categorie: string | null;
  mode: ModeRemuneration;
  taux_horaire: number | null;
  brut: number;
  base_taxable: number;
  charges_salariales: number;
  charges_patronales: number;
  net: number;
  cout_employeur: number;
}

export interface LigneRemuneration {
  id: number;
  nom_complet: string;
  matricule: string | null;
  fonction: string | null;
  statut: string;
  school?: { id: number; name: string; code: string; type: string } | null;
  remuneration: Remuneration | null;
}

export interface TotauxRemunerations {
  effectif: number;
  definies: number;
  sans_remuneration: number;
  masse_brute: number;
  cout_employeur: number;
  net_mensuel: number;
}

export interface Simulation {
  brut: number;
  base_taxable: number;
  charges_salariales: number;
  charges_patronales: number;
  net_avant_deductions: number;
  cout_employeur: number;
  retenues: {
    libelle: string;
    libelle_en: string;
    base: number;
    taux_salarial: number | null;
    taux_patronal: number | null;
    montant_salarial: number;
    montant_patronal: number;
  }[];
}

export async function fetchRemunerations(): Promise<{
  personnels: LigneRemuneration[];
  totaux: TotauxRemunerations;
}> {
  const { data } = await http.get<ApiResponse<never>>("/remunerations");
  return data.data as never;
}

export async function fetchHistoriqueRemunerations(
  personnelId: number,
): Promise<{
  personnel: { id: number; nom_complet: string };
  historique: Remuneration[];
}> {
  const { data } = await http.get<ApiResponse<never>>(
    `/remunerations/${personnelId}/historique`,
  );
  return data.data as never;
}

export async function enregistrerRemuneration(
  personnelId: number,
  payload: Partial<Record<ChampGain, number>> & {
    date_effet: string;
    categorie?: string;
    /** « horaire » pour un vacataire : seules les heures enseignées sont dues. */
    mode?: ModeRemuneration;
    taux_horaire?: number;
  },
): Promise<Remuneration> {
  const { data } = await http.post<ApiResponse<Remuneration>>(
    `/remunerations/${personnelId}`,
    payload,
  );
  return data.data;
}

/**
 * Applique une même rémunération à plusieurs agents — copiée d'un collègue
 * (`source_personnel_id`) ou saisie directement.
 */
export async function appliquerRemuneration(
  payload: {
    personnel_ids: number[];
    date_effet: string;
    source_personnel_id?: number;
    categorie?: string;
  } & Partial<Record<ChampGain, number>>,
): Promise<{ appliquee: number; ignores: number }> {
  const { data } = await http.post<
    ApiResponse<{ appliquee: number; ignores: number }>
  >("/remunerations/appliquer", payload);
  return data.data;
}

/** Applique le barème sans rien enregistrer, pour l'aperçu du net. */
export async function simulerRemuneration(
  gains: Partial<Record<ChampGain, number>>,
): Promise<Simulation> {
  const { data } = await http.post<ApiResponse<Simulation>>(
    "/remunerations/simuler",
    gains,
  );
  return data.data;
}

// ------------------------------------------------------------------ Tarifs

export interface Tarifs {
  annee_scolaire: { id: number; libelle: string };
  tarif_par_defaut: number | null;
  classes: {
    id: number;
    nom: string;
    montant: number | null;
    dossiers_ouverts: number;
  }[];
  frais_annexes: {
    id: number;
    libelle: string;
    montant: number;
    obligatoire: boolean;
    is_active: boolean;
    /** Vide = s'applique à toute l'école. Sinon limité à cette classe, ou à ce groupe de classes. */
    classes: { id: number; nom: string }[];
  }[];
}

export async function fetchTarifs(): Promise<Tarifs> {
  const { data } = await http.get<ApiResponse<Tarifs>>("/tarifs");
  return data.data;
}

/** `classe_id` nul = tarif par défaut de l'établissement. Répercuté aussitôt sur les dossiers déjà ouverts. */
export async function definirTarif(
  classeId: number | null,
  montant: number,
): Promise<{ dossiers_mis_a_jour: number }> {
  const { data } = await http.post<
    ApiResponse<{ dossiers_mis_a_jour: number }>
  >("/tarifs", { classe_id: classeId, montant });
  return data.data;
}

export async function supprimerTarif(
  classeId: number,
): Promise<{ dossiers_mis_a_jour: number }> {
  const { data } = await http.delete<
    ApiResponse<{ dossiers_mis_a_jour: number }>
  >(`/tarifs/classes/${classeId}`);
  return data.data;
}

export async function creerFraisAnnexe(payload: {
  libelle: string;
  montant: number;
  obligatoire: boolean;
  classe_ids?: number[];
}): Promise<void> {
  await http.post("/tarifs/frais-annexes", payload);
}

export async function modifierFraisAnnexe(
  id: number,
  payload: Partial<{
    libelle: string;
    montant: number;
    obligatoire: boolean;
    is_active: boolean;
    classe_ids: number[];
  }>,
): Promise<void> {
  await http.put(`/tarifs/frais-annexes/${id}`, payload);
}

export async function desactiverFraisAnnexe(id: number): Promise<void> {
  await http.delete(`/tarifs/frais-annexes/${id}`);
}

// ---------------------------------------------------------------- Rapports

export interface LigneCompte {
  code: string;
  libelle: string;
  montant: number;
}

export interface Resultat {
  periode: { du: string | null; au: string | null };
  produits: { lignes: LigneCompte[]; total: number };
  charges: { lignes: LigneCompte[]; total: number };
  resultat: number;
  taux_charges: number;
}

export interface Tresorerie {
  comptes: {
    code: string;
    libelle: string;
    debit: number;
    credit: number;
    solde: number;
  }[];
  disponible: number;
}

export interface Balance {
  lignes: {
    code: string;
    libelle: string;
    classe: number;
    debit: number;
    credit: number;
    solde: number;
  }[];
  total_debit: number;
  total_credit: number;
  equilibre: boolean;
}

export interface TableauDeBord {
  scolarite: TotauxScolarite;
  paie: {
    bulletins: number;
    masse_brute: number;
    cout_employeur: number;
    a_regler: number;
  };
  resultat: { produits: number; charges: number; solde: number };
  tresorerie: number;
}

type Periode = { du?: string | null; au?: string | null };

export async function fetchTableauDeBord(): Promise<TableauDeBord> {
  const { data } = await http.get<ApiResponse<TableauDeBord>>(
    "/rapports/tableau-de-bord",
  );
  return data.data;
}

export async function fetchResultat(params: Periode): Promise<Resultat> {
  const { data } = await http.get<ApiResponse<Resultat>>("/rapports/resultat", {
    params,
  });
  return data.data;
}

export async function fetchTresorerie(params: Periode): Promise<Tresorerie> {
  const { data } = await http.get<ApiResponse<Tresorerie>>(
    "/rapports/tresorerie",
    { params },
  );
  return data.data;
}

export async function fetchBalance(params: Periode): Promise<Balance> {
  const { data } = await http.get<ApiResponse<Balance>>("/rapports/balance", {
    params,
  });
  return data.data;
}

// ------------------------------------------------------------- Insolvables

/** Statuts d'une tranche, du plus favorable au plus préoccupant. */
export type StatutTranche = "soldee" | "partielle" | "a_venir" | "en_retard";

/** Une échéance de l'échéancier, telle que le dossier la voit. */
export interface TrancheEcheancier {
  id: number;
  libelle: string;
  ordre: number;
  pourcentage: number;
  date_echeance: string | null;
  montant: number;
  montant_paye: number;
  reste: number;
  echue: boolean;
  statut: StatutTranche;
}

/**
 * Échéancier d'un dossier. `actif` à faux quand l'école n'a pas découpé son
 * année : tout reste alors exigible immédiatement, comme auparavant.
 */
export interface Echeancier {
  actif: boolean;
  total_du: number;
  total_paye: number;
  du_a_ce_jour: number;
  retard: number;
  delai_grace: number;
  prochaine_echeance: TrancheEcheancier | null;
  tranches: TrancheEcheancier[];
}

/** Une tranche du référentiel, telle qu'on la paramètre. */
export interface TrancheScolarite {
  id: number;
  libelle: string;
  pourcentage: number;
  date_echeance: string;
  ordre: number;
  annee_scolaire_id: number;
  school_id: number;
}

export interface TrancheScolaritePayload {
  libelle: string;
  pourcentage: number;
  date_echeance: string;
}

export interface Insolvable {
  eleve: {
    id: number;
    matricule: string | null;
    nom_complet: string;
    classe: string | null;
  };
  school: { id: number; name: string };
  seuil: number;
  total_du: number;
  total_paye: number;
  reste_a_payer: number;
  /** Faux quand l'école n'a pas d'échéancier : le retard vaut alors le reste à payer. */
  echeancier_actif: boolean;
  /** Ce qui aurait dû être versé à ce jour, d'après les échéances passées. */
  du_a_ce_jour: number;
  /** Ce qui manque sur les échéances déjà passées — le motif réel de la relance. */
  retard: number;
  tranches_en_retard: { libelle: string; date_echeance: string | null; reste: number }[];
  rubriques: RubriqueScolarite[];
  moratoire: { date_expiration: string; motif: string | null } | null;
}

export interface TotauxInsolvables {
  effectif: number;
  total_du: number;
  total_reste: number;
}

export async function fetchInsolvables(params: {
  school_id?: number | null;
  classe_id?: number | null;
}): Promise<{ lignes: Insolvable[]; totaux: TotauxInsolvables }> {
  const { data } = await http.get<
    ApiResponse<{ lignes: Insolvable[]; totaux: TotauxInsolvables }>
  >("/finance/insolvables", { params });
  return data.data;
}

// -------------------------------------------------------------- Moratoires

export interface Moratoire {
  id: number;
  eleve: { id: number; nom_complet: string; matricule: string | null };
  date_delivrance: string;
  date_expiration: string;
  motif: string | null;
  valide: boolean;
  accorde_par: string | null;
}

export async function fetchMoratoires(eleveId: number): Promise<Moratoire[]> {
  const { data } = await http.get<ApiResponse<Moratoire[]>>(
    `/eleves/${eleveId}/moratoires`,
  );
  return data.data;
}

export async function creerMoratoire(
  eleveId: number,
  payload: { date_delivrance: string; date_expiration: string; motif?: string },
): Promise<Moratoire> {
  const { data } = await http.post<ApiResponse<Moratoire>>(
    `/eleves/${eleveId}/moratoires`,
    payload,
  );
  return data.data;
}

export async function supprimerMoratoire(id: number): Promise<void> {
  await http.delete(`/moratoires/${id}`);
}

// ----------------------------------------------------------------- Remises

export interface RemiseIndividuelle {
  id: number;
  eleve: { id: number; nom_complet: string };
  annee_scolaire: string | null;
  montant: number;
  motif: string | null;
  accorde_par: string | null;
  created_at: string;
}

export async function fetchRemises(
  eleveId: number,
): Promise<RemiseIndividuelle[]> {
  const { data } = await http.get<ApiResponse<RemiseIndividuelle[]>>(
    `/eleves/${eleveId}/remises`,
  );
  return data.data;
}

export async function creerRemise(
  eleveId: number,
  payload: { montant: number; motif?: string; annee_scolaire_id?: number },
): Promise<RemiseIndividuelle> {
  const { data } = await http.post<ApiResponse<RemiseIndividuelle>>(
    `/eleves/${eleveId}/remises`,
    payload,
  );
  return data.data;
}

export async function supprimerRemise(id: number): Promise<void> {
  await http.delete(`/remises/${id}`);
}

// --------------------------------------------------------- Dettes antérieures

export interface DetteAnterieure {
  id: number;
  eleve: { id: number; nom_complet: string };
  montant: number;
  motif: string | null;
  imputee: boolean;
  accorde_par: string | null;
  created_at: string;
}

export async function fetchDettesAnterieures(
  eleveId: number,
): Promise<DetteAnterieure[]> {
  const { data } = await http.get<ApiResponse<DetteAnterieure[]>>(
    `/eleves/${eleveId}/dettes-anterieures`,
  );
  return data.data;
}

export async function creerDetteAnterieure(
  eleveId: number,
  payload: { montant: number; motif?: string },
): Promise<DetteAnterieure> {
  const { data } = await http.post<ApiResponse<DetteAnterieure>>(
    `/eleves/${eleveId}/dettes-anterieures`,
    payload,
  );
  return data.data;
}

export async function supprimerDetteAnterieure(id: number): Promise<void> {
  await http.delete(`/dettes-anterieures/${id}`);
}

/** Séparateur d'unités de mille, comme sur les documents imprimés. */
export function francs(montant: number | null | undefined): string {
  return `${(montant ?? 0).toLocaleString("fr-FR").replace(/ | /g, " ")} F`;
}

// ------------------------------------------ État de synthèse des exercices

export type NatureCompte = "exploitation" | "investissement" | "capital";

export interface LigneEtat {
  code: string;
  libelle: string;
  libelle_en: string | null;
  nature: NatureCompte;
  assiette: "libre" | "par_eleve";
  montant_unitaire: number | null;
  montant: number;
}

export interface EtatSynthese {
  exercice: {
    annee_scolaire_id: number;
    libelle: string;
    school_id: number;
    effectif: number;
  };
  depenses: LigneEtat[];
  produits: LigneEtat[];
  /** Le document tel que le tient l'établissement, dépôt de l'exploitant compris. */
  document: {
    total_depenses: number;
    total_recettes: number;
    balance: number;
    apport_fondateur: number;
  };
  /** Ce que le document mélange, séparé : seule l'exploitation use l'exercice. */
  analytique: {
    charges_exploitation: number;
    produits_exploitation: number;
    resultat_exploitation: number;
    investissement: number;
    capital: number;
  };
}

export interface ExerciceResume {
  annee_scolaire_id: number;
  libelle: string;
  effectif: number;
  total_depenses: number;
  total_recettes: number;
  balance: number;
  apport_fondateur: number;
  resultat_exploitation: number;
  investissement: number;
}

export interface ExerciceOption {
  id: number;
  libelle: string;
  date_debut: string;
  date_fin: string;
  is_active: boolean;
}

export interface PrelevementEleve {
  code: string;
  libelle: string;
  effectif: number;
  montant_unitaire: number;
  du: number;
  enregistre: number;
  ecart: number;
}

export async function fetchExercices(
  schoolId: number,
): Promise<ExerciceOption[]> {
  const { data } = await http.get<ApiResponse<{ exercices: ExerciceOption[] }>>(
    "/etat-synthese/exercices",
    {
      params: { school_id: schoolId },
    },
  );
  return data.data.exercices;
}

export async function fetchEtatSynthese(
  schoolId: number,
  anneeScolaireId: number,
): Promise<EtatSynthese> {
  const { data } = await http.get<ApiResponse<EtatSynthese>>("/etat-synthese", {
    params: { school_id: schoolId, annee_scolaire_id: anneeScolaireId },
  });
  return data.data;
}

export async function fetchSerieExercices(
  schoolId: number,
): Promise<ExerciceResume[]> {
  const { data } = await http.get<ApiResponse<{ exercices: ExerciceResume[] }>>(
    "/etat-synthese/serie",
    {
      params: { school_id: schoolId },
    },
  );
  return data.data.exercices;
}

export async function fetchPrelevementsEleve(
  schoolId: number,
  anneeScolaireId: number,
): Promise<PrelevementEleve[]> {
  const { data } = await http.get<ApiResponse<{ lignes: PrelevementEleve[] }>>(
    "/prelevements-eleve",
    {
      params: { school_id: schoolId, annee_scolaire_id: anneeScolaireId },
    },
  );
  return data.data.lignes;
}

export async function regulariserPrelevements(
  schoolId: number,
  anneeScolaireId: number,
): Promise<{ lignes: PrelevementEleve[]; message: string }> {
  const { data } = await http.post<ApiResponse<{ lignes: PrelevementEleve[] }>>(
    "/prelevements-eleve/regulariser",
    {
      school_id: schoolId,
      annee_scolaire_id: anneeScolaireId,
    },
  );
  return { lignes: data.data.lignes, message: data.message ?? "" };
}

// ------------------------------------------------ Amortissements et bordereau

export interface DotationAmortissement {
  immobilisation_id: number;
  libelle: string;
  montant: number;
  duree_annees: number;
  cumul: number;
  valeur_residuelle: number;
  dotation: number;
  deja_dote: boolean;
}

export async function fetchAmortissements(
  schoolId: number,
  anneeScolaireId: number,
): Promise<DotationAmortissement[]> {
  const { data } = await http.get<
    ApiResponse<{ lignes: DotationAmortissement[] }>
  >("/amortissements", {
    params: { school_id: schoolId, annee_scolaire_id: anneeScolaireId },
  });
  return data.data.lignes;
}

export async function doterAmortissements(
  schoolId: number,
  anneeScolaireId: number,
): Promise<{ lignes: DotationAmortissement[]; message: string }> {
  const { data } = await http.post<
    ApiResponse<{ lignes: DotationAmortissement[] }>
  >("/amortissements/doter", {
    school_id: schoolId,
    annee_scolaire_id: anneeScolaireId,
  });
  return { lignes: data.data.lignes, message: data.message ?? "" };
}

export interface LigneBordereau {
  bulletin_id: number;
  numero: string;
  personnel_id: number;
  nom_complet: string | null;
  matricule: string | null;
  banque: string | null;
  numero_compte: string | null;
  net_a_payer: number;
  montant: number;
  arrondi: number;
}

export interface BordereauVirement {
  periode: { annee: number; mois: number };
  banques: {
    banque: string;
    effectif: number;
    total: number;
    lignes: LigneBordereau[];
  }[];
  total: number;
  sans_domiciliation: LigneBordereau[];
}

export async function fetchBordereauVirement(
  schoolId: number,
  annee: number,
  mois: number,
): Promise<BordereauVirement> {
  const { data } = await http.get<ApiResponse<BordereauVirement>>(
    "/paie/bordereau",
    {
      params: { school_id: schoolId, annee, mois },
    },
  );
  return data.data;
}

export async function reviserImmobilisation(
  id: number,
  payload: { libelle?: string; duree_annees?: number },
): Promise<DotationAmortissement & { duree_annees: number }> {
  const { data } = await http.patch<
    ApiResponse<DotationAmortissement & { duree_annees: number }>
  >(`/immobilisations/${id}`, payload);
  return data.data;
}

/* ------------------------------------------------------------------ */
/* Échéancier de scolarité                                             */
/* ------------------------------------------------------------------ */

export async function fetchTranchesScolarite(anneeScolaireId?: number): Promise<{
  annee_scolaire_id: number;
  delai_grace: number;
  tranches: TrancheScolarite[];
}> {
  const { data } = await http.get<
    ApiResponse<{ annee_scolaire_id: number; delai_grace: number; tranches: TrancheScolarite[] }>
  >("/tranches-scolarite", {
    params: anneeScolaireId ? { annee_scolaire_id: anneeScolaireId } : undefined,
  });
  return data.data;
}

/**
 * Remplace l'échéancier de l'année en un seul appel. Un tableau vide le
 * supprime — c'est le moyen de revenir à une scolarité exigible en une fois.
 */
export async function remplacerTranchesScolarite(
  anneeScolaireId: number,
  tranches: TrancheScolaritePayload[],
  schoolId?: number | null,
): Promise<TrancheScolarite[]> {
  const { data } = await http.put<ApiResponse<TrancheScolarite[]>>("/tranches-scolarite", {
    annee_scolaire_id: anneeScolaireId,
    school_id: schoolId ?? null,
    tranches,
  });
  return data.data;
}
