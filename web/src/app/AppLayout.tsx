import { useEffect, useMemo, useState } from 'react'
import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  LayoutDashboard,
  Users,
  School,
  UserRound,
  LogOut,
  Building2,
  BookOpen,
  ShieldAlert,
  ShieldCheck,
  Trophy,
  Settings,
  CalendarRange,
  CalendarClock,
  ClipboardCheck,
  ClipboardList,
  FileText,
  ListChecks,
  IdCard,
  Menu,
  X,
  BarChart3,
  Calculator,
  ScanFace,
  Layers,
  GitBranch,
  CalendarCheck,
  BriefcaseBusiness,
  ChevronDown,
  Search,
  Repeat,
  Wallet,
  ReceiptText,
  Banknote,
  HandCoins,
  Tags,
  PanelLeftClose,
  PanelLeftOpen,
  HeartPulse,
  Bus,
  Route as RouteIcon,
  MapPin,
  QrCode,
  ScanLine,
  Megaphone,
  Boxes,
  Store,
  Target,
  SmilePlus,
  PiggyBank,
  Gavel,
  UserCog,
  KeyRound,
  TrendingUp,
  Landmark,
} from 'lucide-react'
import { clsx } from 'clsx'
import logoWordmark from '@/assets/logo-wordmark.png'
import logoMark from '@/assets/logo-mark.png'
import { useAuthStore } from '@/shared/store/authStore'
import { useUiStore } from '@/shared/store/uiStore'
import { logout } from '@/features/auth/api'
import { NotificationBell } from './NotificationBell'

type TypeEcole = 'maternelle' | 'primaire' | 'secondaire'

const groupesTopbarDesktop = new Set([
  'nav.group.pedagogie',
  'nav.group.discipline',
  'nav.group.sante',
  'nav.group.transport',
  'nav.group.resultats',
  'nav.group.finance',
  'nav.group.admin',
])

/**
 * `types` restreint une entrée aux établissements concernés : le secondaire
 * s'organise en départements, le primaire en niveaux d'enseignement, et la
 * maternelle en simples sections. Sans `types`, l'entrée vaut pour toutes.
 */
