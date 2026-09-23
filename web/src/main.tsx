import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

// Fenêtre desktop sans cadre : réserve la place de la barre de titre globale
// (cf. DesktopTitleBar et `.desktop-shell` dans index.css).
if (window.desktop) document.documentElement.classList.add('desktop-shell')

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
