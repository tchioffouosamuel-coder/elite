import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Building2, Check, ChevronDown } from 'lucide-react'
import { clsx } from 'clsx'
import { useAuthStore } from '@/shared/store/authStore'

const LIBELLE_TYPE: Record<string, string> = {
  maternelle: 'Maternelle',
  primaire: 'Primaire',
  secondaire: 'Secondaire',
}

/**
 * Bascule d'un établissement du complexe à l'autre. N'apparaît que si le compte
 * en couvre plusieurs — un compte rattaché à une seule école n'a rien à choisir.
 */
export function SchoolSwitcher({ redirectTo = '/' }: { redirectTo?: string }) {
  const [ouvert, setOuvert] = useState(false)
  const { user, activeSchoolId, setActiveSchool } = useAuthStore()
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const ecoles = user?.ecoles_accessibles ?? []
  if (ecoles.length < 2) return null

  const active = ecoles.find((e) => e.id === activeSchoolId) ?? ecoles[0]

  const basculer = (id: number) => {
    setOuvert(false)
    if (id === activeSchoolId) return

    setActiveSchool(id)
    // Tout le cache est scopé par établissement : le vider évite d'afficher
    // les classes de l'école précédente le temps du rechargement.
    queryClient.clear()
    navigate(redirectTo)
  }

  return (
    <div className="relative min-w-0 max-w-xs flex-1 sm:max-w-none sm:flex-none">
      <button
        onClick={() => setOuvert((o) => !o)}
        className="flex w-full items-center gap-2 rounded-xl border border-navy-100 bg-white px-2.5 py-2 text-sm font-semibold text-navy-800 shadow-soft transition-colors hover:border-gold-300 hover:shadow-card sm:px-3"
      >
        <Building2 className="h-4 w-4 flex-none text-gold-500" />
        <span className="min-w-0 flex-1 truncate text-left sm:max-w-56 sm:flex-none">{active?.name}</span>
        <span className="hidden flex-none rounded-full bg-cream-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-navy-500 md:inline">
          {LIBELLE_TYPE[active?.type ?? ''] ?? active?.type}
        </span>
        <ChevronDown className={clsx('h-4 w-4 flex-none text-navy-400 transition-transform', ouvert && 'rotate-180')} />
      </button>

      {ouvert && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setOuvert(false)} />
          <ul className="absolute left-0 z-20 mt-1.5 w-80 overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lg">
            {ecoles.map((ecole) => (
              <li key={ecole.id}>
                <button
                  onClick={() => basculer(ecole.id)}
                  className="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm transition-colors hover:bg-cream-50"
                >
                  <span className="flex-1">
                    <span className="block font-semibold text-navy-800">{ecole.name}</span>
                    <span className="text-xs text-navy-400">{LIBELLE_TYPE[ecole.type] ?? ecole.type}</span>
                  </span>
                  {ecole.id === active?.id && <Check className="h-4 w-4 flex-none text-gold-500" />}
                </button>
              </li>
            ))}
          </ul>
        </>
      )}
    </div>
  )
}
