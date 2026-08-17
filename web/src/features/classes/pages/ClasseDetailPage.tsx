import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, IdCard, QrCode } from 'lucide-react'
import { fetchClasse } from '@/features/classes/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { ouvrirDocument } from '@/shared/lib/download'
import { ClasseTabs } from '@/features/classes/pages/ClasseTabs'
import { ClasseQrModal } from '@/features/classes/pages/ClasseQrModal'

export function ClasseDetailPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const { id } = useParams<{ id: string }>()
  const classeId = Number(id)
  const [qrOuvert, setQrOuvert] = useState(false)

  const { data: classe, isLoading, isError } = useQuery({ queryKey: ['classe', classeId], queryFn: () => fetchClasse(classeId) })

  if (isLoading) return <Spinner />
  if (isError || !classe) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-start justify-between">
        <div>
          <Link to="/classes" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
            <ArrowLeft className="h-4 w-4" />
            {t('common.back')}
          </Link>
          <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{classe.nom}</h1>
          <p className="text-sm text-navy-400">
            {[
              classe.niveau_scolaire?.libelle ?? classe.niveau?.name_fr,
              classe.filiere,
              classe.titulaire ? `${t('classes.titulaire')} : ${classe.titulaire.nom_complet}` : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </p>
        </div>
        <div className="flex flex-none items-center gap-2">
          {can('emploi_du_temps.manage') && classe.qr_token && (
            <Button variant="secondary" onClick={() => setQrOuvert(true)}>
              <QrCode className="h-4 w-4" />
              Code QR
            </Button>
          )}
          {can('eleves.view') && (
            <Button variant="secondary" onClick={() => ouvrirDocument(`/classes/${classeId}/cartes-scolaires`)}>
              <IdCard className="h-4 w-4" />
              {t('export.carte')}
            </Button>
          )}
        </div>
      </div>

      <ClasseTabs classe={classe} />

      {qrOuvert && <ClasseQrModal classe={classe} onClose={() => setQrOuvert(false)} />}
    </div>
  )
}
