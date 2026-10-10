<script setup lang="ts">
import { computed } from 'vue'
import { useInstructorQbStore } from '../../store/instructorQbStore'

const qbStore = useInstructorQbStore()

const formatDate = (dateString: string | undefined | null) => {
  if (!dateString) return '—'
  try {
    const d = new Date(dateString)
    if (isNaN(d.getTime())) return '—'
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return '—'
  }
}

const stats = computed(() => {
  const banks = qbStore.banks || []
  
  // Sort by created_at for Last Created
  const sortedByCreated = [...banks].sort((a, b) => new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime())
  const lastCreated = sortedByCreated.length > 0 ? sortedByCreated[0] : null
  
  // Sort by updated_at for Last Updated
  const sortedByUpdated = [...banks].sort((a, b) => new Date(b.updated_at || 0).getTime() - new Date(a.updated_at || 0).getTime())
  const lastUpdated = sortedByUpdated.length > 0 ? sortedByUpdated[0] : null

  // Value and subtitle for Recently Created
  const createdRaw = qbStore.stats.last_created?.created_at || lastCreated?.created_at
  const createdDate = formatDate(createdRaw)
  const createdSubtitle = qbStore.stats.last_created?.title || lastCreated?.title || (banks.length > 0 ? 'Latest bank' : 'No banks recorded')

  // Value and subtitle for Last Updated
  const updatedRaw = qbStore.stats.last_updated?.updated_at || lastUpdated?.updated_at
  const updatedDate = formatDate(updatedRaw)
  const updatedSubtitle = qbStore.stats.last_updated?.title || lastUpdated?.title || (banks.length > 0 ? 'Latest modification' : 'No updates recorded')

  return [
    {
      id: 'banks',
      title: 'Question Banks',
      value: qbStore.stats.total_banks ?? banks.length ?? 0,
      subtitle: 'Total question banks',
      badge: 'Active Banks',
      badgeClass: 'bg-indigo-50 text-[#5138ed] border border-indigo-100',
      iconType: 'folder',
      iconBg: 'bg-indigo-50/80 text-[#5138ed] border border-indigo-100/70',
    },
    {
      id: 'questions',
      title: 'Total Questions',
      value: qbStore.stats.total_questions ?? 0,
      subtitle: 'Across all banks',
      badge: 'All Types',
      badgeClass: 'bg-emerald-50 text-emerald-700 border border-emerald-100',
      iconType: 'questions',
      iconBg: 'bg-emerald-50/80 text-emerald-600 border border-emerald-100/70',
    },
    {
      id: 'created',
      title: 'Last Created',
      value: createdDate,
      subtitle: createdSubtitle,
      badge: 'Latest',
      badgeClass: 'bg-blue-50 text-blue-700 border border-blue-100',
      iconType: 'calendar',
      iconBg: 'bg-blue-50/80 text-blue-600 border border-blue-100/70',
    },
    {
      id: 'updated',
      title: 'Last Updated',
      value: updatedDate,
      subtitle: updatedSubtitle,
      badge: 'Recent',
      badgeClass: 'bg-amber-50 text-amber-700 border border-amber-100',
      iconType: 'clock',
      iconBg: 'bg-amber-50/80 text-amber-600 border border-amber-100/70',
    }
  ]
})
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
    <div 
      v-for="stat in stats" 
      :key="stat.id"
      class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs hover:border-slate-300 hover:shadow-sm transition-all duration-200 flex flex-col justify-between"
    >
      <!-- Top row: Icon & Title -->
      <div class="flex items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-3 min-w-0">
          <div 
            class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl flex items-center justify-center shrink-0 shadow-2xs"
            :class="stat.iconBg"
          >
            <!-- Folder Icon -->
            <svg v-if="stat.iconType === 'folder'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <!-- Questions Icon -->
            <svg v-else-if="stat.iconType === 'questions'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <!-- Calendar Icon -->
            <svg v-else-if="stat.iconType === 'calendar'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <!-- Clock Icon -->
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <span class="text-xs font-bold text-slate-500 uppercase tracking-wider truncate">{{ stat.title }}</span>
        </div>
      </div>

      <!-- Middle: Prominent Value -->
      <div class="my-1">
        <div v-if="qbStore.isLoading" class="h-8 w-20 bg-slate-100 animate-pulse rounded-lg my-0.5"></div>
        <div v-else class="text-2xl sm:text-[26px] font-black text-slate-900 tracking-tight leading-tight truncate">
          {{ stat.value }}
        </div>
      </div>

      <!-- Bottom: Subtitle / Bank Title Badge -->
      <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-xs gap-2 min-w-0">
        <span class="text-slate-500 font-medium truncate" :title="stat.subtitle">
          {{ stat.subtitle }}
        </span>
        <span 
          v-if="stat.badge" 
          class="text-[10px] font-bold px-1.5 py-0.5 rounded-md shrink-0 uppercase tracking-wider"
          :class="stat.badgeClass"
        >
          {{ stat.badge }}
        </span>
      </div>
    </div>
  </div>
</template>