const navGroups = [
  {
    label: 'nav.group.overview',
    items: [
      { to: '/', label: 'nav.dashboard', icon: LayoutDashboard, permission: 'dashboard.view' },
      { to: '/annonces', label: 'nav.annonces', icon: Megaphone, permission: 'annonces.view' },
      {
        to: '/enseignant/mes-informations',
        label: 'nav.mesInformations',
        icon: UserRound,
        // Aucun privilège : la fiche et la rémunération du compte connecté
        // uniquement — cf. EnseignantController::mesInformations().
        estEnseignant: true,
      },
      {
        to: '/mes-avances',
        label: 'nav.mesAvances',
        icon: HandCoins,
        // Aucun privilège : l'écran ne montre que les avances du compte
        // connecté. `estPersonnel` le réserve aux agents — un compte
        // purement administratif n'a pas de salaire à avancer.
        estPersonnel: true,
      },
      {
        to: '/mon-budget',
        label: 'nav.monBudget',
        icon: Landmark,
        // Même principe que « Mes avances » : borné au compte connecté,
        // sans privilège dédié — cf. PersonnelEspaceController::mesBudgets().
        estPersonnel: true,
      },
    ],
  },
  {
    label: 'nav.group.staff',
    items: [
      { to: '/personnel', label: 'nav.personnel', icon: Users, permission: 'personnel.view' },
      { to: '/fonctions-referentiel', label: 'nav.fonctionsReferentiel', icon: BriefcaseBusiness, permission: 'personnel.manage', superAdminOnly: true },
      {
        to: '/departements',
        label: 'nav.departements',
        icon: Building2,
        permission: 'personnel.view',
        types: ['secondaire'] as TypeEcole[],
      },
      {
        to: '/enseignant/mon-departement',
        label: 'nav.monDepartement',
        icon: Building2,
        // Aucun privilège de base : seule l'attribution « chef de
        // département » ouvre cet écran, borné au sien.
        chefDepartement: true,
        types: ['secondaire'] as TypeEcole[],
      },
      {
        to: '/enseignant/ma-classe-prof-principal',
        label: 'nav.maClasseProfPrincipal',
        icon: School,
        // Aucun privilège de base : seule l'attribution « professeur
        // principal » ouvre cet écran, borné à sa classe.
        professeurPrincipal: true,
        types: ['secondaire'] as TypeEcole[],
      },
      {
        to: '/niveaux',
        label: 'nav.niveaux',
        icon: Layers,
        permission: 'pedagogie.view',
        // La maternelle ne s'organise pas en degrés : l'entrée ne la concerne pas.
        types: ['primaire'] as TypeEcole[],
      },
    ],
  },
  {
    label: 'nav.group.classes',
    items: [
      {
        to: '/ma-classe',
        label: 'nav.maClasse',
        icon: School,
        permission: 'classes.view',
        // Titulaire d'une seule classe : la liste complète et la fiche
        // classe générique n'ont pas leur place, sa propre classe suffit.
        estEnseignant: true,
        types: ['primaire', 'maternelle'] as TypeEcole[],
      },
      // La maternelle n'attribue pas de note : elle coche un visage, et le
      // bulletin colore la case. Le référentiel de ces niveaux se règle ici.
      {
        to: '/appreciations',
        label: 'nav.appreciations',
        icon: SmilePlus,
        permission: 'pedagogie.view',
        masquerPourTitulaire: true,
        types: ['maternelle'] as TypeEcole[],
      },
      {
        to: '/mes-attributions',
        label: 'nav.mesAttributions',
        icon: UserCog,
        // Aucun privilège : l'écran ne montre que ce qui a été confié au
        // compte. `avecAttribution` le réserve à ceux qui portent au moins une
        // responsabilité — professeur principal, surveillant général, censeur,
        // conseiller d'orientation ou chef de département.
        avecAttribution: true,
      },
      { to: '/classes', label: 'nav.classes', icon: School, permission: 'classes.view', masquerPourTitulaire: true },
      { to: '/eleves', label: 'nav.eleves', icon: UserRound, permission: 'eleves.view', masquerPourTitulaire: true, masquerPourVendeur: true },
      { to: '/eleves/transferts', label: 'nav.transferts', icon: Repeat, permission: 'eleves.manage', masquerPourTitulaire: true },
      { to: '/preinscriptions', label: 'nav.preinscriptions', icon: ClipboardCheck, permission: 'eleves.manage', masquerPourTitulaire: true },
      { to: '/modifications-eleves', label: 'nav.modificationsEleves', icon: UserCog, permission: 'eleves.manage', masquerPourTitulaire: true },
      { to: '/comptes-parents', label: 'nav.comptesParents', icon: KeyRound, permission: 'eleves.manage', masquerPourTitulaire: true },
      { to: '/statistiques-parent', label: 'nav.statsPortailParent', icon: TrendingUp, permission: 'eleves.manage', masquerPourTitulaire: true },
    ],
  },
  {
    label: 'nav.group.pedagogie',
    items: [
      { to: '/matieres', label: 'nav.matieres', icon: BookOpen, permission: 'pedagogie.view', masquerPourTitulaire: true },
      // Référentiel d'évaluation du primaire et de la maternelle : au
      // secondaire la matière se note elle-même, l'écran n'y a rien à montrer.
      {
        to: '/competences',
        label: 'nav.competences',
        icon: Target,
        permission: 'pedagogie.view',
        masquerPourTitulaire: true,
        types: ['primaire', 'maternelle'] as TypeEcole[],
      },
      { to: '/progression', label: 'nav.progression', icon: GitBranch, permission: 'pedagogie.view', masquerPourTitulaire: true },
      {
        to: '/enseignant/mes-matieres',
        label: 'nav.mesMatieres',
        icon: BookOpen,
        permission: 'notes.view',
        // Table des matières affectées au compte connecté, avec saisie des
        // notes dédiée — pas la liste d'établissement de « Matières ». Le
        // primaire/maternelle a son pendant ci-dessous, sur la compétence.
        estEnseignant: true,
        types: ['secondaire'] as TypeEcole[],
      },
      {
        to: '/enseignant/mes-competences',
        label: 'nav.mesCompetences',
        icon: BookOpen,
        permission: 'notes.view',
        // Pendant primaire/maternelle de « Mes matières » ci-dessus : la
        // compétence y remplace la matière comme unité notée.
        estEnseignant: true,
        types: ['primaire', 'maternelle'] as TypeEcole[],
      },
      {
        to: '/enseignant/mon-niveau',
        label: 'nav.monNiveau',
        icon: Layers,
        // Aucun privilège de base : seule l'attribution « animateur de
        // niveau » ouvre cet écran, borné au sien.
        animateurNiveau: true,
        types: ['primaire', 'maternelle'] as TypeEcole[],
      },
      {
        to: '/ma-journee',
        label: 'nav.maJournee',
        icon: CalendarCheck,
        permission: 'appel.manage',
        // Un tableau de bord personnel à qui enseigne — pas un outil de suivi
        // pour l'administration, qui a ses propres écrans (censeur/économe
        // portent pourtant `appel.manage` sans être enseignants).
        estEnseignant: true,
      },
      {
        to: '/scanner-qr',
        label: 'nav.scannerQr',
        icon: ScanLine,
        permission: 'appel.manage',
        // Même logique que « Ma journée » : un geste personnel de
        // l'enseignant en début de cours, pas un écran d'administration.
        estEnseignant: true,
      },
      { to: '/emploi-du-temps', label: 'nav.emploiDuTemps', icon: CalendarClock, permission: 'emploi_du_temps.view' },
      { to: '/seances', label: 'nav.seances', icon: ClipboardCheck, permission: 'emploi_du_temps.view' },
      { to: '/codes-qr', label: 'nav.codesQr', icon: QrCode, permission: 'emploi_du_temps.manage' },
    ],
  },
  {
    label: 'nav.group.discipline',
    items: [
      {
        to: '/sanctions',
        label: 'nav.sanctions',
        icon: ShieldAlert,
        permission: 'discipline.view',
        // Corvée, exclusion temporaire ou définitive : des mesures qu'on ne
        // prononce pas contre de jeunes enfants. Le primaire et la maternelle
        // suivent l'assiduité par l'appel, sans sanctionner.
        types: ['secondaire'] as TypeEcole[],
      },
      // Classée ici plutôt que sous « Résultats » : c'est un suivi de la
      // discipline (sanctions, exclusions), pas des notes — même si la
      // permission qui la protège reste `bulletins.view`, côté API.
      {
        to: '/stats-disciplinaires',
        label: 'nav.statsDisciplinaires',
        icon: BarChart3,
        permission: 'bulletins.view',
      },
    ],
  },
  {
    label: 'nav.group.sante',
    items: [
      { to: '/infirmerie', label: 'nav.infirmerie', icon: HeartPulse, permission: 'infirmerie.view', masquerPourTitulaire: true },
    ],
  },
  {
    label: 'nav.group.transport',
    items: [
      { to: '/bus/vehicules', label: 'nav.busVehicules', icon: Bus, permission: 'bus.view', masquerPourTitulaire: true },
      { to: '/bus/trajets', label: 'nav.busTrajets', icon: RouteIcon, permission: 'bus.view', masquerPourTitulaire: true },
      { to: '/bus/arrets', label: 'nav.busArrets', icon: MapPin, permission: 'bus.view', masquerPourTitulaire: true },
      { to: '/bus/eleves', label: 'nav.busAffectations', icon: Users, permission: 'bus.view', masquerPourTitulaire: true },
    ],
  },
  {
    label: 'nav.group.inventaire',
    items: [
      { to: '/inventaire', label: 'nav.inventaire', icon: Boxes, permission: 'inventaire.view', masquerPourTitulaire: true },
      // Le comptoir vit à côté de l'inventaire : c'est le même stock, vu
      // depuis la caisse plutôt que depuis le registre du matériel.
      { to: '/point-de-vente', label: 'nav.pointDeVente', icon: Store, permission: 'point_de_vente.view', masquerPourTitulaire: true },
      { to: '/infrastructures', label: 'nav.infrastructures', icon: Building2, permission: 'infrastructures.view', masquerPourTitulaire: true },
    ],
  },
  {
    label: 'nav.group.resultats',
    items: [
      { to: '/bulletins', label: 'nav.bulletins', icon: FileText, permission: 'bulletins.view' },
      { to: '/remplissage', label: 'nav.remplissage', icon: ListChecks, permission: 'notes.view' },
      { to: '/palmares', label: 'nav.palmares', icon: Trophy, permission: 'bulletins.view' },
      { to: '/stats-pedagogiques', label: 'nav.statsPedagogiques', icon: BarChart3, permission: 'bulletins.view' },
      { to: '/revendications', label: 'nav.revendications', icon: Gavel, permission: 'revendications.view' },
    ],
  },
  {
    label: 'nav.group.identification',
    items: [
      { to: '/identification', label: 'nav.identification', icon: IdCard, permission: 'eleves.view', masquerPourTitulaire: true, masquerPourVendeur: true },
      {
        to: '/photos-examen',
        label: 'nav.photosExamen',
        icon: ScanFace,
        permission: 'eleves.view',
        // BEPC/Probatoire/BAC (OBC) sont des examens du secondaire ; le
        // primaire ne prépare que le CEP (DECC), la maternelle aucun examen.
        types: ['secondaire'] as TypeEcole[],
        masquerPourVendeur: true,
      },
      {
        to: '/photos-examen',
        label: 'nav.photosExamenPrimaire',
        icon: ScanFace,
        permission: 'eleves.view',
        types: ['primaire'] as TypeEcole[],
        masquerPourTitulaire: true,
        masquerPourVendeur: true,
      },
    ],
  },
  {
    label: 'nav.group.finance',
    items: [
      { to: '/caisse', label: 'nav.caisse', icon: Wallet, permission: 'finance.view' },
      { to: '/tarifs', label: 'nav.tarifs', icon: Tags, permission: 'finance.view' },
      { to: '/depenses', label: 'nav.depenses', icon: ReceiptText, permission: 'finance.view' },
      { to: '/salaires', label: 'nav.salaires', icon: HandCoins, permission: 'finance.paie' },
      { to: '/paie', label: 'nav.paie', icon: Banknote, permission: 'finance.paie' },
      { to: '/avances-salaire', label: 'nav.avancesSalaire', icon: PiggyBank, permission: 'finance.paie' },
      { to: '/budgets-personnel', label: 'nav.budgetsPersonnel', icon: Landmark, permission: 'finance.budget' },
      { to: '/rapports-financiers', label: 'nav.rapportsFinanciers', icon: BarChart3, permission: 'finance.rapports' },
      { to: '/etat-synthese', label: 'nav.etatSynthese', icon: Calculator, permission: 'finance.rapports' },
      { to: '/rentree-scolaire', label: 'nav.rentreeScolaire', icon: ClipboardCheck, permission: 'finance.rapports' },
    ],
  },
  {
    label: 'nav.group.admin',
    items: [
      { to: '/niveaux-globaux', label: 'nav.niveauxGlobaux', icon: Layers, permission: 'niveaux.view' },
      { to: '/permissions', label: 'nav.permissions', icon: ShieldCheck, permission: 'personnel.manage', superAdminOnly: true },
      { to: '/comptes', label: 'nav.comptesUtilisateurs', icon: UserCog, superAdminOnly: true },
      { to: '/session', label: 'nav.session', icon: CalendarRange, permission: 'ecoles.manage' },
      { to: '/parametres', label: 'nav.parametres', icon: Settings, permission: 'ecoles.manage' },
      { to: '/rapport-rentree', label: 'nav.rapportRentree', icon: ClipboardList, permission: 'rapport_rentree.view' },
      { to: '/rapport-trimestre', label: 'nav.rapportTrimestre', icon: ClipboardList, permission: 'rapport_trimestre.view' },
    ],
  },
] as const

