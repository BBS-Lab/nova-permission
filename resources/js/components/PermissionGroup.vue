<template>
  <card>
    <div class="flex items border-b border-gray-200 dark:border-gray-700 py-4 px-4 space-x-3">
      <heading :level="2">
        {{ __(group.display || 'Not grouped') }}
      </heading>
      <CircleBadge>{{ group.guard_name }}</CircleBadge>
    </div>

    <div v-if="loading" class="w-full p-4">
      <loader v-if="loading" width="60"></loader>
    </div>

    <div v-if="error && !loading" class="p-4 text-danger">
      {{ error }}
    </div>

    <no-result-card v-if="!roles.length && !loading" :label="__('No role selected')" />

    <table
      v-if="roles.length && !loading && !error"
      class="w-full divide-y divide-gray-100 dark:divide-gray-700 overflow-x-scroll"
      :data-testid="`resource-table-${group.group}`"
    >
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th class="w-[1%] uppercase text-gray-500 text-xxs tracking-wide p-3 pl-4 text-left">
            {{ __('Permissions') }}
          </th>
          <th v-for="role in roles" :key="role.id" class="uppercase text-gray-500 text-xxs tracking-wide p-3">
            <CheckboxWithLabel
              :name="role.name"
              :checked="roleChecked[role.id]"
              @input="toggleGroup(role)"
              :disabled="false"
              class="inline-block"
            >
              <span>{{ role.name }}</span>
            </CheckboxWithLabel>
          </th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <tr v-for="permission in permissions" :key="permission.id" class="w-full">
          <td
            class="w-[1%] white-space-nowrap dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-900 p-3 pl-4"
          >
            {{ __(permission.name) }}
          </td>
          <td
            v-for="role in roles"
            :key="role.id"
            class="dark:bg-gray-800 group-hover:bg-gray-50 dark:group-hover:bg-gray-900 p-3"
          >
            <checkbox
              v-if="!loaders[permission.id][role.id]"
              @input="toggle(role, permission)"
              :checked="permission.roles[role.id]"
            />
            <template v-else>
              <loader width="30" class="mx-0"></loader>
            </template>
          </td>
        </tr>
      </tbody>
    </table>
  </card>
</template>

<script setup lang="ts">
import { Group, Permission, Role } from '__types__'
import { computed, onMounted, ref } from 'vue'
import { useTranslation } from '@/hooks'
import usePermissionStore from '@/stores/permission'

interface Props {
  group: Group
  roles: Role[]
  search?: string
}

const props = withDefaults(defineProps<Props>(), {
  search: '',
})

const { __ } = useTranslation()

const store = usePermissionStore()

// ACTIONS
const getPermissions = () => {
  loading.value = true

  store
    .getPermissions(props.group)
    .then(response => {
      const result = response.data as unknown as Permission[]

      result.forEach((permission: Permission) => {
        loaders.value[permission.id] = {}
        props.roles.forEach((role: Role) => {
          loaders.value[permission.id][role.id] = false
        })
      })

      permissions.value = result
      loading.value = false
    })
    .catch((e: string) => {
      error.value = e
      loading.value = false
    })
}

const toggleGroup = (role: Role) => toggle(role, null)
const toggle = (role: Role, permission?: Permission) => {
  if (permission) {
    loaders.value[permission.id][role.id] = true
  }

  let newValue = permission ? !permission.roles[role.id] : !roleChecked.value[role.id]
  let p = permission ? [permission] : permissions.value

  store
    .attachPermissions({
      role: role.id,
      permissions: p.map((p: Permission) => p.id),
      value: newValue,
    })
    .then(response => {
      p.forEach((permission: Permission) => {
        permission.roles = Object.assign({}, permission.roles, { [role.id]: newValue })
      })

      window.Nova.success(response.data.message)
    })
    .catch((e: any) => {
      window.Nova.error(e.message)
    })
    .finally(() => {
      if (permission) {
        loaders.value[permission.id][role.id] = false
      }
    })
}

// STATE
const loading = ref<boolean>(false)
const permissions = ref<Permission[]>([])
const loaders = ref<{ [key: string]: { [key: string]: boolean } }>({})
const error = ref<string | null>(null)

// COMPUTED
const roleChecked = computed(() => {
  let checked: { [key: string]: boolean } = {}

  props.roles.forEach((role: Role) => {
    checked[role.id] = true

    if (!permissions.value.length) {
      checked[role.id] = false
    } else {
      permissions.value.forEach((permission: Permission) => {
        if (!Object.prototype.hasOwnProperty.call(permission.roles, role.id) || !permission.roles[role.id]) {
          checked[role.id] = false
          return false
        }
      })
    }
  })

  return checked
})

// HOOKS
onMounted(() => {
  getPermissions()
})
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
