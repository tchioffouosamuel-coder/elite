import { useParams, Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Phone, Mail, UserRound, ShieldCheck, Pencil, Check } from 'lucide-react'
import { fetchFonctionReferentiel, fetchPersonnels } from '@/features/personnel/api'
import { fetchMatricePermissions } from '@/features/permissions/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Card } from '@/shared/ui/Card'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'

export function FonctionReferentielDetailPage() {
  const { id } = useParams<{ id: string }>()
  const fonctionId = Number(id)

  const { data: fonction, isLoading, isError } = useQuery({
    queryKey: ['fonction-referentiel', fonctionId],
    queryFn: () => fetchFonctionReferentiel(fonctionId),
  })

  const { data: personnels, isLoading: chargementPersonnels } = useQuery({
    queryKey: ['personnels', { fonction_id: fonctionId }],
    queryFn: () => fetchPersonnels({ fonction_id: fonctionId, per_page: 500 }),
    enabled: !!fonction,
  })

  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const { data: matrice, isLoading: chargementPermissions } = useQuery({
    queryKey: ['permissions', activeSchoolId],
    queryFn: fetchMatricePermissions,
    enabled: !!fonction,
  })
  const fonctionPermissions = matrice?.fonctions.find((f) => f.id === fonctionId)
  const modulesAccordes = (matrice?.modules ?? [])
    .map((module) => ({
      ...module,
      permissions: module.permissions.filter((p) => fonctionPermissions?.permissions.includes(p.code)),
    }))
    .filter((module) => module.permissions.length > 0)

  if (isLoading) return <Spinner />
  if (isError || !fonction) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link
          to="/fonctions-referentiel"
          className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700"
        >
          <ArrowLeft className="h-4 w-4" />
          Retour
        </Link>
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{fonction.label_fr}</h1>
        <p className="text-sm text-navy-400">{fonction.label_en || '—'}</p>
      </div>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Informations</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-0.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Libellé français</span>
            <span className="text-sm font-medium text-navy-800">{fonction.label_fr}</span>
          </div>
          <div className="flex flex-col gap-0.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">Libellé anglais</span>
            <span className="text-sm font-medium text-navy-800">{fonction.label_en || '—'}</span>
          </div>
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          Personnel
          <Badge tone="blue">{personnels?.length ?? fonction.personnels_count ?? 0}</Badge>
        </h2>

        {chargementPersonnels ? (
          <Spinner />
        ) : !personnels || personnels.length === 0 ? (
          <EmptyState label="Personne n'occupe cette fonction pour le moment." />
        ) : (
          <div className="flex flex-col divide-y divide-navy-100">
            {personnels.map((p) => (
              <div key={p.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-navy-50 ring-1 ring-navy-100">
                    <UserRound className="h-4 w-4 text-navy-400" />
                  </span>
                  <div>
                    <p className="text-sm font-semibold text-navy-800">{p.nom_complet}</p>
                    {p.departement?.nom && <p className="text-xs text-navy-400">{p.departement.nom}</p>}
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-navy-500">
                  {p.telephone && (
                    <span className="flex items-center gap-1">
                      <Phone className="h-3.5 w-3.5" />
                      {p.telephone}
                    </span>
                  )}
                  {p.email && (
                    <span className="flex items-center gap-1">
                      <Mail className="h-3.5 w-3.5" />
                      {p.email}
                    </span>
                  )}
                  <Badge tone={p.statut === 'actif' ? 'green' : 'neutral'}>{p.statut === 'actif' ? 'Actif' : 'Ex-employé'}</Badge>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>

      <Card>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
            <ShieldCheck className="h-4 w-4 text-navy-400" />
            Privilèges
            <Badge tone={fonctionPermissions && fonctionPermissions.permissions.length > 0 ? 'gold' : 'neutral'}>
              {fonctionPermissions?.permissions.length ?? 0}
            </Badge>
          </h2>
          <Link to={`/permissions?fonction=${fonctionId}`}>
            <Button variant="secondary" size="sm">
              <Pencil className="h-4 w-4" />
              Modifier les privilèges
            </Button>
          </Link>
        </div>

        {chargementPermissions ? (
          <Spinner />
        ) : modulesAccordes.length === 0 ? (
          <EmptyState label="Cette fonction ne porte aucun privilège pour le moment." />
        ) : (
          <div className="flex flex-col gap-4">
            {modulesAccordes.map((module) => (
              <div key={module.code}>
                <h3 className="mb-2 text-xs font-bold uppercase tracking-wide text-navy-400">{module.libelle}</h3>
                <div className="grid gap-1.5 sm:grid-cols-2">
                  {module.permissions.map((permission) => (
                    <div key={permission.code} className="flex items-start gap-2 rounded-xl px-2 py-1">
                      <Check className="mt-0.5 h-4 w-4 flex-none text-green-600" />
                      <span className="min-w-0">
                        <span className="block text-sm text-navy-700">{permission.libelle}</span>
                        <code className="text-xs text-navy-300">{permission.code}</code>
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
