<template>
  <card>
    <heading :level="2" class="border-b border-40 py-4 px-4 flex ">
      <span class="inline-block font-semibold">{{ trans(group.display || 'Not grouped') }}</span>
      <span class="inline-block bg-primary text-white rounded-full px-2 py-1 text-sm font-semibold ml-3">{{ group.guard_name}}</span>
    </heading>

    <div v-if="loading" class="w-full p-4">
      <loader v-if="loading" width="60"></loader>
    </div>

    <div v-if="error && !loading" class="p-4 text-danger">
      {{ error }}
    </div>

    <no-result-card
      v-if="!roles.length && !loading"
      :label="trans('No role selected')"
    />

    <table
      v-if="roles.length && !loading && !error"
      class="table w-full overflow-x-scroll"
      :data-testid="`resource-table-${group.group}`"
    >
      <thead>
      <tr>
        <th class="w-1/6 bg-white text-left">{{ trans('Permissions') }}</th>
        <th v-for="role in roles" class="bg-white justify-center flex-col" :key="role.id">
          <div class="pb-2">
            <checkbox
              @input="toggleGroup(role)"
              :checked="roleChecked[role.id]"
            />
          </div>
          <div>{{ role.name }}</div>
        </th>
      </tr>
      </thead>
      <tbody class="w-full">
      <tr v-for="permission in permissions" :key="permission.id" class="w-full">
        <td class="w-1/6">{{ trans(permission.name) }}</td>
        <td v-for="role in roles" class="text-center" :key="role.id">
          <checkbox
            v-if="!loaders[permission.id][role.id]"
            @input="toggle(role, permission)"
            :checked="permission.roles[role.id]"
          />
          <template v-else>
            <loader width="30"></loader>
          </template>
        </td>
      </tr>
      </tbody>
    </table>
  </card>
</template>

<script>
import { Badge, InteractsWithQueryString, Minimum } from 'laravel-nova'
import CustomTranslation from './CustomTranslation'
import NoResultCard from './NoResultCard'
export default {
  props: {
    group: {
      type: Object
    },
    roles: {
      type: Array
    },
    search: {
      type: String,
      default: ''
    }
  },

  components: {
    Badge,
    NoResultCard
  },

  mixins: [
    CustomTranslation,
    InteractsWithQueryString,
  ],

  data: () => ({
    loading: false,
    error: null,
    permissions: [],
    loaders: {},
  }),

  async created() {
    await this.getPermissions()
  },

  computed: {
    authorizable() {
      return !!this.group.authorizable_id && !!this.group.authorizable_type
    },

    roleChecked() {
      let checked = {}

      _.each(this.roles, role => {
        checked[role.id] = true
        if (!this.permissions.length) {
          checked[role.id] = false
        } else {
          _.each(this.permissions, permission => {
            if (!permission.roles.hasOwnProperty(role.id) || !permission.roles[role.id]) {
              checked[role.id] = false
              return false
            }
          })
        }
      })

      return checked
    },

    permissionRequestQueryString() {
      return {
        search: this.search,
      }
    },
  },

  methods: {
    getPermissions() {
      this.loading = true

      this.$nextTick(() => {
        return Minimum(
          this.getRequest(),
          300
        ).then(({ data }) => {
          this.permissions = data

          _.each(this.permissions, permission => {
            this.loaders = Object.assign({}, this.loaders, { [permission.id]: {} })
            _.each(this.roles, role => {
              this.loaders[permission.id] = Object.assign({}, this.loaders[permission.id], { [role.id]: false })
            })
          })
        }).catch(error => this.error = error)
          .finally(() => this.loading = false)
      })
    },

    getRequest() {
      const { authorizable_id: id, authorizable_type: type, group, guard_name: guard} = this.group

      return this.authorizable
        ? Nova.request().post('/nova-vendor/nova-permission/permissions/authorizable', { guard, id, type }, {
          params: this.permissionRequestQueryString
        })
        : Nova.request().post('/nova-vendor/nova-permission/permissions/group', { guard, group }, {
          params: this.permissionRequestQueryString
        })
    },

    setLoader(value, roleId, permission = null) {
      let permissions = !!permission ? [permission] : this.permissions

      _.each(permissions, p => {
        this.loaders[p.id] = Object.assign({}, this.loaders[p.id], {[roleId]: value})
      })
    },

    attach(role, permissions, attach) {
      return Nova.request().post(`/nova-vendor/nova-permission/permissions/${role}/attach`, {
        attach: attach,
        permissions: permissions
      })
    },

    toggle(role, permission = null) {
      this.setLoader(true, role.id, permission)

      let newValue = !!permission ? !permission.roles[role.id] : !this.roleChecked[role.id]
      let permissions = !!permission ? [permission] : this.permissions

      this.$nextTick(() => {
        new Minimum(
          this.attach(role.id, permissions.map(p => p.id), newValue),
          300
        ).then(({ data }) => {
          _.each(permissions, permission => {
            permission.roles = Object.assign({}, permission.roles, { [role.id]: newValue })
          })
          this.$toasted.show(data.message, {type: 'success'})
        }).finally(() => this.setLoader(false, role.id, permission))
      })
    },

    toggleGroup(role) {
      this.toggle(role, null)
    },
  },
}
</script>

<style lang="scss" scoped>
  .table {
    tr {
      &:hover {
        td {
          background-color: inherit;
        }
      }
    }
  }
</style>
