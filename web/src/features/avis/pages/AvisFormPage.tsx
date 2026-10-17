import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { MessageSquarePlus, Star } from 'lucide-react'
import { fetchMesAvis, soumettreAvis } from '@/features/avis/api'
import { Card } from '@/shared/ui/Card'
import { Input, Textarea } from '@/shared/ui/Field'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Espace d'évaluation (point 24) : ouvert à tout compte authentifié — parent,
 * enseignant ou personnel — pour déposer un avis libre sur l'établissement.
 */
export function AvisFormPage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [sujet, setSujet] = useState('')
  const [contenu, setContenu] = useState('')
  const [note, setNote] = useState<number | null>(null)
  const [envoi, setEnvoi] = useState(false)

  const { data: mesAvis, isLoading } = useQuery({ queryKey: ['mes-avis'], queryFn: fetchMesAvis })

  const envoyer = async () => {
    if (!contenu.trim()) return
    setEnvoi(true)
    try {
      await soumettreAvis({ sujet: sujet.trim() || undefined, contenu: contenu.trim(), note: note ?? undefined })
      setSujet('')
      setContenu('')
      setNote(null)
      queryClient.invalidateQueries({ queryKey: ['mes-avis'] })
      succes(t('avis.envoye'))
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <Card>
        <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
          <MessageSquarePlus className="h-4 w-4" />
          {t('avis.title')}
        </h2>

        <div className="flex flex-col gap-3">
          <Input label={t('avis.sujet')} value={sujet} onChange={(e) => setSujet(e.target.value)} maxLength={150} />
          <Textarea
            label={t('avis.contenu')}
            value={contenu}
            onChange={(e) => setContenu(e.target.value)}
            placeholder={t('avis.contenu_placeholder')}
            rows={4}
            maxLength={2000}
          />
          <div className="flex items-center gap-1">
            <span className="mr-2 text-sm text-navy-500">{t('avis.note')}</span>
            {[1, 2, 3, 4, 5].map((valeur) => (
              <button
                key={valeur}
                type="button"
                onClick={() => setNote(note === valeur ? null : valeur)}
                aria-label={`${valeur}/5`}
              >
                <Star
                  className={`h-5 w-5 ${note !== null && valeur <= note ? 'fill-gold-400 text-gold-400' : 'text-navy-200'}`}
                />
              </button>
            ))}
          </div>
          <div>
            <Button onClick={envoyer} disabled={envoi || !contenu.trim()}>
              {envoi ? t('avis.envoi_en_cours') : t('avis.envoyer')}
            </Button>
          </div>
        </div>
      </Card>

      <Card>
        <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">{t('avis.mes_avis')}</h2>
        {isLoading ? (
          <Spinner />
        ) : !mesAvis || mesAvis.length === 0 ? (
          <p className="px-1 text-sm text-navy-400">{t('avis.aucun_avis')}</p>
        ) : (
          <div className="flex flex-col divide-y divide-navy-50">
            {mesAvis.map((a) => (
              <div key={a.id} className="flex flex-col gap-1.5 py-3 first:pt-0 last:pb-0">
                <div className="flex flex-wrap items-center gap-2 text-xs text-navy-400">
                  {a.sujet && <span className="font-semibold text-navy-600">{a.sujet}</span>}
                  <span>{new Date(a.created_at).toLocaleString('fr-FR')}</span>
                  {a.note != null && <span>· {a.note}/5</span>}
                </div>
                <p className="text-sm leading-relaxed text-navy-700">{a.contenu}</p>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}
