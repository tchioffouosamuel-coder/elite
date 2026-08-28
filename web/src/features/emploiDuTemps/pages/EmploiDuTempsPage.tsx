import { useEffect, useMemo, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarClock, Copy, ListChecks, Pencil, Plus, Search, Trash2, UserCog, Wand2, X } from 'lucide-react'
import { fetchClasses, fetchMaClasse } from '@/features/classes/api'
import {
  fetchClasseMatieres,
  fetchMatiereClasses,
  fetchTrimestres,
  batchEnseignantAffectations,
  type ClasseMatiere,
} from '@/features/pedagogie/api'
import { fetchPersonnels } from '@/features/personnel/api'
import {
  JOURS,
  createCreneau,
  updateCreneau,
  deleteCreneau,
  copierCreneaux,
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
import type { ApiError } from '@/shared/types/api'

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

  // Ouvert depuis la fiche d'une classe : elle passe son identifiant en
  // paramètre pour que la grille s'affiche directement, sans re-sélection.
  const [searchParams] = useSearchParams()
  const classeDemandee = Number(searchParams.get('classe')) || ''
  const [classeId, setClasseId] = useState<number | ''>(classeDemandee)
  const [formOuvert, setFormOuvert] = useState(false)
  const [generationOuverte, setGenerationOuverte] = useState(false)
  const [creneauEnEdition, setCreneauEnEdition] = useState<Creneau | null>(null)
  const [modeSelection, setModeSelection] = useState(false)
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [copieOuverte, setCopieOuverte] = useState(false)
  const [assignationOuverte, setAssignationOuverte] = useState(false)

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

  // Changer de classe (ou fermer la sélection) vide le panier : une
  // sélection faite sur une grille n'a pas de sens sur une autre.
  useEffect(() => {
    setSelectedIds(new Set())
  }, [classeActive])

  const basculerSelection = (id: number) =>
    setSelectedIds((actuels) => {
      const suivant = new Set(actuels)
      suivant.has(id) ? suivant.delete(id) : suivant.add(id)
      return suivant
    })

  const quitterSelection = () => {
    setModeSelection(false)
    setSelectedIds(new Set())
  }

  // La copie et l'assignation en masse ne portent que sur les créneaux
  // effectivement portés par cette classe : un créneau de tronc commun venu
  // d'ailleurs ne se sélectionne pas ici (cf. règle du bouton supprimer).
  const classeMatiereIdsSelectionnes = useMemo(() => {
    const ids = new Set<number>()
    creneaux?.forEach((c) => {
      if (selectedIds.has(c.id)) ids.add(c.classe_matiere_id)
    })
    return Array.from(ids)
  }, [creneaux, selectedIds])

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
          <div className="flex flex-wrap gap-2">
            {creneaux?.length ? (
              <Button variant="secondary" onClick={() => (modeSelection ? quitterSelection() : setModeSelection(true))}>
                {modeSelection ? <X className="h-4 w-4" /> : <ListChecks className="h-4 w-4" />}
                {t(modeSelection ? 'emploiDuTemps.annuler_selection' : 'emploiDuTemps.selectionner')}
              </Button>
            ) : null}
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

      {modeSelection && selectedIds.size > 0 && (
        <div className="flex flex-wrap items-center gap-2 rounded-2xl border border-gold-200 bg-gold-50 px-4 py-3">
          <span className="text-sm font-semibold text-navy-700">{selectedIds.size}</span>
          <div className="flex flex-1 flex-wrap justify-end gap-2">
            <Button size="sm" variant="secondary" onClick={() => setAssignationOuverte(true)}>
              <UserCog className="h-4 w-4" />
              {t('emploiDuTemps.assigner_enseignant', { count: selectedIds.size })}
            </Button>
            <Button size="sm" variant="secondary" onClick={() => setCopieOuverte(true)}>
              <Copy className="h-4 w-4" />
              {t('emploiDuTemps.copier_vers_classe', { count: selectedIds.size })}
            </Button>
          </div>
        </div>
      )}

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
                            classeId={Number(classeId)}
                            peutGerer={peutGerer}
                            modeSelection={modeSelection}
                            selectionne={selectedIds.has(c.id)}
                            onBasculerSelection={() => basculerSelection(c.id)}
                            onModifier={() => setCreneauEnEdition(c)}
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

      {creneauEnEdition && classeActive && (
        <CreneauModal
          classeId={classeActive}
          matieres={matieres ?? []}
          creneau={creneauEnEdition}
          onClose={() => setCreneauEnEdition(null)}
        />
      )}

      {generationOuverte && classeActive && (
        <GenerationModal classeId={classeActive} onClose={() => setGenerationOuverte(false)} />
      )}

      {copieOuverte && classeActive && (
        <CopierCreneauxModal
          classeId={classeActive}
          creneauIds={Array.from(selectedIds)}
          onClose={() => setCopieOuverte(false)}
          onCopied={() => {
            setCopieOuverte(false)
            quitterSelection()
          }}
        />
      )}

      {assignationOuverte && (
        <AssignerEnseignantModal
          classeMatiereIds={classeMatiereIdsSelectionnes}
          nbCreneaux={selectedIds.size}
          onClose={() => setAssignationOuverte(false)}
          onDone={() => {
            setAssignationOuverte(false)
            quitterSelection()
            queryClient.invalidateQueries({ queryKey: ['emploi-du-temps', classeActive] })
          }}
        />
      )}
    </div>
  )
}

