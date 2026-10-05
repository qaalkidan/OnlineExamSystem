<script setup lang="ts">
import { computed } from 'vue'
import { useInstructorQbStore } from '../../store/instructorQbStore'

const qbStore = useInstructorQbStore()

const stats = computed(() => {
  const banks = qbStore.banks || []
  
  // Sort by created_at for Last Created
  const sortedByCreated = [...banks].sort((a, b) => new Date((b as any).created_at || 0).getTime() - new Date((a as any).created_at || 0).getTime())
  const lastCreated = sortedByCreated.length > 0 ? sortedByCreated[0] : null
  
  // Sort by updated_at for Last Updated
  const sortedByUpdated = [...banks].sort((a, b) => new Date(b.updated_at || 0).getTime() - new Date(a.updated_at || 0).getTime())
  const lastUpdated = sortedByUpdated.length > 0 ? sortedByUpdated[0] : null

  const formatDate = (dateString: string | undefined | null) => {
    if (!dateString) return '--'
    return new Date(dateString).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  }

  return [
    {
      title: 'Question Banks',
      value: qbStore.stats.total_banks || 0,
      subtitle: 'Total question banks',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>',
      colorClass: 'text-[#5138ed]',
      bgClass: 'bg-indigo-50'
    },
    {
      title: 'Total Questions',
      value: qbStore.stats.total_questions || 0,
      subtitle: 'Across all banks',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
      colorClass: 'text-emerald-500',
      bgClass: 'bg-emerald-50'
    },
    {
      title: 'Last Created',
      value: formatDate((lastCreated as any)?.created_at),
      subtitle: lastCreated?.title || 'No banks yet',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>',
      colorClass: 'text-blue-500',
      bgClass: 'bg-blue-50'
    },
    {
      title: 'Last Updated',
      value: formatDate(lastUpdated?.updated_at),
      subtitle: lastUpdated?.title || 'No banks yet',
      icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
      colorClass: 'text-orange-500',
      bgClass: 'bg-orange-50'
    }
  ]
})
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <div 
      v-for="(stat, index) in stats" 
      :key="index"
      class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-100 shadow-xs flex flex-col items-start gap-3 overflow-hidden"
    >
      <div class="flex flex-row items-center gap-3 sm:gap-4 w-full h-full">
        <div 
          class="w-11 h-11 sm:w-12 sm:h-12 xl:w-14 xl:h-14 rounded-2xl flex items-center justify-center shrink-0"
          :class="stat.bgClass"
        >
          <svg 
            class="w-5 h-5 sm:w-6 sm:h-6 xl:w-7 xl:h-7" 
            :class="stat.colorClass"
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
            v-html="stat.icon"
          ></svg>
        </div>

        <div class="flex flex-col w-full min-w-0 justify-center">
          <h3 class="text-xs sm:text-[13px] font-bold text-slate-500 mb-0.5 sm:mb-1 leading-tight truncate">{{ stat.title }}</h3>
          
          <div v-if="qbStore.isLoading" class="h-6 w-16 bg-slate-100 animate-pulse rounded mb-1"></div>
          <span v-else class="text-lg sm:text-xl xl:text-[22px] font-extrabold text-slate-800 leading-none mb-1 truncate">{{ stat.value }}</span>
          
          <span class="text-[11px] xl:text-xs text-slate-400 font-medium truncate">{{ stat.subtitle }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
