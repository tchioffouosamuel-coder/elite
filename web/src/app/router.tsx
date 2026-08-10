import { createBrowserRouter } from 'react-router-dom'
import { AppLayout } from '@/app/AppLayout'
import { ProtectedRoute } from '@/app/ProtectedRoute'
import { LoginPage } from '@/features/auth/pages/LoginPage'
import { DashboardPage } from '@/features/dashboard/pages/DashboardPage'
import { PersonnelListPage } from '@/features/personnel/pages/PersonnelListPage'
import { DepartementsPage } from '@/features/personnel/pages/DepartementsPage'
import { ClassesListPage } from '@/features/classes/pages/ClassesListPage'
import { ClasseDetailPage } from '@/features/classes/pages/ClasseDetailPage'
import { ElevesListPage } from '@/features/eleves/pages/ElevesListPage'
import { MatieresPage } from '@/features/pedagogie/pages/MatieresPage'
import { SanctionsPage } from '@/features/discipline/pages/SanctionsPage'
import { StatsPedagogiquesPage } from '@/features/statistiques/pages/StatsPedagogiquesPage'
import { StatsDisciplinairesPage } from '@/features/statistiques/pages/StatsDisciplinairesPage'
import { PalmaresPage } from '@/features/resultats/pages/PalmaresPage'
import { BulletinsPage } from '@/features/resultats/pages/BulletinsPage'
import { RemplissagePage } from '@/features/resultats/pages/RemplissagePage'
import { EmploiDuTempsPage } from '@/features/emploiDuTemps/pages/EmploiDuTempsPage'
import { SeancesPage } from '@/features/emploiDuTemps/pages/SeancesPage'
import { IdentificationPage } from '@/features/identification/pages/IdentificationPage'
import { SettingsPage } from '@/features/settings/pages/SettingsPage'
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
      { path: 'departements', element: <ProtectedRoute permission="personnel.view"><DepartementsPage /></ProtectedRoute> },
      { path: 'classes', element: <ProtectedRoute permission="classes.view"><ClassesListPage /></ProtectedRoute> },
      { path: 'classes/:id', element: <ProtectedRoute permission="classes.view"><ClasseDetailPage /></ProtectedRoute> },
      { path: 'eleves', element: <ProtectedRoute permission="eleves.view"><ElevesListPage /></ProtectedRoute> },
      { path: 'matieres', element: <ProtectedRoute permission="pedagogie.view"><MatieresPage /></ProtectedRoute> },
      { path: 'sanctions', element: <ProtectedRoute permission="discipline.view"><SanctionsPage /></ProtectedRoute> },
      { path: 'palmares', element: <ProtectedRoute permission="bulletins.view"><PalmaresPage /></ProtectedRoute> },
      { path: 'bulletins', element: <ProtectedRoute permission="bulletins.view"><BulletinsPage /></ProtectedRoute> },
      { path: 'remplissage', element: <ProtectedRoute permission="notes.view"><RemplissagePage /></ProtectedRoute> },
      { path: 'stats-pedagogiques', element: <ProtectedRoute permission="bulletins.view"><StatsPedagogiquesPage /></ProtectedRoute> },
      { path: 'stats-disciplinaires', element: <ProtectedRoute permission="discipline.view"><StatsDisciplinairesPage /></ProtectedRoute> },
      { path: 'emploi-du-temps', element: <ProtectedRoute permission="emploi_du_temps.view"><EmploiDuTempsPage /></ProtectedRoute> },
      { path: 'seances', element: <ProtectedRoute permission="emploi_du_temps.view"><SeancesPage /></ProtectedRoute> },
      { path: 'identification', element: <ProtectedRoute permission="eleves.view"><IdentificationPage /></ProtectedRoute> },
      { path: 'session', element: <ProtectedRoute permission="ecoles.manage"><SessionPage /></ProtectedRoute> },
      { path: 'parametres', element: <ProtectedRoute permission="ecoles.manage"><SettingsPage /></ProtectedRoute> },
    ],
  },
])
