import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { ClipboardCheck } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchMatieres } from '@/features/pedagogie/api'
import { fetchSuiviSaisies, type SuiviSaisiesLigne } from '@/features/resultats/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { triEcoleSousSystemeNiveauClasse } from '@/shared/lib/triHierarchique'
import { useAuthStore } from '@/shared/store/authStore'

/** Seuil repris de RemplissagePage : rouge sous la moitié, doré sinon, vert au-delà de 90 %. */
function couleurTaux(taux: number): string {
  if (taux >= 90) return 'bg-green-500'
  if (taux >= 50) return 'bg-gold-500'
  return 'bg-red-500'
}

/** Lignes sous ce seuil méritent une relance — même repère que RemplissagePage. */
const SEUIL_ALERTE = 50

function Jauge({ taux }: { taux: number }) {
  return (
    <div className="flex items-center gap-2">
      <div className="h-2 w-24 flex-none overflow-hidden rounded-full bg-navy-100">
        <div className={`h-full rounded-full ${couleurTaux(taux)}`} style={{ width: `${Math.min(taux, 100)}%` }} />
      </div>
      <span className={`text-xs font-semibold ${taux < SEUIL_ALERTE ? 'text-red-600' : 'text-navy-700'}`}>
        {taux.toFixed(0)} %
      </span>
    </div>
  )
}

/**
 * Point 15 de la punch-list : vu d'ensemble pour la direction — qui n'a pas
 * encore saisi ses notes, qui a du retard sur sa progression, sur la
 * séquence active. Réservé à `pedagogie.suivi_saisies` (jamais l'enseignant
 * ordinaire, cf. nav.group.resultats dans AppLayout).
 */
export function SuiviSaisiesPage() {
  const { t } = useTranslation()
  const user = useAuthStore((state) => state.user)
  const activeSchoolId = useAuthStore((state) => state.activeSchoolId)
  const selectionRequise = Boolean(user?.is_super_admin && activeSchoolId === null)
  const [classeId, setClasseId] = useState<number | ''>('')
  const [matiereId, setMatiereId] = useState<number | ''>('')

  const { data: classes } = useQuery({ queryKey: ['classes', 'select'], queryFn: () => fetchClasses() })
  const { data: matieres } = useQuery({ queryKey: ['matieres'], queryFn: fetchMatieres })

  const { data, isLoading, isError } = useQuery({
    queryKey: ['suivi-saisies', activeSchoolId, classeId, matiereId],
    queryFn: () => fetchSuiviSaisies(classeId || undefined, matiereId || undefined),
    enabled: !selectionRequise,
  })

  const triLignes = triEcoleSousSystemeNiveauClasse<SuiviSaisiesLigne>({ classe: (l) => l.classe })

  const lignesAlerte = useMemo(
    () => (data?.lignes ?? []).filter((l) => l.notes_taux < SEUIL_ALERTE || l.lecons_taux < SEUIL_ALERTE).length,
    [data],
  )

  const colonnes: Colonne<SuiviSaisiesLigne>[] = [
    {
      cle: 'classe',
      entete: t('suivi_saisies.classe'),
      valeur: (l) => l.classe,
      cellule: (l) => <span className="font-semibold text-navy-900">{l.classe}</span>,
    },
    {
      cle: 'matiere',
      entete: t('matieres.title'),
      valeur: (l) => l.matiere,
      cellule: (l) => l.matiere,
    },
    {
      cle: 'enseignant',
      entete: t('personnel.enseignant'),
      valeur: (l) => l.enseignant,
      cellule: (l) => l.enseignant ?? '—',
    },
    {
      cle: 'notes',
      entete: t('suivi_saisies.notes_saisies'),
      valeur: (l) => l.notes_taux,
      cellule: (l) => (
        <div className="flex flex-col gap-1">
          <span className="text-xs tabular-nums text-navy-500">{l.notes_saisies}/{l.notes_attendues}</span>
          <Jauge taux={l.notes_taux} />
        </div>
      ),
    },
    {
      cle: 'lecons',
      entete: t('suivi_saisies.lecons_faites'),
      valeur: (l) => l.lecons_taux,
      cellule: (l) => (
        <div className="flex flex-col gap-1">
          <span className="text-xs tabular-nums text-navy-500">{l.lecons_faites}/{l.lecons_attendues}</span>
          <Jauge taux={l.lecons_taux} />
        </div>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('suivi_saisies.title')}
        sousTitre={data?.sequence ? `${data.sequence.trimestre} — ${data.sequence.libelle}` : t('suivi_saisies.subtitle')}
        icon={ClipboardCheck}
      />

      <div className="flex flex-wrap items-end gap-3">
        <Select
          label={t('classes.title')}
          value={classeId}
          onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">{t('suivi_saisies.toutes_classes')}</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>
        <Select
          label={t('matieres.title')}
          value={matiereId}
          onChange={(e) => setMatiereId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">{t('suivi_saisies.toutes_matieres')}</option>
          {matieres?.map((m) => (
            <option key={m.id} value={m.id}>
              {m.nom}
            </option>
          ))}
        </Select>
      </div>

      {lignesAlerte > 0 && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700">
          {t('suivi_saisies.alerte', { count: lignesAlerte })}
        </div>
      )}

      {selectionRequise ? (
        <ErrorState message={t('suivi_saisies.selection_requise')} />
      ) : isLoading ? (
        <Spinner />
      ) : isError ? (
        <ErrorState />
      ) : !data?.sequence ? (
        <Card>
          <EmptyState label={t('suivi_saisies.aucune_sequence_active')} />
        </Card>
      ) : data.lignes.length === 0 ? (
        <Card>
          <EmptyState label={t('suivi_saisies.empty')} />
        </Card>
      ) : (
        <DataTable
          colonnes={colonnes}
          lignes={data.lignes}
          cleLigne={(l) => `${l.classe_id}-${l.matiere_id}`}
          placeholderRecherche={t('suivi_saisies.search')}
          messageVide={t('common.no_results')}
          triDefaut={triLignes}
        />
      )}
    </div>
  )
}
