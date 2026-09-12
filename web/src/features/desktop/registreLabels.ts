/**
 * Libellés humains des clés du registre de synchronisation (cf.
 * `App\Support\Sync\RegistreSync::entites()` côté API) — pour affichage dans
 * la modale de premier clonage (`PremiereSynchronisationModal`) uniquement :
 * une clé absente d'ici retombe sur un libellé dérivé de la clé elle-même
 * (`humaniser()`), jamais sur une erreur.
 */
const LIBELLES: Record<string, { fr: string; en: string }> = {
  annee_scolaires: { fr: 'Années scolaires', en: 'School years' },
  trimestres: { fr: 'Trimestres', en: 'Terms' },
  sequences: { fr: 'Séquences', en: 'Sequences' },
  niveaux: { fr: 'Niveaux (MINESEC)', en: 'Levels' },
  sous_systemes: { fr: 'Sous-systèmes', en: 'Sub-systems' },
  departements: { fr: 'Départements', en: 'Departments' },
  matieres: { fr: 'Matières', en: 'Subjects' },
  competences: { fr: 'Compétences', en: 'Competencies' },
  classe_competences: { fr: 'Compétences par classe', en: 'Class competencies' },
  appreciations: { fr: 'Appréciations', en: 'Appraisals' },
  classes: { fr: 'Classes', en: 'Classes' },
  classe_matieres: { fr: 'Matières par classe', en: 'Class subjects' },
  emplois_du_temps: { fr: "Emplois du temps", en: 'Timetables' },
  progression_items: { fr: 'Fiches de progression', en: 'Progression sheets' },
  niveaux_scolaires: { fr: "Niveaux d'enseignement", en: 'School levels' },
  champs_personnalises: { fr: 'Champs personnalisés', en: 'Custom fields' },
  progression_colonnes: { fr: 'Colonnes de progression', en: 'Progression columns' },
  evaluations: { fr: 'Évaluations', en: 'Assessments' },
  evaluation_questions: { fr: "Questions d'évaluation", en: 'Assessment questions' },
  eleves: { fr: 'Élèves', en: 'Students' },
  personnels: { fr: 'Personnel', en: 'Staff' },
  fonction_referentiel: { fr: 'Fonctions du personnel', en: 'Staff functions' },
  tuteurs: { fr: 'Tuteurs', en: 'Guardians' },
  eleve_tuteurs: { fr: 'Liens élève-tuteur', en: 'Student-guardian links' },
  tuteur_telephones: { fr: 'Téléphones des tuteurs', en: 'Guardian phone numbers' },
  preinscriptions: { fr: 'Préinscriptions', en: 'Pre-registrations' },
  modifications_eleves: { fr: "Demandes de modification élève", en: 'Student edit requests' },
  observations: { fr: 'Observations', en: 'Observations' },
  justifications_absences: { fr: "Justifications d'absence", en: 'Absence justifications' },
  absences_trimestre: { fr: 'Absences par trimestre', en: 'Term absences' },
  seances: { fr: 'Séances de cours', en: 'Class sessions' },
  presences: { fr: 'Présences', en: 'Attendance' },
  notes: { fr: 'Notes', en: 'Grades' },
  sanctions: { fr: 'Sanctions', en: 'Sanctions' },
  revendications: { fr: 'Réclamations', en: 'Claims' },
  bulletin_publications: { fr: 'Publications de bulletins', en: 'Report card publications' },
  annonces: { fr: 'Annonces', en: 'Announcements' },
  notifications_internes: { fr: 'Notifications', en: 'Notifications' },
  grilles_frais: { fr: 'Grilles de frais', en: 'Fee schedules' },
  frais_annexes: { fr: 'Frais annexes', en: 'Additional fees' },
  dossiers_scolarite: { fr: 'Dossiers de scolarité', en: 'Tuition files' },
  dossier_frais_annexes: { fr: 'Frais annexes des dossiers', en: 'File additional fees' },
  versements: { fr: 'Versements', en: 'Payments' },
  versement_lignes: { fr: 'Lignes de versement', en: 'Payment lines' },
  moratoires: { fr: 'Moratoires', en: 'Moratoriums' },
  remises: { fr: 'Remises', en: 'Discounts' },
  dettes_anterieures: { fr: 'Dettes antérieures', en: 'Prior debts' },
  tranches_scolarite: { fr: 'Tranches de scolarité', en: 'Tuition installments' },
  comptes_comptables: { fr: 'Comptes comptables', en: 'Accounting accounts' },
  ecritures_comptables: { fr: 'Écritures comptables', en: 'Accounting entries' },
  budgets_fonctionnement: { fr: 'Budgets de fonctionnement', en: 'Operating budgets' },
  depenses: { fr: 'Dépenses', en: 'Expenses' },
  immobilisations: { fr: 'Immobilisations', en: 'Fixed assets' },
  amortissements: { fr: 'Amortissements', en: 'Depreciations' },
  conseils_ecole: { fr: "Conseils d'école", en: 'School councils' },
  apee: { fr: 'APEE', en: 'PTA' },
  assurances_scolaires: { fr: 'Assurances scolaires', en: 'School insurance' },
  visites_autorites: { fr: 'Visites des autorités', en: 'Authority visits' },
  activites_rentree: { fr: 'Activités de rentrée', en: 'Back-to-school activities' },
  ventes_denrees: { fr: 'Ventes de denrées', en: 'Food sales' },
  rapport_rentree_textes: { fr: 'Rapport de rentrée', en: 'Back-to-school report' },
  rapport_trimestre_textes: { fr: 'Rapport de trimestre', en: 'Term report' },
  ventes_fournitures: { fr: 'Ventes de fournitures', en: 'Supply sales' },
  vente_fourniture_lignes: { fr: 'Lignes de vente', en: 'Sale lines' },
  entrees_stock: { fr: 'Entrées de stock', en: 'Stock entries' },
  malaises_referentiel: { fr: 'Référentiel des malaises', en: 'Ailments reference' },
  document_references: { fr: 'Numéros de documents', en: 'Document numbers' },
  avances_salaire: { fr: 'Avances sur salaire', en: 'Salary advances' },
  demandes_avance_salaire: { fr: "Demandes d'avance", en: 'Advance requests' },
  budgets_personnel: { fr: 'Budgets du personnel', en: 'Staff budgets' },
  bus_vehicules: { fr: 'Véhicules', en: 'Vehicles' },
  bus_trajets: { fr: 'Trajets de bus', en: 'Bus routes' },
  bus_arrets: { fr: 'Arrêts de bus', en: 'Bus stops' },
  bus_affectations: { fr: 'Affectations de bus', en: 'Bus assignments' },
  bus_versements: { fr: 'Versements de bus', en: 'Bus payments' },
  visites_infirmerie: { fr: "Visites à l'infirmerie", en: 'Infirmary visits' },
  visite_infirmerie_materiels: { fr: 'Matériel médical utilisé', en: 'Medical supplies used' },
  inventaire_articles: { fr: "Articles d'inventaire", en: 'Inventory items' },
  infrastructures: { fr: 'Infrastructures', en: 'Infrastructure' },
  equipements_mobiliers: { fr: 'Équipements et mobiliers', en: 'Furniture and equipment' },
}

function humaniser(cle: string): string {
  return cle.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())
}

export function libelleEntite(cle: string, locale: 'fr' | 'en'): string {
  const libelle = LIBELLES[cle]
  if (!libelle) return humaniser(cle)
  return locale === 'en' ? libelle.en : libelle.fr
}
