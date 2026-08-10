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
import { PalmaresPage } from '@/features/resultats/pages/PalmaresPage'
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
      { path: 'session', element: <ProtectedRoute permission="ecoles.manage"><SessionPage /></ProtectedRoute> },
      { path: 'parametres', element: <ProtectedRoute permission="ecoles.manage"><SettingsPage /></ProtectedRoute> },
    ],
  },
])
