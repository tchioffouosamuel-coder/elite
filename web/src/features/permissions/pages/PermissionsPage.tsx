import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ShieldCheck, Save, RotateCcw, Users } from 'lucide-react'
import { clsx } from 'clsx'
import { PageHeader } from '@/shared/ui/PageHeader'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Spinner, ErrorState, EmptyState } from '@/shared/ui/Feedback'
import { succes, erreur, confirmer } from '@/shared/lib/alertes'
import { useAuthStore } from '@/shared/store/authStore'
import { fetchMatricePermissions, updatePermissionsFonction } from '@/features/permissions/api'
import { fetchMe } from '@/features/auth/api'
import { SchoolSwitcher } from '@/app/SchoolSwitcher'
import type { ApiError } from '@/shared/types/api'

/**
 * Écran d'administration des privilèges, réservé au super administrateur.
 *
 * On édite une fonction à la fois plutôt qu'une grande grille fonctions ×
 * privilèges : la grille tiendrait mal sur un écran (12 fonctions × 25
 * privilèges) et surtout elle ne dirait pas combien d'agents chaque
 * modification touche, alors que c'est la seule information qui compte avant
 * d'enregistrer.
 */
export function PermissionsPage() {
  const queryClient = useQueryClient()
  const activeSchoolId = useAuthStore((s) => s.activeSchoolId)
  const refreshUser = useAuthStore((s) => s.refreshUser)
  const [searchParams] = useSearchParams()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['permissions', activeSchoolId],
    queryFn: fetchMatricePermissions,
  })

  const [fonctionId, setFonctionId] = useState<number | null>(null)
  const [selection, setSelection] = useState<Set<string>>(new Set())

  const fonctionActive = useMemo(
    () => data?.fonctions.find((f) => f.id === fonctionId) ?? null,
    [data, fonctionId],
  )

  // Sélectionne la fonction visée par l'URL (venant de sa fiche détail), sinon
  // la première au chargement, et recale la case à cocher sur les privilèges
  // réellement enregistrés à chaque changement de fonction.
  useEffect(() => {
    if (!data) return
    const idParam = Number(searchParams.get('fonction'))
    const cible =
      data.fonctions.find((f) => f.id === fonctionId) ??
      (idParam ? data.fonctions.find((f) => f.id === idParam) : undefined) ??
      data.fonctions[0]
    if (!cible) return

    if (cible.id !== fonctionId) setFonctionId(cible.id)
    setSelection(new Set(cible.permissions))
  }, [data, fonctionId, searchParams])

  const modifie = useMemo(() => {
    if (!fonctionActive) return false
    const enregistres = new Set(fonctionActive.permissions)
    return enregistres.size !== selection.size || [...selection].some((p) => !enregistres.has(p))
  }, [fonctionActive, selection])

  const mutation = useMutation({
    mutationFn: (permissions: string[]) => updatePermissionsFonction(fonctionId!, permissions),
    onSuccess: async ({ message }) => {
      succes(message)
      await queryClient.invalidateQueries({ queryKey: ['permissions'] })

      // Le super administrateur peut modifier la fonction dont il dépend :
      // sans resynchronisation, l'interface continuerait d'afficher des
      // entrées de menu que l'API refuse déjà.
      refreshUser(await fetchMe())
    },
    onError: (e: ApiError) => erreur(e.message),
  })

  const basculer = (code: string) =>
    setSelection((courante) => {
      const suivante = new Set(courante)
      if (suivante.has(code)) suivante.delete(code)
      else suivante.add(code)
      return suivante
    })

  const basculerModule = (codes: string[], tousCoches: boolean) =>
    setSelection((courante) => {
      const suivante = new Set(courante)
      for (const code of codes) {
        if (tousCoches) suivante.delete(code)
        else suivante.add(code)
      }
      return suivante
    })

  const enregistrer = async () => {
    if (!fonctionActive) return

    // Un retrait de privilège se répercute sur tous les agents portant la
    // fonction : il vaut mieux le dire avant qu'après.
    const retires = fonctionActive.permissions.filter((p) => !selection.has(p))
    if (retires.length > 0 && fonctionActive.personnels_count > 0) {
      const ok = await confirmer({
        titre: `Retirer ${retires.length} privilège(s) ?`,
        message: `${fonctionActive.personnels_count} agent(s) portant la fonction « ${fonctionActive.label} » perdront ces accès immédiatement.`,
        action: 'Enregistrer',
      })
      if (!ok) return
    }

    mutation.mutate([...selection])
  }

  if (isLoading) return <Spinner />
  if (isError || !data) return <ErrorState />

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre="Privilèges et permissions"
        sousTitre="Chaque fonction porte un groupe de privilèges dont héritent tous les agents qui l'exercent — propres à l'établissement affiché ci-contre."
        icon={ShieldCheck}
        actions={
          <>
            {/* Le référentiel de fonctions est propre à chaque établissement : une
                même fonction (« Vendeur »…) existe une fois par école, avec ses
                propres privilèges. Sans ce sélecteur explicite, un super admin en
                mode agrégé retombait silencieusement sur une école par défaut et
                pouvait éditer la mauvaise fiche sans s'en apercevoir. */}
            <SchoolSwitcher />
            <Button
              variant="secondary"
              disabled={!modifie || mutation.isPending}
              onClick={() => setSelection(new Set(fonctionActive?.permissions ?? []))}
            >
              <RotateCcw className="h-4 w-4" />
              Annuler
            </Button>
            <Button disabled={!modifie || mutation.isPending} onClick={enregistrer}>
              <Save className="h-4 w-4" />
              Enregistrer
            </Button>
          </>
        }
      />

      {data.fonctions.length === 0 ? (
        <EmptyState label="Aucune fonction dans le référentiel de cet établissement." />
      ) : (
        <div className="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)]">
          <Card className="h-fit overflow-hidden p-0 lg:sticky lg:top-0 lg:self-start">
            <ul className="divide-y divide-navy-100/70 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
              {data.fonctions.map((fonction) => (
                <li key={fonction.id}>
                  <button
                    type="button"
                    onClick={() => setFonctionId(fonction.id)}
                    className={clsx(
                      'flex w-full items-center justify-between gap-3 px-4 py-3 text-left transition-colors first:rounded-t-2xl last:rounded-b-2xl',
                      fonction.id === fonctionId ? 'bg-navy-50 text-navy-900' : 'hover:bg-cream-50 text-navy-600',
                    )}
                  >
                    <span className="min-w-0">
                      <span className="block truncate text-sm font-semibold">{fonction.label}</span>
                      <span className="flex items-center gap-1 text-xs text-navy-400">
                        <Users className="h-3 w-3" />
                        {fonction.personnels_count} agent(s)
                      </span>
                    </span>
                    <span
                      className={clsx(
                        'flex-none rounded-lg px-2 py-0.5 text-xs font-semibold',
                        fonction.permissions.length === 0
                          ? 'bg-cream-100 text-navy-400'
                          : 'bg-gold-50 text-gold-700',
                      )}
                    >
                      {fonction.permissions.length}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </Card>

          <div className="flex flex-col gap-4">
            {data.modules.map((module) => {
              const codes = module.permissions.map((p) => p.code)
              const tousCoches = codes.every((c) => selection.has(c))

              return (
                <Card key={module.code}>
                  <div className="mb-3 flex items-center justify-between gap-3 border-b border-navy-100/70 pb-2.5">
                    <h2 className="font-display text-sm font-bold tracking-tight text-navy-900">{module.libelle}</h2>
                    <button
                      type="button"
                      onClick={() => basculerModule(codes, tousCoches)}
                      className="text-xs font-semibold text-navy-400 transition-colors hover:text-navy-700"
                    >
                      {tousCoches ? 'Tout décocher' : 'Tout cocher'}
                    </button>
                  </div>

                  <div className="grid gap-2 sm:grid-cols-2">
                    {module.permissions.map((permission) => (
                      <label
                        key={permission.code}
                        className="flex cursor-pointer items-start gap-2.5 rounded-xl px-2 py-1.5 transition-colors hover:bg-cream-50"
                      >
                        <input
                          type="checkbox"
                          checked={selection.has(permission.code)}
                          onChange={() => basculer(permission.code)}
                          className="mt-0.5 h-4 w-4 flex-none rounded border-navy-300 text-navy-700 focus:ring-navy-200"
                        />
                        <span className="min-w-0">
                          <span className="block text-sm text-navy-700">{permission.libelle}</span>
                          <code className="text-xs text-navy-300">{permission.code}</code>
                        </span>
                      </label>
                    ))}
                  </div>
                </Card>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
