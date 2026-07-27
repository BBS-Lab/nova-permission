<template>
  <div class="nova-permission">
    <div :class="{ dark }">
      <heading :level="1" class="mb-3 flex items-center">
        {{ __('Permission builder') }}
      </heading>

      <div class="flex gap-2 mb-6">
        <!-- Search -->
        <div class="relative h-9 w-full md:w-1/3 md:shrink-0">
          <IndexSearchInput class="!w-full" v-model="keyword" />
          <div v-if="searching" class="absolute inset-y-0 right-3 flex items-center">
            <loader width="20" />
          </div>
        </div>

        <div class="inline-flex items-center gap-2 ml-auto">
          <button
            class="shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold"
            @click="generatePermissions"
          >
            <loader v-if="store.isGeneratingPermissions" width="30"></loader>
            <span v-else>{{ __('Generate permissions') }}</span>
          </button>
        </div>
      </div>

      <loading-view :loading="store.isFetchingData">
        <card v-if="error" class="p-4 text-danger">
          {{ error }}
        </card>

        <card class="mb-8">
          <heading :level="2" class="border-b border-gray-200 dark:border-gray-700 py-4 px-4 font-semibold">
            Roles
          </heading>
          <div class="">
            <table class="w-full">
              <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                  <th class="w-[1%] uppercase text-transparent text-xxs tracking-wide p-3 pl-4 text-left">
                    {{ __('Permissions') }}
                  </th>
                  <th v-for="role in roles" :key="role.name" class="uppercase text-gray-500 text-xxs tracking-wide p-3">
                    <CheckboxWithLabel
                      :name="role.name"
                      :checked="checked[role.id]"
                      @input="toggleRole(role.id)"
                      :disabled="false"
                      class="inline-block"
                    >
                      <span>{{ role.name }}</span>
                    </CheckboxWithLabel>
                  </th>
                </tr>
              </thead>
            </table>
          </div>
        </card>

        <card v-if="!store.isFetchingData && !store.groups.length">
          <no-result-card :label="__('No permission or group match the given criteria')" />
        </card>

        <permission-group
          v-for="(group, index) in availableGroups"
          :key="groupKey(group)"
          :class="{ 'mb-8': index < store.groups.length - 1 }"
          :group="group"
          :roles="availableRoles"
        />
      </loading-view>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Group } from '__types__'
import _ from 'lodash'
import { computed, onMounted, ref, watch } from 'vue'
import NoResultCard from '@/components/NoResultCard.vue'
import PermissionGroup from '@/components/PermissionGroup.vue'
import { useTranslation } from '@/hooks'
import usePermissionStore from '@/stores/permission'

const { __ } = useTranslation()

const store = usePermissionStore()
// Initialise early (reads ?search= from the URL + syncs dark mode) so the search
// box below can seed its value from the store on first render.
store.init()

// ACTIONS
const generatePermissions = () => store.generatePermissions()
const toggleRole = (role: string) => store.toggle(role)
const groupKey = (group: Group) =>
  [group.group, group.authorizable_type, group.authorizable_id, group.guard_name].join('|')

// STATE
const error = ref<Error | null>(null)

// STATE (search)
// `keyword` mirrors the input immediately; the commit to the store (which
// triggers the re-fetch) is debounced so we don't hit the API on every keystroke.
const keyword = ref<string>(store.search ?? '')
const searching = ref<boolean>(false)
const commitSearch = _.debounce((value: string) => {
  store.setSearch(value)
  store.data().finally(() => {
    searching.value = false
  })
}, 500)

// COMPUTED
const availableGroups = computed(() => _.sortBy(store.groups, g => g.display))
const roles = computed(() => store.roles)
const checked = computed(() => store.checked)
const availableRoles = computed(() => store.roles.filter(role => store.checked[role.id]))
const dark = computed(() => store.dark)

// HOOKS
onMounted(() => {
  store.data()
})

watch(keyword, value => {
  searching.value = true
  commitSearch(value)
})
</script>

<style scoped></style>
