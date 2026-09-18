import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, CheckCircle2, ClipboardCheck, Upload, UserRound, Users } from 'lucide-react'
import {
  fetchChampsManquants,
  completerEnfant,
  completerPhotoEnfant,
  completerTuteur,
  type ChampsManquants,
  type CompleterEnfantPayload,
  type CompleterTuteurPayload,
} from '@/features/parent/api'
import { libelleChamp } from '@/features/parent/champsManquants'
import { Card } from '@/shared/ui/Card'
import { Button } from '@/shared/ui/Button'
import { Input, Select, Textarea } from '@/shared/ui/Field'
import { Spinner, ErrorState } from '@/shared/ui/Feedback'
import { erreur, succes } from '@/shared/lib/alertes'
import type { ApiError } from '@/shared/types/api'

type Brouillon = Record<string, string>

/**
 * Écran de complétion : un formulaire par dossier incomplet (le tuteur
 * lui-même, puis chaque enfant), n'exposant que les champs réellement vides
 * — cf. `champsManquants()` côté API. Chaque section disparaît de la liste
 * dès qu'elle est soumise, via le rafraîchissement de la requête partagée
 * avec l'alerte du portail.
 */
export function ParentCompleterPage() {
  const { data, isLoading, isError } = useQuery({ queryKey: ['parent-champs-manquants'], queryFn: fetchChampsManquants })

  return (
    <div className="flex flex-col gap-5">
      <div>
        <Link to="/parent" className="mb-2 flex w-fit items-center gap-1.5 text-sm font-medium text-navy-500 hover:text-navy-700">
          <ArrowLeft className="h-4 w-4" />
          Mes enfants / My children
        </Link>
        <h1 className="flex items-center gap-2 font-display text-2xl font-bold tracking-tight text-navy-900">
          <ClipboardCheck className="h-6 w-6 text-gold-500" />
          Compléter mes informations / Complete my information
        </h1>
      </div>

      {isLoading ? (
        <Spinner />
      ) : isError || !data ? (
        <ErrorState />
      ) : data.total === 0 ? (
        <Card>
          <div className="flex flex-col items-center gap-2 py-6 text-center">
            <CheckCircle2 className="h-10 w-10 text-green-500" />
            <p className="font-semibold text-navy-800">Tout est à jour. / Everything is up to date.</p>
            <p className="text-sm text-navy-400">Aucune information manquante pour l'instant. / No missing information for now.</p>
          </div>
        </Card>
      ) : (
        <ChampsManquantsFormulaires data={data} />
      )}
    </div>
  )
}

function ChampsManquantsFormulaires({ data }: { data: ChampsManquants }) {
  return (
    <div className="flex flex-col gap-5">
      {data.tuteur && data.tuteur.champs.length > 0 && <TuteurSection champs={data.tuteur.champs} />}
      {data.enfants.map((e) => (
        <EnfantSection key={e.id} eleveId={e.id} nomComplet={e.nom_complet} champs={e.champs} />
      ))}
    </div>
  )
}

