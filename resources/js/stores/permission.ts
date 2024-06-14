import { ApiResponse, ErrorsBag, Group, Permission, Role } from '__types__'
import { AxiosResponse } from 'axios'
import _ from 'lodash'
import { acceptHMRUpdate, defineStore } from 'pinia'
import { client } from '@/helpers/client'

interface State {
  search?: string

  groups: Group[]
  roles: Role[]
  checked: Record<string, boolean>
  permissions: Record<number, Permission[]>

  error?: { attribute: string; bag?: ErrorsBag }

  ready: boolean
  isFetchingData: boolean
  isGeneratingPermissions: boolean

  dark?: boolean
}

const usePermissionStore = defineStore('permission', {
  state: (): State => ({
    search: undefined,

    groups: [],
    roles: [],
    checked: {},
    permissions: [],

    ready: false,
    isFetchingData: false,
    isGeneratingPermissions: false,

    dark: undefined,
  }),

  actions: {
    init() {
      // if we are already initialized, do nothing
      if (this.ready) {
        return
      }

      this.syncDarkMode()

      this.loadFromQueryString()

      this.ready = true
    },

    /**
     * A hook to listen to theme switch to sync the dark mode
     */
    syncDarkMode() {
      if (this.dark === undefined) {
        this.dark = document.documentElement.classList.contains('dark')
      }

      window.Nova.$on('nova-theme-switched', ({ theme }: { theme: 'dark' | 'light' }) => {
        this.dark = theme === 'dark'
      })
    },

    loadFromQueryString() {
      // grab all the query strings in the current url
      const searchParams = Object.fromEntries(new URLSearchParams(window?.location.search).entries())

      // loop on each query string
      for (const [key, value] of Object.entries(searchParams)) {
        // if we match one of these keys, we trigger the setter mutation
        if (['search'].includes(key)) {
          this.$patch({ [key]: value })
        }
      }
    },

    setError({ attribute, bag }: { attribute: string; bag?: ErrorsBag }) {
      this.error = { attribute, bag }
    },

    resetError() {
      this.error = undefined
    },

    /**
     * Update the current query strings with new parameters
     *
     * @param {{[key:string]: string|null}} parameters
     * @returns
     */
    setQueryString({ parameters }: { parameters: Record<string, string | number | null | undefined> }) {
      const searchParams = new URLSearchParams(window.location.search)

      const page = window.Nova.app.config.globalProperties.$inertia.page

      for (const [key, value] of Object.entries(parameters)) {
        const content = value?.toString()

        if (!content) {
          searchParams.delete(key)

          continue
        }

        if (content?.length > 0) {
          searchParams.set(key, content)
        }
      }

      if (page.url !== `${window.location.pathname}?${searchParams}`) {
        page.url = `${window.location.pathname}?${searchParams}`

        const separator = searchParams.toString().length > 0 ? '?' : ''

        window.history.pushState(page, '', `${window.location.pathname}${separator}${searchParams}`)
      }
    },

    reset() {
      const keys = ['search']

      keys.forEach(key => {
        this.$patch({
          [key]: null,
        })
      })
    },

    setSearch(search?: string) {
      this.search = search

      this.setQueryString({ parameters: { search } })
    },

    async data(): Promise<void> {
      this.isFetchingData = true

      const { data } = await this.get({
        path: '/groups',
        params: {
          search: this.search,
        },
      })

      this.groups = data.groups
      this.roles = data.roles

      _.each(this.roles, role => {
        this.checked = Object.assign({}, this.checked, { [role.id]: true })
      })

      this.isFetchingData = false
    },

    /**
     * GET request wrapper
     */
    async get({ path, params, options = {} }: { path?: string; params?: object; options?: object }) {
      return await client().get(`/nova-vendor/nova-permission${path ?? ''}`, {
        params,
        ...options,
      })
    },

    /**
     * POST request wrapper
     */
    async post<T>({ path, data }: { path?: string; data?: Record<string, any> }): Promise<AxiosResponse<T>> {
      return await client().post<T>(`/nova-vendor/nova-permission${path ?? ''}`, data)
    },

    toggle(role: string) {
      this.checked = Object.assign({}, this.checked, { [role]: !this.checked[role] })
    },

    async generatePermissions() {
      this.isGeneratingPermissions = true

      try {
        const { data } = await this.get({
          path: '/permissions/generate',
        })

        window.Nova.success(data.message)

        this.data()
      } catch (error: any) {
        console.error(error)
        window.Nova.error(error.message)
      }
    },

    async getPermissions(permissionGroup: Group) {
      const { authorizable_id: id, authorizable_type: type, group, guard_name: guard } = permissionGroup
      const authorizable = !!id && !!type

      const path = authorizable ? '/permissions/authorizable' : '/permissions/group'
      const data = authorizable ? { guard, id, type } : { guard, group }

      return this.post<ApiResponse>({
        path: `${path}?search=${this.search?.length ? this.search : ''}`,
        data,
      })
    },

    async attachPermissions({ role, permissions, value }: { role: number; permissions: Number[]; value: boolean }) {
      return this.post<ApiResponse>({
        path: `/permissions/${role}/attach`,
        data: {
          attach: value,
          permissions,
        },
      })
    },
  },
})

if (import.meta.hot) {
  import.meta.hot.accept(acceptHMRUpdate(usePermissionStore, import.meta.hot))
}

export default usePermissionStore
