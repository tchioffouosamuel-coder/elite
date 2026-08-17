import { createBrowserRouter } from 'react-router-dom'
import { AppLayout } from '@/app/AppLayout'
import { ProtectedRoute } from '@/app/ProtectedRoute'
import { LoginPage } from '@/features/auth/pages/LoginPage'
import { ChangerMotDePassePage } from '@/features/auth/pages/ChangerMotDePassePage'
import { UserProfilePage } from '@/features/auth/pages/UserProfilePage'
import { DashboardPage } from '@/features/dashboard/pages/DashboardPage'
import { PersonnelListPage } from '@/features/personnel/pages/PersonnelListPage'
import { PersonnelFormPage } from '@/features/personnel/pages/PersonnelFormPage'
import { DepartementsPage } from '@/features/personnel/pages/DepartementsPage'
import { FonctionsReferentielPage } from '@/features/personnel/pages/FonctionsReferentielPage'
import { FonctionReferentielDetailPage } from '@/features/personnel/pages/FonctionReferentielDetailPage'
import { ClassesListPage } from '@/features/classes/pages/ClassesListPage'
import { ClasseDetailPage } from '@/features/classes/pages/ClasseDetailPage'
import { QrCodesPage } from '@/features/classes/pages/QrCodesPage'
import { MaClassePage } from '@/features/classes/pages/MaClassePage'
import { SousSystemesListPage } from '@/features/classes/sous-systemes/SousSystemesListPage'
import { ElevesListPage } from '@/features/eleves/pages/ElevesListPage'
import { EleveDetailPage } from '@/features/eleves/pages/EleveDetailPage'
import { EleveInscriptionPage } from '@/features/eleves/pages/EleveInscriptionPage'
import { EleveTransfertsPage } from '@/features/eleves/pages/EleveTransfertsPage'
import { MatieresPage } from '@/features/pedagogie/pages/MatieresPage'
import { MatiereFormPage } from '@/features/pedagogie/pages/MatiereFormPage'
import { SanctionsPage } from '@/features/discipline/pages/SanctionsPage'
import { InfirmeriePage } from '@/features/infirmerie/pages/InfirmeriePage'
import { VisiteInfirmerieFormPage } from '@/features/infirmerie/pages/VisiteInfirmerieFormPage'
import { BusVehiculesPage } from '@/features/bus/pages/BusVehiculesPage'
import { BusTrajetsPage } from '@/features/bus/pages/BusTrajetsPage'
import { BusTrajetDetailPage } from '@/features/bus/pages/BusTrajetDetailPage'
import { BusArretsPage } from '@/features/bus/pages/BusArretsPage'
import { BusAffectationsPage } from '@/features/bus/pages/BusAffectationsPage'
import { BusSouscriptionPage } from '@/features/bus/pages/BusSouscriptionPage'
import { PhotosExamenPage } from '@/features/identification/pages/PhotosExamenPage'
import { StatsPedagogiquesPage } from '@/features/statistiques/pages/StatsPedagogiquesPage'
import { StatsDisciplinairesPage } from '@/features/statistiques/pages/StatsDisciplinairesPage'
import { PalmaresPage } from '@/features/resultats/pages/PalmaresPage'
import { BulletinsPage } from '@/features/resultats/pages/BulletinsPage'
import { RemplissagePage } from '@/features/resultats/pages/RemplissagePage'
import { EmploiDuTempsPage } from '@/features/emploiDuTemps/pages/EmploiDuTempsPage'
import { SeancesPage } from '@/features/emploiDuTemps/pages/SeancesPage'
import { AppelPage } from '@/features/emploiDuTemps/pages/AppelPage'
import { IdentificationPage } from '@/features/identification/pages/IdentificationPage'
import { NiveauxScolairesPage } from '@/features/primaire/pages/NiveauxScolairesPage'
import { NiveauxListPage } from '@/features/niveaux/pages/NiveauxListPage'
import { ProgressionPage } from '@/features/progression/pages/ProgressionPage'
import { MaJourneePage } from '@/features/progression/pages/MaJourneePage'
import { QrScanPage } from '@/features/progression/pages/QrScanPage'
import { QrScannerPage } from '@/features/progression/pages/QrScannerPage'
import { SettingsPage } from '@/features/settings/pages/SettingsPage'
import { PermissionsPage } from '@/features/permissions/pages/PermissionsPage'
import { CaissePage } from '@/features/finance/pages/CaissePage'
import { EncaissementPage } from '@/features/finance/pages/EncaissementPage'
import { DepensesPage } from '@/features/finance/pages/DepensesPage'
import { PaiePage } from '@/features/finance/pages/PaiePage'
import { RapportsFinanciersPage } from '@/features/finance/pages/RapportsFinanciersPage'
import { TarifsPage } from '@/features/finance/pages/TarifsPage'
import { RemunerationsPage } from '@/features/finance/pages/RemunerationsPage'
import { SessionPage } from '@/features/session/pages/SessionPage'

