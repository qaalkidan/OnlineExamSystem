<script setup lang="ts">
import type { Exam } from '../store/instructorStore'
import { Calendar, Clock, Users, ArrowRight } from 'lucide-vue-next'

const props = defineProps<{
  exams: Exam[]
  isLoading: boolean
}>()

const getDay = (iso: string | null) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-US', { day: '2-digit' })
}
const getMonth = (iso: string | null) => {
  if (!iso) return ''
  return new Date(iso).toLocaleDateString('en-US', { month: 'short' }).toUpperCase()
}
const getTime = (iso: string | null) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-5 sm:p-6 shadow-xs">
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
          <Calendar class="w-4.5 h-4.5" />
        </div>
        <h2 class="text-base font-bold text-slate-900 tracking-tight">Upcoming Exams</h2>
      </div>
      <router-link
        to="/instructor/exams"
        class="text-xs font-bold text-[#5138ed] hover:text-indigo-700 transition-colors"
      >
        View All
      </router-link>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="isLoading" class="space-y-4">
      <div v-for="i in 3" :key="i" class="flex gap-3 animate-pulse">
        <div class="w-12 h-13 bg-slate-100 rounded-xl shrink-0"></div>
        <div class="flex-1 space-y-2 pt-1">
          <div class="h-3.5 bg-slate-100 rounded w-3/4"></div>
          <div class="h-3 bg-slate-100 rounded w-1/2"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="exams.length === 0" class="text-center py-8 px-4 rounded-xl border border-dashed border-slate-200">
      <div class="w-12 h-12 mx-auto mb-2.5 rounded-2xl bg-slate-50 text-slate-400 flex items-center justify-center">
        <Calendar class="w-6 h-6 text-slate-300" />
      </div>
      <h3 class="text-sm font-bold text-slate-700">No upcoming exams scheduled</h3>
      <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto">
        All examinations for this course have already been conducted or none are currently pending.
      </p>
      <router-link
        to="/instructor/exams"
        class="mt-4 inline-flex items-center justify-center gap-1.5 w-full py-2.5 rounded-xl border border-indigo-100 text-xs font-bold text-[#5138ed] bg-indigo-50/50 hover:bg-[#5138ed] hover:text-white transition-colors"
      >
        <span>View Full Schedule</span>
        <ArrowRight class="w-3.5 h-3.5" />
      </router-link>
    </div>

    <!-- Exam List -->
    <div v-else class="space-y-3.5">
      <router-link
        v-for="exam in exams"
        :key="exam.id"
        to="/instructor/exams"
        class="flex gap-3.5 items-start p-2.5 rounded-xl hover:bg-slate-50/80 transition-colors group border border-transparent hover:border-slate-100"
      >
        <!-- Date Badge -->
        <div class="flex flex-col items-center justify-center w-12 h-13 rounded-xl border border-indigo-100 bg-indigo-50/40 group-hover:bg-[#5138ed] group-hover:border-[#5138ed] transition-colors shrink-0">
          <span class="text-base font-black text-[#5138ed] group-hover:text-white leading-none">
            {{ getDay(exam.scheduled_at) }}
          </span>
          <span class="text-[9px] font-black text-slate-400 group-hover:text-indigo-100 mt-1 tracking-wider uppercase">
            {{ getMonth(exam.scheduled_at) }}
          </span>
        </div>

        <!-- Exam Info -->
        <div class="flex flex-col flex-1 min-w-0 pt-0.5">
          <h4 class="text-[13px] font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors truncate">
            {{ exam.title }}
          </h4>
          <div class="flex items-center gap-1.5 mt-1 text-slate-500 text-[11px] font-medium">
            <Clock class="w-3 h-3 text-slate-400 shrink-0" />
            <span>{{ getTime(exam.scheduled_at) }} • {{ exam.duration_minutes }}m</span>
          </div>
          <div class="flex items-center gap-1.5 mt-0.5 text-slate-500 text-[11px] font-medium">
            <Users class="w-3 h-3 text-slate-400 shrink-0" />
            <span>{{ exam.students_count ?? 0 }} students • {{ exam.course_code }}</span>
          </div>
        </div>
      </router-link>

      <router-link
        to="/instructor/exams"
        class="w-full mt-2 inline-flex items-center justify-center gap-1.5 py-2.5 rounded-xl border border-indigo-100 text-xs font-bold text-[#5138ed] bg-indigo-50/50 hover:bg-[#5138ed] hover:text-white transition-colors"
      >
        <span>View Full Schedule</span>
        <ArrowRight class="w-3.5 h-3.5" />
      </router-link>
    </div>
  </div>
</template>
