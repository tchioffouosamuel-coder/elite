import { useEffect } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { createSousSysteme, updateSousSysteme, type SousSysteme } from './api'
import { fetchSchools } from '@/features/classes/api'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { useForm } from 'react-hook-form'
import { erreur, succes } from '@/shared/lib/alertes'

interface SousSystemeFormModalProps {
    sousSysteme?: SousSysteme | null
    onClose: () => void
    onCreated: () => void
}

export function SousSystemeFormModal({ sousSysteme, onClose, onCreated }: SousSystemeFormModalProps) {
    const { t } = useTranslation()
    const { data: schools } = useQuery({ queryKey: ['schools'], queryFn: fetchSchools })
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
            school_id: sousSysteme?.school_id,
        },
    })

    useEffect(() => {
        reset({
            code: sousSysteme?.code ?? '',
            nom: sousSysteme?.nom ?? '',
            description: sousSysteme?.description ?? '',
            school_id: sousSysteme?.school_id,
        })
    }, [sousSysteme, reset])

    const onSubmit = async (data: any) => {
        try {
            const payload = { ...data, school_id: data.school_id ? Number(data.school_id) : null }
            if (sousSysteme) {
                await updateSousSysteme(sousSysteme.id, payload)
                succes(t('sousSystemes.updated'))
            } else {
                await createSousSysteme(payload)
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
                {!sousSysteme && (
                    <Select
                        label={`${t('classes.ecole')} *`}
                        error={errors.school_id?.message}
                        {...register('school_id', {
                            required: (schools?.length ?? 0) > 1 ? "L'école est requise." : false,
                        })}
                    >
                        <option value="">—</option>
                        {schools?.map((s) => (
                            <option key={s.id} value={s.id}>
                                {s.name}
                            </option>
                        ))}
                    </Select>
                )}

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
