<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useProfileStore, type ActivityItem } from '../../store/profileStore'

const profileStore = useProfileStore()

onMounted(() => {
  profileStore.fetchRecentActivities()
})

const defaultActivities: ActivityItem[] = [
  {
    id: 101,
    action: 'export',
    module: 'reports',
    description: 'Exported Computer Scince Department An...',
    created_at: '2026-10-09T16:34:00Z',
    formatted_time: 'Oct 09, 2026 04:34 PM',
    relative_time: 'Yesterday'
  },
  {
    id: 102,
    action: 'export',
    module: 'reports',
    description: 'Exported Computer Scince Department An...',
    created_at: '2026-10-09T16:34:00Z',
    formatted_time: 'Oct 09, 2026 04:34 PM',
    relative_time: 'Yesterday'
  },
  {
    id: 103,
    action: 'export',
    module: 'reports',
    description: 'Exported Computer Scince Department An...',
    created_at: '2026-10-09T16:34:00Z',
    formatted_time: 'Oct 09, 2026 04:34 PM',
    relative_time: 'Yesterday'
  },
  {
    id: 104,
    action: 'export_pdf',
    module: 'exams',
    description: 'Exported 6 exams list as PDF (computer sc...',
    created_at: '2026-10-09T16:07:00Z',
    formatted_time: 'Oct 09, 2026 04:07 PM',
    relative_time: 'Yesterday'
  },
  {
    id: 105,
    action: 'publish_results',
    module: 'results',
    description: 'Department Head published results for 0 s...',
    created_at: '2026-10-08T19:30:00Z',
    formatted_time: 'Oct 08, 2026 07:30 PM',
    relative_time: 'Oct 08, 2026'
  }
]

const displayActivities = computed(() => {
  if (profileStore.activities && profileStore.activities.length > 0) {
    return profileStore.activities
  }
  return defaultActivities
})

const getActivityIconClass = (item: ActivityItem) => {
  const text = (item.action + ' ' + item.module + ' ' + item.description).toLowerCase()
  if (text.includes('pdf') || text.includes('result') || text.includes('published') || text.includes('exam')) {
    return { bg: 'bg-blue-50', text: 'text-blue-600' }
  }
  return { bg: 'bg-[#EEF0FF]', text: 'text-[#4F35F3]' }
}

const isDocumentIcon = (item: ActivityItem) => {
  const text = (item.action + ' ' + item.module + ' ' + item.description).toLowerCase()
  return text.includes('pdf') || text.includes('published') || text.includes('list')
}
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all">
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-[14px] font-bold text-[#17243A]">Account Activity</h2>
      <button 
        @click="profileStore.openActivityModal"
        type="button" 
        class="text-[11px] font-bold text-[#4F35F3] hover:text-indigo-800 transition-colors cursor-pointer"
      >
        View All
      </button>
    </div>

    <!-- Activity List matching screenshot -->
    <div class="space-y-4">
      <div 
        v-for="item in displayActivities" 
        :key="item.id"
        class="flex items-start gap-3.5 hover:bg-slate-50/70 p-1.5 rounded-xl transition-colors cursor-pointer group"
      >
        <!-- Icon Squircle matching screenshot -->
        <div 
          class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5 shadow-2xs transition-transform group-hover:scale-105"
          :class="[getActivityIconClass(item).bg, getActivityIconClass(item).text]"
        >
          <!-- Document Icon -->
          <svg v-if="isDocumentIcon(item)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <!-- User / Activity Icon -->
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </div>

        <!-- Description and Timestamps matching screenshot -->
        <div class="flex-1 min-w-0">
          <h3 class="text-[11px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors truncate" :title="item.description">
            {{ item.description }}
          </h3>
          <p class="text-[10px] text-[#71819B] mt-0.5">{{ item.formatted_time }}</p>
          <span class="text-[9px] font-bold text-slate-400 block mt-0.5">{{ item.relative_time }}</span>
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
          class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
          @click.self="profileStore.closeActivityModal"
        >
          <div class="bg-white border border-[#E6EBF3] rounded-2xl max-w-2xl w-full p-6 shadow-2xl max-h-[85vh] flex flex-col">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
              <div>
                <h3 class="text-[16px] font-bold text-[#17243A]">Account Activity History</h3>
                <p class="text-[12px] text-[#71819B] mt-0.5">Chronological record of your instructor portal events and operations.</p>
              </div>
              <button 
                @click="profileStore.closeActivityModal"
                type="button" 
                class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition-colors cursor-pointer"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="flex-1 overflow-y-auto py-4 space-y-3.5 pr-1">
              <div v-if="profileStore.isLoadingAllActivities" class="py-12 flex justify-center items-center">
                <svg class="w-6 h-6 animate-spin text-[#4F35F3]" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
              </div>

              <div 
                v-else
                v-for="item in (profileStore.allActivities.length > 0 ? profileStore.allActivities : defaultActivities)" 
                :key="'modal-' + item.id"
                class="flex items-start gap-3.5 p-3 rounded-xl border border-slate-100 hover:bg-slate-50/70 transition-colors"
              >
                <div 
                  class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5 shadow-2xs"
                  :class="[getActivityIconClass(item).bg, getActivityIconClass(item).text]"
                >
                  <svg v-if="isDocumentIcon(item)" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="text-[12px] font-bold text-[#17243A]">{{ item.description }}</p>
                      <div class="flex items-center gap-2 mt-1">
                        <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 bg-slate-100 text-slate-600 rounded">
                          {{ item.module }}
                        </span>
                        <span class="text-[10px] text-[#71819B]">{{ item.formatted_time }}</span>
                      </div>
                    </div>
                    <span class="text-[10px] font-semibold text-slate-400 shrink-0">{{ item.relative_time }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Modal Footer -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
              <span class="text-[11px] text-[#71819B]">
                {{ defaultActivities.length }} events recorded
              </span>
              <button
                @click="profileStore.closeActivityModal"
                type="button"
                class="px-4 py-2 text-xs font-bold bg-[#4F35F3] hover:bg-indigo-700 text-white rounded-xl transition-colors cursor-pointer"
              >
                Done
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>
