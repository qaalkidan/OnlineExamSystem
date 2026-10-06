<script setup lang="ts">
import { onMounted } from 'vue'
import { useProfileStore, type ActivityItem } from '../../store/profileStore'

const profileStore = useProfileStore()

onMounted(() => {
  profileStore.fetchRecentActivities()
})

const getActivityIconClass = (item: ActivityItem) => {
  const text = (item.action + ' ' + item.module + ' ' + item.description).toLowerCase()
  if (text.includes('login') || text.includes('auth')) {
    return { bg: 'bg-emerald-50', text: 'text-emerald-500' }
  }
  if (text.includes('exam') || text.includes('question')) {
    return { bg: 'bg-indigo-50', text: 'text-[#5138ed]' }
  }
  if (text.includes('result') || text.includes('grade')) {
    return { bg: 'bg-orange-50', text: 'text-orange-500' }
  }
  if (text.includes('password') || text.includes('security')) {
    return { bg: 'bg-rose-50', text: 'text-rose-500' }
  }
  if (text.includes('profile') || text.includes('user') || text.includes('photo')) {
    return { bg: 'bg-blue-50', text: 'text-blue-500' }
  }
  return { bg: 'bg-purple-50', text: 'text-purple-500' }
}

const getActivityIcon = (item: ActivityItem) => {
  const text = (item.action + ' ' + item.module + ' ' + item.description).toLowerCase()
  if (text.includes('login') || text.includes('auth')) {
    return 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1'
  }
  if (text.includes('exam') || text.includes('question')) {
    return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
  }
  if (text.includes('result') || text.includes('grade')) {
    return 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'
  }
  if (text.includes('password') || text.includes('security')) {
    return 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
  }
  return 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-[14px] font-bold text-slate-800">Account Activity</h2>
      <button 
        @click="profileStore.openActivityModal"
        type="button" 
        class="text-[11px] font-bold text-[#5138ed] hover:underline cursor-pointer"
      >
        View All
      </button>
    </div>

    <!-- Empty State -->
    <div v-if="profileStore.activities.length === 0" class="py-6 text-center text-slate-400 text-[12px]">
      No recent activity recorded yet.
    </div>

    <!-- Activity List -->
    <div v-else class="space-y-6">
      <div 
        v-for="item in profileStore.activities" 
        :key="item.id"
        class="flex items-start gap-4"
      >
        <div 
          class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
          :class="[getActivityIconClass(item).bg, getActivityIconClass(item).text]"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActivityIcon(item)"></path>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
              <h3 class="text-[12px] font-bold text-slate-800 truncate">{{ item.description }}</h3>
              <p class="text-[11px] text-slate-500 mt-0.5">{{ item.formatted_time }}</p>
            </div>
            <span class="text-[10px] font-bold text-slate-400 shrink-0">{{ item.relative_time }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ========================================== -->
    <!-- VIEW ALL ACTIVITIES MODAL                  -->
    <!-- ========================================== -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div 
          v-if="profileStore.activityModalOpen"
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
          @click.self="profileStore.closeActivityModal"
        >
          <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl max-h-[85vh] flex flex-col">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
              <div>
                <h3 class="text-[16px] font-bold text-slate-800">Account Activity History</h3>
                <p class="text-[12px] text-slate-500 mt-0.5">Chronological record of your instructor portal events and logins.</p>
              </div>
              <button 
                @click="profileStore.closeActivityModal"
                type="button" 
                class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition-colors"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="flex-1 overflow-y-auto py-4 space-y-4 pr-1">
              <div v-if="profileStore.isLoadingAllActivities" class="py-12 flex justify-center items-center">
                <svg class="w-6 h-6 animate-spin text-[#5138ed]" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
              </div>

              <div v-else-if="profileStore.allActivities.length === 0" class="py-12 text-center text-slate-400 text-sm">
                No logs recorded yet.
              </div>

              <div 
                v-else
                v-for="item in profileStore.allActivities"
                :key="'modal-' + item.id"
                class="flex items-start gap-3.5 p-3 rounded-xl border border-slate-100 hover:bg-slate-50/60 transition-colors"
              >
                <div 
                  class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                  :class="[getActivityIconClass(item).bg, getActivityIconClass(item).text]"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActivityIcon(item)"></path>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="text-[13px] font-bold text-slate-800">{{ item.description }}</p>
                      <div class="flex items-center gap-2 mt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 bg-slate-100 text-slate-600 rounded">
                          {{ item.module }}
                        </span>
                        <span class="text-[11px] text-slate-400">{{ item.formatted_time }}</span>
                      </div>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-400 shrink-0">{{ item.relative_time }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer / Pagination -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[12px] text-slate-500">
                Page {{ profileStore.activityPagination.current_page }} of {{ profileStore.activityPagination.last_page }}
                ({{ profileStore.activityPagination.total }} events total)
              </span>
              <div class="flex items-center gap-2">
                <button
                  @click="profileStore.fetchAllActivities(profileStore.activityPagination.current_page - 1)"
                  :disabled="profileStore.activityPagination.current_page <= 1"
                  type="button"
                  class="px-3 py-1.5 text-xs font-bold text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                >
                  Previous
                </button>
                <button
                  @click="profileStore.fetchAllActivities(profileStore.activityPagination.current_page + 1)"
                  :disabled="profileStore.activityPagination.current_page >= profileStore.activityPagination.last_page"
                  type="button"
                  class="px-3 py-1.5 text-xs font-bold text-[#5138ed] border border-[#5138ed] rounded-lg hover:bg-indigo-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                >
                  Next
                </button>
              </div>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>
