import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
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
    const { t } = useTranslation()
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
                succes(t('sousSystemes.updated'))
            } else {
                await createSousSysteme(data)
                succes(t('sousSystemes.created'))
            }
            onCreated()
            onClose()
        } catch (err: any) {
            erreur(err.response?.data?.message || t('common.error_generic'))
        }
    }

    return (
        <Modal title={sousSysteme ? t('sousSystemes.edit') : t('sousSystemes.create')} onClose={onClose}>
            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-navy-900">{t('sousSystemes.code')} *</label>
                    <Input
                        placeholder={t('sousSystemes.code_placeholder')}
                        {...register('code', { required: t('sousSystemes.code_required') })}
                        error={errors.code?.message}
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-navy-900">{t('sousSystemes.nom')} *</label>
                    <Input
                        placeholder={t('sousSystemes.nom_placeholder')}
                        {...register('nom', { required: t('sousSystemes.nom_required') })}
                        error={errors.nom?.message}
                    />
                </div>

                <div>
                    <label className="block text-sm font-medium text-navy-900">{t('sousSystemes.description')}</label>
                    <textarea
                        placeholder={t('sousSystemes.description_placeholder')}
                        {...register('description')}
                        className="w-full rounded-lg border border-navy-200 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-1 focus:ring-gold-500"
                        rows={3}
                    />
                </div>

                <div className="flex justify-end gap-3 pt-4">
                    <Button variant="secondary" onClick={onClose} type="button">
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit" disabled={isSubmitting}>
                        {isSubmitting ? t('common.saving') : sousSysteme ? t('common.update') : t('common.create')}
                    </Button>
                </div>
            </form>
        </Modal>
    )
}
