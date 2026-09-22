import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardList, FileDown, FileSpreadsheet, FileText, MapPin, Save, Trash2 } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import {
  COLONNES_LISTE_TRANSPORT,
  creerListeTransportModele,
  fetchListePersonnaliseeBus,
  fetchListeTransportModeles,
  fetchTrajets,
  genererListePersonnaliseeTransport,
  LIBELLES_COLONNES_TRANSPORT,
  LIBELLES_OPTION_TRAJET,
  supprimerListeTransportModele,
  type BusAffectation,
  type ColonneListeTransport,
  type FiltresListeBus,
  type GroupeListeBus,
  type OptionTrajet,
} from '@/features/bus/api'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const OPTIONS_GROUPE: { valeur: GroupeListeBus | ''; label: string }[] = [
  { valeur: '', label: 'Aucun regroupement' },
  { valeur: 'classe', label: 'Par classe' },
  { valeur: 'trajet', label: 'Par trajet' },
  { valeur: 'arret', label: 'Par destination (arrêt)' },
  { valeur: 'option_trajet', label: 'Par sens (aller/retour)' },
]

/**
 * Liste personnalisée du transport : les mêmes affectations que la vue
 * « Élèves affectés », mais filtrables (classe, trajet, destination, sens,
 * nom de famille) et regroupables à la demande — pour composer, selon le
 * besoin du moment, un manifeste par classe, par quartier ou par fratrie,
 * sans être limité à un trajet ou un véhicule précis.
 */
