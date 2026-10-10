<script setup lang="ts">
import { computed } from 'vue'
import { useInstructorExamStore } from '../../store/instructorExamStore'

const examStore = useInstructorExamStore()

const stats = computed(() => [
  {
    id: 'total',
    title: 'Total Exams',
    value: examStore.stats.total ?? 0,
    subtitle: 'All exams created',
    iconType: 'document',
    badge: 'Overall',
    badgeClass: 'bg-indigo-50 text-[#5138ed] border border-indigo-100',
    iconBg: 'bg-indigo-50/80 text-[#5138ed] border border-indigo-100/70',
  },
  {
    id: 'upcoming',
    title: 'Upcoming Exams',
    value: examStore.stats.upcoming ?? 0,
    subtitle: 'Scheduled exams',
    iconType: 'calendar',
    badge: 'Scheduled',
    badgeClass: 'bg-emerald-50 text-emerald-700 border border-emerald-100',
    iconBg: 'bg-emerald-50/80 text-emerald-600 border border-emerald-100/70',
  },
  {
    id: 'active',
    title: 'Active Exams',
    value: examStore.stats.active ?? 0,
    subtitle: 'Currently running',
    iconType: 'clock',
    badge: 'Live',
    badgeClass: 'bg-blue-50 text-blue-700 border border-blue-100',
    iconBg: 'bg-blue-50/80 text-blue-600 border border-blue-100/70',
  },
  {
    id: 'completed',
    title: 'Completed Exams',
    value: examStore.stats.completed ?? 0,
    subtitle: 'Finished exams',
    iconType: 'check-circle',
    badge: 'Ended',
    badgeClass: 'bg-amber-50 text-amber-700 border border-amber-100',
    iconBg: 'bg-amber-50/80 text-amber-600 border border-amber-100/70',
  },
  {
    id: 'draft',
    title: 'Draft Exams',
    value: examStore.stats.draft ?? 0,
    subtitle: 'Not yet published',
    iconType: 'pencil',
    badge: 'Unpublished',
    badgeClass: 'bg-rose-50 text-rose-700 border border-rose-100',
    iconBg: 'bg-rose-50/80 text-rose-600 border border-rose-100/70',
  },
  {
    id: 'archived',
    title: 'Archived Exams',
    value: examStore.stats.archived ?? 0,
    subtitle: 'Archived exams',
    iconType: 'archive',
    badge: 'Stored',
    badgeClass: 'bg-slate-100 text-slate-700 border border-slate-200',
    iconBg: 'bg-slate-100 text-slate-600 border border-slate-200/70',
  }
])
</script>

<template>
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 sm:gap-4">
    <div 
      v-for="stat in stats" 
      :key="stat.id"
      class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-xs hover:border-slate-300 hover:shadow-sm transition-all duration-200 flex flex-col justify-between"
    >
      <!-- Top Row: Icon & Title -->
      <div class="flex items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2.5 min-w-0">
          <div 
            class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs"
            :class="stat.iconBg"
          >
            <!-- Document Icon -->
            <svg v-if="stat.iconType === 'document'" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <!-- Calendar Icon -->
            <svg v-else-if="stat.iconType === 'calendar'" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <!-- Clock Icon -->
            <svg v-else-if="stat.iconType === 'clock'" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Check Circle Icon -->
            <svg v-else-if="stat.iconType === 'check-circle'" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Pencil Icon -->
            <svg v-else-if="stat.iconType === 'pencil'" class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <!-- Archive Icon -->
            <svg v-else class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
          </div>
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider truncate">{{ stat.title }}</span>
        </div>
      </div>

      <!-- Middle: Prominent Count -->
      <div class="my-1">
        <div v-if="examStore.isLoading" class="h-8 w-16 bg-slate-100 animate-pulse rounded-lg my-0.5"></div>
        <div v-else class="text-2xl sm:text-[26px] font-black text-slate-900 tracking-tight leading-tight truncate">
          {{ stat.value }}
        </div>
      </div>

      <!-- Bottom: Subtitle / Badge -->
      <div class="mt-2 pt-2 border-t border-slate-100 flex items-center justify-between text-xs gap-1.5 min-w-0">
        <span class="text-[11px] text-slate-400 font-medium truncate" :title="stat.subtitle">
          {{ stat.subtitle }}
        </span>
        <span 
          class="text-[9px] font-bold px-1.5 py-0.5 rounded shrink-0 uppercase tracking-wider"
          :class="stat.badgeClass"
        >
          {{ stat.badge }}
        </span>
      </div>
    </div>
  </div>
</template>