function CelluleCreneau({
  creneau,
  classeId,
  peutGerer,
  modeSelection,
  selectionne,
  onBasculerSelection,
  onModifier,
  onSupprimer,
}: {
  creneau: Creneau
  classeId: number
  peutGerer: boolean
  modeSelection: boolean
  selectionne: boolean
  onBasculerSelection: () => void
  onModifier: () => void
  onSupprimer: () => void
}) {
  const { t } = useTranslation()
  // Un créneau de tronc commun porté par une autre classe n'est ni éditable,
  // ni supprimable, ni sélectionnable en masse depuis cette grille-ci.
  const gerable = peutGerer && creneau.classe_id === classeId

  return (
    <div
      className={`group relative mb-1 rounded-lg bg-gold-50 px-2 py-1.5 ring-1 ring-gold-200 ${
        modeSelection && gerable ? 'cursor-pointer pl-7' : ''
      } ${selectionne ? 'bg-gold-100 ring-2 ring-gold-400' : ''}`}
      onClick={modeSelection && gerable ? onBasculerSelection : undefined}
    >
      {modeSelection && gerable && (
        <input
          type="checkbox"
          checked={selectionne}
          onChange={onBasculerSelection}
          onClick={(e) => e.stopPropagation()}
          className="absolute left-1.5 top-1.5 h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
        />
      )}

      <p className="truncate text-xs font-bold text-navy-800">{creneau.matiere}</p>
      <p className="truncate text-[11px] text-navy-500">
        {creneau.heure_debut}–{creneau.heure_fin}
        {creneau.salle ? ` · ${creneau.salle}` : ''}
      </p>
      {creneau.enseignant && <p className="truncate text-[11px] text-navy-400">{creneau.enseignant}</p>}

      {creneau.tronc_commun && (
        <p
          className="mt-1 truncate text-[10px] font-semibold uppercase tracking-wide text-gold-700"
          title={creneau.classes_associees.map((c) => c.nom).join(' · ')}
        >
          {t('emploiDuTemps.tronc_commun_badge')} · {creneau.classes_associees.length + 1}
        </p>
      )}

      {/* Sur la grille d'une classe associée, le créneau est porté ailleurs :
          le supprimer d'ici reviendrait à l'ôter à tout le groupe. */}
      {creneau.tronc_commun && creneau.classe_id !== classeId && (
        <p className="truncate text-[10px] italic text-navy-400">
          {t('emploiDuTemps.tronc_commun_porte_par', { classe: creneau.classe ?? '' })}
        </p>
      )}

      {gerable && !modeSelection && (
        <div className="absolute right-1 top-1 flex gap-0.5">
          <button
            onClick={onModifier}
            title={t('emploiDuTemps.modifier_creneau_title')}
            // Toujours présent plutôt qu'au survol : sur écran tactile un contrôle
            // qui n'apparaît qu'au hover est inatteignable.
            className="rounded p-0.5 text-navy-300 opacity-60 transition-opacity hover:bg-white hover:text-navy-700 hover:opacity-100 focus-visible:opacity-100"
          >
            <Pencil className="h-3.5 w-3.5" />
          </button>
          <button
            onClick={onSupprimer}
            title={t('emploiDuTemps.supprimer_creneau_title')}
            className="rounded p-0.5 text-navy-300 opacity-60 transition-opacity hover:bg-white hover:text-red-500 hover:opacity-100 focus-visible:opacity-100"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </button>
        </div>
      )}
    </div>
  )
}

