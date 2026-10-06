import { useEffect, useRef, useState, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Camera, ImageUp, Loader2, Pencil, Share2, Trash2, X } from 'lucide-react'
import { useAuthStore } from '@/shared/store/authStore'
import { succes } from '@/shared/lib/alertes'
import { uploadElevePhoto, deleteElevePhoto } from '@/features/eleves/api'
import { PhotoCaptureModal } from '@/features/parent/components/PhotoCaptureModal'

export function Photo({ url, nom, eleveId, onReplace, className = '', children }: {
  url: string
  nom: string
  eleveId?: number
  onReplace?: (file: File) => Promise<void>
  children: ReactNode
  className?: string
}) {
  const { t } = useTranslation()
  const can = useAuthStore((s) => s.can)
  const queries = useQueryClient()
  const [open, setOpen] = useState(false)
  const [camera, setCamera] = useState(false)
  const [busy, setBusy] = useState(false)
  const [failed, setFailed] = useState(false)
  const [confirmDelete, setConfirmDelete] = useState(false)
  const [message, setMessage] = useState('')
  const dialog = useRef<HTMLDialogElement>(null)
  const input = useRef<HTMLInputElement>(null)
  const menu = useRef<HTMLDetailsElement>(null)
  const editable = eleveId !== undefined && can('eleves.update')

  useEffect(() => {
    if (open && !camera) dialog.current?.showModal()
  }, [open, camera])

  const refresh = async () => {
    const keys = ['eleve', 'eleves', 'eleves-identification', 'eleves-identification-summary', 'dossier-examen', 'classes-examen', 'parent-enfants', 'parent-enfant', 'eleve-moi']
    await Promise.all(keys.map((key) => queries.invalidateQueries({ queryKey: [key] })))
  }
  const replace = async (file: File) => {
    if (!['image/jpeg', 'image/png'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      setMessage(t('photos.invalid'))
      return
    }
    setBusy(true)
    setMessage('')
    try {
      if (onReplace) await onReplace(file)
      else if (editable) {
        await uploadElevePhoto(eleveId!, file)
        succes(t('eleves.photo_updated'))
      } else return
      await refresh()
      setOpen(false)
      setCamera(false)
    } catch (error) {
      setMessage(error && typeof error === 'object' && 'message' in error ? String(error.message) : t('photos.action_error'))
    } finally { setBusy(false) }
  }
  const remove = async () => {
    if (!editable) return
    setBusy(true)
    try {
      await deleteElevePhoto(eleveId!)
      await refresh()
      succes(t('eleves.photo_deleted'))
      setOpen(false)
    } catch (error) {
      setMessage(error && typeof error === 'object' && 'message' in error ? String(error.message) : t('photos.action_error'))
    } finally { setBusy(false) }
  }
  const share = async () => {
    setBusy(true)
    try {
      // Public photo URLs must not receive the API client's authorization header.
      const response = await fetch(url)
      if (!response.ok) throw new Error(t('photos.error'))
      const blob = await response.blob()
      if (!blob.type.startsWith('image/')) throw new Error(t('photos.error'))
      const extension = blob.type === 'image/png' ? 'png' : blob.type === 'image/webp' ? 'webp' : 'jpg'
      const file = new File([blob], `photo.${extension}`, { type: blob.type })
      if (navigator.canShare?.({ files: [file] })) await navigator.share({ files: [file], title: nom })
      else if (navigator.share) await navigator.share({ url, title: nom })
      else {
        const objectUrl = URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = objectUrl
        link.download = file.name
        link.click()
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
      }
    } catch (error) {
      if (!(error instanceof DOMException && error.name === 'AbortError')) setMessage(t('photos.error'))
    } finally { setBusy(false) }
  }
  const close = () => { if (!busy) { setOpen(false); setCamera(false) } }
  const buttonClass = 'flex h-11 w-11 flex-none items-center justify-center rounded-lg hover:bg-gray-100 disabled:opacity-40'

  return <>
    <button type="button" className={`inline-flex flex-none cursor-zoom-in rounded-[inherit] focus-visible:outline-2 focus-visible:outline-offset-2 ${className}`} title={t('photos.view')} aria-label={`${t('photos.view')} : ${nom}`} onClick={(event) => { event.stopPropagation(); setFailed(false); setMessage(''); setConfirmDelete(false); setOpen(true) }}>{children}</button>
    {open && camera && <PhotoCaptureModal titre={nom} errorMessage={message} onClose={() => { if (!busy) setCamera(false) }} onValider={replace} />}
    {open && !camera && createPortal(
      <dialog ref={dialog} aria-label={`${t('eleves.photo_title')} : ${nom}`} onCancel={(event) => { event.preventDefault(); close() }} onClick={(event) => {
        event.stopPropagation()
        if (menu.current && !menu.current.contains(event.target as Node)) menu.current.open = false
        if (event.target === event.currentTarget) close()
      }} className="m-auto w-[calc(100%-24px)] max-w-3xl overflow-hidden rounded-lg bg-white p-0 text-gray-900 shadow-xl backdrop:bg-black/60">
        <header className="flex items-center gap-2 border-b px-4 py-2"><h2 className="min-w-0 flex-1 truncate font-semibold">{nom}</h2><button type="button" className={buttonClass} title={t('common.close')} aria-label={t('common.close')} disabled={busy} onClick={close}><X size={20} /></button></header>
        <div className="flex h-[min(62svh,560px)] items-center justify-center overflow-auto bg-gray-950">
          {failed ? <p role="alert" className="p-4 text-white">{t('photos.error')}</p> : <img src={url} alt={nom} className="h-full w-full object-contain" onError={() => setFailed(true)} />}
        </div>
        {message && <p role="alert" className="px-4 py-2 text-sm text-red-700">{message}</p>}
        {confirmDelete && <div className="border-t px-4 py-3"><p className="mb-3 text-sm">{t('eleves.photo_delete_message')}</p><div className="flex justify-end gap-2"><button type="button" disabled={busy} className="rounded-lg border px-3 py-2" onClick={() => setConfirmDelete(false)}>{t('common.cancel')}</button><button type="button" disabled={busy} className="rounded-lg bg-red-600 px-3 py-2 text-white" onClick={() => void remove()}>{t('common.delete')}</button></div></div>}
        <footer className="flex min-h-16 items-center justify-end gap-2 px-4 py-2" aria-busy={busy}>
          {busy && <Loader2 className="mr-auto animate-spin" aria-label={t('photos.sending')} />}
          {(editable || onReplace) && <details ref={menu} className="relative">
            <summary aria-label={t('common.edit')} title={t('common.edit')} className={`${buttonClass} list-none cursor-pointer`}><Pencil size={20} /></summary>
            <div className="absolute bottom-12 right-0 z-10 min-w-48 rounded-lg border bg-white p-1 shadow-lg">
              <button type="button" disabled={busy} className="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 hover:bg-gray-100" onClick={() => input.current?.click()}><ImageUp size={18} />{t('photos.gallery')}</button>
              <button type="button" disabled={busy} className="flex w-full items-center gap-2 whitespace-nowrap px-3 py-2 hover:bg-gray-100" onClick={() => setCamera(true)}><Camera size={18} />{t('photos.camera')}</button>
            </div>
          </details>}
          {editable && <button type="button" disabled={busy || confirmDelete} className={`${buttonClass} text-red-600`} title={t('common.delete')} aria-label={t('common.delete')} onClick={() => setConfirmDelete(true)}><Trash2 size={20} /></button>}
          <button type="button" disabled={busy || failed} className={buttonClass} title={t('photos.share')} aria-label={t('photos.share')} onClick={() => void share()}><Share2 size={20} /></button>
          <input ref={input} type="file" accept="image/jpeg,image/png" aria-label={t('photos.gallery')} className="hidden" onChange={(event) => { const file = event.target.files?.[0]; event.target.value = ''; if (file) void replace(file) }} />
        </footer>
      </dialog>, document.body)}
  </>
}
