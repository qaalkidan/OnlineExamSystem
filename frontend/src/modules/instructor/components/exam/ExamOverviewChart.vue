<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  ArcElement
} from 'chart.js'
import { useInstructorExamStore } from '../../store/instructorExamStore'

ChartJS.register(Title, Tooltip, Legend, ArcElement)

const examStore = useInstructorExamStore()

const stats = computed(() => {
  const exams = examStore.exams || []
  const upcoming = exams.filter(e => e.status === 'scheduled').length
  const published = exams.filter(e => e.status === 'published').length
  const completed = exams.filter(e => e.status === 'completed').length
  const drafts = exams.filter(e => e.status === 'draft').length
  const total = exams.length

  const pct = (val: number) => {
    if (total === 0) return '0%'
    return `${Math.round((val / total) * 100)}%`
  }

  return {
    upcoming,
    published,
    completed,
    drafts,
    total,
    pctUpcoming: pct(upcoming),
    pctPublished: pct(published),
    pctCompleted: pct(completed),
    pctDrafts: pct(drafts),
  }
})

const chartData = computed(() => {
  const { upcoming, published, completed, drafts, total } = stats.value
  
  if (total === 0) {
    return {
      labels: ['No Exams'],
      datasets: [
        {
          backgroundColor: ['#e2e8f0'],
          data: [1],
          borderWidth: 0,
          hoverOffset: 0
        }
      ]
    }
  }

  return {
    labels: ['Upcoming', 'Published', 'Completed', 'Drafts'],
    datasets: [
      {
        backgroundColor: ['#5138ed', '#10b981', '#f59e0b', '#94a3b8'],
        data: [upcoming, published, completed, drafts],
        borderWidth: 0,
        hoverOffset: 4
      }
    ]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: '#1e293b',
      padding: 10,
      cornerRadius: 10,
      bodyFont: { size: 12, weight: 'bold' as const },
      displayColors: true,
      callbacks: {
        label: function(context: any) {
          if (stats.value.total === 0) return 'No exams recorded'
          return ` ${context.label}: ${context.raw} exams`
        }
      }
    }
  }
}
</script>

<template>
  <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col justify-between">
    <div class="flex items-center gap-2 mb-4">
      <div class="w-7 h-7 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed]">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
        </svg>
      </div>
      <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Exam Status Distribution</h2>
    </div>
    
    <div class="flex flex-col items-center">
      <!-- Chart Container with Absolute Center Text -->
      <div class="relative w-32 h-32 mb-4">
        <Doughnut :data="chartData" :options="chartOptions" />
        
        <!-- Center Text -->
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <span class="text-2xl font-black text-slate-900 leading-none">
            {{ stats.total }}
          </span>
          <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-1">
            Total Exams
          </span>
        </div>
      </div>

      <!-- Real Dynamic Legend -->
      <div class="w-full grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
        <!-- Upcoming -->
        <div class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-50 border border-slate-100">
          <div class="w-2 h-2 rounded-full bg-[#5138ed] shrink-0"></div>
          <div class="min-w-0">
            <span class="text-[11px] font-bold text-slate-700 block truncate">Upcoming</span>
            <span class="text-[10px] text-slate-400 font-medium">{{ stats.upcoming }} ({{ stats.pctUpcoming }})</span>
          </div>
        </div>
        
        <!-- Published -->
        <div class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-50 border border-slate-100">
          <div class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></div>
          <div class="min-w-0">
            <span class="text-[11px] font-bold text-slate-700 block truncate">Published</span>
            <span class="text-[10px] text-slate-400 font-medium">{{ stats.published }} ({{ stats.pctPublished }})</span>
          </div>
        </div>

        <!-- Completed -->
        <div class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-50 border border-slate-100">
          <div class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></div>
          <div class="min-w-0">
            <span class="text-[11px] font-bold text-slate-700 block truncate">Completed</span>
            <span class="text-[10px] text-slate-400 font-medium">{{ stats.completed }} ({{ stats.pctCompleted }})</span>
          </div>
        </div>
        
        <!-- Drafts -->
        <div class="flex items-center gap-2 p-1.5 rounded-lg bg-slate-50 border border-slate-100">
          <div class="w-2 h-2 rounded-full bg-slate-400 shrink-0"></div>
          <div class="min-w-0">
            <span class="text-[11px] font-bold text-slate-700 block truncate">Drafts</span>
            <span class="text-[10px] text-slate-400 font-medium">{{ stats.drafts }} ({{ stats.pctDrafts }})</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
