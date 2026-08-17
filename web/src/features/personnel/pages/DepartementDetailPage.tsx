import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useParams, useNavigate } from 'react-router-dom'
import { ArrowLeft, Building2, Users, BookOpen, BarChart3, Download, X } from 'lucide-react'
import { fetchDepartementDetail, fetchStatsPedagogiquesParDepartement, updateDepartement, exportStatistiquesAsPdf } from '@/features/personnel/api'
import { fetchPersonnels } from '@/features/personnel/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { Card } from '@/shared/ui/Card'
import { StatistiquesChart } from '@/features/personnel/components/StatistiquesChart'

export function DepartementDetailPage() {
    const { t } = useTranslation()
    const { id } = useParams<{ id: string }>()
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const can = useAuthStore((s) => s.can)

    const depId = parseInt(id || '0')
    const { data: dept, isLoading, isError } = useQuery({
        queryKey: ['departement', depId],
        queryFn: () => fetchDepartementDetail(depId),
        enabled: depId > 0,
    })
    const { data: personnels } = useQuery({
        queryKey: ['personnels'],
        queryFn: () => fetchPersonnels()
    })
    const { data: stats } = useQuery({
        queryKey: ['departement-stats', depId],
        queryFn: () => fetchStatsPedagogiquesParDepartement(depId),
        enabled: depId > 0,
    })

    const [headPersonnelId, setHeadPersonnelId] = useState<number | null>(dept?.head_personnel_id ?? null)
    const [submitting, setSubmitting] = useState(false)
    const [exporting, setExporting] = useState(false)
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null)

    const handleUpdateHead = async () => {
        if (!dept) return
        setSubmitting(true)
        try {
            await updateDepartement(dept.id, { head_personnel_id: headPersonnelId })
            queryClient.invalidateQueries({ queryKey: ['departement', depId] })
            setMessage({ type: 'success', text: t('common.saved') })
            setTimeout(() => setMessage(null), 3000)
        } catch (err) {
            setMessage({ type: 'error', text: t('common.error') })
        } finally {
            setSubmitting(false)
        }
    }

    const handleExportPdf = async () => {
        if (!dept) return
        setExporting(true)
        try {
            await exportStatistiquesAsPdf(dept.id, undefined, dept.nom)
            setMessage({ type: 'success', text: t('departements.detail.export_success') })
            setTimeout(() => setMessage(null), 3000)
        } catch (err) {
            setMessage({ type: 'error', text: t('departements.detail.export_error') })
        } finally {
            setExporting(false)
        }
    }

    if (isLoading) return <Spinner />
    if (isError || !dept) return <ErrorState />

    const enseignants = personnels?.filter((p) => p.fonction?.toLowerCase().includes('enseignant')) || []

    return (
        <div className="flex flex-col gap-6">
            <div className="flex items-center gap-3">
                <button
                    onClick={() => navigate('/departements')}
                    className="flex h-9 w-9 items-center justify-center rounded-lg text-navy-500 hover:bg-cream-100"
                >
                    <ArrowLeft className="h-5 w-5" />
                </button>
                <PageHeader titre={dept.nom} icon={Building2} />
            </div>

            {message && (
                <div className={`rounded-lg p-4 flex items-center justify-between ${message.type === 'success'
                    ? 'bg-green-50 text-green-800 border border-green-200'
                    : 'bg-red-50 text-red-800 border border-red-200'
                    }`}>
                    <span>{message.text}</span>
                    <button onClick={() => setMessage(null)} className="ml-4">
                        <X className="h-4 w-4" />
                    </button>
                </div>
            )}

            {/* Chef de département */}
            {can('personnel.manage') && (
                <Card>
                    <div className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold text-navy-900 flex items-center gap-2">
                            <Users className="h-5 w-5 text-gold-500" />
                            {t('departements.detail.chef_title')}
                        </h2>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Select
                                value={headPersonnelId?.toString() || ''}
                                onChange={(e) => setHeadPersonnelId(e.target.value ? parseInt(e.target.value) : null)}
                                label={t('departements.detail.chef_label')}
                            >
                                <option value="">{t('departements.detail.none')}</option>
                                {enseignants.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.nom_complet}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className="flex justify-end">
                            <Button
                                onClick={handleUpdateHead}
                                disabled={submitting || headPersonnelId === dept.head_personnel_id}
                            >
                                {submitting ? t('common.saving') : t('departements.detail.save')}
                            </Button>
                        </div>
                    </div>
                </Card>
            )}

            {/* Matières du département */}
            <Card>
                <div className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold text-navy-900 flex items-center gap-2">
                        <BookOpen className="h-5 w-5 text-gold-500" />
                        {t('departements.detail.matieres_title')}
                    </h2>
                    {dept.matieres && dept.matieres.length > 0 ? (
                        <div className="grid gap-2">
                            {dept.matieres.map((matiere) => (
                                <div
                                    key={matiere.id}
                                    className="flex items-center justify-between rounded-lg border border-navy-100 p-3 hover:bg-cream-50"
                                >
                                    <span className="font-medium text-navy-900">{matiere.nom}</span>
                                    {can('pedagogie.manage') && (
                                        <Button size="sm" variant="secondary">
                                            {t('departements.detail.modify')}
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="text-navy-500">{t('departements.detail.no_matiere')}</p>
                    )}
                </div>
            </Card>

            {/* Statistiques pédagogiques */}
            {stats && (
                <Card>
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-navy-900 flex items-center gap-2">
                                <BarChart3 className="h-5 w-5 text-gold-500" />
                                {t('departements.detail.stats_title', { trimestre: stats.trimestre.libelle })}
                            </h2>
                            <Button
                                onClick={handleExportPdf}
                                disabled={exporting}
                                variant="secondary"
                                size="sm"
                                className="flex items-center gap-2"
                            >
                                <Download className="h-4 w-4" />
                                {exporting ? t('departements.detail.exporting') : t('departements.detail.export_pdf')}
                            </Button>
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div className="rounded-lg bg-cream-50 p-4">
                                <p className="text-sm text-navy-600">{t('departements.detail.effectif_total')}</p>
                                <p className="text-2xl font-bold text-navy-900">{stats.stats_consolidees.effectif_total}</p>
                            </div>
                            <div className="rounded-lg bg-cream-50 p-4">
                                <p className="text-sm text-navy-600">{t('departements.detail.moyenne_generale')}</p>
                                <p className="text-2xl font-bold text-navy-900">
                                    {stats.stats_consolidees.moyenne_generale?.toFixed(2) || '—'}
                                </p>
                            </div>
                            <div className="rounded-lg bg-cream-50 p-4">
                                <p className="text-sm text-navy-600">{t('departements.detail.taux_reussite')}</p>
                                <p className="text-2xl font-bold text-navy-900">
                                    {stats.stats_consolidees.taux_reussite_moyen?.toFixed(1) || '—'}%
                                </p>
                            </div>
                        </div>

                        <StatistiquesChart stats={stats} />

                        <div className="mt-4">
                            <h3 className="mb-3 text-base font-semibold text-navy-900">{t('departements.detail.recap_matiere')}</h3>
                            <div className="grid gap-3">
                                {stats.matieres.map((m) => (
                                    <div key={m.id} className="flex items-center justify-between rounded border border-navy-100 p-3">
                                        <div>
                                            <p className="font-medium text-navy-900">{m.nom}</p>
                                            <p className="text-sm text-navy-600">
                                                {t('departements.detail.eleves_moyenne', { count: m.effectif_eleves, moyenne: m.moyenne?.toFixed(2) || '—' })}
                                            </p>
                                        </div>
                                        <span className="text-sm font-semibold text-gold-600">
                                            {m.taux_reussite?.toFixed(1) || '—'}%
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </Card>
            )}
        </div>
    )
}
