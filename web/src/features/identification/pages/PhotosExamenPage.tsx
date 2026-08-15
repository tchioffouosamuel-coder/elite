import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, Download, ImageOff, ScanFace } from 'lucide-react'
import {
  fetchClassesExamen,
  fetchDossierExamen,
  telechargerArchiveExamen,
  type CandidatExamen,
} from '@/features/identification/apiPhotosExamen'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card, StatCard } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { PageHeader } from '@/shared/ui/PageHeader'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'
import { confirmer, erreur, info, succes } from '@/shared/lib/alertes'
import { estSecondaire } from '@/shared/lib/ecole'
import type { ApiError } from '@/shared/types/api'

/** Vignette d'un candidat, à l'image de ce que recevra l'organisme. */
function Vignette({ candidat }: { candidat: CandidatExamen }) {
  return (
    <div className="flex flex-col items-center gap-1.5">
      <div className="relative aspect-4/5 w-full overflow-hidden rounded-xl border border-navy-100 bg-cream-50 shadow-soft">
        {candidat.photo_prete && candidat.photo_url ? (
          <img src={candidat.photo_url} alt={candidat.nom_complet} className="h-full w-full object-cover" />
        ) : (
          <div className="flex h-full w-full flex-col items-center justify-center gap-1 text-navy-300">
            <ImageOff className="h-6 w-6" />
            <span className="text-[10px] font-semibold uppercase">Sans photo</span>
          </div>
        )}
        {!candidat.photo_prete && <span className="absolute inset-0 ring-2 ring-inset ring-red-300" />}
      </div>
      <span className="w-full truncate text-center text-xs font-semibold text-navy-800" title={candidat.nom_complet}>
        {candidat.nom_complet}
      </span>
      <span className="font-mono text-[10px] text-navy-400">{candidat.matricule ?? '—'}</span>
    </div>
  )
}

export function PhotosExamenPage() {
  const [classeId, setClasseId] = useState<number | ''>('')
  const [enCours, setEnCours] = useState(false)

  const { data: classes, isLoading: chargementClasses } = useQuery({
    queryKey: ['classes-examen'],
    queryFn: fetchClassesExamen,
  })

  // Une seule classe d'examen : la présélectionner évite un clic inutile.
  useEffect(() => {
    if (classeId === '' && classes?.length === 1) setClasseId(classes[0].id)
  }, [classes, classeId])

  const { data: dossier, isLoading } = useQuery({
    queryKey: ['dossier-examen', classeId],
    queryFn: () => fetchDossierExamen(Number(classeId)),
    enabled: classeId !== '',
  })

  const telecharger = async () => {
    if (!dossier) return

    if (dossier.manquants > 0) {
      const suite = await confirmer({
        titre: `${dossier.manquants} candidat(s) sans photo`,
        message:
          "Ils seront absents de l'archive. Vous pouvez générer un envoi partiel maintenant, ou charger les photos manquantes depuis la page Photos & cartes scolaires.",
        action: 'Générer quand même',
        destructif: false,
      })
      if (!suite) return
    }

    setEnCours(true)
    try {
      const { traites, ignores } = await telechargerArchiveExamen(Number(classeId))
      succes(`Archive générée : ${traites} photo(s).`)
      if (ignores > 0) info(`${ignores} candidat(s) écarté(s) faute de photo exploitable.`)
    } catch (e) {
      erreur((e as ApiError).message ?? "Génération de l'archive impossible.")
    } finally {
      setEnCours(false)
    }
  }

  if (chargementClasses) return <Spinner />

  return (
    <div className="flex flex-col gap-5">
      <PageHeader
        titre={estSecondaire() ? 'Photos DECC & OBC' : 'Photos DECC'}
        sousTitre="Photos des candidats aux examens officiels"
        icon={ScanFace}
        actions={
          dossier && (
            <Button onClick={telecharger} disabled={enCours || dossier.prets === 0}>
              <Download className="h-4 w-4" />
              {enCours ? 'Génération…' : "Télécharger l'archive"}
            </Button>
          )
        }
      />

      {!classes?.length ? (
        <Card>
          <EmptyState label="Aucune classe d'examen. Renseignez le code d'examen d'une classe (BEPC, Probatoire, BAC, CEP…) sur sa fiche pour l'inscrire ici." />
        </Card>
      ) : (
        <>
          <Select
            label="Classe d'examen"
            value={classeId}
            onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
            className="max-w-sm"
          >
            <option value="">Sélectionner une classe…</option>
            {classes.map((c) => (
              <option key={c.id} value={c.id}>
                {c.nom} — {c.code_examen} ({c.effectif} élève(s))
              </option>
            ))}
          </Select>

          {classeId === '' ? (
            <Card>
              <EmptyState label="Choisissez une classe pour afficher ses candidats." />
            </Card>
          ) : isLoading || !dossier ? (
            <Spinner />
          ) : (
            <>
              <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Candidats" value={dossier.candidats.length} accent="navy" />
                <StatCard label="Photos prêtes" value={dossier.prets} icon={CheckCircle2} accent="green" />
                <StatCard label="Sans photo" value={dossier.manquants} icon={ImageOff} accent="red" />
                <StatCard
                  label="Code examen"
                  value={dossier.code_examen ?? '—'}
                  accent="gold"
                  hint={dossier.centre ? `Centre ${dossier.centre}` : 'Centre non renseigné'}
                />
              </div>

              {!dossier.centre && (
                <Card className="flex items-start gap-3 border-gold-100 bg-gold-50/50">
                  <ImageOff className="mt-0.5 h-4 w-4 flex-none text-gold-600" />
                  <p className="text-sm text-navy-600">
                    Le <strong className="text-navy-800">code du centre d'examen</strong> n'est pas renseigné : il
                    apparaîtra vide sous chaque photo. Réglez-le dans Paramètres, section « Examens officiels ».
                  </p>
                </Card>
              )}

              <Card className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <h2 className="font-display text-base font-bold text-navy-900">Candidats</h2>
                  {dossier.manquants > 0 && (
                    <Badge tone="red">{dossier.manquants} sans photo</Badge>
                  )}
                </div>
                {/* Chaque photo est recomposée à la génération : fond blanc,
                    date, nom et code d'examen. La vignette montre la source. */}
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8">
                  {dossier.candidats.map((c) => (
                    <Vignette key={c.eleve_id} candidat={c} />
                  ))}
                </div>
              </Card>
            </>
          )}
        </>
      )}
    </div>
  )
}