function TuteurSection({ champs }: { champs: string[] }) {
  const queryClient = useQueryClient()
  const [valeurs, setValeurs] = useState<Brouillon>({})
  const [envoi, setEnvoi] = useState(false)

  const soumettre = async () => {
    const payload: CompleterTuteurPayload = {}
    for (const champ of champs) {
      const valeur = valeurs[champ]?.trim()
      if (valeur) (payload as Record<string, string>)[champ] = valeur
    }
    if (Object.keys(payload).length === 0) {
      erreur('Renseignez au moins un champ. / Fill in at least one field.')
      return
    }

    setEnvoi(true)
    try {
      await completerTuteur(payload)
      succes('Vos coordonnées ont été complétées. / Your contact details were completed.')
      queryClient.invalidateQueries({ queryKey: ['parent-champs-manquants'] })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <UserRound className="h-4 w-4" />
        Vos coordonnées / Your contact details
      </h2>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {champs.map((champ) => (
          <ChampInput key={champ} champ={champ} valeur={valeurs[champ] ?? ''} onChange={(v) => setValeurs((s) => ({ ...s, [champ]: v }))} />
        ))}
      </div>
      <div className="mt-4 flex justify-end">
        <Button onClick={soumettre} disabled={envoi}>
          {envoi ? 'Envoi… / Sending…' : 'Enregistrer / Save'}
        </Button>
      </div>
    </Card>
  )
}

function EnfantSection({ eleveId, nomComplet, champs }: { eleveId: number; nomComplet: string; champs: string[] }) {
  const queryClient = useQueryClient()
  const [valeurs, setValeurs] = useState<Brouillon>({})
  const [envoi, setEnvoi] = useState(false)
  const [photo, setPhoto] = useState<File | null>(null)
  const [envoiPhoto, setEnvoiPhoto] = useState(false)

  const champsTexte = champs.filter((c) => c !== 'photo')
  const demandePhoto = champs.includes('photo')

  const soumettre = async () => {
    const payload: CompleterEnfantPayload = {}
    for (const champ of champsTexte) {
      const valeur = valeurs[champ]?.trim()
      if (valeur) (payload as Record<string, string>)[champ] = valeur
    }
    if (Object.keys(payload).length === 0) {
      erreur('Renseignez au moins un champ. / Fill in at least one field.')
      return
    }

    setEnvoi(true)
    try {
      await completerEnfant(eleveId, payload)
      succes('Informations complétées. / Information completed.')
      queryClient.invalidateQueries({ queryKey: ['parent-champs-manquants'] })
      queryClient.invalidateQueries({ queryKey: ['parent-enfant', eleveId] })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoi(false)
    }
  }

  const envoyerPhoto = async () => {
    if (!photo) return
    setEnvoiPhoto(true)
    try {
      await completerPhotoEnfant(eleveId, photo)
      succes('Photo ajoutée. / Photo added.')
      setPhoto(null)
      queryClient.invalidateQueries({ queryKey: ['parent-champs-manquants'] })
      queryClient.invalidateQueries({ queryKey: ['parent-enfant', eleveId] })
      queryClient.invalidateQueries({ queryKey: ['parent-enfants'] })
    } catch (err) {
      erreur((err as ApiError).message)
    } finally {
      setEnvoiPhoto(false)
    }
  }

  return (
    <Card>
      <h2 className="mb-4 flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-navy-500">
        <Users className="h-4 w-4" />
        {nomComplet}
      </h2>

      {demandePhoto && (
        <div className="mb-4 flex flex-wrap items-center gap-3 rounded-xl border border-navy-100 px-3.5 py-3">
          <span className="text-sm font-medium text-navy-700">Photo</span>
          <input
            type="file"
            accept="image/jpeg,image/png"
            onChange={(e) => setPhoto(e.target.files?.[0] ?? null)}
            className="text-xs text-navy-500 file:mr-2 file:rounded-lg file:border-0 file:bg-navy-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-navy-700"
          />
          <Button size="sm" variant="secondary" onClick={envoyerPhoto} disabled={!photo || envoiPhoto} className="ml-auto">
            <Upload className="h-3.5 w-3.5" />
            {envoiPhoto ? 'Envoi… / Sending…' : 'Ajouter / Add'}
          </Button>
        </div>
      )}

      {champsTexte.length > 0 && (
        <>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {champsTexte.map((champ) => (
              <ChampInput key={champ} champ={champ} valeur={valeurs[champ] ?? ''} onChange={(v) => setValeurs((s) => ({ ...s, [champ]: v }))} />
            ))}
          </div>
          <div className="mt-4 flex justify-end">
            <Button onClick={soumettre} disabled={envoi}>
              {envoi ? 'Envoi… / Sending…' : 'Enregistrer / Save'}
            </Button>
          </div>
        </>
      )}
    </Card>
  )
}

const CHAMPS_LONGS = new Set(['situation_sanitaire', 'allergies'])

function ChampInput({ champ, valeur, onChange }: { champ: string; valeur: string; onChange: (v: string) => void }) {
  const label = libelleChamp(champ)

  if (champ === 'sexe') {
    return (
      <Select label={label} value={valeur} onChange={(e) => onChange(e.target.value)}>
        <option value="">Sélectionner… / Select…</option>
        <option value="F">Féminin / Female</option>
        <option value="M">Masculin / Male</option>
      </Select>
    )
  }

  if (champ === 'date_naissance') {
    return <Input label={label} type="date" value={valeur} onChange={(e) => onChange(e.target.value)} />
  }

  if (champ === 'email') {
    return <Input label={label} type="email" value={valeur} onChange={(e) => onChange(e.target.value)} />
  }

  if (CHAMPS_LONGS.has(champ)) {
    return (
      <div className="sm:col-span-2">
        <Textarea label={label} value={valeur} onChange={(e) => onChange(e.target.value)} />
      </div>
    )
  }

  return <Input label={label} value={valeur} onChange={(e) => onChange(e.target.value)} />
}