function CreneauModal({
  classeId,
  matieres,
  creneau,
  onClose,
}: {
  classeId: number
  matieres: ClasseMatiere[]
  /** Présent en mode édition : préremplit le formulaire et bascule la mutation sur `updateCreneau`. */
  creneau?: Creneau
  onClose: () => void
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const enEdition = !!creneau
  const [form, setForm] = useState({
    classe_matiere_id: creneau?.classe_matiere_id ?? matieres[0]?.id ?? 0,
    jour: creneau?.jour ?? 1,
    heure_debut: creneau?.heure_debut ?? '08:00',
    heure_fin: creneau?.heure_fin ?? '10:00',
    salle: creneau?.salle ?? '',
  })
  const [rechercheClasse, setRechercheClasse] = useState('')

  /*
   * Tronc commun : les classes qui rejoignent celle-ci sur ce créneau. Rien
   * de coché = cours ordinaire ; le regroupement se déduit de la sélection,
   * il n'y a pas de case « tronc commun » à maintenir en accord avec elle.
   *
   * En édition, on part des classes déjà associées au créneau plutôt que de
   * la suggestion automatique — sans quoi rouvrir le formulaire les
   * décocherait toutes le temps que la requête des attributions réponde.
   */
  const [associees, setAssociees] = useState<number[]>(creneau?.classes_associees.map((c) => c.id) ?? [])
  const matiereInitiale = useRef(form.classe_matiere_id)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })

  const affectationChoisie = matieres.find((m) => m.id === form.classe_matiere_id)

  /*
   * Tronc commun prévisionnel.
   *
   * Un même enseignant qui donne la même matière dans plusieurs classes les
   * réunit presque toujours sur le même créneau — c'est même la raison d'être
   * du tronc commun. Plutôt que de laisser l'utilisateur recocher ces classes
   * à la main à chaque créneau, on les lit dans les attributions de matières
   * et on les propose déjà cochées.
   *
   * Ce n'est qu'une proposition : les cases restent décochables, et une classe
   * sans enseignant assigné n'entre pas dans le calcul — deux affectations
   * vides ne prouvent pas qu'il s'agit du même professeur.
   */
  const { data: classesDeLaMatiere } = useQuery({
    queryKey: ['matiere-classes', affectationChoisie?.matiere.id],
    queryFn: () => fetchMatiereClasses(Number(affectationChoisie?.matiere.id)),
    enabled: !!affectationChoisie?.matiere.id,
  })

  const enseignantId = affectationChoisie?.enseignant?.id ?? null

  const suggerees = useMemo(() => {
    if (enseignantId === null) return []

    return (classesDeLaMatiere ?? [])
      .filter((ligne) => ligne.enseignant?.id === enseignantId && ligne.classe && ligne.classe.id !== classeId)
      .map((ligne) => ligne.classe!.id)
  }, [classesDeLaMatiere, enseignantId, classeId])

  // La suggestion se recalcule à chaque changement de matière : garder les
  // cases d'une matière précédente donnerait un regroupement faux. Tant que
  // la matière n'a pas changé depuis l'ouverture, on laisse le tronc commun
  // déjà en place au lieu de l'écraser par la suggestion (cf. ci-dessus).
  useEffect(() => {
    if (enEdition && form.classe_matiere_id === matiereInitiale.current) return
    setAssociees(suggerees)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [suggerees])

  const autresClasses = (classes ?? []).filter((c) => c.id !== classeId)
  const terme = rechercheClasse.trim().toLowerCase()
  const classesAffichees = terme === ''
    ? autresClasses
    : autresClasses.filter((c) => c.nom.toLowerCase().includes(terme))

  const basculer = (id: number) =>
    setAssociees((actuelles) =>
      actuelles.includes(id) ? actuelles.filter((c) => c !== id) : [...actuelles, id],
    )

  const creation = useMutation({
    mutationFn: () => {
      const payload = { ...form, salle: form.salle || null, classes_associees: associees }
      return creneau ? updateCreneau(classeId, creneau.id, payload) : createCreneau(classeId, payload)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['emploi-du-temps', classeId] })
      succes(t(enEdition ? 'emploiDuTemps.creneau_modifie' : 'emploiDuTemps.creneau_ajoute'))
      onClose()
    },
    onError: (e: { message?: string }) => erreur(e.message ?? t('emploiDuTemps.enregistrement_impossible')),
  })

  return (
    <Modal title={t(enEdition ? 'emploiDuTemps.modifier_creneau' : 'emploiDuTemps.nouveau_creneau')} onClose={onClose}>
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
              {m.matiere.nom_en ? `${m.matiere.nom} / ${m.matiere.nom_en}` : m.matiere.nom}
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

        {autresClasses.length > 0 && (
          <div className="flex flex-col gap-2">
            <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
              {t('emploiDuTemps.tronc_commun_label')}
            </span>
            <p className="text-xs text-navy-400">{t('emploiDuTemps.tronc_commun_aide')}</p>

            {suggerees.length > 0 && (
              <p className="rounded-lg bg-navy-50 px-3 py-2 text-xs text-navy-600">
                {t('emploiDuTemps.tronc_commun_suggere', {
                  count: suggerees.length,
                  enseignant: affectationChoisie?.enseignant?.nom_complet ?? '',
                })}
              </p>
            )}

            <Input
              value={rechercheClasse}
              onChange={(e) => setRechercheClasse(e.target.value)}
              placeholder={t('emploiDuTemps.tronc_commun_recherche')}
              icon={Search}
            />

            <div className="flex max-h-40 flex-col gap-1 overflow-y-auto rounded-xl border border-navy-100 p-2">
              {classesAffichees.length === 0 ? (
                <p className="px-2 py-1 text-sm text-navy-400">{t('common.no_results')}</p>
              ) : (
                classesAffichees.map((c) => (
                  <label key={c.id} className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm hover:bg-navy-50">
                    <input
                      type="checkbox"
                      checked={associees.includes(c.id)}
                      onChange={() => basculer(c.id)}
                      className="h-4 w-4 rounded border-navy-200"
                    />
                    <span className="text-navy-700">{c.nom}</span>
                    {suggerees.includes(c.id) && (
                      <span className="text-[10px] font-semibold uppercase tracking-wide text-navy-400">
                        {t('emploiDuTemps.tronc_commun_meme_enseignant')}
                      </span>
                    )}
                  </label>
                ))
              )}
            </div>
            {associees.length > 0 && (
              <p className="rounded-lg bg-gold-50 px-3 py-2 text-xs text-gold-800">
                {t('emploiDuTemps.tronc_commun_resume', { count: associees.length + 1 })}
              </p>
            )}
          </div>
        )}

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

/**
 * Copie une sélection de créneaux vers une seule classe de destination, sans
 * reprendre l'enseignant : la classe cible garde ou choisit son propre
 * professeur pour la matière (cf. `EmploiDuTempsService::copierVers` côté
 * API). Une seule classe cible à la fois — contrairement à la copie
 * d'affectations de matières, l'horaire copié peut chevaucher un cours
 * existant et se voit alors ignoré plutôt que forcé.
 */
function CopierCreneauxModal({
  classeId,
  creneauIds,
  onClose,
  onCopied,
}: {
  classeId: number
  creneauIds: number[]
  onClose: () => void
  onCopied: () => void
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [cibleId, setCibleId] = useState<number | ''>('')
  const [envoi, setEnvoi] = useState(false)

  const { data: classes, isLoading } = useQuery({ queryKey: ['classes', 'copier-creneaux'], queryFn: () => fetchClasses() })

  const classesDisponibles = (classes ?? []).filter((c) => c.id !== classeId)
  const terme = recherche.trim().toLowerCase()
  const classesFiltrees = terme === '' ? classesDisponibles : classesDisponibles.filter((c) => c.nom.toLowerCase().includes(terme))

  const copier = async () => {
    if (cibleId === '') {
      erreur(t('emploiDuTemps.choisir_classe_destination'))
      return
    }

    setEnvoi(true)
    try {
      const { copies, ignores } = await copierCreneaux(classeId, { creneau_ids: creneauIds, classe_id: Number(cibleId) })
      succes(
        ignores > 0
          ? t('emploiDuTemps.copier_creneaux_resultat_avec_ignores', { copies, ignores })
          : t('emploiDuTemps.copier_creneaux_resultat', { copies }),
      )
      // Portée large : la classe de destination n'a pas forcément sa propre
      // grille déjà chargée, mais elle doit voir la copie si on y navigue ensuite.
      queryClient.invalidateQueries({ queryKey: ['emploi-du-temps'] })
      onCopied()
    } catch (err) {
      erreur((err as ApiError).message ?? t('emploiDuTemps.copier_impossible'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={t('emploiDuTemps.copier_creneaux_titre', { count: creneauIds.length })} onClose={onClose}>
      <div className="flex flex-col gap-3">
        <p className="text-xs text-navy-400">{t('emploiDuTemps.copier_creneaux_aide')}</p>

        <Input
          value={recherche}
          onChange={(e) => setRecherche(e.target.value)}
          placeholder={t('emploiDuTemps.tronc_commun_recherche')}
          icon={Search}
          autoFocus
        />

        {isLoading ? (
          <Spinner />
        ) : classesFiltrees.length === 0 ? (
          <p className="rounded-xl border border-navy-100 bg-cream-50 px-3.5 py-2.5 text-sm text-navy-400">
            {t('common.no_results')}
          </p>
        ) : (
          <div className="flex max-h-64 flex-col divide-y divide-navy-50 overflow-y-auto rounded-xl border border-navy-100">
            {classesFiltrees.map((c) => (
              <label key={c.id} className="flex cursor-pointer items-center gap-2.5 px-3 py-2 text-sm hover:bg-cream-50">
                <input
                  type="radio"
                  name="creneaux-classe-cible"
                  checked={cibleId === c.id}
                  onChange={() => setCibleId(c.id)}
                  className="h-4 w-4 flex-none border-navy-300 text-gold-600 focus:ring-gold-500"
                />
                <span className="text-navy-800">{c.nom}</span>
              </label>
            ))}
          </div>
        )}

        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button type="button" onClick={copier} disabled={envoi || cibleId === ''}>
            <Copy className="h-4 w-4" />
            {t('emploiDuTemps.copier_bouton')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}

/**
 * Change l'enseignant de plusieurs créneaux d'un coup. L'enseignant vit sur
 * l'affectation matière-classe (`ClasseMatiere`), pas sur le créneau : la
 * sélection est donc réduite aux affectations distinctes qu'elle couvre
 * avant d'appeler le même point d'entrée que la copie en masse des
 * affectations (cf. `AffectationsTab`), pour ne pas dupliquer cette logique.
 * « Aucun enseignant » reste une option à part entière pour détacher un
 * agent affecté par erreur.
 */
function AssignerEnseignantModal({
  classeMatiereIds,
  nbCreneaux,
  onClose,
  onDone,
}: {
  classeMatiereIds: number[]
  nbCreneaux: number
  onClose: () => void
  onDone: () => void
}) {
  const { t } = useTranslation()
  const [personnelId, setPersonnelId] = useState<number | '' | 'aucun'>('')
  const [envoi, setEnvoi] = useState(false)

  const { data: personnels, isLoading } = useQuery({
    queryKey: ['personnels', 'edt-batch'],
    queryFn: () => fetchPersonnels({ per_page: 500 }),
  })

  const valider = async () => {
    if (personnelId === '') return

    setEnvoi(true)
    try {
      const { modifiees } = await batchEnseignantAffectations(
        classeMatiereIds,
        personnelId === 'aucun' ? null : Number(personnelId),
      )
      succes(t('emploiDuTemps.assigner_enseignant_resultat', { count: modifiees }))
      onDone()
    } catch (err) {
      erreur((err as ApiError).message ?? t('emploiDuTemps.assigner_enseignant_impossible'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modal title={t('emploiDuTemps.assigner_enseignant_titre', { count: nbCreneaux })} onClose={onClose}>
      <div className="flex flex-col gap-4">
        <p className="text-sm text-navy-500">
          {t('emploiDuTemps.assigner_enseignant_aide', { count: classeMatiereIds.length })}
        </p>

        {isLoading ? (
          <Spinner />
        ) : (
          <Select
            label={t('pedagogie.enseignant')}
            value={personnelId}
            onChange={(e) => {
              const brut = e.target.value
              setPersonnelId(brut === '' ? '' : brut === 'aucun' ? 'aucun' : Number(brut))
            }}
          >
            <option value="">—</option>
            <option value="aucun">{t('emploiDuTemps.aucun_enseignant_option')}</option>
            {personnels?.map((personnel) => (
              <option key={personnel.id} value={personnel.id}>
                {personnel.nom_complet}
              </option>
            ))}
          </Select>
        )}

        <div className="mt-1 flex justify-end gap-2">
          <Button variant="secondary" onClick={onClose}>
            {t('common.cancel')}
          </Button>
          <Button disabled={personnelId === '' || envoi} onClick={valider}>
            {t('common.save')}
          </Button>
        </div>
      </div>
    </Modal>
  )
}
