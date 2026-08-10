import { useRef } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Image as ImageIcon, Trash2, Upload } from 'lucide-react'
import {
  fetchEcole,
  supprimerImageEcole,
  uploadImageEcole,
  type EcoleProfile,
  type ImageEcole,
} from '@/features/settings/api'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Spinner } from '@/shared/ui/Feedback'

const IMAGES: { type: ImageEcole; titre: string; aide: string; champ: keyof EcoleProfile }[] = [
  {
    type: 'logo',
    titre: 'Logo',
    aide: "Affiché dans l'en-tête des bulletins et sur le recto des cartes scolaires.",
    champ: 'logo_url',
  },
  {
    type: 'cachet',
    titre: 'Cachet',
    aide: 'Apposé sur le cartouche de visa des bulletins et au verso des cartes.',
    champ: 'cachet_url',
  },
  {
    type: 'signature',
    titre: 'Signature',
    aide: "Signature du chef d'établissement, apposée à côté du cachet.",
    champ: 'signature_url',
  },
]

/**
 * Import des images officielles de l'établissement. Le PNG à fond transparent
 * est recommandé pour le cachet et la signature, qui se superposent au document.
 */
export function EcoleImagesCard() {
  const { data: ecole, isLoading } = useQuery({ queryKey: ['ecole'], queryFn: fetchEcole })

  if (isLoading) return <Spinner />

  return (
    <Card className="flex flex-col gap-4">
      <div className="flex items-center gap-2">
        <ImageIcon className="h-4 w-4 text-gold-500" />
        <h2 className="font-display text-base font-bold text-navy-900">Logo, cachet et signature</h2>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        {IMAGES.map((image) => (
          <ChampImage key={image.type} image={image} url={(ecole?.[image.champ] as string | null) ?? null} />
        ))}
      </div>
    </Card>
  )
}

function ChampImage({
  image,
  url,
}: {
  image: { type: ImageEcole; titre: string; aide: string }
  url: string | null
}) {
  const queryClient = useQueryClient()
  const inputRef = useRef<HTMLInputElement>(null)

  const rafraichir = () => queryClient.invalidateQueries({ queryKey: ['ecole'] })

  const envoi = useMutation({ mutationFn: (file: File) => uploadImageEcole(image.type, file), onSuccess: rafraichir })
  const suppression = useMutation({ mutationFn: () => supprimerImageEcole(image.type), onSuccess: rafraichir })

  return (
    <div className="flex flex-col gap-2 rounded-xl border border-navy-100 p-3">
      <span className="text-xs font-bold uppercase tracking-wide text-navy-500">{image.titre}</span>

      <div className="flex h-28 items-center justify-center rounded-lg bg-cream-50">
        {url ? (
          <img src={url} alt={image.titre} className="max-h-24 max-w-full object-contain" />
        ) : (
          <span className="text-xs text-navy-300">Aucune image</span>
        )}
      </div>

      <p className="text-[11px] leading-snug text-navy-400">{image.aide}</p>

      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0]
          if (file) envoi.mutate(file)
          e.target.value = ''
        }}
      />

      <div className="flex gap-2">
        <Button size="sm" variant="secondary" onClick={() => inputRef.current?.click()} disabled={envoi.isPending}>
          <Upload className="h-3.5 w-3.5" />
          {envoi.isPending ? 'Envoi…' : url ? 'Remplacer' : 'Importer'}
        </Button>
        {url && (
          <Button
            size="sm"
            variant="secondary"
            onClick={() => suppression.mutate()}
            disabled={suppression.isPending}
            title="Supprimer"
          >
            <Trash2 className="h-3.5 w-3.5" />
          </Button>
        )}
      </div>
    </div>
  )
}
