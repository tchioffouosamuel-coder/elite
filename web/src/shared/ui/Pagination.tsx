import type { Pagination as PaginationType } from '@/shared/types/api'
import { ChevronLeft, ChevronRight } from 'lucide-react'

export function Pagination({ pagination, onChange }: { pagination: PaginationType; onChange: (page: number) => void }) {
  if (pagination.last_page <= 1) return null

  return (
    <div className="flex items-center justify-between border-t border-navy-50 px-4 py-3.5 text-sm text-navy-500">
      <span>
        <span className="font-semibold text-navy-700">{pagination.total}</span> · {pagination.current_page}/{pagination.last_page}
      </span>
      <div className="flex gap-1.5">
        <button
          disabled={pagination.current_page <= 1}
          onClick={() => onChange(pagination.current_page - 1)}
          className="rounded-lg border border-navy-200 p-1.5 transition-colors hover:border-navy-300 hover:bg-cream-50 disabled:opacity-40 disabled:hover:border-navy-200 disabled:hover:bg-transparent"
        >
          <ChevronLeft className="h-4 w-4" />
        </button>
        <button
          disabled={pagination.current_page >= pagination.last_page}
          onClick={() => onChange(pagination.current_page + 1)}
          className="rounded-lg border border-navy-200 p-1.5 transition-colors hover:border-navy-300 hover:bg-cream-50 disabled:opacity-40 disabled:hover:border-navy-200 disabled:hover:bg-transparent"
        >
          <ChevronRight className="h-4 w-4" />
        </button>
      </div>
    </div>
  )
}
