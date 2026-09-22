import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Camera, Check, Plus, ScanLine, Trash2 } from 'lucide-react'
import { Modal } from '@/shared/ui/Modal'
import { Button } from '@/shared/ui/Button'
import { Select } from '@/shared/ui/Field'
import {
  apercuOcrPresencePersonnel,
  confirmerImportOcrPresence,
  type LignePresenceOcr,
  type PersonnelOptionOcr,
  type ResultatImportOcrPresence,
} from '../api'
import type { ApiError } from '@/shared/types/api'

/**
 * Ligne éditable de la prévisualisation : reprend LignePresenceOcr mais
 * laisse le champ nom libre au clavier (l'OCR peut s'être trompé de bout en
 * bout, l'utilisateur doit pouvoir la choisir manuellement sans qu'elle
 * reparte du texte lu sur la photo).
 */
type LigneEditable = LignePresenceOcr & { cle: string }

let compteurCle = 0
function nouvelleCle() {
  return `ocr-${Date.now()}-${compteurCle++}`
}

/**
 * Import de la fiche de présence à partir d'une photo (version papier
 * signée à la main) : la photo est passée à l'OCR côté serveur, puis
 * chaque ligne reconnue (agent, heure d'arrivée, heure de départ) est
 * présentée ici pour vérification/correction avant d'être réellement
 * enregistrée — l'écriture manuscrite laisse trop de place à l'erreur pour
 * importer directement le résultat brut de l'OCR.
 */
