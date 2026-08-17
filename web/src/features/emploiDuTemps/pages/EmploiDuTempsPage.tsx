import { useEffect, useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarClock, Plus, Trash2, Wand2 } from 'lucide-react'
import { fetchClasses, fetchMaClasse } from '@/features/classes/api'
import { fetchClasseMatieres, fetchTrimestres } from '@/features/pedagogie/api'
import {
  JOURS,
  createCreneau,
  deleteCreneau,
  fetchEmploiDuTemps,
  genererSeances,
  type Creneau,
} from '@/features/emploiDuTemps/api'
import { useAuthStore } from '@/shared/store/authStore'
import { estSecondaire } from '@/shared/lib/ecole'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Input, Select } from '@/shared/ui/Field'
import { Modal } from '@/shared/ui/Modal'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'

/** Bornes de la grille : la journée scolaire va de 7 h à 18 h. */
const HEURE_MIN = 7
const HEURE_MAX = 18

function enMinutes(heure: string): number {
  const [h, m] = heure.split(':').map(Number)
  return h * 60 + m
}

export function EmploiDuTempsPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const estEnseignant = useAuthStore((s) => s.user?.est_enseignant ?? false)
  const peutGerer = can('emploi_du_temps.manage')
  const queryClient = useQueryClient()

  // Au primaire et à la maternelle, un enseignant est titulaire d'une seule
  // classe : son propre emploi du temps directement, pas un sélecteur à parcourir.
  const restreintATitulaire = estEnseignant && !estSecondaire()

  const [classeId, setClasseId] = useState<number | ''>('')
  const [formOuvert, setFormOuvert] = useState(false)
  const [generationOuverte, setGenerationOuverte] = useState(false)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses(), enabled: !restreintATitulaire })
  const { data: maClasse, isLoading: maClasseEnChargement } = useQuery({
    queryKey: ['ma-classe'],
    queryFn: fetchMaClasse,
    enabled: restreintATitulaire,
  })

  useEffect(() => {
    if (restreintATitulaire && maClasse && classeId === '') {
      setClasseId(maClasse.id)
    }
  }, [restreintATitulaire, maClasse, classeId])

  const classeActive = classeId ? Number(classeId) : null

  const { data: creneaux, isLoading } = useQuery({
    queryKey: ['emploi-du-temps', classeActive],
    queryFn: () => fetchEmploiDuTemps(classeActive!),
    enabled: classeActive !== null,
  })

  const { data: matieres } = useQuery({
    queryKey: ['classe-matieres', classeActive],
    queryFn: () => fetchClasseMatieres(classeActive!),
    enabled: classeActive !== null,
  })

  const suppression = useMutation({
    mutationFn: (id: number) => deleteCreneau(classeActive!, id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['emploi-du-temps', classeActive] })
      succes(t('emploiDuTemps.timeslot_deleted'))
    },
    onError: (e: { message?: string }) => erreur(e.message ?? t('emploiDuTemps.deletion_failed')),
  })

  const supprimerCreneau = async (creneau: Creneau) => {
    const confirme = await confirmerSuppression(
      t('emploiDuTemps.creneau_delete_quoi', { matiere: creneau.matiere }),
      t('emploiDuTemps.creneau_delete_precision'),
    )
    if (confirme) suppression.mutate(creneau.id)
  }

  // La grille se cale sur les heures réellement utilisées, arrondies à l'heure,
  // pour ne pas afficher une colonne de créneaux vides toute la journée.
  const plage = useMemo(() => {
    if (!creneaux?.length) return [] as number[]
    const debut = Math.min(...creneaux.map((c) => Math.floor(enMinutes(c.heure_debut) / 60)), HEURE_MAX)
    const fin = Math.max(...creneaux.map((c) => Math.ceil(enMinutes(c.heure_fin) / 60)), HEURE_MIN)
    return Array.from({ length: Math.max(fin - debut, 1) }, (_, i) => debut + i)
  }, [creneaux])

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <CalendarClock className="h-6 w-6 text-gold-500" />
          <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('emploiDuTemps.title')}</h1>
        </div>
        {peutGerer && classeActive && (
          <div className="flex gap-2">
            <Button variant="secondary" onClick={() => setGenerationOuverte(true)}>
              <Wand2 className="h-4 w-4" />
              {t('emploiDuTemps.generer_seances')}
            </Button>
            <Button onClick={() => setFormOuvert(true)}>
              <Plus className="h-4 w-4" />
              {t('emploiDuTemps.ajouter_creneau')}
            </Button>
          </div>
        )}
      </div>

      {restreintATitulaire ? (
        maClasse && (
          <div className="flex flex-col gap-1.5">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('emploiDuTemps.classe_label')}</span>
            <span className="text-sm font-semibold text-navy-800">{maClasse.nom}</span>
          </div>
        )
      ) : (
        <Select
          label={t('emploiDuTemps.classe_label')}
          value={classeId}
          onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
          className="max-w-xs"
        >
          <option value="">{t('emploiDuTemps.select_classe_placeholder')}</option>
          {classes?.map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </Select>
      )}

      {restreintATitulaire && maClasseEnChargement ? (
        <Spinner />
      ) : restreintATitulaire && !maClasse ? (
        <Card>
          <EmptyState label={t('classes.aucune_classe_confiee')} />
        </Card>
      ) : !classeActive ? (
        <Card>
          <EmptyState label={t('emploiDuTemps.choisir_classe_hint')} />
        </Card>
      ) : isLoading ? (
        <Spinner />
      ) : !creneaux?.length ? (
        <Card>
          <EmptyState label={t('emploiDuTemps.empty_creneaux')} />
        </Card>
      ) : (
        <div className="overflow-x-auto rounded-2xl border border-navy-100 bg-white">
          <table className="w-full min-w-[820px] border-collapse text-sm">
            <thead>
              <tr>
                <th className="w-20 border-b border-navy-100 px-2 py-2.5 text-xs font-bold uppercase tracking-wide text-navy-400">
                  {t('emploiDuTemps.heure_col')}
                </th>
                {JOURS.map((jour) => (
                  <th
                    key={jour.valeur}
                    className="border-b border-l border-navy-100 px-2 py-2.5 text-xs font-bold uppercase tracking-wide text-navy-500"
                  >
                    {t(`emploiDuTemps.jours.${jour.libelle}`)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {plage.map((heure) => (
                <tr key={heure}>
                  <td className="border-b border-navy-50 px-2 py-1.5 text-center text-xs font-semibold text-navy-400">
                    {String(heure).padStart(2, '0')}:00
                  </td>
                  {JOURS.map((jour) => {
                    // Le créneau n'est dessiné que sur sa ligne de début : le
                    // répéter sur chaque heure couverte le ferait apparaître en
                    // double pour un cours de deux heures.
                    const duCreneau = creneaux.filter(
                      (c) =>
                        c.jour === jour.valeur &&
                        enMinutes(c.heure_debut) >= heure * 60 &&
                        enMinutes(c.heure_debut) < (heure + 1) * 60,
                    )

                    return (
                      <td key={jour.valeur} className="border-b border-l border-navy-50 p-1 align-top">
                        {duCreneau.map((c) => (
                          <CelluleCreneau
                            key={c.id}
                            creneau={c}
                            peutGerer={peutGerer}
                            onSupprimer={() => supprimerCreneau(c)}
                          />
                        ))}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {formOuvert && classeActive && (
        <CreneauModal classeId={classeActive} matieres={matieres ?? []} onClose={() => setFormOuvert(false)} />
      )}

      {generationOuverte && classeActive && (
        <GenerationModal classeId={classeActive} onClose={() => setGenerationOuverte(false)} />
      )}
    </div>
  )
}

function CelluleCreneau({
  creneau,
  peutGerer,
  onSupprimer,
}: {
  creneau: Creneau
  peutGerer: boolean
  onSupprimer: () => void
}) {
  const { t } = useTranslation()
  return (
    <div className="group relative mb-1 rounded-lg bg-gold-50 px-2 py-1.5 ring-1 ring-gold-200">
      <p className="truncate text-xs font-bold text-navy-800">{creneau.matiere}</p>
      <p className="truncate text-[11px] text-navy-500">
        {creneau.heure_debut}–{creneau.heure_fin}
        {creneau.salle ? ` · ${creneau.salle}` : ''}
      </p>
      {creneau.enseignant && <p className="truncate text-[11px] text-navy-400">{creneau.enseignant}</p>}
      {peutGerer && (
        <button
          onClick={onSupprimer}
          title={t('emploiDuTemps.supprimer_creneau_title')}
          // Toujours présent plutôt qu'au survol : sur écran tactile un contrôle
          // qui n'apparaît qu'au hover est inatteignable.
          className="absolute right-1 top-1 rounded p-0.5 text-navy-300 opacity-60 transition-opacity hover:bg-white hover:text-red-500 hover:opacity-100 focus-visible:opacity-100"
        >
          <Trash2 className="h-3.5 w-3.5" />
        </button>
      )}
    </div>
  )
}

function CreneauModal({
  classeId,
  matieres,
  onClose,
}: {
  classeId: number
  matieres: { id: number; matiere: { nom: string } }[]
  onClose: () => void
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [form, setForm] = useState({
    classe_matiere_id: matieres[0]?.id ?? 0,
    jour: 1,
    heure_debut: '08:00',
    heure_fin: '10:00',
    salle: '',
  })

  const creation = useMutation({
    mutationFn: () => createCreneau(classeId, { ...form, salle: form.salle || null }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['emploi-du-temps', classeId] })
      succes(t('emploiDuTemps.creneau_ajoute'))
      onClose()
    },
    onError: (e: { message?: string }) => erreur(e.message ?? t('emploiDuTemps.enregistrement_impossible')),
  })

  return (
    <Modal title={t('emploiDuTemps.nouveau_creneau')} onClose={onClose}>
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault()
          creation.mutate()
        }}
      >
        <Select
          label={t('emploiDuTemps.matiere_label')}
          value={form.classe_matiere_id}
          onChange={(e) => setForm({ ...form, classe_matiere_id: Number(e.target.value) })}
          required
        >
          {matieres.map((m) => (
            <option key={m.id} value={m.id}>
              {m.matiere.nom}
            </option>
          ))}
        </Select>

        <Select label={t('emploiDuTemps.jour_label')} value={form.jour} onChange={(e) => setForm({ ...form, jour: Number(e.target.value) })}>
          {JOURS.map((j) => (
            <option key={j.valeur} value={j.valeur}>
              {t(`emploiDuTemps.jours.${j.libelle}`)}
            </option>
          ))}
        </Select>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('emploiDuTemps.debut_label')}
            type="time"
            value={form.heure_debut}
            onChange={(e) => setForm({ ...form, heure_debut: e.target.value })}
            required
          />
          <Input
            label={t('emploiDuTemps.fin_label')}
            type="time"
            value={form.heure_fin}
            onChange={(e) => setForm({ ...form, heure_fin: e.target.value })}
            required
          />
        </div>

        <Input label={t('emploiDuTemps.salle_label')} value={form.salle} onChange={(e) => setForm({ ...form, salle: e.target.value })} />

        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" disabled={creation.isPending || matieres.length === 0}>
            {t('common.save')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

function GenerationModal({ classeId, onClose }: { classeId: number; onClose: () => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { data: trimestres } = useQuery({ queryKey: ['trimestres'], queryFn: fetchTrimestres })
  const [form, setForm] = useState({ date_debut: '', date_fin: '', trimestre_id: '' as number | '' })
  const [resultat, setResultat] = useState<string | null>(null)

  const generation = useMutation({
    mutationFn: () =>
      genererSeances(classeId, {
        date_debut: form.date_debut,
        date_fin: form.date_fin,
        trimestre_id: form.trimestre_id ? Number(form.trimestre_id) : undefined,
      }),
    onSuccess: (data) => {
      setResultat(t('emploiDuTemps.seances_creees', { count: data.creees }))
      queryClient.invalidateQueries({ queryKey: ['seances'] })
      succes(data.creees > 0 ? t('emploiDuTemps.seances_generees', { count: data.creees }) : t('emploiDuTemps.aucune_nouvelle_seance'))
    },
    onError: (e: { message?: string }) => erreur(e.message ?? t('emploiDuTemps.generation_impossible')),
  })

  return (
    <Modal title={t('emploiDuTemps.generer_seances')} onClose={onClose}>
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault()
          generation.mutate()
        }}
      >
        <p className="text-sm text-navy-500">{t('emploiDuTemps.generation_hint')}</p>

        <div className="grid grid-cols-2 gap-3">
          <Input
            label={t('emploiDuTemps.du')}
            type="date"
            value={form.date_debut}
            onChange={(e) => setForm({ ...form, date_debut: e.target.value })}
            required
          />
          <Input
            label={t('emploiDuTemps.au')}
            type="date"
            value={form.date_fin}
            onChange={(e) => setForm({ ...form, date_fin: e.target.value })}
            required
          />
        </div>

        <Select
          label={t('notes.trimestre')}
          value={form.trimestre_id}
          onChange={(e) => setForm({ ...form, trimestre_id: e.target.value ? Number(e.target.value) : '' })}
        >
          <option value="">{t('niveauxGlobaux.aucun')}</option>
          {trimestres?.map((trimestre) => (
            <option key={trimestre.id} value={trimestre.id}>
              {trimestre.libelle}
            </option>
          ))}
        </Select>

        {resultat && <p className="text-sm font-semibold text-green-600">{resultat}</p>}

        <div className="flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.close')}
          </Button>
          <Button type="submit" disabled={generation.isPending}>
            {t('emploiDuTemps.generer')}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
