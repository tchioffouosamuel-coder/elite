import { UserPlus, BriefcaseBusiness, LogIn } from 'lucide-react'
import type { ActiviteLog } from '@/features/dashboard/api'

// « eleve » reste géré pour la vue classe (titulaire), qui liste encore de
// simples inscriptions plutôt que le journal complet — cf. DashboardService::statsClasse.
const ICONES_ACTIVITE: Record<string, { Icon: typeof UserPlus; classe: string }> = {
  connexion: { Icon: LogIn, classe: 'bg-green-50 text-green-600' },
  'eleve.cree': { Icon: UserPlus, classe: 'bg-navy-50 text-navy-600' },
  eleve: { Icon: UserPlus, classe: 'bg-navy-50 text-navy-600' },
  'personnel.cree': { Icon: BriefcaseBusiness, classe: 'bg-gold-50 text-gold-600' },
}
const ICONE_PAR_DEFAUT = { Icon: BriefcaseBusiness, classe: 'bg-gold-50 text-gold-600' }

export function LigneActivite({ a }: { a: ActiviteLog }) {
  const { Icon, classe } = ICONES_ACTIVITE[a.type] ?? ICONE_PAR_DEFAUT
  return (
    <li className="flex items-center gap-3 py-3 text-sm">
      <span className={`flex h-8 w-8 flex-none items-center justify-center rounded-lg ${classe}`}>
        <Icon className="h-4 w-4" />
      </span>
      <span className="flex-1 text-navy-700">{a.libelle}</span>
      <span className="flex-none text-xs text-navy-400">{new Date(a.date).toLocaleString()}</span>
    </li>
  )
}