function initials(name?: string) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase()
}

function normaliserRecherche(texte: string): string {
  return texte
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .toLowerCase()
}

export function AppLayout() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const { user, can, aAttribution, clearSession, activeSchool } = useAuthStore()
  const { locale, setLocale, sidebarOpen, toggleSidebar } = useUiStore()
  const [menuOuvert, setMenuOuvert] = useState(false)
  const [groupeTopbarOuvert, setGroupeTopbarOuvert] = useState<string | null>(null)
  const [rechercheMenu, setRechercheMenu] = useState('')
  const [groupesOuverts, setGroupesOuverts] = useState<Record<string, boolean>>(() =>
    Object.fromEntries(navGroups.map((group) => [group.label, true])),
  )
  const typeEcole = activeSchool()?.type
  const logoEcole = activeSchool()?.logo_url ?? null

  // Le tiroir se referme à chaque navigation : sur mobile il recouvre la page,
  // le laisser ouvert masquerait l'écran qu'on vient d'ouvrir.
  useEffect(() => setMenuOuvert(false), [location.pathname])

  useEffect(() => setGroupeTopbarOuvert(null), [location.pathname])

  useEffect(() => {
    const groupeActif = navGroups.find((group) =>
      group.items.some((item) => (item.to === '/' ? location.pathname === '/' : location.pathname.startsWith(item.to))),
    )

    if (groupeActif) {
      setGroupesOuverts((actuel) => ({ ...actuel, [groupeActif.label]: true }))
    }
  }, [location.pathname])

  const handleLogout = async () => {
    try {
      await logout()
    } finally {
      clearSession()
      navigate('/connexion', { replace: true })
    }
  }

  const requeteMenu = normaliserRecherche(rechercheMenu.trim())

  // Titulaire d'une classe unique de primaire/maternelle : sa sidebar se
  // limite à « Ma classe » et aux écrans qui la concernent directement.
  const estTitulaireDeClasse = Boolean(user?.est_enseignant) && (typeEcole === 'primaire' || typeEcole === 'maternelle')

  // Le vendeur garde `eleves.view` pour peupler le sélecteur d'élève au
  // comptoir (vente à crédit), mais les écrans élèves eux-mêmes (fiche,
  // liste, identification…) restent hors de son périmètre — cf.
  // masquerPourVendeur, pendant de masquerPourTitulaire ci-dessus.
  const estVendeur = Boolean(user?.est_vendeur)

  // Au moins une responsabilité nominative confiée : c'est ce qui ouvre
  // « Mes attributions », et non un privilège — un enseignant et un censeur
  // partagent `classes.view` sans porter les mêmes responsabilités.
  const aUneAttribution = (user?.attributions ?? []).length > 0

  // Le compte représente un agent : c'est ce qui ouvre « Mes avances », et non
  // un privilège — l'écran ne parle que du salaire de son titulaire.
  const estPersonnel = Boolean(user?.est_personnel)

  // Dirige au moins un département : ouvre « Mon département », sur son seul
  // département (cf. EnseignantController::monDepartement()).
  const estChefDepartement = aAttribution('chef_departement')

  // Professeur principal d'au moins une classe : ouvre « Ma classe », sur sa
  // seule classe (cf. EnseignantController::maClasseProfPrincipal()).
  const estProfesseurPrincipal = aAttribution('professeur_principal')

  // Anime au moins un niveau scolaire (primaire/maternelle) : ouvre « Mon
  // niveau », le pendant de « Mon département » pour ces cycles.
  const estAnimateurNiveau = aAttribution('animateur_niveau')

  const groupesVisibles = useMemo(
    () =>
      navGroups
        .map((group) => {
          const libelleGroupe = t(group.label)
          const groupeCorrespond = requeteMenu !== '' && normaliserRecherche(libelleGroupe).includes(requeteMenu)
          const itemsAutorises = group.items.filter(
            (item) =>
              (!('permission' in item) || can(item.permission)) &&
              (!('avecAttribution' in item) || !item.avecAttribution || aUneAttribution) &&
              (!('superAdminOnly' in item) || !item.superAdminOnly || user?.is_super_admin) &&
              (!('types' in item) || !typeEcole || (item.types as TypeEcole[]).includes(typeEcole)) &&
              (!('estEnseignant' in item) || !item.estEnseignant || user?.est_enseignant) &&
              (!('estPersonnel' in item) || !item.estPersonnel || estPersonnel) &&
              (!('chefDepartement' in item) || !item.chefDepartement || estChefDepartement) &&
              (!('professeurPrincipal' in item) || !item.professeurPrincipal || estProfesseurPrincipal) &&
              (!('animateurNiveau' in item) || !item.animateurNiveau || estAnimateurNiveau) &&
              (!('masquerPourTitulaire' in item) || !item.masquerPourTitulaire || !estTitulaireDeClasse) &&
              (!('masquerPourVendeur' in item) || !item.masquerPourVendeur || !estVendeur),
          )
          const items = requeteMenu
            ? itemsAutorises.filter((item) => groupeCorrespond || normaliserRecherche(t(item.label)).includes(requeteMenu))
            : itemsAutorises

          // En mode agrégé (super admin, pas de `typeEcole`), le filtre `types`
          // ci-dessus s'efface et deux entrées visant des types différents
          // (ex. Photos DECC secondaire/primaire) peuvent pointer vers la même
          // route : n'en garder qu'une pour éviter les clés React dupliquées.
          const itemsUniques = items.filter((item, index) => items.findIndex((i) => i.to === item.to) === index)

          return { ...group, items: itemsUniques }
        })
        .filter((group) => group.items.length > 0),
    [can, requeteMenu, t, typeEcole, user?.is_super_admin, user?.est_enseignant, estTitulaireDeClasse, estVendeur, aUneAttribution, estPersonnel, estChefDepartement, estProfesseurPrincipal, estAnimateurNiveau],
  )

  /**
   * `/eleves` est un préfixe de `/eleves/transferts` : sans ceci, NavLink (en
   * mode préfixe, cf. `end`) activerait les deux à la fois. On ne retient que
   * l'entrée du menu dont le chemin correspond le plus précisément à l'URL.
   */
  const cheminActif = useMemo(() => {
    let meilleur: string | null = null
    for (const group of groupesVisibles) {
      for (const item of group.items) {
        const correspond = location.pathname === item.to || location.pathname.startsWith(`${item.to}/`)
        if (correspond && (!meilleur || item.to.length > meilleur.length)) meilleur = item.to
      }
    }
    return meilleur
  }, [groupesVisibles, location.pathname])

  const groupesTopbar = useMemo(
    () => {
      // Pour les enseignants, pas de topbar : tout dans la sidebar
      if (user?.est_enseignant) return []
      return groupesVisibles.filter((group) => groupesTopbarDesktop.has(group.label))
    },
    [groupesVisibles, user?.est_enseignant],
  )

  const groupesSidebar = groupesVisibles

  return (
    <div className="flex h-svh overflow-hidden bg-cream-50">
      {menuOuvert && (
        <div
          className="animate-fade-in fixed inset-0 z-40 bg-navy-900/60 backdrop-blur-sm lg:hidden"
          onClick={() => setMenuOuvert(false)}
          aria-hidden
        />
      )}

      <aside
        className={clsx(
          // `fixed` seul : `relative` en plus faisait gagner `position:
          // relative` selon l'ordre de génération des classes Tailwind — la
          // sidebar restait alors dans le flux flex même repoussée hors
          // champ par `-translate-x-full`, écrasant <main> sur mobile.
          'fixed inset-y-0 left-0 z-50 flex w-[17rem] min-w-0 flex-none flex-col overflow-hidden bg-[linear-gradient(145deg,#391651_0%,#481e67_48%,#230c32_100%)] text-cream-50 shadow-lifted transition-transform duration-200 ease-out',
          'lg:static lg:z-auto lg:shadow-none lg:transition-[width] lg:duration-200',
          menuOuvert ? 'translate-x-0' : '-translate-x-full',
          sidebarOpen ? 'lg:w-64 lg:translate-x-0' : 'lg:w-20 lg:translate-x-0',
        )}
      >
        <div
          className="pointer-events-none absolute inset-0 opacity-[0.08]"
          style={{ backgroundImage: 'radial-gradient(circle at 1px 1px, white 1.5px, transparent 0)', backgroundSize: '28px 28px' }}
          aria-hidden
        />
        <div className="pointer-events-none absolute -right-28 -top-24 h-96 w-96 rounded-full bg-gold-500/15 blur-3xl" aria-hidden />
        <div className="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-green-500/20 blur-3xl" aria-hidden />

        <div className={clsx('relative z-10 flex items-center gap-2.5 px-5 py-5', !sidebarOpen && 'lg:justify-center lg:px-0')}>
          <span className={clsx('flex h-14 flex-none items-center justify-center rounded-xl bg-white px-2.5 py-1.5 shadow-soft', !sidebarOpen && 'lg:hidden')}>
            <img src={logoWordmark} alt={t('app.name')} className="h-11 w-auto object-contain" />
          </span>
          <span className={clsx('hidden h-14 w-14 flex-none items-center justify-center rounded-xl bg-white p-1.5 shadow-soft', !sidebarOpen && 'lg:flex')}>
            <img src={logoMark} alt={t('app.name')} className="h-full w-full object-contain" />
          </span>
          <button
            onClick={() => setMenuOuvert(false)}
            className="ml-auto rounded-lg p-1.5 text-navy-300 transition-colors hover:bg-white/10 hover:text-white lg:hidden"
            aria-label={t('common.close')}
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className={clsx('relative z-10 px-3 pb-3', !sidebarOpen && 'lg:hidden')}>
          <div className="relative">
            <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400" />
            <input
              value={rechercheMenu}
              onChange={(e) => setRechercheMenu(e.target.value)}
              placeholder={t('nav.searchMenu')}
              className="h-10 w-full rounded-xl border border-white/10 bg-white/8 py-2 pl-9 pr-3 text-sm text-white outline-none transition-colors placeholder:text-navy-400 focus:border-gold-400/50 focus:bg-white/12 focus:ring-4 focus:ring-gold-400/10"
            />
          </div>
        </div>

        <nav className="relative z-10 flex min-h-0 min-w-0 flex-1 flex-col gap-1.5 overflow-x-hidden overflow-y-auto px-3 pt-1 pb-4">
          {groupesSidebar.map((group) => {
            // Pour les enseignants, tout est visible en sidebar puisqu'il n'y a pas de topbar
            const groupeEnTopbar = !user?.est_enseignant && groupesTopbarDesktop.has(group.label)

            return (
              <div key={group.label} className={clsx('flex flex-col gap-1', groupeEnTopbar && !requeteMenu && 'lg:hidden')}>
                <button
                  type="button"
                  aria-expanded={Boolean(requeteMenu) || groupesOuverts[group.label]}
                  onClick={() =>
                    setGroupesOuverts((actuel) => ({
                      ...actuel,
                      [group.label]: !actuel[group.label],
                    }))
                  }
                  className={clsx(
                    'flex h-7 items-center justify-between gap-2 rounded-lg px-3 text-left text-[9px] font-bold uppercase tracking-wider text-navy-400 transition-colors hover:bg-white/5 hover:text-navy-200',
                    !sidebarOpen && 'lg:hidden',
                  )}
                >
                  <span className="truncate">{t(group.label)}</span>
                  <ChevronDown
                    className={clsx(
                      'h-3.5 w-3.5 flex-none transition-transform',
                      (requeteMenu || groupesOuverts[group.label]) && 'rotate-180',
                    )}
                  />
                </button>
                {/* Réduite, la sidebar n'a plus d'accordéon à replier : chaque
                    groupe reste visible pour garder ses icônes accessibles. */}
                {(requeteMenu || groupesOuverts[group.label] || !sidebarOpen) && (
                  <div className="flex flex-col gap-1">
                    {group.items.map((item) => {
                      const estActif = item.to === cheminActif
                      return (
                        <NavLink
                          key={item.to}
                          to={item.to}
                          end
                          aria-current={estActif ? 'page' : undefined}
                          title={!sidebarOpen ? t(item.label) : undefined}
                          className={clsx(
                            'group relative flex items-center gap-3 rounded-xl px-3 py-2 text-[13px] font-medium transition-colors',
                            !sidebarOpen && 'lg:justify-center lg:px-2',
                            estActif
                              ? 'bg-white/10 text-white shadow-[inset_0_1px_0_0_rgb(255_255_255/0.06)]'
                              : 'text-navy-200 hover:bg-white/5 hover:text-white',
                          )}
                        >
                          {estActif && (
                            <span className="absolute left-0 top-1/2 h-5 w-1 -translate-y-1/2 rounded-r-full bg-gold-400" />
                          )}
                          <item.icon
                            className={clsx(
                              'h-[18px] w-[18px] flex-none',
                              estActif ? 'text-gold-300' : 'text-navy-300 group-hover:text-gold-200',
                            )}
                          />
                          <span className={clsx('truncate', !sidebarOpen && 'lg:hidden')}>{t(item.label)}</span>
                        </NavLink>
                      )
                    })}
                  </div>
                )}
              </div>
            )
          })}
          {groupesSidebar.length === 0 && (
            <div className="rounded-xl border border-white/10 px-3.5 py-4 text-sm text-navy-300">
              {t('nav.noMenuFound')}
            </div>
          )}
        </nav>

        <div
          className={clsx(
            'relative z-10 sticky bottom-0 flex items-center gap-3 border-t border-white/10 bg-navy-800/70 px-4 py-4 backdrop-blur-sm',
            !sidebarOpen && 'lg:flex-col lg:gap-2 lg:px-2',
          )}
        >
          <button
            type="button"
            onClick={() => navigate('/profil')}
            title={t('nav.profile')}
            className={clsx(
              'flex min-w-0 flex-1 items-center gap-3 rounded-xl text-left transition-colors hover:bg-white/5',
              sidebarOpen ? '-ml-2 px-2 py-1.5' : 'lg:justify-center lg:px-0',
            )}
          >
            <span className="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gold-500/20 text-xs font-bold text-gold-200 ring-1 ring-gold-400/30">
              {initials(user?.name)}
            </span>
            <span className={clsx('min-w-0 flex-1', !sidebarOpen && 'lg:hidden')}>
              <span className="block truncate text-sm font-semibold text-white">{user?.name}</span>
              <span className="block truncate text-xs text-navy-300">{user?.fonction || user?.roles.join(', ')}</span>
            </span>
          </button>
          <button
            onClick={handleLogout}
            title={t('nav.logout')}
            className="flex h-8 w-8 flex-none items-center justify-center rounded-lg text-navy-300 transition-colors hover:bg-white/10 hover:text-white"
          >
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </aside>

      <div className="flex min-h-0 min-w-0 flex-1 flex-col">
        <header className="relative z-30 flex flex-none items-center gap-3 border-b border-navy-100 bg-white/85 px-4 py-3 backdrop-blur-sm sm:px-6">
          <button
            onClick={() => setMenuOuvert(true)}
            className="-ml-1 flex h-9 w-9 flex-none items-center justify-center rounded-xl text-navy-500 transition-colors hover:bg-cream-100 hover:text-navy-800 lg:hidden"
            aria-label={t('nav.openMenu')}
          >
            <Menu className="h-5 w-5" />
          </button>

          <button
            onClick={toggleSidebar}
            className="-ml-1 hidden h-9 w-9 flex-none items-center justify-center rounded-xl text-navy-500 transition-colors hover:bg-cream-100 hover:text-navy-800 lg:flex"
            aria-label={sidebarOpen ? t('nav.hideMenu') : t('nav.openMenu')}
            title={sidebarOpen ? t('nav.hideMenu') : t('nav.openMenu')}
          >
            {sidebarOpen ? <PanelLeftClose className="h-5 w-5" /> : <PanelLeftOpen className="h-5 w-5" />}
          </button>

          {groupesTopbar.length > 0 && (
            <nav className="hidden min-w-0 flex-1 items-center gap-0.5 lg:flex" aria-label={t('nav.topbarMenu')}>
              {groupesTopbar.map((group) => {
                const groupeActif = group.items.some((item) => item.to === cheminActif)
                const ouvert = groupeTopbarOuvert === group.label

                return (
                  <div key={group.label} className="relative">
                    <button
                      type="button"
                      aria-expanded={ouvert}
                      onClick={() => setGroupeTopbarOuvert((actuel) => (actuel === group.label ? null : group.label))}
                      className={clsx(
                        'flex h-8 items-center gap-1 rounded-lg px-2 text-xs font-semibold transition-colors',
                        groupeActif
                          ? 'bg-navy-50 text-navy-900'
                          : 'text-navy-500 hover:bg-cream-100 hover:text-navy-800',
                      )}
                    >
                      {t(group.label)}
                      <ChevronDown className={clsx('h-3 w-3 transition-transform', ouvert && 'rotate-180')} />
                    </button>

                    {ouvert && (
                      <>
                        <div className="fixed inset-0 z-30" onClick={() => setGroupeTopbarOuvert(null)} />
                        <div className="absolute left-0 top-full z-40 mt-2 min-w-52 overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lifted">
                          {group.items.map((item) => {
                            const estActif = item.to === cheminActif

                            return (
                              <NavLink
                                key={item.to}
                                to={item.to}
                                end
                                className={clsx(
                                  'flex items-center gap-2 px-2.5 py-1.5 text-xs transition-colors',
                                  estActif
                                    ? 'bg-gold-50 font-semibold text-navy-900'
                                    : 'text-navy-600 hover:bg-cream-50 hover:text-navy-900',
                                )}
                              >
                                <item.icon className={clsx('h-3.5 w-3.5 flex-none', estActif ? 'text-gold-600' : 'text-navy-400')} />
                                <span className="truncate">{t(item.label)}</span>
                              </NavLink>
                            )
                          })}
                        </div>
                      </>
                    )}
                  </div>
                )
              })}
            </nav>
          )}

          {groupesTopbar.length === 0 && <div className="min-w-0 flex-1" />}

          <NotificationBell />

          <div className="flex flex-none gap-1 rounded-full bg-cream-100 p-1 text-xs font-bold">
            {(['fr', 'en'] as const).map((l) => (
              <button
                key={l}
                onClick={() => setLocale(l)}
                className={clsx(
                  'rounded-full px-2.5 py-1 transition-colors',
                  locale === l ? 'bg-gold-500 text-navy-900 shadow-soft' : 'text-navy-400 hover:text-navy-600',
                )}
              >
                {l.toUpperCase()}
              </button>
            ))}
          </div>

          <button
            type="button"
            onClick={() => navigate('/profil')}
            className="hidden items-center gap-3 rounded-xl px-2 py-1.5 text-left transition-colors hover:bg-cream-100 xl:flex"
          >
            <div className="max-w-[160px] text-right text-sm leading-tight">
              <p className="truncate font-semibold text-navy-800" title={user?.name}>{user?.name}</p>
              <p className="truncate text-xs text-navy-400">{user?.fonction || user?.roles.join(', ')}</p>
            </div>
            <span className="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-navy-700 text-xs font-bold text-cream-50">
              {initials(user?.name)}
            </span>
          </button>
        </header>

        <main className="relative min-h-0 flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
          {logoEcole && (
            /*
             * Filigrane : logo de l'établissement actif, fixe au centre de la
             * zone de travail. `fixed` plutôt qu'`absolute` pour qu'il reste
             * en place au défilement, et `pointer-events-none` pour qu'il ne
             * capte jamais un clic destiné au contenu par-dessus.
             *
             * Beaucoup d'écrans (barres de recherche, cartes de statistiques,
             * bandeaux de sélection…) posent leur contenu à même le fond de
             * page, sans panneau opaque par-dessus : le filigrane doit donc
             * rester lisible-mais-discret tout seul, sans compter sur un
             * habillage supplémentaire pour l'atténuer. `grayscale` désature
             * le logo pour qu'il se fonde dans le fond crème plutôt que de
             * ressortir comme une image en couleur.
             */
            <div
              aria-hidden
              className="pointer-events-none fixed inset-0 z-0 flex items-center justify-center overflow-hidden"
            >
              <img
                src={logoEcole}
                alt=""
                className="w-[min(36vw,20rem)] max-w-none grayscale opacity-[0.05] select-none"
              />
            </div>
          )}

          <div className="relative z-10 mx-auto max-w-7xl">
            <Outlet />
          </div>
        </main>
      </div>
    </div>
  )
}
