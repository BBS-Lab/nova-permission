import { AxiosError } from 'axios'

export type ApiError = AxiosError<ErrorsBag>

export type ApiResponse = {
  message: string
}

export type Breadcrumb = {
  id: string
  path: string
  name: string
  current: boolean
}

export type Errors = {
  [key: string]: string[]
}

export type ErrorsBag = {
  message: string
  errors: Errors
}

export type Group = {
  authorizable_id?: string | number
  authorizable_type?: string
  display: string
  guard_name: string
  group?: string
}

export type Pagination = {
  current_page: number
  last_page: number
  from: number
  to: number
  total: number
  links: object
}

export type Permission = {
  id: number
  name: string
  guard_name: string
  roles: Record<number, boolean>
}

export type Role = {
  id: number
  name: string
  guard_name: string
}
