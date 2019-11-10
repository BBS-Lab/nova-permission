<template>
  <div>
    <heading class="mb-3">{{ trans('Permission builder') }}</heading>

    <div class="flex">
      <!-- Search -->
      <div class="relative h-9 flex-no-shrink mb-6">
        <icon type="search" class="absolute search-icon-center ml-3 text-70"></icon>

        <input
          data-testid="search-input"
          dusk="search"
          class="appearance-none form-search w-search pl-search shadow"
          :placeholder="trans('Search')"
          type="search"
          v-model="search"
          @keydown.stop="performSearch"
          @search="performSearch"
        />
      </div>

      <div class="flex-no-shrink ml-auto">
        <button class="btn btn-default btn-primary"
          @click="generatePermissions"
        >
          <loader v-if="generatingPermissions" width="30"></loader>
          <span v-else>{{ trans('Generate permissions') }}</span>
        </button>
      </div>
    </div>

    <loading-view :loading="loading">
      <card v-if="error" class="p-4 text-danger">
        {{ error }}
      </card>

      <card v-if="roles.length && !error">
        <heading :level="2" class="border-b border-40 py-4 px-4 font-semibold">
          Roles
        </heading>
        <div class="flex mb-8 flex-wrap pb-4">
          <div v-for="role in roles" class="inline-block px-4 pt-4">
            <checkbox-with-label
              :checked="checked[role.id]"
              @input="toggle(role.id)"
            >
              {{ role.name }}
            </checkbox-with-label>
          </div>
        </div>
      </card>

      <card v-if="!loading && !groups.length">
        <no-result-card :label="trans('No permission or group match the given criteria')" />
      </card>

      <permission-group
        v-for="(group, index) in groups"
        :key="index"
        :class="{'mb-8': index < groups.length - 1}"
        :group="group"
        :roles="availableRoles"
        :search="search"
      />
    </loading-view>
  </div>
</template>

<script>
import { InteractsWithQueryString, Minimum } from 'laravel-nova'
import CustomTranslation from './CustomTranslation'
import NoResultCard from './NoResultCard'
import PermissionGroup from './PermissionGroup'
export default {
  mixins: [
    CustomTranslation,
    InteractsWithQueryString,
  ],

  components: {
    NoResultCard,
    PermissionGroup
  },

  data: () => ({
    error: null,
    roles: [],
    groups: [],
    loading: false,
    checked: {},
    search: '',
    generatingPermissions: false,
  }),

  async created() {
    this.initializeSearchFromQueryString()

    await this.getGroups()

    this.$watch(
      () => {
        return (this.currentSearch)
      },
      () => {
        this.getGroups()
      }
    )
  },

  computed: {
    availableRoles() {
      return this.roles.filter(role => this.checked[role.id])
    },

    /**
     * Get the name of the search query string variable.
     */
    searchParameter() {
      return '_search'
    },

    /**
     * Get the current search value from the query string.
     */
    currentSearch() {
      return this.$route.query[this.searchParameter] || ''
    },

    /**
     * Build the resource request query string.
     */
    groupRequestQueryString() {
      return {
        search: this.currentSearch,
      }
    },

    /**
     * Determine if there are any groups for the view
     */
    hasGroups() {
      return Boolean(this.groups.length > 0)
    },
  },

  methods: {
    getGroups() {
      this.loading = true

      this.$nextTick(() => {
        return Minimum(
          Nova.request().get('/nova-vendor/nova-permission/groups', {
            params: this.groupRequestQueryString
          }),
          300
        ).then(({ data }) => {
          this.groups = data.groups
          this.roles = data.roles

          _.each(this.roles, role => {
            this.checked = Object.assign({}, this.checked, { [role.id]: true })
          })
        }).catch(error => this.error = error)
          .finally(() => this.loading = false)
      })
    },

    /**
     * Sync the current search value from the query string.
     */
    initializeSearchFromQueryString() {
      this.search = this.currentSearch
    },

    toggle(role) {
      this.checked = Object.assign({}, this.checked, {[role]: !this.checked[role]})
    },

    performSearch(event) {
      this.debouncer(() => {
        // Only search if we're not tabbing into the field
        if (event.which != 9) {
          this.updateQueryString({
            [this.searchParameter]: this.search,
          })
        }
      })
    },

    debouncer: _.debounce(callback => callback(), 500),

    generatePermissions() {
      this.generatingPermissions = true

      this.$nextTick(() => {
        new Minimum(
          Nova.request().get('/nova-vendor/nova-permission/permissions/generate'),
          300
        ).then(({ data }) => {
          this.$toasted.show(data.message, { type: 'success' })
          this.getGroups()
        }).catch(error => {
          this.$toasted.show(error, { type: 'error' })
        }).finally(() => this.generatingPermissions = false)
      })
    }
  },
}
</script>
