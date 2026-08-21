import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, FileDown, Plus, Trash2, X } from 'lucide-react'
import { fetchSanctions, updateSanction, deleteSanction, ouvrirPvConseil } from '@/features/discipline/api'
import { SanctionFormModal } from '@/features/discipline/SanctionFormModal'
import type { Sanction, StatutSanction, TypeSanction } from '@/features/discipline/api'
import { fetchClasses } from '@/features/classes/api'
import { fetchTrimestres } from '@/features/pedagogie/api'
import { useAuthStore } from '@/shared/store/authStore'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Badge } from '@/shared/ui/Badge'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TYPE_TONE: Record<TypeSanction, 'gold' | 'red' | 'neutral' | 'blue'> = {
  avertissement: 'blue',
  blame: 'gold',
  corvee: 'gold',
  exclusion_temporaire: 'red',
  exclusion_definitive: 'red',
  autre: 'neutral',
}

const STATUT_TONE: Record<StatutSanction, 'gold' | 'green' | 'neutral'> = {
  en_attente: 'gold',
  confirmee: 'green',
  annulee: 'neutral',
}

export function SanctionsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()
  const [classeFiltre, setClasseFiltre] = useState<number | ''>('')
  const [showForm, setShowForm] = useState(false)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const trimestreActif = trimestres?.find((tr) => tr.is_active) ?? trimestres?.[0]
  const { data: sanctions, isLoading } = useQuery({
    queryKey: ['sanctions', classeFiltre],
    queryFn: () => fetchSanctions(classeFiltre ? { classe_id: Number(classeFiltre) } : {}),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['sanctions'] })

  const genererPv = async () => {
    if (!trimestreActif) {
      erreur(t('discipline.no_trimestre_actif'))
      return
    }
    try {
      await ouvrirPvConseil(trimestreActif.id, classeFiltre ? Number(classeFiltre) : undefined)
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  // Le conseil de discipline tranche : confirmer entérine la sanction (elle
  // compte désormais dans le dossier de l'élève, et peut valoir exclusion
  // active), annuler la classe sans suite sans effacer qu'elle a existé.
  const trancher = async (sanction: Sanction, statut: StatutSanction) => {
    if (statut === 'annulee') {
      const ok = await confirmer({
        titre: t('discipline.cancel_sanction_title'),
        message: t('discipline.cancel_sanction_message', { nom: sanction.eleve.nom_complet }),
        action: t('discipline.cancel_sanction_action'),
      })
      if (!ok) return
    }

    try {
      await updateSanction(sanction.id, { statut })
      invalidate()
      succes(statut === 'confirmee' ? t('discipline.sanction_confirmee') : t('discipline.sanction_annulee'))
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnes: Colonne<Sanction>[] = [
    {
      cle: 'eleve',
      entete: t('eleves.nom_complet'),
      valeur: (s) => s.eleve.nom_complet,
      cellule: (s) => <span className="font-semibold text-navy-900">{s.eleve.nom_complet}</span>,
    },
    { cle: 'classe', entete: t('classes.title'), valeur: (s) => s.classe, cellule: (s) => s.classe ?? '—' },
    {
      cle: 'school',
      entete: t('classes.ecole'),
      valeur: (s) => s.school?.name,
      cellule: (s) => <span className="text-navy-600">{s.school?.name ?? '—'}</span>,
      masquerMobile: true,
    },
    {
      cle: 'type',
      entete: t('discipline.type'),
      valeur: (s) => s.type,
      cellule: (s) => (
        <>
          <Badge tone={TYPE_TONE[s.type]}>{t(`discipline.type_${s.type}`)}</Badge>
          {s.duree_jours && <span className="ml-1 text-xs text-navy-400">({s.duree_jours}j)</span>}
        </>
      ),
    },
    {
      cle: 'motif',
      entete: t('discipline.motif'),
      valeur: (s) => s.motif,
      cellule: (s) => <span className="block max-w-xs truncate">{s.motif}</span>,
      masquerMobile: true,
    },
    {
      cle: 'date',
      entete: t('discipline.date'),
      valeur: (s) => s.date_sanction,
      cellule: (s) => new Date(s.date_sanction).toLocaleDateString('fr-FR'),
    },
    {
      cle: 'statut',
      entete: t('discipline.statut'),
      valeur: (s) => s.statut,
      cellule: (s) => <Badge tone={STATUT_TONE[s.statut]}>{t(`discipline.statut_${s.statut}`)}</Badge>,
    },
    ...(can('discipline.manage')
      ? [
        {
          cle: 'actions',
          entete: t('common.actions'),
          cellule: (s: Sanction) => (
            <div className="flex items-center gap-1">
              {s.statut === 'en_attente' && (
                <>
                  <button
                    title={t('discipline.confirmer')}
                    onClick={() => trancher(s, 'confirmee')}
                    className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-green-50 hover:text-green-600"
                  >
                    <Check className="h-4 w-4" />
                  </button>
                  <button
                    title={t('discipline.annuler_sanction')}
                    onClick={() => trancher(s, 'annulee')}
                    className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </>
              )}
              <button
                title={t('common.delete')}
                onClick={async () => {
                  if (!(await confirmerSuppression(t('discipline.delete_target', { nom: s.eleve.nom_complet })))) return
                  await deleteSanction(s.id)
                  invalidate()
                  succes(t('alerts.sanction_deleted'))
                }}
                className="rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-red-500"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ),
        } satisfies Colonne<Sanction>,
      ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('discipline.sanctions')}</h1>
        {can('discipline.manage') && (
          <div className="flex items-center gap-2">
            <Button variant="secondary" onClick={genererPv}>
              <FileDown className="h-4 w-4" />
              {t('discipline.pv_conseil')}
            </Button>
            <Button onClick={() => setShowForm(true)}>
              <Plus className="h-4 w-4" />
              {t('discipline.add_sanction')}
            </Button>
          </div>
        )}
      </div>

      <Select value={classeFiltre} onChange={(e) => setClasseFiltre(e.target.value ? Number(e.target.value) : '')} className="max-w-xs">
        <option value="">{t('common.all')}</option>
        {classes?.map((c) => (
          <option key={c.id} value={c.id}>
            {c.nom}
          </option>
        ))}
      </Select>

      {isLoading ? (
        <Spinner />
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={sanctions ?? []}
          cleLigne={(s) => s.id}
          placeholderRecherche={t('discipline.search_placeholder')}
          messageVide={t('discipline.empty_sanctions')}
          largeurMin={860}
        />
      )}

      {showForm && (
        <SanctionFormModal
          onClose={() => setShowForm(false)}
          onCreated={() => {
            setShowForm(false)
            invalidate()
          }}
        />
      )}
    </div>
  )
}