export function BusListePersonnaliseePage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const [filtres, setFiltres] = useState<FiltresListeBus>({})
  const [nomSaisi, setNomSaisi] = useState('')
  const [impressionEnCours, setImpressionEnCours] = useState(false)
  const [titreFr, setTitreFr] = useState('Liste personnalisée — transport scolaire')
  const [titreEn, setTitreEn] = useState('Custom transport list')
  const [colonnes, setColonnes] = useState<Set<ColonneListeTransport>>(new Set(['numero', 'nom_prenom', 'classe', 'trajet', 'arret', 'option_trajet']))
  const [format, setFormat] = useState<'pdf' | 'word' | 'excel'>('pdf')
  const [modeleChoisiId, setModeleChoisiId] = useState<number | ''>('')

  const { data: classes } = useQuery({ queryKey: ['classes', 'select'], queryFn: () => fetchClasses() })
  const { data: trajets } = useQuery({ queryKey: ['bus-trajets'], queryFn: fetchTrajets })
  const { data: modeles } = useQuery({ queryKey: ['liste-transport-modeles'], queryFn: fetchListeTransportModeles })

  const arrets = useMemo(
    () =>
      (trajets ?? [])
        .filter((tr) => !filtres.trajet_id || tr.id === filtres.trajet_id)
        .flatMap((tr) => tr.arrets.map((a) => ({ ...a, trajetNom: tr.nom }))),
    [trajets, filtres.trajet_id],
  )

  const filtresActifs = useMemo<FiltresListeBus>(
    () => ({ ...filtres, nom: nomSaisi.trim() || undefined }),
    [filtres, nomSaisi],
  )

  const { data, isLoading } = useQuery({
    queryKey: ['bus-liste-personnalisee', filtresActifs],
    queryFn: () => fetchListePersonnaliseeBus(filtresActifs),
  })

  const groupes: [string, BusAffectation[]][] = useMemo(() => {
    if (!data) return []
    if (Array.isArray(data.resultats)) return [['', data.resultats]]
    return Object.entries(data.resultats)
  }, [data])

  const imprimer = async () => {
    if (colonnes.size === 0 || !titreFr.trim() || !titreEn.trim()) {
      erreur('Renseignez le titre bilingue et au moins une colonne.')
      return
    }

    setImpressionEnCours(true)
    try {
      await genererListePersonnaliseeTransport({
        filtres: filtresActifs,
        titreFr,
        titreEn,
        colonnes: Array.from(colonnes),
        format,
      })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setImpressionEnCours(false)
    }
  }

  const modifierFiltre = <K extends keyof FiltresListeBus>(cle: K, valeur: FiltresListeBus[K]) =>
    setFiltres((f) => ({ ...f, [cle]: valeur || undefined }))

  const toggleColonne = (colonne: ColonneListeTransport) => {
    setColonnes((precedent) => {
      const suivant = new Set(precedent)
      if (suivant.has(colonne)) suivant.delete(colonne)
      else suivant.add(colonne)
      return suivant
    })
  }

  const chargerModele = (modeleId: number | '') => {
    setModeleChoisiId(modeleId)
    if (modeleId === '') return
    const modele = modeles?.find((m) => m.id === modeleId)
    if (!modele) return
    setTitreFr(modele.titre_fr)
    setTitreEn(modele.titre_en)
    setColonnes(new Set(modele.colonnes))
  }

  const enregistrerModele = async () => {
    if (!titreFr.trim() || !titreEn.trim() || colonnes.size === 0) {
      erreur('Renseignez le titre bilingue et au moins une colonne.')
      return
    }
    try {
      await creerListeTransportModele({ titre_fr: titreFr, titre_en: titreEn, colonnes: Array.from(colonnes) })
      succes('Modèle enregistré.')
      queryClient.invalidateQueries({ queryKey: ['liste-transport-modeles'] })
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerModele = async (modeleId: number) => {
    const ok = await confirmer({
      titre: 'Supprimer ce modèle ?',
      message: 'Il ne sera plus proposé dans la liste personnalisée du transport.',
      action: 'Supprimer',
    })
    if (!ok) return
    try {
      await supprimerListeTransportModele(modeleId)
      succes('Modèle supprimé.')
      if (modeleChoisiId === modeleId) setModeleChoisiId('')
      queryClient.invalidateQueries({ queryKey: ['liste-transport-modeles'] })
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Liste personnalisée"
        sousTitre="Composez la liste des élèves transportés selon la classe, le trajet, la destination, le sens ou le nom — regroupez-la comme il vous faut."
        icon={ClipboardList}
        actions={
          <Button onClick={() => void imprimer()} disabled={impressionEnCours || colonnes.size === 0 || !titreFr.trim() || !titreEn.trim()}>
            {format === 'pdf' && <FileDown className="h-4 w-4" />}
            {format === 'word' && <FileText className="h-4 w-4" />}
            {format === 'excel' && <FileSpreadsheet className="h-4 w-4" />}
            {impressionEnCours ? t('common.loading') : 'Générer'}
          </Button>
        }
      />

      <div className="grid gap-3 rounded-lg border border-navy-200 bg-white p-4 shadow-soft sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label className="mb-1 block text-xs font-medium text-navy-500">{t('bus.toutes_classes')}</label>
          <Select
            value={filtres.classe_id ?? ''}
            onChange={(e) => modifierFiltre('classe_id', e.target.value ? Number(e.target.value) : undefined)}
          >
            <option value="">{t('bus.toutes_classes')}</option>
            {classes?.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-navy-500">{t('bus.trajets_title')}</label>
          <Select
            value={filtres.trajet_id ?? ''}
            onChange={(e) => {
              const trajetId = e.target.value ? Number(e.target.value) : undefined
              setFiltres((f) => ({ ...f, trajet_id: trajetId, arret_id: undefined }))
            }}
          >
            <option value="">{t('bus.all_trajets')}</option>
            {trajets?.map((tr) => (
              <option key={tr.id} value={tr.id}>
                {tr.nom}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-navy-500">Destination (arrêt)</label>
          <Select
            value={filtres.arret_id ?? ''}
            onChange={(e) => modifierFiltre('arret_id', e.target.value ? Number(e.target.value) : undefined)}
          >
            <option value="">Toutes les destinations</option>
            {arrets.map((a) => (
              <option key={a.id} value={a.id}>
                {a.nom}{!filtres.trajet_id ? ` — ${a.trajetNom}` : ''}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-navy-500">{t('bus.option_trajet')}</label>
          <Select
            value={filtres.option_trajet ?? ''}
            onChange={(e) => modifierFiltre('option_trajet', (e.target.value || undefined) as OptionTrajet | undefined)}
          >
            <option value="">Tous les sens</option>
            {(Object.keys(LIBELLES_OPTION_TRAJET) as OptionTrajet[]).map((opt) => (
              <option key={opt} value={opt}>
                {LIBELLES_OPTION_TRAJET[opt]}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-navy-500">Nom de famille</label>
          <Input
            value={nomSaisi}
            onChange={(e) => setNomSaisi(e.target.value)}
            placeholder="Ex. : Nguema"
          />
        </div>

        <div>
          <label className="mb-1 block text-xs font-medium text-navy-500">Regrouper par</label>
          <Select
            value={filtres.group_by ?? ''}
            onChange={(e) => modifierFiltre('group_by', (e.target.value || undefined) as GroupeListeBus | undefined)}
          >
            {OPTIONS_GROUPE.map((g) => (
              <option key={g.valeur} value={g.valeur}>
                {g.label}
              </option>
            ))}
          </Select>
        </div>

        <div className="flex items-end sm:col-span-2 lg:col-span-1">
          <Button
            variant="secondary"
            onClick={() => {
              setFiltres({})
              setNomSaisi('')
            }}
          >
            Réinitialiser les filtres
          </Button>
        </div>
      </div>

      <div className="flex flex-col gap-4 rounded-lg border border-navy-200 bg-white p-4 shadow-soft">
        {(modeles?.length ?? 0) > 0 && (
          <Select label="Modèle enregistré" value={modeleChoisiId} onChange={(e) => chargerModele(e.target.value ? Number(e.target.value) : '')}>
            <option value="">Choisir un modèle</option>
            {modeles?.map((m) => (
              <option key={m.id} value={m.id}>
                {m.titre_fr}
              </option>
            ))}
          </Select>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          <Input label="Titre français" value={titreFr} onChange={(e) => setTitreFr(e.target.value)} />
          <Input label="Titre anglais" value={titreEn} onChange={(e) => setTitreEn(e.target.value)} />
        </div>

        <div className="flex flex-col gap-2">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Colonnes à imprimer</span>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {COLONNES_LISTE_TRANSPORT.map((colonne) => (
              <label key={colonne} className="flex items-center gap-2 rounded-lg border border-navy-100 bg-white px-3 py-2 text-sm text-navy-700">
                <input
                  type="checkbox"
                  checked={colonnes.has(colonne)}
                  onChange={() => toggleColonne(colonne)}
                  className="h-4 w-4 rounded border-navy-300 text-navy-700 focus:ring-navy-200"
                />
                {LIBELLES_COLONNES_TRANSPORT[colonne]}
              </label>
            ))}
          </div>
        </div>

        <div className="flex flex-wrap items-end justify-between gap-3">
          <Select label="Format" value={format} onChange={(e) => setFormat(e.target.value as 'pdf' | 'word' | 'excel')}>
            <option value="pdf">PDF</option>
            <option value="word">Word</option>
            <option value="excel">Excel</option>
          </Select>
          <div className="flex flex-wrap gap-2">
            <Button type="button" variant="secondary" onClick={enregistrerModele}>
              <Save className="h-4 w-4" />
              Enregistrer comme modèle
            </Button>
            <Button type="button" onClick={() => void imprimer()} disabled={impressionEnCours || colonnes.size === 0 || !titreFr.trim() || !titreEn.trim()}>
              {format === 'pdf' && <FileDown className="h-4 w-4" />}
              {format === 'word' && <FileText className="h-4 w-4" />}
              {format === 'excel' && <FileSpreadsheet className="h-4 w-4" />}
              {impressionEnCours ? t('common.loading') : 'Générer'}
            </Button>
          </div>
        </div>
      </div>

      {(modeles?.length ?? 0) > 0 && (
        <div className="flex flex-col gap-2 rounded-lg border border-navy-200 bg-white p-4 shadow-soft">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Modèles transport</span>
          <ul className="flex flex-col divide-y divide-navy-50">
            {modeles?.map((m) => (
              <li key={m.id} className="flex items-center justify-between gap-2 py-2">
                <span className="text-sm text-navy-700">{m.titre_fr}</span>
                <button
                  type="button"
                  onClick={() => supprimerModele(m.id)}
                  className="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-red-500 transition-colors hover:bg-red-50"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                  {t('common.delete')}
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}

      {isLoading ? (
        <Spinner />
      ) : !data || data.total === 0 ? (
        <div className="rounded-lg border border-navy-200 bg-white p-8 text-center text-navy-400 shadow-soft">
          Aucun élève ne correspond à ces filtres.
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-navy-500">{data.total} élève(s) transporté(s) trouvé(s).</p>
          {groupes.map(([libelleGroupe, lignes]) => (
            <div key={libelleGroupe || '__all__'} className="overflow-hidden rounded-lg border border-navy-200 bg-white shadow-soft">
              {libelleGroupe && (
                <div className="flex items-center justify-between bg-navy-50 px-4 py-2">
                  <span className="font-semibold text-navy-900">{libelleGroupe}</span>
                  <Badge tone="neutral">{lignes.length}</Badge>
                </div>
              )}
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-navy-100 text-left text-xs font-medium uppercase text-navy-400">
                    <th className="px-4 py-2">{t('bus.eleve')}</th>
                    <th className="px-4 py-2">{t('bus.nom')}</th>
                    <th className="px-4 py-2">{t('bus.trajets_title')}</th>
                    <th className="px-4 py-2">
                      <span className="inline-flex items-center gap-1">
                        <MapPin className="h-3.5 w-3.5" />
                        {t('bus.arret_select')}
                      </span>
                    </th>
                    <th className="px-4 py-2">{t('bus.option_trajet')}</th>
                  </tr>
                </thead>
                <tbody>
                  {lignes.map((a) => (
                    <tr key={a.id} className="border-b border-navy-50 last:border-0">
                      <td className="px-4 py-2 font-medium text-navy-900">{a.eleve.nom_complet}</td>
                      <td className="px-4 py-2 text-navy-600">{a.eleve.classe ?? '—'}</td>
                      <td className="px-4 py-2 text-navy-600">{a.trajet.nom}</td>
                      <td className="px-4 py-2 text-navy-600">{a.arret?.nom ?? '—'}</td>
                      <td className="px-4 py-2 text-navy-600">{t(`bus.${a.option_trajet}`)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
