import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { MessageSquareText } from 'lucide-react'
import { fetchAvis } from '@/features/avis/api'
import type { Avis } from '@/features/avis/api'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'

const ROLES = [
  'parent',
  'enseignant',
  'admin_ecole',
  'admin_college',
  'censeur_sg',
  'surveillant_general',
] as const

/** Consultation et filtrage, côté administration, des avis déposés (point 24). */
export function AvisAdminPage() {
  const { t } = useTranslation()
  const [role, setRole] = useState('')
  const [page, setPage] = useState(1)

  const { data, isLoading } = useQuery({
    queryKey: ['avis-admin', role, page],
    queryFn: () => fetchAvis({ role: role || undefined, page }),
  })

  const colonnes: Colonne<Avis>[] = [
    {
      cle: 'created_at',
      entete: t('avis.date'),
      valeur: (a) => a.created_at,
      cellule: (a) => <span className="text-xs text-navy-500">{new Date(a.created_at).toLocaleString('fr-FR')}</span>,
    },
    {
      cle: 'auteur',
      entete: t('avis.auteur'),
      valeur: (a) => a.auteur?.name,
      cellule: (a) => (
        <div className="flex flex-col">
          <span className="font-medium text-navy-700">{a.auteur?.name ?? '—'}</span>
          {a.auteur?.role && <Badge tone="blue">{a.auteur.role}</Badge>}
        </div>
      ),
    },
    {
      cle: 'sujet',
      entete: t('avis.sujet'),
      valeur: (a) => a.sujet,
      cellule: (a) => a.sujet ?? '—',
    },
    {
      cle: 'contenu',
      entete: t('avis.contenu'),
      valeur: (a) => a.contenu,
      cellule: (a) => <span className="line-clamp-2 max-w-md text-sm text-navy-700">{a.contenu}</span>,
      largeur: '420px',
    },
    {
      cle: 'note',
      entete: t('avis.note'),
      valeur: (a) => a.note ?? 0,
      cellule: (a) => (a.note != null ? `${a.note}/5` : '—'),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <MessageSquareText className="h-4 w-4" />
          {t('avis.avis_recus_title')}
        </h2>

        <div className="mb-4 max-w-xs">
          <Select
            label={t('avis.role_auteur')}
            value={role}
            onChange={(e) => {
              setRole(e.target.value)
              setPage(1)
            }}
          >
            <option value="">{t('avis.tous_les_roles')}</option>
            {ROLES.map((r) => (
              <option key={r} value={r}>
                {t(`avis.role_${r}`)}
              </option>
            ))}
          </Select>
        </div>

        <DataTable
          colonnes={colonnes}
          lignes={data?.avis ?? []}
          cleLigne={(a) => a.id}
          recherche
          parPage={0}
          pagination={
            data?.pagination
              ? {
                  page: data.pagination.current_page,
                  totalPages: data.pagination.last_page,
                  total: data.pagination.total,
                  onPageChange: setPage,
                }
              : undefined
          }
        />
        {isLoading && <p className="mt-2 text-xs text-navy-400">{t('avis.chargement')}</p>}
      </Card>
    </div>
  )
}
