import { Download } from 'lucide-react'
import type { DocumentBibliothequeLecture } from '@/features/bibliotheque/api'
import { EmptyState } from '@/shared/ui/Feedback'

function formatTaille(octets: number): string {
  if (octets < 1024) return `${octets} o`
  if (octets < 1024 * 1024) return `${(octets / 1024).toFixed(0)} Ko`
  return `${(octets / (1024 * 1024)).toFixed(1)} Mo`
}

/** Liste en lecture seule des documents de la bibliothèque, partagée entre l'espace personnel et l'espace parent. */
export function BibliothequeListeLecture({ documents, messageVide }: { documents: DocumentBibliothequeLecture[]; messageVide: string }) {
  if (documents.length === 0) return <EmptyState label={messageVide} />

  return (
    <div className="flex flex-col gap-3">
      {documents.map((document) => (
        <div key={document.id} className="rounded-2xl border border-navy-100/70 bg-white p-4 shadow-card">
          <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
              <h2 className="font-display text-base font-bold text-navy-900">{document.titre}</h2>
              <p className="mt-0.5 text-xs text-navy-400">{formatTaille(document.taille)}</p>
            </div>
            <a
              href={document.fichier_url}
              target="_blank"
              rel="noreferrer"
              title="Télécharger"
              className="flex-none rounded-lg p-1.5 text-navy-400 transition-colors hover:bg-cream-100 hover:text-navy-700"
            >
              <Download className="h-4 w-4" />
            </a>
          </div>
          {document.description && <p className="mt-2 whitespace-pre-wrap text-sm text-navy-700">{document.description}</p>}
        </div>
      ))}
    </div>
  )
}
