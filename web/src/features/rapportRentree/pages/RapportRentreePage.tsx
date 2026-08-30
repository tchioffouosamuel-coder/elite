import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm } from 'react-hook-form'
import { ClipboardList, Cookie, Download, Pencil, Plus, ShieldAlert, Trash2, Users } from 'lucide-react'
import {
  creerActiviteRentree,
  creerVenteDenree,
  creerVisiteAutorite,
  definirTexteRentree,
  fetchActivitesRentree,
  fetchTextesRentree,
  fetchVentesDenrees,
  fetchVisitesAutorites,
  modifierActiviteRentree,
  modifierVenteDenree,
  modifierVisiteAutorite,
  supprimerActiviteRentree,
  supprimerVenteDenree,
  supprimerVisiteAutorite,
  type ActiviteRentree,
  type ActiviteRentreePayload,
  type CategorieActivite,
  type RubriqueTexteRentree,
  type VenteDenree,
  type VenteDenreePayload,
  type VisiteAutorite,
  type VisiteAutoritePayload,
} from '@/features/rapportRentree/api'
import { fetchAnneesScolaires } from '@/features/session/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { PageHeader } from '@/shared/ui/PageHeader'
import { DataTable, type Colonne } from '@/shared/ui/DataTable'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { Modal } from '@/shared/ui/Modal'
import { Tabs } from '@/shared/ui/Tabs'
import { useAuthStore } from '@/shared/store/authStore'
import { confirmerSuppression, erreur, succes } from '@/shared/lib/alertes'
import { ouvrirDocument } from '@/shared/lib/download'
import type { ApiError } from '@/shared/types/api'

const CATEGORIES: { value: CategorieActivite; label: string }[] = [
  { value: 'pedagogique', label: 'Pédagogie' },
  { value: 'eps', label: 'EPS' },
  { value: 'fenassco', label: 'FENASSCO' },
]

const RUBRIQUES_SECURITE: { value: RubriqueTexteRentree; label: string }[] = [
  { value: 'securite_cloture', label: 'Clôture' },
  { value: 'securite_detecteur_metaux', label: 'Détecteur de métaux' },
  { value: 'securite_controle_armes', label: 'Contrôle des armes blanches' },
  { value: 'securite_surveillance_pauses', label: 'Surveillance des pauses' },
  { value: 'securite_autres_mesures', label: 'Autres mesures' },
]

const RUBRIQUES_AUTRES: { value: RubriqueTexteRentree; label: string }[] = [
  { value: 'probleme_infrastructure_maternelle', label: "Problèmes d'infrastructure (maternelle)" },
  { value: 'problemes_fonctionnement', label: 'Problèmes rencontrés dans le fonctionnement' },
  { value: 'resolutions_conseil_maitres', label: 'Résolutions des conseils des maîtres' },
  { value: 'gouvernements_enfants', label: "Gouvernements d'enfants" },
  { value: 'irr', label: 'IRR' },
  { value: 'evenements_socioculturels', label: 'Événements socio-culturels' },
  { value: 'fetes_nationales', label: 'Fêtes nationales' },
  { value: 'doleances', label: 'Doléances' },
  { value: 'conclusion_generale', label: 'Conclusion générale' },
]

