import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, Trash2, Layers } from 'lucide-react'
import { fetchSousSystemes, deleteSousSysteme, type SousSysteme } from './api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { PageHeader } from '@/shared/ui/PageHeader'
import { ImportExportBar } from '@/shared/ui/ImportExportBar'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { confirmer, succes } from '@/shared/lib/alertes'
import { SousSystemeFormModal } from './SousSystemeFormModal'

export function SousSystemesListPage() {
    const { t } = useTranslation()
    const can = useAuthStore((s) => s.can)
    const queryClient = useQueryClient()

    const [showForm, setShowForm] = useState(false)
    const [editingSousSysteme, setEditingSousSysteme] = useState<SousSysteme | null>(null)

    const { data, isLoading, isError } = useQuery({
        queryKey: ['sous-systemes'],
        queryFn: () => fetchSousSystemes(),
    })

    const invalidate = () => {
        queryClient.invalidateQueries({ queryKey: ['sous-systemes'] })
    }

    const handleDelete = async (sousSysteme: SousSysteme) => {
        const confirme = await confirmer({
            titre: t('sousSystemes.delete_title', { nom: sousSysteme.nom }),
            message: t('alerts.irreversible'),
            action: t('common.delete'),
        })
        if (!confirme) return

        try {
            await deleteSousSysteme(sousSysteme.id)
            invalidate()
            succes(t('sousSystemes.deleted'))
        } catch (error: any) {
            const errorMsg = error.response?.data?.message || t('sousSystemes.delete_error')
            console.error('Erreur:', errorMsg)
        }
    }

    const colonnes: Colonne<SousSysteme>[] = [
        {
            cle: 'code',
            entete: t('sousSystemes.code'),
            valeur: (s) => s.code,
            cellule: (s) => <span className="font-mono font-semibold text-navy-900">{s.code}</span>,
        },
        {
            cle: 'nom',
            entete: t('sousSystemes.nom'),
            valeur: (s) => s.nom,
            cellule: (s) => <span className="font-semibold text-navy-900">{s.nom}</span>,
        },
        {
            cle: 'description',
            entete: t('sousSystemes.description'),
            valeur: (s) => s.description,
            cellule: (s) => <span className="text-navy-600">{s.description ?? '—'}</span>,
        },
        {
            cle: 'nb_classes',
            entete: t('sousSystemes.nb_classes'),
            valeur: (s) => s.nb_classes,
            cellule: (s) => <span className="text-navy-600">{s.nb_classes ?? 0}</span>,
        },
        {
            cle: 'school',
            entete: t('classes.ecole'),
            valeur: (s) => s.school?.name,
            cellule: (s) => <span className="text-navy-600">{s.school?.name ?? '—'}</span>,
            masquerMobile: true,
        },
        {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (s) =>
                can('classes.manage') ? (
                    <div className="flex items-center gap-1">
                        <button
                            title={t('common.edit')}
                            onClick={() => setEditingSousSysteme(s)}
                            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                        >
                            <Pencil className="h-4 w-4" />
                        </button>
                        <button
                            title={t('common.delete')}
                            onClick={() => handleDelete(s)}
                            className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-600"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    </div>
                ) : null,
        },
    ]

    return (
        <div className="flex flex-col gap-5">
            <PageHeader
                titre={t('sousSystemes.title')}
                icon={Layers}
                actions={
                    can('classes.manage') && (
                        <div className="flex items-center gap-2">
                            <ImportExportBar
                                titreImport={t('sousSystemes.title')}
                                importUrl="/sous-systemes/import"
                                exportUrl="/sous-systemes/export"
                                modeleUrl="/sous-systemes/modele"
                                colonnes={['Code', 'Nom', 'Description']}
                                nomFichier="sous-systemes"
                                onImported={() => queryClient.invalidateQueries({ queryKey: ['sous-systemes'] })}
                            />
                            <Button onClick={() => setShowForm(true)}>
                                <Plus className="h-4 w-4" />
                                {t('sousSystemes.create')}
                            </Button>
                        </div>
                    )
                }
            />

            {isLoading ? (
                <Spinner />
            ) : isError || !data ? (
                <ErrorState />
            ) : (
                <DataTable
                    colonnes={colonnes}
                    lignes={data}
                    cleLigne={(s) => s.id}
                    placeholderRecherche={t('sousSystemes.search_placeholder')}
                    messageVide={t('sousSystemes.empty')}
                    largeurMin={800}
                />
            )}

            {showForm && (
                <SousSystemeFormModal
                    onClose={() => setShowForm(false)}
                    onCreated={() => {
                        setShowForm(false)
                        invalidate()
                    }}
                />
            )}
            {editingSousSysteme && (
                <SousSystemeFormModal
                    sousSysteme={editingSousSysteme}
                    onClose={() => setEditingSousSysteme(null)}
                    onCreated={() => {
                        setEditingSousSysteme(null)
                        invalidate()
                    }}
                />
            )}
        </div>
    )
}
