import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { LayoutGrid, Plus, Trash2, Save } from 'lucide-react'
import {
  fetchChampsPersonnalises,
  enregistrerChampsPersonnalises,
  type ChampPersonnaliseDef,
} from '@/features/progression/api'
import { Button } from '@/shared/ui/Button'
import { Spinner } from '@/shared/ui/Feedback'
import { succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

const TYPES: Record<ChampPersonnaliseDef['type'], string> = {
  texte: 'Texte',
  nombre: 'Nombre',
  case: 'Case à cocher',
}

/**
 * Tableaux d'informations spécifiques : chaque matière définit ses propres
 * champs (projet de groupe, travaux pratiques…), remplis ensuite séance par
 * séance dans « Ma journée ».
 */
export function ChampsPersonnalisesEditor({ classeMatiereId }: { classeMatiereId: number }) {
  const { data, isLoading, refetch } = useQuery({
    queryKey: ['champs-personnalises', classeMatiereId],
    queryFn: () => fetchChampsPersonnalises(classeMatiereId),
  })

  const [champs, setChamps] = useState<ChampPersonnaliseDef[]>([])
  const [submitting, setSubmitting] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (data) setChamps(data)
  }, [data])

  const ajouter = () => setChamps((c) => [...c, { id: 0, libelle: '', type: 'texte' }])
  const modifier = (index: number, champ: Partial<ChampPersonnaliseDef>) =>
    setChamps((c) => c.map((item, i) => (i === index ? { ...item, ...champ } : item)))
  const supprimer = (index: number) => setChamps((c) => c.filter((_, i) => i !== index))

  const enregistrer = async () => {
    setSubmitting(true)
    setErreur(null)
    try {
      const valides = champs.filter((c) => c.libelle.trim() !== '')
      const maj = await enregistrerChampsPersonnalises(
        classeMatiereId,
        valides.map((c) => ({ id: c.id || undefined, libelle: c.libelle, type: c.type })),
      )
      setChamps(maj)
      succes('Champs personnalisés enregistrés.')
      refetch()
    } catch (e) {
      setErreur((e as ApiError).message)
    } finally {
      setSubmitting(false)
    }
  }

  if (isLoading) return <Spinner />

  return (
    <div className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
      <div className="mb-1 flex items-center justify-between">
        <h2 className="flex items-center gap-2 font-display text-base font-bold text-navy-800">
          <LayoutGrid className="h-4 w-4 text-navy-400" />
          Tableaux personnalisés
        </h2>
        <Button size="sm" onClick={enregistrer} disabled={submitting}>
          <Save className="h-3.5 w-3.5" />
          {submitting ? 'Enregistrement…' : 'Enregistrer'}
        </Button>
      </div>
      <p className="mb-3 text-xs text-navy-400">
        Champs saisis par l'enseignant à chaque séance, en plus de l'appel — projets de groupe, points particuliers…
      </p>

      {erreur && <p className="mb-2 text-sm text-red-500">{erreur}</p>}

      <div className="flex flex-col gap-2">
        {champs.map((champ, index) => (
          <div key={index} className="flex items-center gap-2">
            <input
              value={champ.libelle}
              onChange={(e) => modifier(index, { libelle: e.target.value })}
              placeholder="Ex. : Projet de groupe"
              className="min-w-0 flex-1 rounded-lg border border-navy-200 px-2.5 py-1.5 text-sm shadow-soft focus:border-navy-400 focus:outline-none focus:ring-2 focus:ring-navy-100"
            />
            <select
              value={champ.type}
              onChange={(e) => modifier(index, { type: e.target.value as ChampPersonnaliseDef['type'] })}
              className="flex-none rounded-lg border border-navy-200 bg-white px-2 py-1.5 text-xs shadow-soft focus:border-navy-400 focus:outline-none"
            >
              {Object.entries(TYPES).map(([cle, libelle]) => (
                <option key={cle} value={cle}>
                  {libelle}
                </option>
              ))}
            </select>
            <button
              onClick={() => supprimer(index)}
              className="flex-none rounded-lg p-1.5 text-navy-400 hover:bg-cream-100 hover:text-red-500"
            >
              <Trash2 className="h-3.5 w-3.5" />
            </button>
          </div>
        ))}

        {champs.length === 0 && <p className="py-2 text-center text-sm text-navy-400">Aucun champ défini.</p>}
      </div>

      <Button variant="secondary" size="sm" className="mt-3" onClick={ajouter}>
        <Plus className="h-3.5 w-3.5" />
        Ajouter un champ
      </Button>
    </div>
  )
}

