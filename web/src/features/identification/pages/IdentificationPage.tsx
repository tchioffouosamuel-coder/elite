import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Camera, IdCard, Upload } from 'lucide-react'
import { fetchClasses } from '@/features/classes/api'
import { fetchEleves, uploadElevePhoto, type Eleve } from '@/features/eleves/api'
import { ouvrirDocument } from '@/shared/lib/download'
import { useAuthStore } from '@/shared/store/authStore'
import { Badge } from '@/shared/ui/Badge'
import { Button } from '@/shared/ui/Button'
import { Card } from '@/shared/ui/Card'
import { Select } from '@/shared/ui/Field'
import { EmptyState, Spinner } from '@/shared/ui/Feedback'

/**
 * Photos et cartes scolaires par classe — équivalent de manage_photos.php et
 * generate_IDcards_for_a_class.php dans _smapp : on suit la couverture photo de
 * la classe puis on édite la planche de cartes recto-verso en un seul PDF.
 */
export function IdentificationPage() {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const [classeId, setClasseId] = useState<number | ''>('')
  const [editionEnCours, setEditionEnCours] = useState(false)

  const { data: classes } = useQuery({ queryKey: ['classes'], queryFn: () => fetchClasses() })
  const classeActive = classeId ? Number(classeId) : null

  const { data, isLoading } = useQuery({
    queryKey: ['eleves-identification', classeActive],
    queryFn: () => fetchEleves({ classe_id: classeActive!, per_page: 200 }),
    enabled: classeActive !== null,
  })

  const eleves = data?.items ?? []
  const avecPhoto = eleves.filter((e) => e.photo_url).length

  const editerCartes = async () => {
    setEditionEnCours(true)
    try {
      await ouvrirDocument(`/classes/${classeActive}/cartes-scolaires`)
    } finally {
      setEditionEnCours(false)
    }
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <IdCard className="h-6 w-6 text-gold-500" />
          <h1 className="font-display text-2xl font-bold tracking-tight text-navy-900">{t('identification.title')}</h1>
        </div>
        {classeActive && (
          <Button onClick={editerCartes} disabled={editionEnCours || eleves.length === 0}>
            <IdCard className="h-4 w-4" />
            {editionEnCours ? t('identification.generating') : t('identification.edit_cards')}
          </Button>
        )}
      </div>

      <Select
        label={t('eleves.classe')}
        value={classeId}
        onChange={(e) => setClasseId(e.target.value ? Number(e.target.value) : '')}
        className="max-w-xs"
      >
        <option value="">{t('identification.select_classe_placeholder')}</option>
        {classes?.map((c) => (
          <option key={c.id} value={c.id}>
            {c.nom}
          </option>
        ))}
      </Select>

      {!classeActive ? (
        <Card>
          <EmptyState label={t('identification.empty_select_classe')} />
        </Card>
      ) : isLoading ? (
        <Spinner />
      ) : eleves.length === 0 ? (
        <Card>
          <EmptyState label={t('identification.empty_eleves_classe')} />
        </Card>
      ) : (
        <>
          <Card className="flex items-center gap-3 text-sm">
            <Camera className="h-4 w-4 text-gold-500" />
            <span className="font-medium text-navy-700">
              {t('identification.photo_coverage', { avec: avecPhoto, total: eleves.length })}
            </span>
            {avecPhoto < eleves.length && (
              <Badge tone="red">{t('identification.missing_count', { count: eleves.length - avecPhoto })}</Badge>
            )}
          </Card>

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            {eleves.map((eleve) => (
              <VignetteEleve key={eleve.id} eleve={eleve} classeId={classeActive} peutModifier={can('eleves.manage')} />
            ))}
          </div>
        </>
      )}
    </div>
  )
}

function VignetteEleve({
  eleve,
  classeId,
  peutModifier,
}: {
  eleve: Eleve
  classeId: number
  peutModifier: boolean
}) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const inputRef = useRef<HTMLInputElement>(null)

  const envoi = useMutation({
    mutationFn: (file: File) => uploadElevePhoto(eleve.id, file),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['eleves-identification', classeId] }),
  })

  return (
    <div className="flex flex-col items-center gap-2 rounded-2xl border border-navy-100 bg-white p-3">
      {eleve.photo_url ? (
        <img src={eleve.photo_url} alt={eleve.nom_complet} className="h-24 w-24 rounded-xl object-cover" />
      ) : (
        <div className="flex h-24 w-24 items-center justify-center rounded-xl bg-navy-800 text-lg font-bold text-gold-300">
          {eleve.nom_complet
            .split(' ')
            .map((p) => p[0])
            .slice(0, 2)
            .join('')}
        </div>
      )}

      <p className="w-full truncate text-center text-xs font-semibold text-navy-800">{eleve.nom_complet}</p>
      <p className="text-[11px] text-navy-400">{eleve.matricule ?? '—'}</p>

      {peutModifier && (
        <>
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
          <Button size="sm" variant="secondary" onClick={() => inputRef.current?.click()} disabled={envoi.isPending}>
            <Upload className="h-3.5 w-3.5" />
            {envoi.isPending ? t('identification.sending') : eleve.photo_url ? t('identification.replace_photo') : t('identification.add_photo')}
          </Button>
        </>
      )}
    </div>
  )
}
