import { useEffect } from 'react'
import { createSousSysteme, updateSousSysteme, type SousSysteme } from './api'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input } from '@/shared/ui/Field'
import { useForm } from 'react-hook-form'
import { erreur, succes } from '@/shared/lib/alertes'

interface SousSystemeFormModalProps {
    sousSysteme?: SousSysteme | null
    onClose: () => void
    onCreated: () => void
}

export function SousSystemeFormModal({ sousSysteme, onClose, onCreated }: SousSystemeFormModalProps) {
    const {
        register,
        handleSubmit,
        reset,
        formState: { isSubmitting, errors },
    } = useForm({
        defaultValues: {
            code: sousSysteme?.code ?? '',
            nom: sousSysteme?.nom ?? '',
            description: sousSysteme?.description ?? '',
        },
    })

    useEffect(() => {
        reset({
            code: sousSysteme?.code ?? '',
            nom: sousSysteme?.nom ?? '',
            description: sousSysteme?.description ?? '',
        })
    }, [sousSysteme, reset])

    const onSubmit = async (data: any) => {
        try {
            if (sousSysteme) {
                await updateSousSysteme(sousSysteme.id, data)
                succes('Sous-système mis à jour.')
            } else {
                await createSousSysteme(data)
                succes('Sous-système créé.')
            }
            onCreated()
            onClose()
        } catch (err: any) {
            erreur(err.response?.data?.message || 'Une erreur est survenue.')
        }
    }

    return (
        <Modal title={sousSysteme ? 'Modifier le sous-système' : 'Créer un sous-système'} onClose={onClose}>
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-navy-900">Code *</label>
                    <Input
                        placeholder="ex: FR, EN, BI"
                        {...register('code', { required: 'Le code est requis' })}
                        error={errors.code?.message}
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-navy-900">Nom *</label>
                    <Input
                        placeholder="ex: Francophone, Anglophone, Bilingue"
                        {...register('nom', { required: 'Le nom est requis' })}
                        error={errors.nom?.message}
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-navy-900">Description</label>
                    <textarea
                        placeholder="Description du sous-système"
                        {...register('description')}
                        className="w-full rounded-lg border border-navy-200 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500"
                        rows={3}
                    />
                </div>

                <div className="flex justify-end gap-3 pt-4">
                    <Button variant="secondary" onClick={onClose} type="button">
                        Annuler
                    </Button>
                    <Button type="submit" disabled={isSubmitting}>
                        {isSubmitting ? 'En cours...' : sousSysteme ? 'Mettre à jour' : 'Créer'}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
