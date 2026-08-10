export interface ApiResponse<T> {
  success: boolean
  data: T
  message: string
  errors: Record<string, string[]> | null
  meta: { pagination?: Pagination } | null
}

export interface Pagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface ApiError {
  message: string
  status: number
  errors: Record<string, string[]> | null
}
