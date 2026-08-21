import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { Store } from 'lucide-react'
import { useAuthStore } from '@/shared/store/authStore'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Tabs } from '@/shared/ui/Tabs'
import { ComptoirTab } from '@/features/pointDeVente/pages/ComptoirTab'
import { VentesTab } from '@/features/pointDeVente/pages/VentesTab'
import { EntreesTab } from '@/features/pointDeVente/pages/EntreesTab'

/**
 * Point de vente des fournitures scolaires : le comptoir, le journal des
 * ventes et le réassort sur un seul écran.
 *
 * Trois onglets plutôt que trois entrées de menu : vendre, vérifier une
 * facture et enregistrer une livraison sont les gestes d'une même personne au
 * même poste — les séparer dans la navigation lui coûterait des allers-retours
 * toute la journée.
 */
export function PointDeVentePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const [searchParams, setSearchParams] = useSearchParams()

  const onglets = [
    can('point_de_vente.vendre') && { key: 'comptoir', label: t('pointDeVente.onglet_comptoir') },
    { key: 'ventes', label: t('pointDeVente.onglet_ventes') },
    { key: 'entrees', label: t('pointDeVente.onglet_entrees') },
  ].filter(Boolean) as { key: string; label: string }[]

  const demande = searchParams.get('onglet')
  const onglet = onglets.some((o) => o.key === demande) ? (demande as string) : onglets[0]?.key
  const changerOnglet = (cle: string) => {
    const suivant = new URLSearchParams(searchParams)
    suivant.set('onglet', cle)
    setSearchParams(suivant, { replace: true })
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader titre={t('pointDeVente.title')} sousTitre={t('pointDeVente.subtitle')} icon={Store} />

      <Tabs tabs={onglets} active={onglet ?? ''} onChange={changerOnglet} />

      {onglet === 'comptoir' && <ComptoirTab />}
      {onglet === 'ventes' && <VentesTab />}
      {onglet === 'entrees' && <EntreesTab />}
    </div>
  )
}
