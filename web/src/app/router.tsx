import { createBrowserRouter } from 'react-router-dom'
import { AppLayout } from '@/app/AppLayout'
import { ProtectedRoute } from '@/app/ProtectedRoute'
import { LoginPage } from '@/features/auth/pages/LoginPage'
import { DashboardPage } from '@/features/dashboard/pages/DashboardPage'
import { PersonnelListPage } from '@/features/personnel/pages/PersonnelListPage'
import { DepartementsPage } from '@/features/personnel/pages/DepartementsPage'
import { FonctionsReferentielPage } from '@/features/personnel/pages/FonctionsReferentielPage'
import { FonctionReferentielDetailPage } from '@/features/personnel/pages/FonctionReferentielDetailPage'
import { ClassesListPage } from '@/features/classes/pages/ClassesListPage'
import { ClasseDetailPage } from '@/features/classes/pages/ClasseDetailPage'
import { SousSystemesListPage } from '@/features/classes/sous-systemes/SousSystemesListPage'
import { ElevesListPage } from '@/features/eleves/pages/ElevesListPage'
import { EleveDetailPage } from '@/features/eleves/pages/EleveDetailPage'
import { EleveInscriptionPage } from '@/features/eleves/pages/EleveInscriptionPage'
import { EleveTransfertsPage } from '@/features/eleves/pages/EleveTransfertsPage'
import { MatieresPage } from '@/features/pedagogie/pages/MatieresPage'
import { MatiereFormPage } from '@/features/pedagogie/pages/MatiereFormPage'
import { SanctionsPage } from '@/features/discipline/pages/SanctionsPage'
import { PhotosExamenPage } from '@/features/identification/pages/PhotosExamenPage'
import { StatsPedagogiquesPage } from '@/features/statistiques/pages/StatsPedagogiquesPage'
import { StatsDisciplinairesPage } from '@/features/statistiques/pages/StatsDisciplinairesPage'
import { PalmaresPage } from '@/features/resultats/pages/PalmaresPage'
import { BulletinsPage } from '@/features/resultats/pages/BulletinsPage'
import { RemplissagePage } from '@/features/resultats/pages/RemplissagePage'
import { EmploiDuTempsPage } from '@/features/emploiDuTemps/pages/EmploiDuTempsPage'
import { SeancesPage } from '@/features/emploiDuTemps/pages/SeancesPage'
import { IdentificationPage } from '@/features/identification/pages/IdentificationPage'
import { NiveauxScolairesPage } from '@/features/primaire/pages/NiveauxScolairesPage'
import { NiveauxListPage } from '@/features/niveaux/pages/NiveauxListPage'
import { ProgressionPage } from '@/features/progression/pages/ProgressionPage'
import { MaJourneePage } from '@/features/progression/pages/MaJourneePage'
import { SettingsPage } from '@/features/settings/pages/SettingsPage'
import { PermissionsPage } from '@/features/permissions/pages/PermissionsPage'
import { SessionPage } from '@/features/session/pages/SessionPage'