export const router = createBrowserRouter([
  { path: '/connexion', element: <LoginPage /> },
  // Hors du gabarit applicatif : tant que le mot de passe est provisoire, il
  // n'y a ni menu ni tableau de bord à afficher autour.
  { path: '/mot-de-passe', element: <ChangerMotDePassePage /> },
  {
    path: '/',
    element: (
      <ProtectedRoute>
        <AppLayout />
      </ProtectedRoute>
    ),
    children: [
      { index: true, element: <ProtectedRoute permission="dashboard.view"><DashboardPage /></ProtectedRoute> },
      { path: 'profil', element: <ProtectedRoute><UserProfilePage /></ProtectedRoute> },
      { path: 'personnel', element: <ProtectedRoute permission="personnel.view"><PersonnelListPage /></ProtectedRoute> },
      { path: 'personnel/nouveau', element: <ProtectedRoute permission="personnel.manage"><PersonnelFormPage /></ProtectedRoute> },
      { path: 'personnel/:id/edit', element: <ProtectedRoute permission="personnel.manage"><PersonnelFormPage /></ProtectedRoute> },
      { path: 'fonctions-referentiel', element: <ProtectedRoute superAdminOnly><FonctionsReferentielPage /></ProtectedRoute> },
      { path: 'fonctions-referentiel/:id', element: <ProtectedRoute superAdminOnly><FonctionReferentielDetailPage /></ProtectedRoute> },
      { path: 'departements', element: <ProtectedRoute permission="personnel.view"><DepartementsPage /></ProtectedRoute> },
      { path: 'niveaux', element: <ProtectedRoute permission="pedagogie.view"><NiveauxScolairesPage /></ProtectedRoute> },
      { path: 'niveaux-globaux', element: <ProtectedRoute permission="niveaux.view"><NiveauxListPage /></ProtectedRoute> },
      { path: 'classes', element: <ProtectedRoute permission="classes.view" masquerPourTitulaire><ClassesListPage /></ProtectedRoute> },
      { path: 'classes/:id', element: <ProtectedRoute permission="classes.view" masquerPourTitulaire><ClasseDetailPage /></ProtectedRoute> },
      { path: 'ma-classe', element: <ProtectedRoute permission="classes.view" enseignantOnly><MaClassePage /></ProtectedRoute> },
      { path: 'sous-systemes', element: <ProtectedRoute permission="classes.manage"><SousSystemesListPage /></ProtectedRoute> },
      { path: 'eleves', element: <ProtectedRoute permission="eleves.view" masquerPourTitulaire><ElevesListPage /></ProtectedRoute> },
      { path: 'eleves/:id', element: <ProtectedRoute permission="eleves.view" masquerPourTitulaire><EleveDetailPage /></ProtectedRoute> },
      { path: 'eleves/nouveau', element: <ProtectedRoute permission="eleves.manage" masquerPourTitulaire><EleveInscriptionPage /></ProtectedRoute> },
      { path: 'eleves/transferts', element: <ProtectedRoute permission="eleves.manage" masquerPourTitulaire><EleveTransfertsPage /></ProtectedRoute> },
      { path: 'eleves/:id/edit', element: <ProtectedRoute permission="eleves.manage" masquerPourTitulaire><EleveInscriptionPage /></ProtectedRoute> },
      { path: 'matieres', element: <ProtectedRoute permission="pedagogie.view" masquerPourTitulaire><MatieresPage /></ProtectedRoute> },
      { path: 'matieres/nouvelle', element: <ProtectedRoute permission="pedagogie.manage" masquerPourTitulaire><MatiereFormPage /></ProtectedRoute> },
      { path: 'matieres/:id/edit', element: <ProtectedRoute permission="pedagogie.manage" masquerPourTitulaire><MatiereFormPage /></ProtectedRoute> },
      { path: 'sanctions', element: <ProtectedRoute permission="discipline.view"><SanctionsPage /></ProtectedRoute> },
      { path: 'infirmerie', element: <ProtectedRoute permission="infirmerie.view"><InfirmeriePage /></ProtectedRoute> },
      { path: 'infirmerie/nouvelle', element: <ProtectedRoute permission="infirmerie.manage"><VisiteInfirmerieFormPage /></ProtectedRoute> },
      { path: 'infirmerie/:id/edit', element: <ProtectedRoute permission="infirmerie.manage"><VisiteInfirmerieFormPage /></ProtectedRoute> },
      { path: 'bus/vehicules', element: <ProtectedRoute permission="bus.view"><BusVehiculesPage /></ProtectedRoute> },
      { path: 'bus/trajets', element: <ProtectedRoute permission="bus.view"><BusTrajetsPage /></ProtectedRoute> },
      { path: 'bus/trajets/:id', element: <ProtectedRoute permission="bus.view"><BusTrajetDetailPage /></ProtectedRoute> },
      { path: 'bus/arrets', element: <ProtectedRoute permission="bus.view"><BusArretsPage /></ProtectedRoute> },
      { path: 'bus/eleves', element: <ProtectedRoute permission="bus.view"><BusAffectationsPage /></ProtectedRoute> },
      { path: 'bus/souscription', element: <ProtectedRoute permission="bus.manage"><BusSouscriptionPage /></ProtectedRoute> },
      { path: 'bus/souscription/:eleveId', element: <ProtectedRoute permission="bus.manage"><BusSouscriptionPage /></ProtectedRoute> },
      { path: 'palmares', element: <ProtectedRoute permission="bulletins.view"><PalmaresPage /></ProtectedRoute> },
      { path: 'bulletins', element: <ProtectedRoute permission="bulletins.view"><BulletinsPage /></ProtectedRoute> },
      { path: 'remplissage', element: <ProtectedRoute permission="notes.view"><RemplissagePage /></ProtectedRoute> },
      { path: 'stats-pedagogiques', element: <ProtectedRoute permission="bulletins.view"><StatsPedagogiquesPage /></ProtectedRoute> },
      { path: 'stats-disciplinaires', element: <ProtectedRoute permission="bulletins.view"><StatsDisciplinairesPage /></ProtectedRoute> },
      { path: 'emploi-du-temps', element: <ProtectedRoute permission="emploi_du_temps.view"><EmploiDuTempsPage /></ProtectedRoute> },
      { path: 'seances', element: <ProtectedRoute permission="emploi_du_temps.view"><SeancesPage /></ProtectedRoute> },
      { path: 'seances/:id/appel', element: <ProtectedRoute permission="appel.manage"><AppelPage /></ProtectedRoute> },
      { path: 'codes-qr', element: <ProtectedRoute permission="emploi_du_temps.manage"><QrCodesPage /></ProtectedRoute> },
      { path: 'progression', element: <ProtectedRoute permission="pedagogie.view" masquerPourTitulaire><ProgressionPage /></ProtectedRoute> },
      { path: 'ma-journee', element: <ProtectedRoute permission="appel.manage" enseignantOnly><MaJourneePage /></ProtectedRoute> },
      { path: 'scanner-qr', element: <ProtectedRoute permission="appel.manage"><QrScannerPage /></ProtectedRoute> },
      {
        path: 'qr/:token',
        // Un censeur ou un admin peut aussi scanner pour dépanner une classe —
        // le service `peutIntervenir()` les autorise déjà, la route ne doit
        // pas les bloquer avant même d'y arriver.
        element: <ProtectedRoute permission="appel.manage"><QrScanPage /></ProtectedRoute>,
      },
      { path: 'identification', element: <ProtectedRoute permission="eleves.view" masquerPourTitulaire><IdentificationPage /></ProtectedRoute> },
      { path: 'photos-examen', element: <ProtectedRoute permission="eleves.view" masquerPourTitulaire><PhotosExamenPage /></ProtectedRoute> },
      { path: 'session', element: <ProtectedRoute permission="ecoles.manage"><SessionPage /></ProtectedRoute> },
      { path: 'caisse', element: <ProtectedRoute permission="finance.view"><CaissePage /></ProtectedRoute> },
      { path: 'caisse/encaisser/:eleveId', element: <ProtectedRoute permission="finance.encaisser"><EncaissementPage /></ProtectedRoute> },
      { path: 'tarifs', element: <ProtectedRoute permission="finance.view"><TarifsPage /></ProtectedRoute> },
      { path: 'depenses', element: <ProtectedRoute permission="finance.view"><DepensesPage /></ProtectedRoute> },
      { path: 'salaires', element: <ProtectedRoute permission="finance.paie"><RemunerationsPage /></ProtectedRoute> },
      { path: 'paie', element: <ProtectedRoute permission="finance.paie"><PaiePage /></ProtectedRoute> },
      { path: 'rapports-financiers', element: <ProtectedRoute permission="finance.rapports"><RapportsFinanciersPage /></ProtectedRoute> },
      { path: 'permissions', element: <ProtectedRoute superAdminOnly><PermissionsPage /></ProtectedRoute> },
      { path: 'parametres', element: <ProtectedRoute permission="ecoles.manage"><SettingsPage /></ProtectedRoute> },
    ],
  },
])