export function RapportRentreePage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queryClient = useQueryClient()

  const { data: annees } = useQuery({ queryKey: ['annees-scolaires'], queryFn: fetchAnneesScolaires })
  const anneeActive = annees?.find((a) => a.is_active) ?? annees?.[0]

  const [categorieActive, setCategorieActive] = useState<CategorieActivite>('pedagogique')

  const { data: visites, isLoading: chargeVisites } = useQuery({
    queryKey: ['visites-autorites', anneeActive?.id],
    queryFn: () => fetchVisitesAutorites(anneeActive?.id),
    enabled: !!anneeActive,
  })
  const { data: activites, isLoading: chargeActivites } = useQuery({
    queryKey: ['activites-rentree', anneeActive?.id, categorieActive],
    queryFn: () => fetchActivitesRentree(anneeActive?.id, categorieActive),
    enabled: !!anneeActive,
  })
  const { data: ventes, isLoading: chargeVentes } = useQuery({
    queryKey: ['ventes-denrees', anneeActive?.id],
    queryFn: () => fetchVentesDenrees(anneeActive?.id),
    enabled: !!anneeActive,
  })
  const { data: textes } = useQuery({
    queryKey: ['rapport-rentree-textes', anneeActive?.id],
    queryFn: () => fetchTextesRentree(anneeActive?.id),
    enabled: !!anneeActive,
  })

  const [visiteEnEdition, setVisiteEnEdition] = useState<VisiteAutorite | null>(null)
  const [showVisiteForm, setShowVisiteForm] = useState(false)
  const [activiteEnEdition, setActiviteEnEdition] = useState<ActiviteRentree | null>(null)
  const [showActiviteForm, setShowActiviteForm] = useState(false)
  const [venteEnEdition, setVenteEnEdition] = useState<VenteDenree | null>(null)
  const [showVenteForm, setShowVenteForm] = useState(false)
  const [exportEnCours, setExportEnCours] = useState(false)

  const invalidate = (cle: string) => queryClient.invalidateQueries({ queryKey: [cle] })

  const exporterPdf = async () => {
    setExportEnCours(true)
    try {
      await ouvrirDocument(
        '/rapport-rentree/complet/pdf',
        anneeActive ? { annee_scolaire_id: anneeActive.id } : undefined,
        undefined,
        t('rapportRentree.title'),
      )
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setExportEnCours(false)
    }
  }

  const peutModifier = can('rapport_rentree.manage')

  const supprimerVisite = async (visite: VisiteAutorite) => {
    const confirme = await confirmerSuppression(t('rapportRentree.confirm_delete', { nom: visite.qualite_autorite }))
    if (!confirme) return
    try {
      await supprimerVisiteAutorite(visite.id)
      succes(t('rapportRentree.deleted'))
      invalidate('visites-autorites')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerActivite = async (activite: ActiviteRentree) => {
    const confirme = await confirmerSuppression(t('rapportRentree.confirm_delete', { nom: activite.activite }))
    if (!confirme) return
    try {
      await supprimerActiviteRentree(activite.id)
      succes(t('rapportRentree.deleted'))
      invalidate('activites-rentree')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerVente = async (vente: VenteDenree) => {
    const confirme = await confirmerSuppression(t('rapportRentree.confirm_delete', { nom: vente.nature }))
    if (!confirme) return
    try {
      await supprimerVenteDenree(vente.id)
      succes(t('rapportRentree.deleted'))
      invalidate('ventes-denrees')
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const colonnesVisites: Colonne<VisiteAutorite>[] = [
    { cle: 'date', entete: t('rapportRentree.date_col'), valeur: (v) => v.date_visite, cellule: (v) => v.date_visite },
    { cle: 'qualite', entete: t('rapportRentree.qualite_col'), valeur: (v) => v.qualite_autorite, cellule: (v) => <span className="font-semibold text-navy-900">{v.qualite_autorite}</span> },
    { cle: 'nature', entete: t('rapportRentree.nature_col'), valeur: (v) => v.nature_visite, cellule: (v) => v.nature_visite ?? '—' },
    { cle: 'objectifs', entete: t('rapportRentree.objectifs_col'), valeur: (v) => v.objectifs, cellule: (v) => v.objectifs ?? '—', masquerMobile: true },
    { cle: 'observations', entete: t('rapportRentree.observations_col'), valeur: (v) => v.observations, cellule: (v) => v.observations ?? '—', masquerMobile: true },
    ...(peutModifier
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (v: VisiteAutorite) => (
              <div className="flex items-center gap-1">
                <button title={t('common.edit')} onClick={() => { setVisiteEnEdition(v); setShowVisiteForm(true) }} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                  <Pencil className="h-4 w-4" />
                </button>
                <button title={t('common.delete')} onClick={() => supprimerVisite(v)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-600">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<VisiteAutorite>,
        ]
      : []),
  ]

  const colonnesActivites: Colonne<ActiviteRentree>[] = [
    { cle: 'activite', entete: t('rapportRentree.activite_col'), valeur: (a) => a.activite, cellule: (a) => <span className="font-semibold text-navy-900">{a.activite}</span> },
    { cle: 'prevues', entete: t('rapportRentree.prevues_col'), valeur: (a) => a.prevues, cellule: (a) => a.prevues ?? '—' },
    { cle: 'faites', entete: t('rapportRentree.faites_col'), valeur: (a) => a.faites, cellule: (a) => a.faites ?? '—' },
    { cle: 'taux', entete: t('rapportRentree.taux_col'), valeur: (a) => a.taux_affichage, cellule: (a) => (a.taux_affichage !== null ? <span className="font-semibold tabular-nums">{a.taux_affichage}%</span> : '—') },
    { cle: 'observations', entete: t('rapportRentree.observations_col'), valeur: (a) => a.observations, cellule: (a) => a.observations ?? '—', masquerMobile: true },
    ...(peutModifier
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (a: ActiviteRentree) => (
              <div className="flex items-center gap-1">
                <button title={t('common.edit')} onClick={() => { setActiviteEnEdition(a); setShowActiviteForm(true) }} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                  <Pencil className="h-4 w-4" />
                </button>
                <button title={t('common.delete')} onClick={() => supprimerActivite(a)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-600">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<ActiviteRentree>,
        ]
      : []),
  ]

  const colonnesVentes: Colonne<VenteDenree>[] = [
    { cle: 'nature', entete: t('rapportRentree.nature_denree_col'), valeur: (v) => v.nature, cellule: (v) => <span className="font-semibold text-navy-900">{v.nature}</span> },
    { cle: 'vendeur', entete: t('rapportRentree.vendeur_col'), valeur: (v) => v.vendeur_nom, cellule: (v) => v.vendeur_nom ?? '—' },
    { cle: 'dossier', entete: t('rapportRentree.dossier_medical_col'), valeur: (v) => v.dossier_medical_ok, cellule: (v) => (v.dossier_medical_ok === null ? '—' : v.dossier_medical_ok ? t('common.yes') : t('common.no')) },
    { cle: 'frais', entete: t('rapportRentree.frais_verses_col'), valeur: (v) => v.frais_verses, cellule: (v) => <span className="tabular-nums">{v.frais_verses}</span> },
    ...(peutModifier
      ? [
          {
            cle: 'actions',
            entete: t('common.actions'),
            cellule: (v: VenteDenree) => (
              <div className="flex items-center gap-1">
                <button title={t('common.edit')} onClick={() => { setVenteEnEdition(v); setShowVenteForm(true) }} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-navy-700">
                  <Pencil className="h-4 w-4" />
                </button>
                <button title={t('common.delete')} onClick={() => supprimerVente(v)} className="rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-600">
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            ),
          } satisfies Colonne<VenteDenree>,
        ]
      : []),
  ]

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={t('rapportRentree.title')}
        sousTitre={t('rapportRentree.subtitle')}
        icon={ClipboardList}
        actions={
          <Button variant="secondary" disabled={exportEnCours || !anneeActive} onClick={exporterPdf}>
            <Download className="h-4 w-4" />
            {t('rapportRentree.export_pdf')}
          </Button>
        }
      />

      {!anneeActive ? (
        <Spinner />
      ) : (
        <>
          <Card>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <Users className="h-4 w-4" />
                {t('rapportRentree.section_visites')}
              </h2>
              {peutModifier && (
                <Button onClick={() => { setVisiteEnEdition(null); setShowVisiteForm(true) }}>
                  <Plus className="h-4 w-4" />
                  {t('rapportRentree.add')}
                </Button>
              )}
            </div>
            {chargeVisites ? <Spinner /> : <DataTable colonnes={colonnesVisites} lignes={visites ?? []} cleLigne={(v) => v.id} messageVide={t('rapportRentree.empty_visites')} largeurMin={680} />}
          </Card>

          <Card>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <ClipboardList className="h-4 w-4" />
                {t('rapportRentree.section_activites')}
              </h2>
              {peutModifier && (
                <Button onClick={() => { setActiviteEnEdition(null); setShowActiviteForm(true) }}>
                  <Plus className="h-4 w-4" />
                  {t('rapportRentree.add')}
                </Button>
              )}
            </div>
            <Tabs
              tabs={CATEGORIES.map((c) => ({ key: c.value, label: c.label }))}
              active={categorieActive}
              onChange={(key) => setCategorieActive(key as CategorieActivite)}
            />
            {chargeActivites ? <Spinner /> : <DataTable colonnes={colonnesActivites} lignes={activites ?? []} cleLigne={(a) => a.id} messageVide={t('rapportRentree.empty_activites')} largeurMin={640} />}
          </Card>

          <Card>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <Cookie className="h-4 w-4" />
                {t('rapportRentree.section_ventes')}
              </h2>
              {peutModifier && (
                <Button onClick={() => { setVenteEnEdition(null); setShowVenteForm(true) }}>
                  <Plus className="h-4 w-4" />
                  {t('rapportRentree.add')}
                </Button>
              )}
            </div>
            {chargeVentes ? <Spinner /> : <DataTable colonnes={colonnesVentes} lignes={ventes ?? []} cleLigne={(v) => v.id} messageVide={t('rapportRentree.empty_ventes')} largeurMin={560} />}
          </Card>

          {textes && (
            <Card>
              <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
                <ShieldAlert className="h-4 w-4" />
                {t('rapportRentree.section_securite')}
              </h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {RUBRIQUES_SECURITE.map((r) => (
                  <ChampTexteLibre key={r.value} rubrique={r.value} label={r.label} valeur={textes[r.value]} anneeScolaireId={anneeActive.id} peutModifier={peutModifier} />
                ))}
              </div>
            </Card>
          )}

          {textes && (
            <Card>
              <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-navy-500">{t('rapportRentree.section_autres_rubriques')}</h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {RUBRIQUES_AUTRES.map((r) => (
                  <ChampTexteLibre key={r.value} rubrique={r.value} label={r.label} valeur={textes[r.value]} anneeScolaireId={anneeActive.id} peutModifier={peutModifier} />
                ))}
              </div>
            </Card>
          )}
        </>
      )}

      {showVisiteForm && anneeActive && (
        <VisiteFormModal visite={visiteEnEdition} anneeScolaireId={anneeActive.id} onClose={() => setShowVisiteForm(false)} onSaved={() => { setShowVisiteForm(false); setVisiteEnEdition(null); invalidate('visites-autorites') }} />
      )}
      {showActiviteForm && anneeActive && (
        <ActiviteFormModal activite={activiteEnEdition} anneeScolaireId={anneeActive.id} categorieParDefaut={categorieActive} onClose={() => setShowActiviteForm(false)} onSaved={() => { setShowActiviteForm(false); setActiviteEnEdition(null); invalidate('activites-rentree') }} />
      )}
      {showVenteForm && anneeActive && (
        <VenteFormModal vente={venteEnEdition} anneeScolaireId={anneeActive.id} onClose={() => setShowVenteForm(false)} onSaved={() => { setShowVenteForm(false); setVenteEnEdition(null); invalidate('ventes-denrees') }} />
      )}
    </div>
  )
}

function ChampTexteLibre({
  rubrique,
  label,
  valeur,
  anneeScolaireId,
  peutModifier,
}: {
  rubrique: RubriqueTexteRentree
  label: string
  valeur: string | null
  anneeScolaireId: number
  peutModifier: boolean
}) {
  const { t } = useTranslation()
  const [contenu, setContenu] = useState(valeur ?? '')
  const [submitting, setSubmitting] = useState(false)
  const modifie = contenu !== (valeur ?? '')

  const enregistrer = async () => {
    setSubmitting(true)
    try {
      await definirTexteRentree(rubrique, anneeScolaireId, contenu)
      succes(t('rapportRentree.updated'))
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="flex flex-col gap-1">
      <label className="text-xs font-semibold text-navy-500">{label}</label>
      <textarea
        className="min-h-[70px] rounded-xl border border-navy-100 bg-white p-2 text-sm text-navy-900 focus:border-gold-400 focus:outline-none disabled:bg-cream-50"
        value={contenu}
        disabled={!peutModifier}
        onChange={(e) => setContenu(e.target.value)}
      />
      {peutModifier && modifie && (
        <Button type="button" variant="secondary" disabled={submitting} onClick={enregistrer} className="self-end">
          {t('common.save')}
        </Button>
      )}
    </div>
  )
}

function VisiteFormModal({ visite, anneeScolaireId, onClose, onSaved }: { visite: VisiteAutorite | null; anneeScolaireId: number; onClose: () => void; onSaved: () => void }) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { register, handleSubmit, formState: { isSubmitting, errors } } = useForm<VisiteAutoritePayload>({
    defaultValues: visite
      ? { date_visite: visite.date_visite, qualite_autorite: visite.qualite_autorite, nature_visite: visite.nature_visite ?? '', objectifs: visite.objectifs ?? '', observations: visite.observations ?? '' }
      : { annee_scolaire_id: anneeScolaireId, date_visite: new Date().toISOString().slice(0, 10) },
  })

  const onSubmit = async (values: VisiteAutoritePayload) => {
    setServerError(null)
    try {
      if (visite) {
        await modifierVisiteAutorite(visite.id, values)
        succes(t('rapportRentree.updated'))
      } else {
        await creerVisiteAutorite({ ...values, annee_scolaire_id: anneeScolaireId })
        succes(t('rapportRentree.created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={visite ? t('rapportRentree.edit_title') : t('rapportRentree.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <div className="grid grid-cols-2 gap-3">
          <Input label={t('rapportRentree.date_col')} type="date" error={errors.date_visite?.message} {...register('date_visite', { required: true })} />
          <Input label={t('rapportRentree.qualite_col')} error={errors.qualite_autorite?.message} {...register('qualite_autorite', { required: true })} />
        </div>
        <Input label={t('rapportRentree.nature_col')} {...register('nature_visite')} />
        <Input label={t('rapportRentree.objectifs_col')} {...register('objectifs')} />
        <Input label={t('rapportRentree.observations_col')} {...register('observations')} />
        {serverError && <p className="text-sm text-red-500">{serverError}</p>}
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
          <Button type="submit" disabled={isSubmitting}>{t('common.save')}</Button>
        </div>
      </form>
    </Modal>
  )
}

function ActiviteFormModal({
  activite,
  anneeScolaireId,
  categorieParDefaut,
  onClose,
  onSaved,
}: {
  activite: ActiviteRentree | null
  anneeScolaireId: number
  categorieParDefaut: CategorieActivite
  onClose: () => void
  onSaved: () => void
}) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { register, handleSubmit, watch, formState: { isSubmitting, errors } } = useForm<ActiviteRentreePayload>({
    defaultValues: activite
      ? {
          categorie: activite.categorie,
          activite: activite.activite,
          periode: activite.periode ?? '',
          objectifs_vises: activite.objectifs_vises ?? '',
          prevues: activite.prevues ?? undefined,
          faites: activite.faites ?? undefined,
          taux_realisation: activite.taux_realisation ?? undefined,
          observations: activite.observations ?? '',
        }
      : { annee_scolaire_id: anneeScolaireId, categorie: categorieParDefaut },
  })

  const categorie = watch('categorie')

  const onSubmit = async (values: ActiviteRentreePayload) => {
    setServerError(null)
    const payload: ActiviteRentreePayload = {
      ...values,
      annee_scolaire_id: anneeScolaireId,
      prevues: values.prevues ? Number(values.prevues) : null,
      faites: values.faites ? Number(values.faites) : null,
      taux_realisation: values.taux_realisation ? Number(values.taux_realisation) : null,
    }

    try {
      if (activite) {
        await modifierActiviteRentree(activite.id, payload)
        succes(t('rapportRentree.updated'))
      } else {
        await creerActiviteRentree(payload)
        succes(t('rapportRentree.created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={activite ? t('rapportRentree.edit_title') : t('rapportRentree.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Select label={t('rapportRentree.categorie_col')} {...register('categorie', { required: true })}>
          {CATEGORIES.map((c) => (
            <option key={c.value} value={c.value}>{c.label}</option>
          ))}
        </Select>
        <Input label={t('rapportRentree.activite_col')} error={errors.activite?.message} {...register('activite', { required: true })} />
        <Input label={t('rapportRentree.periode_col')} {...register('periode')} />
        {categorie === 'pedagogique' ? (
          <div className="grid grid-cols-2 gap-3">
            <Input label={t('rapportRentree.prevues_col')} type="number" min={0} {...register('prevues')} />
            <Input label={t('rapportRentree.faites_col')} type="number" min={0} {...register('faites')} />
          </div>
        ) : (
          <>
            <Input label={t('rapportRentree.objectifs_vises_col')} {...register('objectifs_vises')} />
            <Input label={t('rapportRentree.taux_col')} type="number" min={0} max={100} {...register('taux_realisation')} />
          </>
        )}
        <Input label={t('rapportRentree.observations_col')} {...register('observations')} />
        {serverError && <p className="text-sm text-red-500">{serverError}</p>}
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
          <Button type="submit" disabled={isSubmitting}>{t('common.save')}</Button>
        </div>
      </form>
    </Modal>
  )
}

function VenteFormModal({ vente, anneeScolaireId, onClose, onSaved }: { vente: VenteDenree | null; anneeScolaireId: number; onClose: () => void; onSaved: () => void }) {
  const { t } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)
  const { register, handleSubmit, formState: { isSubmitting, errors } } = useForm<VenteDenreePayload>({
    defaultValues: vente
      ? { nature: vente.nature, vendeur_nom: vente.vendeur_nom ?? '', frais_verses: vente.frais_verses, gestion_frais: vente.gestion_frais ?? '' }
      : { annee_scolaire_id: anneeScolaireId, frais_verses: 0 },
  })

  const onSubmit = async (values: VenteDenreePayload) => {
    setServerError(null)
    const payload: VenteDenreePayload = { ...values, annee_scolaire_id: anneeScolaireId, frais_verses: Number(values.frais_verses) || 0 }

    try {
      if (vente) {
        await modifierVenteDenree(vente.id, payload)
        succes(t('rapportRentree.updated'))
      } else {
        await creerVenteDenree(payload)
        succes(t('rapportRentree.created'))
      }
      onSaved()
    } catch (err) {
      setServerError((err as ApiError).message)
    }
  }

  return (
    <Modal title={vente ? t('rapportRentree.edit_title') : t('rapportRentree.add')} onClose={onClose}>
      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input label={t('rapportRentree.nature_denree_col')} placeholder="Pains, Gâteaux, Beignets…" error={errors.nature?.message} {...register('nature', { required: true })} />
        <Input label={t('rapportRentree.vendeur_col')} {...register('vendeur_nom')} />
        <Input label={t('rapportRentree.frais_verses_col')} type="number" min={0} {...register('frais_verses')} />
        <Input label={t('rapportRentree.gestion_frais_col')} {...register('gestion_frais')} />
        {serverError && <p className="text-sm text-red-500">{serverError}</p>}
        <div className="mt-2 flex justify-end gap-2">
          <Button type="button" variant="secondary" onClick={onClose}>{t('common.cancel')}</Button>
          <Button type="submit" disabled={isSubmitting}>{t('common.save')}</Button>
        </div>
      </form>
    </Modal>
  )
}