export function ImportPresenceOcrModal({
  date,
  onClose,
  onImported,
}: {
  date: string
  onClose: () => void
  onImported: () => void
}) {
  const { t } = useTranslation()
  const [fichier, setFichier] = useState<File | null>(null)
  const [apercu, setApercu] = useState<string | null>(null)
  const [analyse, setAnalyse] = useState(false)
  const [personnels, setPersonnels] = useState<PersonnelOptionOcr[]>([])
  const [lignes, setLignes] = useState<LigneEditable[] | null>(null)
  const [enregistrement, setEnregistrement] = useState(false)
  const [resultat, setResultat] = useState<ResultatImportOcrPresence | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const choisirFichier = (fichierChoisi: File | null) => {
    if (apercu) URL.revokeObjectURL(apercu)
    setFichier(fichierChoisi)
    setApercu(fichierChoisi ? URL.createObjectURL(fichierChoisi) : null)
    setLignes(null)
    setErreur(null)
  }

  const analyserPhoto = async () => {
    if (!fichier) return
    setAnalyse(true)
    setErreur(null)
    try {
      const donnees = await apercuOcrPresencePersonnel(fichier, date)
      setPersonnels(donnees.personnels)
      setLignes(donnees.lignes.map((ligne) => ({ ...ligne, cle: nouvelleCle() })))
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setAnalyse(false)
    }
  }

  const modifierLigne = (cle: string, patch: Partial<LigneEditable>) => {
    setLignes((precedent) => precedent?.map((ligne) => (ligne.cle === cle ? { ...ligne, ...patch } : ligne)) ?? null)
  }

  const supprimerLigne = (cle: string) => {
    setLignes((precedent) => precedent?.filter((ligne) => ligne.cle !== cle) ?? null)
  }

  const ajouterLigne = () => {
    setLignes((precedent) => [
      ...(precedent ?? []),
      { cle: nouvelleCle(), personnel_id: null, nom_complet: '', texte_ocr: '', heure_arrivee: null, heure_depart: null, confiance: 'faible' },
    ])
  }

  const confirmer = async () => {
    if (!lignes) return
    const lignesRenseignees = lignes.filter((ligne) => ligne.personnel_id !== null)
    if (lignesRenseignees.length === 0) {
      setErreur(t('personnel.suivi_activite.import_ocr_aucune_ligne'))
      return
    }

    setEnregistrement(true)
    setErreur(null)
    try {
      const donnees = await confirmerImportOcrPresence(
        date,
        lignesRenseignees.map((ligne) => ({
          personnel_id: ligne.personnel_id,
          nom_complet: ligne.nom_complet,
          heure_arrivee: ligne.heure_arrivee,
          heure_depart: ligne.heure_depart,
        })),
      )
      setResultat(donnees)
      onImported()
    } catch (err) {
      setErreur((err as ApiError).message)
    } finally {
      setEnregistrement(false)
    }
  }

  return (
    <Modal title={t('personnel.suivi_activite.import_ocr_title')} onClose={onClose} taille="lg">
      <div className="flex flex-col gap-4">
        {!lignes && (
          <>
            <p className="rounded-lg bg-cream-100 p-3 text-xs text-navy-500">{t('personnel.suivi_activite.import_ocr_hint')}</p>

            <label className="flex flex-col gap-1.5">
              <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">
                {t('personnel.suivi_activite.import_ocr_photo')}
              </span>
              <input
                type="file"
                accept="image/*"
                capture="environment"
                onChange={(e) => choisirFichier(e.target.files?.[0] ?? null)}
                className="w-full rounded-xl border border-navy-200 bg-white px-3.5 py-2.5 text-sm shadow-soft file:mr-3 file:rounded-lg file:border-0 file:bg-navy-700 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-cream-50"
              />
            </label>

            {apercu && (
              <img src={apercu} alt="" className="max-h-64 w-full rounded-xl border border-navy-100 object-contain" />
            )}

            {erreur && <p className="text-sm text-red-500">{erreur}</p>}

            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={onClose}>
                {t('common.cancel')}
              </Button>
              <Button type="button" onClick={analyserPhoto} disabled={!fichier || analyse}>
                <ScanLine className="h-4 w-4" />
                {analyse ? t('personnel.suivi_activite.import_ocr_analyse_en_cours') : t('personnel.suivi_activite.import_ocr_analyser')}
              </Button>
            </div>
          </>
        )}

        {lignes && !resultat && (
          <>
            <p className="rounded-lg bg-cream-100 p-3 text-xs text-navy-500">{t('personnel.suivi_activite.import_ocr_verification_hint')}</p>

            <div className="overflow-x-auto rounded-xl border border-navy-100">
              <table className="w-full min-w-[560px] text-sm">
                <thead className="bg-cream-50 text-xs font-semibold uppercase tracking-wide text-navy-500">
                  <tr>
                    <th className="px-3 py-2 text-left">{t('personnel.suivi_activite.column_staff')}</th>
                    <th className="w-32 px-3 py-2 text-left">{t('personnel.suivi_activite.import_ocr_arrivee')}</th>
                    <th className="w-32 px-3 py-2 text-left">{t('personnel.suivi_activite.import_ocr_depart')}</th>
                    <th className="w-10 px-2 py-2" />
                  </tr>
                </thead>
                <tbody className="divide-y divide-navy-50">
                  {lignes.map((ligne) => (
                    <tr key={ligne.cle} className={ligne.personnel_id === null ? 'bg-amber-50' : undefined}>
                      <td className="px-3 py-2">
                        <Select
                          value={ligne.personnel_id ? String(ligne.personnel_id) : ''}
                          onChange={(e) => {
                            const id = e.target.value ? Number(e.target.value) : null
                            modifierLigne(ligne.cle, {
                              personnel_id: id,
                              nom_complet: personnels.find((p) => p.id === id)?.nom_complet ?? ligne.nom_complet,
                            })
                          }}
                        >
                          <option value="">{t('personnel.suivi_activite.import_ocr_non_reconnu')}</option>
                          {personnels.map((personnel) => (
                            <option key={personnel.id} value={personnel.id}>
                              {personnel.nom_complet}
                            </option>
                          ))}
                        </Select>
                        {ligne.personnel_id === null && ligne.texte_ocr && (
                          <p className="mt-1 truncate text-xs text-navy-400" title={ligne.texte_ocr}>
                            {t('personnel.suivi_activite.import_ocr_texte_lu')} « {ligne.texte_ocr} »
                          </p>
                        )}
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="time"
                          value={ligne.heure_arrivee ?? ''}
                          onChange={(e) => modifierLigne(ligne.cle, { heure_arrivee: e.target.value || null })}
                          className="w-full rounded-lg border border-navy-200 bg-white px-2 py-1.5 text-sm"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="time"
                          value={ligne.heure_depart ?? ''}
                          onChange={(e) => modifierLigne(ligne.cle, { heure_depart: e.target.value || null })}
                          className="w-full rounded-lg border border-navy-200 bg-white px-2 py-1.5 text-sm"
                        />
                      </td>
                      <td className="px-2 py-2 text-center">
                        <button
                          type="button"
                          onClick={() => supprimerLigne(ligne.cle)}
                          aria-label={t('common.delete')}
                          className="rounded-full p-1.5 text-navy-300 transition-colors hover:bg-red-50 hover:text-red-500"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </td>
                    </tr>
                  ))}
                  {lignes.length === 0 && (
                    <tr>
                      <td colSpan={4} className="px-3 py-6 text-center text-sm text-navy-400">
                        {t('personnel.suivi_activite.import_ocr_aucune_ligne')}
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            <Button type="button" variant="secondary" size="sm" onClick={ajouterLigne} className="self-start">
              <Plus className="h-3.5 w-3.5" />
              {t('personnel.suivi_activite.import_ocr_ajouter_ligne')}
            </Button>

            {erreur && <p className="text-sm text-red-500">{erreur}</p>}

            <div className="mt-2 flex justify-end gap-2">
              <Button type="button" variant="secondary" onClick={() => setLignes(null)}>
                <Camera className="h-4 w-4" />
                {t('personnel.suivi_activite.import_ocr_reprendre_photo')}
              </Button>
              <Button type="button" onClick={confirmer} disabled={enregistrement}>
                <Check className="h-4 w-4" />
                {t('personnel.suivi_activite.import_ocr_confirmer')}
              </Button>
            </div>
          </>
        )}

        {resultat && (
          <>
            <p className="text-sm text-green-600">
              {t('import.result', { imported: resultat.imported, failed: resultat.failed })}
              {resultat.updated ? ` ${t('import.updated', { count: resultat.updated })}` : ''}
            </p>
            {resultat.errors.length > 0 && (
              <div className="flex flex-col gap-1 rounded-lg border border-red-100 bg-red-50 p-2.5">
                {resultat.errors.map((err) => (
                  <p key={err.ligne} className="text-xs text-red-600">
                    {err.nom ?? `Ligne ${err.ligne}`} — {err.message}
                  </p>
                ))}
              </div>
            )}
            <div className="mt-2 flex justify-end">
              <Button type="button" onClick={onClose}>
                {t('common.close')}
              </Button>
            </div>
          </>
        )}
      </div>
    </Modal>
  )
}