export const router = createBrowserRouter([
  { path: '/connexion', element: <LoginPage /> },
  {
    path: '/',
    element: (
      <ProtectedRoute>
        <AppLayout />
      </ProtectedRoute>
    ),
    children: [
      { index: true, element: <ProtectedRoute permission="dashboard.view"><DashboardPage /></ProtectedRoute> },
      { path: 'personnel', element: <ProtectedRoute permission="personnel.view"><PersonnelListPage /></ProtectedRoute> },
      { path: 'fonctions-referentiel', element: <ProtectedRoute superAdminOnly><FonctionsReferentielPage /></ProtectedRoute> },
      { path: 'fonctions-referentiel/:id', element: <ProtectedRoute superAdminOnly><FonctionReferentielDetailPage /></ProtectedRoute> },
      { path: 'departements', element: <ProtectedRoute permission="personnel.view"><DepartementsPage /></ProtectedRoute> },
      { path: 'niveaux', element: <ProtectedRoute permission="pedagogie.view"><NiveauxScolairesPage /></ProtectedRoute> },
      { path: 'niveaux-globaux', element: <ProtectedRoute permission="niveaux.view"><NiveauxListPage /></ProtectedRoute> },
      { path: 'classes', element: <ProtectedRoute permission="classes.view"><ClassesListPage /></ProtectedRoute> },
      { path: 'classes/:id', element: <ProtectedRoute permission="classes.view"><ClasseDetailPage /></ProtectedRoute> },
      { path: 'sous-systemes', element: <ProtectedRoute permission="classes.manage"><SousSystemesListPage /></ProtectedRoute> },
      { path: 'eleves', element: <ProtectedRoute permission="eleves.view"><ElevesListPage /></ProtectedRoute> },
      { path: 'eleves/:id', element: <ProtectedRoute permission="eleves.view"><EleveDetailPage /></ProtectedRoute> },
      { path: 'eleves/nouveau', element: <ProtectedRoute permission="eleves.manage"><EleveInscriptionPage /></ProtectedRoute> },
      { path: 'eleves/transferts', element: <ProtectedRoute permission="eleves.manage"><EleveTransfertsPage /></ProtectedRoute> },
      { path: 'eleves/:id/edit', element: <ProtectedRoute permission="eleves.manage"><EleveInscriptionPage /></ProtectedRoute> },
      { path: 'matieres', element: <ProtectedRoute permission="pedagogie.view"><MatieresPage /></ProtectedRoute> },
      { path: 'matieres/nouvelle', element: <ProtectedRoute permission="pedagogie.manage"><MatiereFormPage /></ProtectedRoute> },
      { path: 'matieres/:id/edit', element: <ProtectedRoute permission="pedagogie.manage"><MatiereFormPage /></ProtectedRoute> },
      { path: 'sanctions', element: <ProtectedRoute permission="discipline.view"><SanctionsPage /></ProtectedRoute> },
      { path: 'palmares', element: <ProtectedRoute permission="bulletins.view"><PalmaresPage /></ProtectedRoute> },
      { path: 'bulletins', element: <ProtectedRoute permission="bulletins.view"><BulletinsPage /></ProtectedRoute> },
      { path: 'remplissage', element: <ProtectedRoute permission="notes.view"><RemplissagePage /></ProtectedRoute> },
      { path: 'stats-pedagogiques', element: <ProtectedRoute permission="bulletins.view"><StatsPedagogiquesPage /></ProtectedRoute> },
      { path: 'stats-disciplinaires', element: <ProtectedRoute permission="discipline.view"><StatsDisciplinairesPage /></ProtectedRoute> },
      { path: 'emploi-du-temps', element: <ProtectedRoute permission="emploi_du_temps.view"><EmploiDuTempsPage /></ProtectedRoute> },
      { path: 'seances', element: <ProtectedRoute permission="emploi_du_temps.view"><SeancesPage /></ProtectedRoute> },
      { path: 'progression', element: <ProtectedRoute permission="pedagogie.view"><ProgressionPage /></ProtectedRoute> },
      { path: 'ma-journee', element: <ProtectedRoute permission="appel.manage" roles={['enseignant']}><MaJourneePage /></ProtectedRoute> },
      { path: 'identification', element: <ProtectedRoute permission="eleves.view"><IdentificationPage /></ProtectedRoute> },
      { path: 'photos-examen', element: <ProtectedRoute permission="eleves.view"><PhotosExamenPage /></ProtectedRoute> },
      { path: 'session', element: <ProtectedRoute permission="ecoles.manage"><SessionPage /></ProtectedRoute> },
      { path: 'permissions', element: <ProtectedRoute superAdminOnly><PermissionsPage /></ProtectedRoute> },
      { path: 'parametres', element: <ProtectedRoute permission="ecoles.manage"><SettingsPage /></ProtectedRoute> },
    ],
  },
])
