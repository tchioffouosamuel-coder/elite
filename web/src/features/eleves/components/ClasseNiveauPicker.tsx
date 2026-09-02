import { useEffect, useMemo } from 'react'
import { clsx } from 'clsx'
import { Select } from '@/shared/ui/Field'
import type { Classe, Niveau } from '@/features/classes/api'

/**
 * Sélection de la classe en deux temps : le niveau d'abord, puis la classe au
 * sein de ce niveau — avec comparaison des effectifs par classe pour guider
 * le choix. Même principe que l'inscription : quand un seul niveau ne compte
 * qu'une classe, elle est retenue automatiquement sans faire choisir l'admin.
 */
export function ClasseNiveauPicker({
  niveaux,
  classes,
  niveauId,
  onChangeNiveauId,
  classeId,
  onChangeClasseId,
  hint = 'La classe pourra être modifiée ultérieurement dans les paramètres de l\'élève.',
  niveauLabel = 'Niveau',
  niveauPlaceholder = 'Sélectionner un niveau…',
  aucuneClasseLabel = 'Aucune classe pour ce niveau.',
  classeUniqueLabel = 'Classe retenue pour ce niveau :',
  effectifsTitle = 'Effectifs des classes de ce niveau',
  effectifsHint = 'Comparez les effectifs puis choisissez la classe de destination.',
}: {
  niveaux: Niveau[] | undefined
  classes: Classe[] | undefined
  niveauId: number | undefined
  onChangeNiveauId: (id: number | undefined) => void
  classeId: number | undefined
  onChangeClasseId: (id: number | undefined) => void
  hint?: string
  niveauLabel?: string
  niveauPlaceholder?: string
  aucuneClasseLabel?: string
  classeUniqueLabel?: string
  effectifsTitle?: string
  effectifsHint?: string
}) {
  const classesDuNiveau = useMemo(
    () => (niveauId ? classes?.filter((c) => c.niveau_id === niveauId) ?? [] : []),
    [classes, niveauId],
  )

  // Une seule classe pour ce niveau : on ne fait pas choisir l'utilisateur.
  useEffect(() => {
    if (classesDuNiveau.length === 1) onChangeClasseId(classesDuNiveau[0].id)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [classesDuNiveau])

  return (
    <div className="flex flex-col gap-2">
      <Select
        label={niveauLabel}
        value={niveauId ?? ''}
        onChange={(e) => {
          const val = e.target.value ? Number(e.target.value) : undefined
          onChangeNiveauId(val)
          onChangeClasseId(undefined)
        }}
      >
        <option value="">{niveauPlaceholder}</option>
        {niveaux?.map((n) => (
          <option key={n.id} value={n.id}>
            {n.name_fr}
          </option>
        ))}
      </Select>
      <div className="mt-2 p-4 bg-blue-50 border border-blue-200 rounded-lg">
        <p className="text-sm text-blue-800">{hint}</p>
      </div>

      {niveauId && classesDuNiveau.length === 0 && (
        <p className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">{aucuneClasseLabel}</p>
      )}

      {niveauId && classesDuNiveau.length === 1 && (
        <p className="rounded-xl border border-navy-100 bg-navy-50 px-3 py-2 text-sm text-navy-700">
          {classeUniqueLabel} <span className="font-semibold">{classesDuNiveau[0].nom}</span>
        </p>
      )}

      {niveauId && classesDuNiveau.length > 1 && (
        <div className="flex flex-col gap-2 pt-2">
          <div>
            <span className="text-sm font-semibold text-navy-800">{effectifsTitle}</span>
            <p className="text-xs text-navy-400">{effectifsHint}</p>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {classesDuNiveau.map((c) => {
              const estSelectionnee = c.id === classeId
              return (
                <label
                  key={c.id}
                  className={clsx(
                    'flex cursor-pointer flex-col gap-3 rounded-2xl border p-4 shadow-soft transition-colors',
                    estSelectionnee ? 'border-navy-500 bg-navy-50' : 'border-navy-100 bg-white hover:border-navy-300',
                  )}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-semibold text-navy-900">{c.nom}</span>
                    <input
                      type="radio"
                      name="classe_id_niveau"
                      checked={estSelectionnee}
                      onChange={() => onChangeClasseId(c.id)}
                      className="h-4 w-4 flex-none text-navy-600 focus:ring-navy-300"
                    />
                  </div>
                  <dl className="grid grid-cols-3 gap-2 text-center text-xs">
                    <div>
                      <dt className="text-navy-400">Filles</dt>
                      <dd className="font-semibold tabular-nums text-navy-700">{c.filles ?? 0}</dd>
                    </div>
                    <div>
                      <dt className="text-navy-400">Garçons</dt>
                      <dd className="font-semibold tabular-nums text-navy-700">{c.garcons ?? 0}</dd>
                    </div>
                    <div>
                      <dt className="text-navy-400">Total</dt>
                      <dd className="font-semibold tabular-nums text-navy-900">{c.effectif ?? 0}</dd>
                    </div>
                  </dl>
                </label>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
