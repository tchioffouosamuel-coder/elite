import { useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Camera, IdCard, Upload } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchEleves, uploadElevePhoto, type Eleve } from '@/features/eleves/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'

export function IdentificationClassePage() {
    const { t } = useTranslation()
    const navigate = useNavigate()
    const can = useAuthStore((s) => s.can)
    const { classeId: classeIdParam } = useParams<{ classeId: string }>()
    const classeId = Number(classeIdParam)
    const [editionEnCours, setEditionEnCours] = useState(false)

    const { data: classes, isLoading: chargementClasses } = useQuery({
        queryKey: ['classes'],
        queryFn: () => fetchClasses(),
    })
    const classe = classes?.find((item) => item.id === classeId)
    const { data, isLoading } = useQuery({
        queryKey: ['eleves-identification', classeId],
        queryFn: () => fetchEleves({ classe_id: classeId, per_page: 1000 }),
        enabled: Number.isInteger(classeId) && classeId > 0,
    })
    const eleves = data?.items ?? []
    const avecPhoto = eleves.filter((eleve) => eleve.photo_url).length

    const editerCartes = async () => {
        setEditionEnCours(true)
        try {
            await ouvrirDocument(`/classes/${classeId}/cartes-scolaires`)
        } finally {
            setEditionEnCours(false)
        }
    }

    if (chargementClasses || isLoading) return <Spinner />

    return (
        <div className="flex flex-col gap-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button variant="secondary" onClick={() => navigate('/identification')}>
                        <ArrowLeft className="h-4 w-4" />
                        {t('common.back')}
                    </Button>
                    <div>
                        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">
                            {classe?.nom ?? t('identification.class_details')}
                        </h1>
                        <p className="text-sm text-navy-500">{t('identification.class_details_subtitle')}</p>
                    </div>
                </div>
                <Button onClick={editerCartes} disabled={editionEnCours || eleves.length === 0}>
                    <IdCard className="h-4 w-4" />
                    {editionEnCours ? t('identification.generating') : t('identification.edit_cards')}
                </Button>
            </div>

            {!classe || !Number.isInteger(classeId) ? (
                <Card>
                    <EmptyState label={t('identification.class_not_found')} />
                </Card>
            ) : eleves.length === 0 ? (
                <Card>
                    <EmptyState label={t('identification.empty_eleves_classe')} />
                </Card>
            ) : (
                <>
                    <Card className="flex items-center gap-3 text-sm">
                        <Camera className="h-4 w-4 text-gold-500" />
                        <span className="font-medium text-navy-700">
                            {t('identification.photo_coverage', { avec: avecPhoto, total: eleves.length })}
                        </span>
                        {avecPhoto < eleves.length && (
                            <Badge tone="red">{t('identification.missing_count', { count: eleves.length - avecPhoto })}</Badge>
                        )}
                    </Card>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {eleves.map((eleve) => (
                            <VignetteEleve key={eleve.id} eleve={eleve} classeId={classeId} peutModifier={can('eleves.manage')} />
                        ))}
                    </div>
                </>
            )}
        </div>
    )
}

function VignetteEleve({
    eleve,
    classeId,
    peutModifier,
}: {
    eleve: Eleve
    classeId: number
    peutModifier: boolean
}) {
    const { t } = useTranslation()
    const queryClient = useQueryClient()
    const inputRef = useRef<HTMLInputElement>(null)

    const envoi = useMutation({
        mutationFn: (file: File) => uploadElevePhoto(eleve.id, file),
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['eleves-identification', classeId] })
            queryClient.invalidateQueries({ queryKey: ['eleves-identification-summary', classeId] })
        },
    })

    return (
        <div className="flex flex-col items-center gap-2 rounded-2xl border border-navy-100 bg-white p-3">
            {eleve.photo_url ? (
                <img src={eleve.photo_url} alt={eleve.nom_complet} className="h-24 w-24 rounded-xl object-cover" />
            ) : (
                <div className="flex h-24 w-24 items-center justify-center rounded-xl bg-navy-800 text-lg font-bold text-gold-300">
                    {eleve.nom_complet
                        .split(' ')
                        .map((partie) => partie[0])
                        .slice(0, 2)
                        .join('')}
                </div>
            )}

            <p className="w-full truncate text-center text-xs font-semibold text-navy-800">{eleve.nom_complet}</p>
            <p className="text-[11px] text-navy-400">{eleve.matricule ?? '—'}</p>

            {peutModifier && (
                <>
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/jpeg,image/png"
                        className="hidden"
                        onChange={(event) => {
                            const file = event.target.files?.[0]
                            if (file) envoi.mutate(file)
                            event.target.value = ''
                        }}
                    />
                    <Button size="sm" variant="secondary" onClick={() => inputRef.current?.click()} disabled={envoi.isPending}>
                        <Upload className="h-3.5 w-3.5" />
                        {envoi.isPending ? t('identification.sending') : eleve.photo_url ? t('identification.replace_photo') : t('identification.add_photo')}
                    </Button>
                </>
            )}
        </div>
    )
}
