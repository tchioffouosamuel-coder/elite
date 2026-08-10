import { clsx } from 'clsx'

export interface TabItem {
  key: string
  label: string
}

export function Tabs({ tabs, active, onChange }: { tabs: TabItem[]; active: string; onChange: (key: string) => void }) {
  return (
    // Défilement horizontal plutôt qu'un retour à la ligne : une barre
    // d'onglets sur deux lignes se lit mal et déplace le contenu sous elle.
    <div className="-mx-4 flex gap-1 overflow-x-auto overscroll-x-contain border-b border-navy-100 px-4 sm:mx-0 sm:px-0">
      {tabs.map((tab) => (
        <button
          key={tab.key}
          onClick={() => onChange(tab.key)}
          className={clsx(
            'whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-semibold transition-colors sm:px-4',
            active === tab.key
              ? 'border-navy-700 text-navy-800'
              : 'border-transparent text-navy-400 hover:text-navy-600',
          )}
        >
          {tab.label}
        </button>
      ))}
    </div>
  )
}
