import { RouterProvider } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { router } from '@/app/router'
import { DocumentPreviewModal } from '@/shared/ui/DocumentPreviewModal'
import { DesktopTitleBar } from '@/shared/ui/DesktopTitleBar'
import '@/shared/i18n'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30_000,
      // Desktop : les données sont locales et ne changent qu'au passage de
      // la synchronisation — relancer toutes les requêtes de l'écran à chaque
      // retour sur la fenêtre n'apportait qu'une rafale d'appels.
      refetchOnWindowFocus: !window.desktop,
    },
  },
})

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <DesktopTitleBar />
      <RouterProvider router={router} />
      <DocumentPreviewModal />
    </QueryClientProvider>
  )
}

export default App
