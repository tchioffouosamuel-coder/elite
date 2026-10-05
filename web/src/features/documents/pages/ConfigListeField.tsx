import { Input, Select } from '@/shared/ui/Field'
import type { ConfigListe, ModeleListe } from '@/features/documents/api'

/**
 * Réglage d'une liste personnalisée pour le centre de documents : partir
 * d'un modèle déjà enregistré (écrans Listes personnalisées) ou choisir
 * soi-même titres et colonnes.
 */
export function ConfigListeField({
  titre,
  valeur,
  onChange,
  colonnes,
  modeles,
  avecMoyenne = false,
}: {
  titre: string
  valeur: ConfigListe
  onChange: (valeur: ConfigListe) => void
  colonnes: { cle: string; libelle: string }[]
  modeles: ModeleListe[]
  avecMoyenne?: boolean
}) {
  const basculer = (cle: string) =>
    onChange({
      ...valeur,
      // Ordre du catalogue conservé : c'est l'ordre des colonnes du document.
      colonnes: colonnes.map((c) => c.cle).filter((c) => (c === cle ? !valeur.colonnes.includes(c) : valeur.colonnes.includes(c))),
    })

  return (
    <fieldset className="flex flex-col gap-3 rounded-xl border border-navy-100 p-3">
      <legend className="px-1 text-sm font-semibold text-navy-800">{titre}</legend>

      {modeles.length > 0 && (
        <Select
          label="Partir d'un modèle enregistré"
          value=""
          onChange={(e) => {
            const modele = modeles.find((m) => String(m.id) === e.target.value)
            if (modele) {
              onChange({
                titre_fr: modele.titre_fr,
                titre_en: modele.titre_en,
                colonnes: modele.colonnes,
                moyenne_type: modele.moyenne_type ?? undefined,
              })
            }
          }}
        >
          <option value="">— Choisir un modèle —</option>
          {modeles.map((m) => (
            <option key={m.id} value={String(m.id)}>
              {m.titre_fr}
            </option>
          ))}
        </Select>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <Input label="Titre (français)" value={valeur.titre_fr} onChange={(e) => onChange({ ...valeur, titre_fr: e.target.value })} />
        <Input label="Titre (anglais)" value={valeur.titre_en} onChange={(e) => onChange({ ...valeur, titre_en: e.target.value })} />
      </div>

      {avecMoyenne && (
        <Select
          label="Moyenne affichée (si la colonne est choisie)"
          value={valeur.moyenne_type ?? 'annuelle'}
          onChange={(e) => onChange({ ...valeur, moyenne_type: e.target.value })}
        >
          <option value="annuelle">Annuelle</option>
          <option value="trimestre">Du trimestre choisi</option>
        </Select>
      )}

      <div>
        <p className="mb-1.5 text-xs font-medium text-navy-500">
          Colonnes ({valeur.colonnes.length} sélectionnée{valeur.colonnes.length > 1 ? 's' : ''})
        </p>
        <div className="grid grid-cols-1 gap-1 sm:grid-cols-2 lg:grid-cols-3">
          {colonnes.map((c) => (
            <label key={c.cle} className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm text-navy-700 hover:bg-cream-50">
              <input
                type="checkbox"
                checked={valeur.colonnes.includes(c.cle)}
                onChange={() => basculer(c.cle)}
                className="h-4 w-4 rounded border-navy-300 text-gold-600 focus:ring-gold-500"
              />
              {c.libelle}
            </label>
          ))}
        </div>
      </div>
    </fieldset>
  )
}
