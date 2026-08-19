import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Building2, Check, ChevronDown, LayoutGrid } from 'lucide-react'
import { clsx } from 'clsx'
import { useAuthStore } from '@/shared/store/authStore'

const LIBELLE_TYPE: Record<string, string> = {
  maternelle: 'Maternelle',
  primaire: 'Primaire',
  secondaire: 'Secondaire',
}

/**
 * Filtre optionnel du super admin : "Toutes les écoles" (mode agrégé, par
 * défaut) ou le focus sur un seul établissement du complexe. Un compte
 * rattaché à une seule école n'a rien à choisir et ne voit pas ce contrôle.
 */
export function SchoolSwitcher({ redirectTo }: { redirectTo?: string }) {
  const [ouvert, setOuvert] = useState(false)
  const { t } = useTranslation()
  const { user, activeSchoolId, setActiveSchool } = useAuthStore()
  const queryClient = useQueryClient()
  const navigate = useNavigate()

  const ecoles = user?.ecoles_accessibles ?? []
  if (!user?.is_super_admin || ecoles.length < 2) return null

  const active = ecoles.find((e) => e.id === activeSchoolId) ?? null

  const basculer = (id: number | null) => {
    setOuvert(false)
    if (id === activeSchoolId) return

    setActiveSchool(id)
    // Tout le cache est scopé par établissement : le vider évite d'afficher
    // les données de l'école (ou du périmètre) précédent le temps du
    // rechargement.
    queryClient.clear()
    if (redirectTo) navigate(redirectTo)
  }

  return (
    <div className="relative min-w-0 max-w-xs flex-1 sm:max-w-none sm:flex-none">
      <button
        onClick={() => setOuvert((o) => !o)}
        className="flex w-full items-center gap-2 rounded-xl border border-navy-100 bg-white px-2.5 py-2 text-sm font-semibold text-navy-800 shadow-soft transition-colors hover:border-gold-300 hover:shadow-card sm:px-3"
      >
        {active ? (
          <Building2 className="h-4 w-4 flex-none text-gold-500" />
        ) : (
          <LayoutGrid className="h-4 w-4 flex-none text-gold-500" />
        )}
        <span className="min-w-0 flex-1 truncate text-left sm:max-w-56 sm:flex-none">
          {active?.name ?? t('nav.toutesLesEcoles', { defaultValue: 'Toutes les écoles' })}
        </span>
        {active && (
          <span className="hidden flex-none rounded-full bg-cream-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-navy-500 md:inline">
            {LIBELLE_TYPE[active.type] ?? active.type}
          </span>
        )}
        <ChevronDown className={clsx('h-4 w-4 flex-none text-navy-400 transition-transform', ouvert && 'rotate-180')} />
      </button>

      {ouvert && (
        <>
          <div className="fixed inset-0 z-10" onClick={() => setOuvert(false)} />
          <ul className="absolute left-0 z-20 mt-1.5 w-80 overflow-hidden rounded-xl border border-navy-100 bg-white py-1 shadow-lg">
            <li>
              <button
                onClick={() => basculer(null)}
                className="flex w-full items-center gap-2.5 px-3.5 py-2.5 text-left text-sm transition-colors hover:bg-cream-50"
              >
                <LayoutGrid className="h-4 w-4 flex-none text-gold-500" />
                <span className="flex-1">
                  <span className="block font-semibold text-navy-800">
                    {t('nav.toutesLesEcoles')}
                  </span>
                  <span className="text-xs text-navy-400">{t('nav.complexe')}</span>
                </span>
                {!active && <Check className="h-4 w-4 flex-none text-gold-500" />}
              </button>
            </li>
            <li className="my-1 border-t border-navy-100" />
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
