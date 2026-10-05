<script setup lang="ts">
import type { Exam } from '../store/instructorStore'

const props = defineProps<{
  exams: Exam[]
  isLoading: boolean
}>()

const statusConfig: Record<string, { label: string; class: string }> = {
  draft:     { label: 'Draft',     class: 'bg-slate-100 text-slate-500' },
  published: { label: 'Published', class: 'bg-blue-50 text-blue-600' },
  scheduled: { label: 'Scheduled', class: 'bg-indigo-50 text-[#5138ed]' },
  completed: { label: 'Completed', class: 'bg-emerald-50 text-emerald-600' },
}

const formatDate = (iso: string | null) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short', day: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4 sm:mb-6">
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <h2 class="text-base sm:text-lg font-bold text-slate-800">Recent Exams</h2>
      </div>
      <router-link to="/instructor/exams" class="text-xs sm:text-sm font-semibold text-[#5138ed] bg-indigo-50 px-3 sm:px-4 py-1.5 rounded-lg hover:bg-indigo-100 transition-colors min-h-[36px] flex items-center">
        View All
      </router-link>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="space-y-4">
      <div v-for="i in 4" :key="i" class="flex gap-4 animate-pulse">
        <div class="h-4 bg-slate-100 rounded flex-1"></div>
        <div class="h-4 bg-slate-100 rounded w-32"></div>
        <div class="h-4 bg-slate-100 rounded w-28"></div>
        <div class="h-4 bg-slate-100 rounded w-20"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="exams.length === 0" class="text-center py-10 text-slate-400">
      <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p class="font-medium text-sm">No exams created yet.</p>
    </div>

    <!-- Content: Desktop Table & Mobile Cards -->
    <div v-else>
      <!-- Desktop Table -->
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-100 text-[13px] font-bold text-slate-400 uppercase tracking-wide">
              <th class="pb-4 pr-4 font-semibold">Exam Title</th>
              <th class="pb-4 px-4 font-semibold">Course</th>
              <th class="pb-4 px-4 font-semibold">Scheduled</th>
              <th class="pb-4 px-4 font-semibold">Students</th>
              <th class="pb-4 px-4 font-semibold">Status</th>
              <th class="pb-4 pl-4 text-center font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="exam in exams"
              :key="exam.id"
              class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0 group"
            >
              <td class="py-4 pr-4 text-[14px] font-semibold text-slate-700">{{ exam.title }}</td>
              <td class="py-4 px-4 text-[14px] text-slate-500 font-medium">{{ exam.course_code }}</td>
              <td class="py-4 px-4 text-[14px] text-slate-500 font-medium">{{ formatDate(exam.scheduled_at) }}</td>
              <td class="py-4 px-4 text-[14px] text-slate-600 font-semibold">{{ exam.students_count ?? 0 }}</td>
              <td class="py-4 px-4">
                <span
                  class="px-3 py-1 text-[12px] font-bold rounded-full"
                  :class="statusConfig[exam.status]?.class || 'bg-slate-100 text-slate-500'"
                >
                  {{ statusConfig[exam.status]?.label || exam.status }}
                </span>
              </td>
              <td class="py-4 pl-4 text-center">
                <router-link
                  to="/instructor/exams"
                  class="text-slate-400 hover:text-[#5138ed] transition-colors p-1.5 rounded-lg hover:bg-indigo-50 inline-flex items-center justify-center min-h-[36px] min-w-[36px]"
                >
                  <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </router-link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card View -->
      <div class="md:hidden space-y-3">
        <div
          v-for="exam in exams"
          :key="exam.id"
          class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <span class="inline-block px-2 py-0.5 text-[11px] font-bold rounded-md bg-indigo-50 text-[#5138ed] mb-1">
                {{ exam.course_code }}
              </span>
              <h3 class="text-sm font-bold text-slate-800 leading-snug">{{ exam.title }}</h3>
            </div>
            <span
              class="px-2.5 py-0.5 text-[11px] font-bold rounded-full shrink-0"
              :class="statusConfig[exam.status]?.class || 'bg-slate-100 text-slate-500'"
            >
              {{ statusConfig[exam.status]?.label || exam.status }}
            </span>
          </div>

          <div class="flex items-center justify-between text-xs text-slate-500 pt-1 border-t border-slate-200/60">
            <span class="flex items-center gap-1">
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              {{ formatDate(exam.scheduled_at) }}
            </span>
            <span class="flex items-center gap-1 font-semibold text-slate-700">
              <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
              {{ exam.students_count ?? 0 }} Students
            </span>
          </div>

          <router-link
            to="/instructor/exams"
            class="w-full flex items-center justify-center gap-2 py-2 text-xs font-semibold text-[#5138ed] bg-white border border-indigo-100 rounded-lg hover:bg-indigo-50 transition-colors min-h-[44px]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View Details
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
