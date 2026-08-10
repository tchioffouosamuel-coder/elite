import { clsx } from 'clsx'

export interface TabItem {
  key: string
  label: string
}

export function Tabs({ tabs, active, onChange }: { tabs: TabItem[]; active: string; onChange: (key: string) => void }) {
  return (
    <div className="flex gap-1 border-b border-navy-100">
      {tabs.map((tab) => (
        <button
          key={tab.key}
          onClick={() => onChange(tab.key)}
          className={clsx(
            'border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors',
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
