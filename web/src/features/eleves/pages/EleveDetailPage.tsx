import { useTranslation } from 'react-i18next'
import { useParams, Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Pencil, FileDown, FileText, Phone, Mail, Briefcase, UserRound } from 'lucide-react'
import { fetchEleve } from '@/features/eleves/api'
import { ouvrirBulletin } from '@/features/resultats/api'
import { telechargerFichier } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Badge } from '@/shared/ui/Badge'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'

function Champ({ label, valeur }: { label: string; valeur: string | null | undefined }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-semibold uppercase tracking-wide text-navy-400">{label}</span>
      <span className="text-sm font-medium text-navy-800">{valeur || '—'}</span>
    </div>
  )
}

export function EleveDetailPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const can = useAuthStore((s) => s.can)
  const { id } = useParams<{ id: string }>()
  const eleveId = Number(id)

  const { data: eleve, isLoading, isError } = useQuery({ queryKey: ['eleve', eleveId], queryFn: () => fetchEleve(eleveId) })

  if (isLoading) return <Spinner />
  if (isError || !eleve) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/eleves" className="mb-2 flex items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
            <ArrowLeft className="h-4 w-4" />
            {t('common.back')}
          </Link>
          <div className="flex items-center gap-3">
            {eleve.photo_url ? (
              <img src={eleve.photo_url} alt={eleve.nom_complet} className="h-14 w-14 rounded-full object-cover ring-1 ring-navy-100" />
            ) : (
              <span className="flex h-14 w-14 items-center justify-center rounded-full bg-navy-700 text-lg font-bold text-cream-50">
                {eleve.nom_complet
                  .split(' ')
                  .map((p) => p[0])
                  .slice(0, 2)
                  .join('')
                  .toUpperCase()}
              </span>
            )}
            <div>
              <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{eleve.nom_complet}</h1>
              <p className="text-sm text-navy-400">
                {[eleve.matricule, eleve.classe?.nom].filter(Boolean).join(' · ') || '—'}
              </p>
            </div>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {eleve.classe && (
            <>
              <Button variant="secondary" onClick={() => ouvrirBulletin(eleve.id)}>
                <FileDown className="h-4 w-4" />
                PDF
              </Button>
              <Button
                variant="secondary"
                onClick={() => telechargerFichier(`/eleves/${eleve.id}/attestation-scolarite`, undefined, 'attestation.docx')}
              >
                <FileText className="h-4 w-4" />
                Word
              </Button>
            </>
          )}
          {can('eleves.manage') && (
            <Button onClick={() => navigate(`/eleves/${eleve.id}/edit`)}>
              <Pencil className="h-4 w-4" />
              {t('common.edit')}
            </Button>
          )}
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <Badge tone={eleve.statut === 'actif' ? 'green' : 'neutral'}>{t(`eleves.${eleve.statut}`)}</Badge>
        <Badge tone={eleve.sexe === 'F' ? 'gold' : 'neutral'}>{eleve.sexe === 'F' ? t('eleves.feminin') : t('eleves.masculin')}</Badge>
        {eleve.redoublant && <Badge tone="red">Redoublant</Badge>}
      </div>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">Informations personnelles</h2>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Champ label={t('eleves.matricule')} valeur={eleve.matricule} />
          <Champ label={t('eleves.date_naissance')} valeur={eleve.date_naissance} />
          <Champ label="Lieu de naissance" valeur={eleve.lieu_naissance} />
          <Champ label="Nationalité" valeur={eleve.nationalite} />
          <Champ label={t('eleves.classe')} valeur={eleve.classe?.nom} />
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">{t('eleves.tuteur')}</h2>
        {eleve.tuteurs.length === 0 ? (
          <p className="text-sm text-navy-400">Aucun tuteur enregistré.</p>
        ) : (
          <div className="flex flex-col divide-y divide-navy-100">
            {eleve.tuteurs.map((tuteur) => (
              <div key={tuteur.id} className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                <div className="flex items-center gap-3">
                  <span className="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-navy-50 ring-1 ring-navy-100">
                    <UserRound className="h-4 w-4 text-navy-400" />
                  </span>
                  <div>
                    <p className="text-sm font-semibold text-navy-800">
                      {tuteur.nom_complet}
                      {tuteur.is_principal && (
                        <span className="ml-2 text-xs font-medium text-gold-600">Principal</span>
                      )}
                    </p>
                    <p className="text-xs text-navy-400">{tuteur.lien_parente || '—'}</p>
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-navy-500">
                  {tuteur.telephone && (
                    <span className="flex items-center gap-1">
                      <Phone className="h-3.5 w-3.5" />
                      {tuteur.telephone}
                    </span>
                  )}
                  {tuteur.email && (
                    <span className="flex items-center gap-1">
                      <Mail className="h-3.5 w-3.5" />
                      {tuteur.email}
                    </span>
                  )}
                  {tuteur.profession && (
                    <span className="flex items-center gap-1">
                      <Briefcase className="h-3.5 w-3.5" />
                      {tuteur.profession}
                    </span>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
