import { useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardList, FileDown, FileSpreadsheet, FileText, Save, Trash2 } from 'lucide-react'
import { fetchClasse, fetchClasses } from '@/features/classes/api'
import { fetchTrimestresAll } from '@/features/session/api'
import {
  COLONNES_AVEC_PERIODE,
  COLONNES_LISTE_PERSONNALISEE,
  creerListeClasseModele,
  fetchListeClasseModeles,
  genererListePersonnalisee,
  supprimerListeClasseModele,
  type ColonneListePersonnalisee,
  type MoyenneType,
} from '@/features/classes/listePersonnalisee/api'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { Spinner } from '@/shared/ui/Feedback'
import { succes, erreur, confirmer } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

/**
 * Assistant en une page (pas d'étapes forcées : tout est visible, l'ordre
 * naturel — classe, titre, colonnes, format — guide déjà l'utilisateur) pour
 * générer une liste de classe aux colonnes choisies, dans le format voulu, et
 * en sauvegarder la configuration (titre + colonnes) comme modèle réutilisable.
 *
 * Montée sur `/classes/liste-personnalisee` (classe à choisir) ou sur
 * `/classes/:id/liste-personnalisee` (classe déjà connue, verrouillée).
 */
export function ListePersonnaliseePage() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { id } = useParams<{ id: string }>()
  const classeIdVerrouillee = id ? Number(id) : null

  const [classeId, setClasseId] = useState<number | null>(classeIdVerrouillee)
  const [titreFr, setTitreFr] = useState('')
  const [titreEn, setTitreEn] = useState('')
  const [colonnes, setColonnes] = useState<Set<ColonneListePersonnalisee>>(new Set(['numero', 'nom_prenom']))
  const [moyenneType, setMoyenneType] = useState<MoyenneType | ''>('')
  const [moyenneReferenceId, setMoyenneReferenceId] = useState<number | ''>('')
  const [format, setFormat] = useState<'pdf' | 'word' | 'excel'>('pdf')
  const [modeleChoisiId, setModeleChoisiId] = useState<number | ''>('')
  const [generation, setGeneration] = useState(false)

  const { data: classe } = useQuery({
    queryKey: ['classe', classeIdVerrouillee],
    queryFn: () => fetchClasse(classeIdVerrouillee as number),
    enabled: classeIdVerrouillee !== null,
  })

  const { data: classes } = useQuery({
    queryKey: ['classes'],
    queryFn: fetchClasses,
    enabled: classeIdVerrouillee === null,
  })

  const { data: trimestres } = useQuery({ queryKey: ['trimestres-all'], queryFn: fetchTrimestresAll })

  const { data: modeles } = useQuery({ queryKey: ['liste-classe-modeles'], queryFn: fetchListeClasseModeles })

  const sequencesDuTrimestre = useMemo(
    () => trimestres?.flatMap((tr) => tr.sequences.map((seq) => ({ ...seq, trimestreLibelle: tr.libelle }))) ?? [],
    [trimestres],
  )

  const toggleColonne = (colonne: ColonneListePersonnalisee) => {
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
    setMoyenneType(modele.moyenne_type ?? '')
    setMoyenneReferenceId('')
  }

  const enregistrerModele = async () => {
    if (!titreFr.trim() || !titreEn.trim()) {
      erreur(t('listePersonnalisee.modele_nom_manquant'))
      return
    }
    try {
      await creerListeClasseModele({
        titre_fr: titreFr,
        titre_en: titreEn,
        colonnes: Array.from(colonnes),
        moyenne_type: moyenneType || null,
      })
      succes(t('listePersonnalisee.modele_enregistre'))
      queryClient.invalidateQueries({ queryKey: ['liste-classe-modeles'] })
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerModele = async (modeleId: number) => {
    const confirme = await confirmer({
      titre: t('listePersonnalisee.modele_supprimer_titre'),
      message: t('listePersonnalisee.modele_supprimer_message'),
      action: t('common.delete'),
    })
    if (!confirme) return
    try {
      await supprimerListeClasseModele(modeleId)
      succes(t('listePersonnalisee.modele_supprime'))
      if (modeleChoisiId === modeleId) setModeleChoisiId('')
      queryClient.invalidateQueries({ queryKey: ['liste-classe-modeles'] })
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const generer = async () => {
    if (!classeId) return
    if (colonnes.size === 0) {
      erreur(t('listePersonnalisee.aucune_colonne'))
      return
    }
    setGeneration(true)
    try {
      await genererListePersonnalisee({
        classeId,
        titreFr,
        titreEn,
        colonnes: Array.from(colonnes),
        moyenneType: moyenneType || null,
        moyenneReferenceId: moyenneReferenceId || null,
        format,
      })
    } catch (err) {
      erreur((err as ApiError).message ?? t('listePersonnalisee.erreur_generation'))
    } finally {
      setGeneration(false)
    }
  }

  const peutGenerer = classeId !== null && titreFr.trim() !== '' && titreEn.trim() !== '' && colonnes.size > 0

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={classeIdVerrouillee && classe ? `${t('listePersonnalisee.title')} — ${classe.nom}` : t('listePersonnalisee.title')}
        sousTitre={t('listePersonnalisee.subtitle')}
        icon={ClipboardList}
      />

      <div className="flex flex-col gap-5 rounded-xl border border-navy-100 bg-white/75 p-4 shadow-soft sm:p-6">
        {classeIdVerrouillee === null && (
          <Select
            label={t('listePersonnalisee.champ_classe')}
            value={classeId ?? ''}
            onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : null)}
          >
            <option value="">{t('listePersonnalisee.champ_classe_placeholder')}</option>
            {classes?.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom}
              </option>
            ))}
          </Select>
        )}

        {(modeles?.length ?? 0) > 0 && (
          <Select
            label={t('listePersonnalisee.modele_charger')}
            value={modeleChoisiId}
            onChange={(e) => chargerModele(e.target.value ? Number(e.target.value) : '')}
          >
            <option value="">{t('listePersonnalisee.modele_placeholder')}</option>
            {modeles?.map((m) => (
              <option key={m.id} value={m.id}>
                {m.titre_fr}
              </option>
            ))}
          </Select>
        )}

        <div className="grid gap-3 sm:grid-cols-2">
          <Input
            label={t('listePersonnalisee.champ_titre_fr')}
            placeholder={t('listePersonnalisee.champ_titre_fr_placeholder')}
            value={titreFr}
            onChange={(e) => setTitreFr(e.target.value)}
          />
          <Input
            label={t('listePersonnalisee.champ_titre_en')}
            placeholder={t('listePersonnalisee.champ_titre_en_placeholder')}
            value={titreEn}
            onChange={(e) => setTitreEn(e.target.value)}
          />
        </div>

        <div className="flex flex-col gap-2">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('listePersonnalisee.colonnes_label')}</span>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {COLONNES_LISTE_PERSONNALISEE.map((colonne) => (
              <label key={colonne} className="flex items-center gap-2 rounded-lg border border-navy-100 bg-white px-3 py-2 text-sm text-navy-700">
                <input
                  type="checkbox"
                  checked={colonnes.has(colonne)}
                  onChange={() => toggleColonne(colonne)}
                  className="h-4 w-4 rounded border-navy-300 text-navy-700 focus:ring-navy-200"
                />
                {t(`listePersonnalisee.colonne_${colonne}`)}
              </label>
            ))}
          </div>
        </div>

        {COLONNES_AVEC_PERIODE.some((c) => colonnes.has(c)) && (
          <div className="grid gap-3 sm:grid-cols-2">
            <Select
              label={t('listePersonnalisee.moyenne_type_label')}
              value={moyenneType}
              onChange={(e) => {
                setMoyenneType(e.target.value as MoyenneType | '')
                setMoyenneReferenceId('')
              }}
            >
              <option value="">{t('listePersonnalisee.moyenne_periode_placeholder')}</option>
              <option value="trimestre">{t('listePersonnalisee.moyenne_type_trimestre')}</option>
              <option value="sequence">{t('listePersonnalisee.moyenne_type_sequence')}</option>
              <option value="annuelle">{t('listePersonnalisee.moyenne_type_annuelle')}</option>
            </Select>

            {moyenneType === 'trimestre' && (
              <Select
                label={t('listePersonnalisee.moyenne_periode_label')}
                value={moyenneReferenceId}
                onChange={(e) => setMoyenneReferenceId(e.target.value ? Number(e.target.value) : '')}
              >
                <option value="">{t('listePersonnalisee.moyenne_periode_placeholder')}</option>
                {trimestres?.map((tr) => (
                  <option key={tr.id} value={tr.id}>
                    {tr.libelle}
                  </option>
                ))}
              </Select>
            )}

            {moyenneType === 'sequence' && (
              <Select
                label={t('listePersonnalisee.moyenne_periode_label')}
                value={moyenneReferenceId}
                onChange={(e) => setMoyenneReferenceId(e.target.value ? Number(e.target.value) : '')}
              >
                <option value="">{t('listePersonnalisee.moyenne_periode_placeholder')}</option>
                {sequencesDuTrimestre.map((seq) => (
                  <option key={seq.id} value={seq.id}>
                    {seq.trimestreLibelle} — {seq.libelle}
                  </option>
                ))}
              </Select>
            )}
          </div>
        )}

        <Select label={t('listePersonnalisee.format_label')} value={format} onChange={(e) => setFormat(e.target.value as 'pdf' | 'word' | 'excel')}>
          <option value="pdf">{t('listePersonnalisee.format_pdf')}</option>
          <option value="word">{t('listePersonnalisee.format_word')}</option>
          <option value="excel">{t('listePersonnalisee.format_excel')}</option>
        </Select>

        <div className="flex flex-wrap justify-end gap-3">
          <Button type="button" variant="secondary" onClick={enregistrerModele}>
            <Save className="h-4 w-4" />
            {t('listePersonnalisee.modele_enregistrer')}
          </Button>
          <Button type="button" onClick={generer} disabled={!peutGenerer || generation}>
            {format === 'pdf' && <FileDown className="h-4 w-4" />}
            {format === 'word' && <FileText className="h-4 w-4" />}
            {format === 'excel' && <FileSpreadsheet className="h-4 w-4" />}
            {generation ? t('listePersonnalisee.generation_en_cours') : t('listePersonnalisee.generer')}
          </Button>
        </div>
      </div>

      {(modeles?.length ?? 0) > 0 && (
        <div className="flex flex-col gap-2 rounded-xl border border-navy-100 bg-white/75 p-4 shadow-soft sm:p-6">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">{t('listePersonnalisee.modele_charger')}</span>
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

      {classeIdVerrouillee !== null && !classe && <Spinner />}
    </div>
  )
}
