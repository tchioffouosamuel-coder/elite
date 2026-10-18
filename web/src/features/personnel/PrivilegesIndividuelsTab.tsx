import { useState, useEffect, useMemo } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Save, RotateCcw } from 'lucide-react'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { succes, erreur } from '@/shared/lib/alertes'
import { fetchPermissionsUtilisateur, updatePermissionsUtilisateur } from '@/features/permissions/api'
import type { ApiError } from '@/shared/types/api'

/**
 * Privilèges accordés en propre à cet agent, en plus de ceux hérités de sa
 * fonction — le cumul est volontaire (cf. User::permissionsDeBase() côté
 * API) : une exception individuelle ne doit pas obliger à créer une fonction
 * rien que pour elle. Les privilèges de la fonction restent lecture seule
 * ici ; ils se gèrent sur l'écran « Privilèges » par fonction.
 */
export function PrivilegesIndividuelsTab({ personnelId }: { personnelId: number }) {
  const queryClient = useQueryClient()
  const { data, isLoading, isError } = useQuery({
    queryKey: ['permissions-utilisateur', personnelId],
    queryFn: () => fetchPermissionsUtilisateur(personnelId),
  })

  const [selection, setSelection] = useState<Set<string>>(new Set())

  useEffect(() => {
    if (data) setSelection(new Set(data.permissions_directes))
  }, [data])

  const herites = useMemo(() => new Set(data?.permissions_fonction ?? []), [data])

  const modifie = useMemo(() => {
    if (!data) return false
    const enregistres = new Set(data.permissions_directes)
    return enregistres.size !== selection.size || [...selection].some((p) => !enregistres.has(p))
  }, [data, selection])

  const mutation = useMutation({
    mutationFn: (permissions: string[]) => updatePermissionsUtilisateur(personnelId, permissions),
    onSuccess: ({ message }) => {
      succes(message)
      queryClient.invalidateQueries({ queryKey: ['permissions-utilisateur', personnelId] })
    },
    onError: (e: ApiError) => erreur(e.message),
  })

  const basculer = (code: string) => {
    if (herites.has(code)) return
    setSelection((courante) => {
      const suivante = new Set(courante)
      if (suivante.has(code)) suivante.delete(code)
      else suivante.add(code)
      return suivante
    })
  }

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  return (
    <div className="flex flex-col gap-4">
      <Card>
        <p className="text-sm text-navy-500">
          Privilèges accordés directement à <strong>{data.nom_complet}</strong>, en plus de ceux de sa fonction. Les
          privilèges hérités de la fonction (grisés) ne se retirent pas ici — modifiez la fonction elle-même pour ça.
        </p>
        <div className="mt-3 flex justify-end gap-2">
          <Button
            variant="secondary"
            disabled={!modifie || mutation.isPending}
            onClick={() => setSelection(new Set(data.permissions_directes))}
          >
            <RotateCcw className="h-4 w-4" />
            Annuler
          </Button>
          <Button disabled={!modifie || mutation.isPending} onClick={() => mutation.mutate([...selection])}>
            <Save className="h-4 w-4" />
            Enregistrer
          </Button>
        </div>
      </Card>

      {data.modules.map((module) => (
        <Card key={module.code}>
          <h2 className="mb-3 border-b border-navy-100/70 pb-2.5 font-display text-sm font-bold tracking-tight text-navy-900">
            {module.libelle}
          </h2>
          <div className="grid gap-2 sm:grid-cols-2">
            {module.permissions.map((permission) => {
              const estHerite = herites.has(permission.code)
              const coche = estHerite || selection.has(permission.code)

              return (
                <label
                  key={permission.code}
                  className={`flex items-start gap-2.5 rounded-xl px-2 py-1.5 transition-colors ${
                    estHerite ? 'cursor-not-allowed opacity-60' : 'cursor-pointer hover:bg-cream-50'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={coche}
                    disabled={estHerite}
                    onChange={() => basculer(permission.code)}
                    className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300 text-navy-700 focus:ring-navy-200"
                  />
                  <span className="min-w-0">
                    <span className="block text-sm text-navy-700">
                      {permission.libelle}
                      {estHerite && <span className="ml-1.5 text-xs text-navy-400">(fonction)</span>}
                    </span>
                    <code className="text-xs text-navy-300">{permission.code}</code>
                  </span>
                </label>
              )
            })}
          </div>
        </Card>
      ))}
    </div>
  )
}
