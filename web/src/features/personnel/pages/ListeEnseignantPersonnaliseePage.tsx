import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardList, FileDown, FileSpreadsheet, FileText, Save, Trash2 } from 'lucide-react'
import {
  COLONNES_LISTE_ENSEIGNANT,
  creerListeEnseignantModele,
  fetchDepartements,
  fetchListeEnseignantModeles,
  genererListePersonnaliseeEnseignants,
  LIBELLES_COLONNES_ENSEIGNANT,
  supprimerListeEnseignantModele,
  type ColonneListeEnseignant,
} from '@/features/personnel/api'
import { Button } from '@/shared/ui/Button'
import { Input, Select } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { confirmer, erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

export function ListeEnseignantPersonnaliseePage() {
  const queryClient = useQueryClient()

  const [titreFr, setTitreFr] = useState('Liste personnalisée des enseignants')
  const [titreEn, setTitreEn] = useState('Custom teachers list')
  const [colonnes, setColonnes] = useState<Set<ColonneListeEnseignant>>(new Set(['numero', 'matricule', 'nom_prenom', 'departement', 'telephone']))
  const [format, setFormat] = useState<'pdf' | 'word' | 'excel'>('pdf')
  const [search, setSearch] = useState('')
  const [departementId, setDepartementId] = useState<number | ''>('')
  const [statut, setStatut] = useState<'actif' | 'ex_employe' | ''>('actif')
  const [modeleChoisiId, setModeleChoisiId] = useState<number | ''>('')
  const [generation, setGeneration] = useState(false)

  const { data: departements } = useQuery({ queryKey: ['departements'], queryFn: fetchDepartements })
  const { data: modeles } = useQuery({ queryKey: ['liste-enseignant-modeles'], queryFn: fetchListeEnseignantModeles })

  const toggleColonne = (colonne: ColonneListeEnseignant) => {
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
      await creerListeEnseignantModele({ titre_fr: titreFr, titre_en: titreEn, colonnes: Array.from(colonnes) })
      succes('Modèle enregistré.')
      queryClient.invalidateQueries({ queryKey: ['liste-enseignant-modeles'] })
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const supprimerModele = async (modeleId: number) => {
    const ok = await confirmer({
      titre: 'Supprimer ce modèle ?',
      message: 'Il ne sera plus proposé dans la liste personnalisée des enseignants.',
      action: 'Supprimer',
    })
    if (!ok) return

    try {
      await supprimerListeEnseignantModele(modeleId)
      succes('Modèle supprimé.')
      if (modeleChoisiId === modeleId) setModeleChoisiId('')
      queryClient.invalidateQueries({ queryKey: ['liste-enseignant-modeles'] })
    } catch (err) {
      erreur((err as ApiError).message)
    }
  }

  const generer = async () => {
    if (!titreFr.trim() || !titreEn.trim() || colonnes.size === 0) {
      erreur('Renseignez le titre bilingue et au moins une colonne.')
      return
    }

    setGeneration(true)
    try {
      await genererListePersonnaliseeEnseignants({
        titreFr,
        titreEn,
        colonnes: Array.from(colonnes),
        format,
        search: search.trim() || undefined,
        departementId: departementId === '' ? null : Number(departementId),
        statut,
      })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setGeneration(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Liste personnalisée des enseignants"
        sousTitre="Composez un fichier enseignant avec les colonnes dont vous avez besoin, puis exportez-le en PDF, Word ou Excel."
        icon={ClipboardList}
      />

      <div className="grid gap-3 rounded-lg border border-navy-200 bg-white p-4 shadow-soft sm:grid-cols-2 lg:grid-cols-4">
        <Input label="Recherche" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Nom de l’enseignant" />
        <Select label="Département" value={departementId} onChange={(e) => setDepartementId(e.target.value ? Number(e.target.value) : '')}>
          <option value="">Tous les départements</option>
          {departements?.map((d) => (
            <option key={d.id} value={d.id}>
              {d.nom}
            </option>
          ))}
        </Select>
        <Select label="Statut" value={statut} onChange={(e) => setStatut(e.target.value as 'actif' | 'ex_employe' | '')}>
          <option value="">Tous les statuts</option>
          <option value="actif">Actifs</option>
          <option value="ex_employe">Ex-employés</option>
        </Select>
        <Select label="Format" value={format} onChange={(e) => setFormat(e.target.value as 'pdf' | 'word' | 'excel')}>
          <option value="pdf">PDF</option>
          <option value="word">Word</option>
          <option value="excel">Excel</option>
        </Select>
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
            {COLONNES_LISTE_ENSEIGNANT.map((colonne) => (
              <label key={colonne} className="flex items-center gap-2 rounded-lg border border-navy-100 bg-white px-3 py-2 text-sm text-navy-700">
                <input
                  type="checkbox"
                  checked={colonnes.has(colonne)}
                  onChange={() => toggleColonne(colonne)}
                  className="h-4 w-4 rounded border-navy-300 text-navy-700 focus:ring-navy-200"
                />
                {LIBELLES_COLONNES_ENSEIGNANT[colonne]}
              </label>
            ))}
          </div>
        </div>

        <div className="flex flex-wrap justify-end gap-2">
          <Button type="button" variant="secondary" onClick={enregistrerModele}>
            <Save className="h-4 w-4" />
            Enregistrer comme modèle
          </Button>
          <Button type="button" onClick={generer} disabled={generation || colonnes.size === 0 || !titreFr.trim() || !titreEn.trim()}>
            {format === 'pdf' && <FileDown className="h-4 w-4" />}
            {format === 'word' && <FileText className="h-4 w-4" />}
            {format === 'excel' && <FileSpreadsheet className="h-4 w-4" />}
            {generation ? 'Génération…' : 'Générer'}
          </Button>
        </div>
      </div>

      {(modeles?.length ?? 0) > 0 && (
        <div className="flex flex-col gap-2 rounded-lg border border-navy-200 bg-white p-4 shadow-soft">
          <span className="text-xs font-semibold uppercase tracking-wide text-navy-500">Modèles enseignants</span>
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
                  Supprimer
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}
