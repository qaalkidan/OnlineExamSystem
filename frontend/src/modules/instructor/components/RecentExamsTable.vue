<script setup lang="ts">
import type { Exam } from '../store/instructorStore'
import { FileText, Eye, Calendar, Users, Clock, ArrowRight } from 'lucide-vue-next'

const props = defineProps<{
  exams: Exam[]
  isLoading: boolean
}>()

const statusConfig: Record<string, { label: string; class: string }> = {
  draft:     { label: 'Draft',     class: 'bg-slate-100 text-slate-700 border-slate-200' },
  published: { label: 'Published', class: 'bg-blue-50 text-blue-700 border-blue-200/80' },
  scheduled: { label: 'Scheduled', class: 'bg-indigo-50 text-[#5138ed] border-indigo-200/80' },
  completed: { label: 'Completed', class: 'bg-emerald-50 text-emerald-700 border-emerald-200/80' },
}

const formatDate = (iso: string | null) => {
  if (!iso) return 'Not scheduled'
  const d = new Date(iso)
  return d.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-xs">
    <div class="flex items-center justify-between mb-5 flex-wrap gap-2">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center">
          <FileText class="w-4.5 h-4.5" />
        </div>
        <div>
          <h2 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight">Recent Exams</h2>
          <p class="text-[12px] text-slate-400">Latest examination records for your assigned course</p>
        </div>
      </div>
      <router-link
        to="/instructor/exams"
        class="inline-flex items-center gap-1.5 text-xs font-bold text-[#5138ed] bg-indigo-50/80 hover:bg-[#5138ed] hover:text-white px-3.5 py-2 rounded-xl transition-all shadow-2xs"
      >
        <span>View All Exams</span>
        <ArrowRight class="w-3.5 h-3.5" />
      </router-link>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="p-4 rounded-xl border border-slate-100 animate-pulse flex items-center justify-between gap-4">
        <div class="h-4 bg-slate-100 rounded w-1/3"></div>
        <div class="h-4 bg-slate-100 rounded w-1/6"></div>
        <div class="h-4 bg-slate-100 rounded w-1/4"></div>
        <div class="h-6 bg-slate-100 rounded-full w-20"></div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="exams.length === 0" class="text-center py-12 px-4 rounded-xl border border-dashed border-slate-200">
      <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-indigo-50/60 text-[#5138ed] flex items-center justify-center">
        <FileText class="w-6 h-6 text-indigo-400" />
      </div>
      <h3 class="text-sm font-bold text-slate-800">No exams created yet</h3>
      <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
        You haven't conducted or scheduled any exams for this course in the current semester.
      </p>
      <router-link
        to="/instructor/exams"
        class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors"
      >
        <span>Go to Exams Center</span>
        <ArrowRight class="w-3.5 h-3.5" />
      </router-link>
    </div>

    <!-- Desktop Table -->
    <div v-else class="hidden md:block overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
            <th class="pb-3.5 pr-4">Exam Title</th>
            <th class="pb-3.5 px-4">Course</th>
            <th class="pb-3.5 px-4">Scheduled Date & Time</th>
            <th class="pb-3.5 px-4 text-center">Students</th>
            <th class="pb-3.5 px-4">Status</th>
            <th class="pb-3.5 pl-4 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr
            v-for="exam in exams"
            :key="exam.id"
            class="hover:bg-slate-50/60 transition-colors group"
          >
            <!-- Exam Title -->
            <td class="py-3.5 pr-4">
              <div class="flex flex-col">
                <span class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors">
                  {{ exam.title }}
                </span>
                <span class="text-[11px] text-slate-400">
                  {{ exam.duration_minutes }} mins • {{ exam.total_marks }} marks
                </span>
              </div>
            </td>

            <!-- Course -->
            <td class="py-3.5 px-4 whitespace-nowrap">
              <span class="px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200/70">
                {{ exam.course_code }}
              </span>
            </td>

            <!-- Scheduled -->
            <td class="py-3.5 px-4 whitespace-nowrap">
              <div class="flex items-center gap-1.5 text-slate-600 text-[12px] font-medium">
                <Calendar class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                <span>{{ formatDate(exam.scheduled_at) }}</span>
              </div>
            </td>

            <!-- Students -->
            <td class="py-3.5 px-4 text-center whitespace-nowrap">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-50 text-slate-700 border border-slate-100">
                <Users class="w-3 h-3 text-slate-400" />
                {{ exam.students_count ?? 0 }}
              </span>
            </td>

            <!-- Status -->
            <td class="py-3.5 px-4 whitespace-nowrap">
              <span
                class="px-2.5 py-1 text-[11px] font-bold rounded-full border shadow-2xs inline-block"
                :class="statusConfig[exam.status]?.class || 'bg-slate-100 text-slate-600 border-slate-200'"
              >
                {{ statusConfig[exam.status]?.label || exam.status }}
              </span>
            </td>

            <!-- Actions -->
            <td class="py-3.5 pl-4 text-center whitespace-nowrap">
              <router-link
                to="/instructor/exams"
                title="View Exam Details"
                class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] hover:bg-[#5138ed] hover:text-white transition-all inline-flex items-center justify-center shadow-2xs group-hover:scale-105"
              >
                <Eye class="w-4 h-4" />
              </router-link>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards View -->
    <div v-if="!isLoading && exams.length > 0" class="md:hidden space-y-3">
      <div
        v-for="exam in exams"
        :key="exam.id"
        class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors space-y-2.5"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-md bg-indigo-50 text-[#5138ed] border border-indigo-100 mb-1">
              {{ exam.course_code }}
            </span>
            <h4 class="text-[13px] font-bold text-slate-800 leading-snug">{{ exam.title }}</h4>
          </div>
          <span
            class="px-2 py-0.5 text-[10px] font-bold rounded-full border shrink-0"
            :class="statusConfig[exam.status]?.class || 'bg-slate-100 text-slate-600 border-slate-200'"
          >
            {{ statusConfig[exam.status]?.label || exam.status }}
          </span>
        </div>

        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-200/50">
          <span class="flex items-center gap-1">
            <Calendar class="w-3.5 h-3.5 text-slate-400" />
            {{ formatDate(exam.scheduled_at) }}
          </span>
          <span class="font-semibold text-slate-700 flex items-center gap-1">
            <Users class="w-3.5 h-3.5 text-slate-400" />
            {{ exam.students_count ?? 0 }} Students
          </span>
        </div>

        <router-link
          to="/instructor/exams"
          class="w-full min-h-[38px] inline-flex items-center justify-center gap-1.5 text-xs font-bold text-[#5138ed] bg-white border border-indigo-100 rounded-xl hover:bg-indigo-50 transition-colors"
        >
          <Eye class="w-3.5 h-3.5" />
          <span>View Exam Details</span>
        </router-link>
      </div>
    </div>
  </div>
</template>
