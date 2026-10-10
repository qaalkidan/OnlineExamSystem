<script setup lang="ts">
import { ref, computed } from 'vue'

interface ExamSummaryItem {
  id: number
  title: string
  code: string
  type: string
  typeClass: string
  students: number
  avgScore: number
  passRate: number
  failRate: number
  highScore: number
  lowScore: number
}

const rawSummaries: ExamSummaryItem[] = [
  {
    id: 1,
    title: 'Database Systems Mid Exam',
    code: 'CS 301',
    type: 'Mid Exam',
    typeClass: 'bg-indigo-50 text-[#4F35F3] border-indigo-100',
    students: 98,
    avgScore: 82.6,
    passRate: 78.3,
    failRate: 21.7,
    highScore: 98.0,
    lowScore: 42.0
  },
  {
    id: 2,
    title: 'Web Programming Quiz 1',
    code: 'CS 204',
    type: 'Quiz',
    typeClass: 'bg-emerald-50 text-emerald-700 border-emerald-100',
    students: 95,
    avgScore: 81.4,
    passRate: 76.5,
    failRate: 23.5,
    highScore: 96.0,
    lowScore: 48.0
  },
  {
    id: 3,
    title: 'Data Structures Final Exam',
    code: 'CS 202',
    type: 'Final Exam',
    typeClass: 'bg-amber-50 text-amber-700 border-amber-100',
    students: 92,
    avgScore: 75.6,
    passRate: 70.4,
    failRate: 29.6,
    highScore: 94.0,
    lowScore: 38.0
  },
  {
    id: 4,
    title: 'Operating Systems Mid Exam',
    code: 'CS 305',
    type: 'Mid Exam',
    typeClass: 'bg-indigo-50 text-[#4F35F3] border-indigo-100',
    students: 88,
    avgScore: 79.8,
    passRate: 74.2,
    failRate: 25.8,
    highScore: 95.0,
    lowScore: 44.0
  },
  {
    id: 5,
    title: 'Theory of Computation Final',
    code: 'CS 401',
    type: 'Final Exam',
    typeClass: 'bg-amber-50 text-amber-700 border-amber-100',
    students: 65,
    avgScore: 58.2,
    passRate: 45.1,
    failRate: 54.9,
    highScore: 88.0,
    lowScore: 28.0
  }
]

const searchQuery = ref('')
const selectedTypeFilter = ref('All')
const selectedExamForDetail = ref<ExamSummaryItem | null>(null)
const showAll = ref(false)

const filteredSummaries = computed(() => {
  return rawSummaries.filter(item => {
    const matchesSearch = item.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                          item.code.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchesType = selectedTypeFilter.value === 'All' || item.type === selectedTypeFilter.value
    return matchesSearch && matchesType
  })
})

const exportSingleExam = (exam: ExamSummaryItem) => {
  const rows = [
    ['Metric', 'Value'],
    ['Exam Title', `"${exam.title}"`],
    ['Course Code', `"${exam.code}"`],
    ['Exam Type', `"${exam.type}"`],
    ['Total Students', exam.students],
    ['Average Score', `${exam.avgScore}%`],
    ['Pass Rate', `${exam.passRate}%`],
    ['Fail Rate', `${exam.failRate}%`],
    ['Highest Score', `${exam.highScore}%`],
    ['Lowest Score', `${exam.lowScore}%`],
    ['Exported At', `"${new Date().toISOString()}"`]
  ]
  const csvContent = '\uFEFF' + rows.map(r => r.join(',')).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `Wollo_University_Exam_Report_${exam.code}_${exam.type.replace(/\s+/g, '_')}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl shadow-2xs overflow-hidden hover:border-slate-300 transition-all">
    
    <!-- Header with Search & Filter -->
    <div class="p-5 sm:p-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-[14px] sm:text-[15px] font-bold text-[#17243A]">Exam Summary</h2>
        <p class="text-xs text-[#71819B] mt-0.5">Comprehensive performance analytics by examination</p>
      </div>

      <div class="flex items-center gap-2.5">
        <!-- Search bar -->
        <div class="relative min-w-[180px]">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search exam..."
            class="w-full pl-8 pr-3 py-1.5 text-xs border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-[#17243A] placeholder-slate-400 shadow-2xs"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Filter tabs -->
        <div class="hidden md:flex items-center gap-1 bg-slate-50 p-1 rounded-xl border border-slate-200/60 text-[11px] font-bold">
          <button
            v-for="t in ['All', 'Mid Exam', 'Final Exam', 'Quiz']"
            :key="t"
            @click="selectedTypeFilter = t"
            class="px-2.5 py-1 rounded-lg transition-colors cursor-pointer"
            :class="selectedTypeFilter === t ? 'bg-white text-[#4F35F3] shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
          >
            {{ t }}
          </button>
        </div>
      </div>
    </div>

    <!-- Table matching screenshot -->
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-slate-50/60 border-b border-slate-100 text-[10px] font-bold text-[#71819B] tracking-wider uppercase">
            <th class="px-6 py-3">Exam Title</th>
            <th class="px-4 py-3">Exam Type</th>
            <th class="px-4 py-3 text-center">Total Students</th>
            <th class="px-4 py-3 text-center">Average Score</th>
            <th class="px-4 py-3 text-center">Pass Rate</th>
            <th class="px-4 py-3 text-center">Fail Rate</th>
            <th class="px-6 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-[12px]">
          <tr
            v-for="exam in filteredSummaries"
            :key="exam.id"
            class="hover:bg-slate-50/70 transition-colors group cursor-pointer"
          >
            <!-- Title -->
            <td class="px-6 py-4">
              <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors">
                {{ exam.title }}
              </div>
              <div class="text-[10px] text-[#71819B] font-medium">{{ exam.code }}</div>
            </td>

            <!-- Type badge -->
            <td class="px-4 py-4 whitespace-nowrap">
              <span :class="`inline-block px-2.5 py-1 text-[10px] font-bold rounded-lg border ${exam.typeClass}`">
                {{ exam.type }}
              </span>
            </td>

            <!-- Total students -->
            <td class="px-4 py-4 text-center">
              <span class="text-[11px] font-semibold text-slate-700">{{ exam.students }}</span>
            </td>

            <!-- Average score with progress bar -->
            <td class="px-4 py-4 text-center">
              <div class="inline-flex flex-col items-center">
                <span class="text-[11px] font-bold text-[#17243A]">{{ exam.avgScore }}%</span>
                <div class="w-16 h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden">
                  <div
                    class="h-full rounded-full"
                    :class="exam.avgScore >= 75 ? 'bg-[#4F35F3]' : exam.avgScore >= 60 ? 'bg-amber-500' : 'bg-rose-500'"
                    :style="{ width: `${exam.avgScore}%` }"
                  ></div>
                </div>
              </div>
            </td>

            <!-- Pass rate -->
            <td class="px-4 py-4 text-center whitespace-nowrap">
              <span
                class="text-[11px] font-bold"
                :class="exam.passRate >= 60 ? 'text-emerald-600' : 'text-rose-600'"
              >
                {{ exam.passRate }}%
              </span>
            </td>

            <!-- Fail rate -->
            <td class="px-4 py-4 text-center whitespace-nowrap">
              <span
                class="text-[11px] font-bold"
                :class="exam.failRate > 40 ? 'text-rose-600' : 'text-slate-600'"
              >
                {{ exam.failRate }}%
              </span>
            </td>

            <!-- Actions -->
            <td class="px-6 py-4">
              <div class="flex items-center justify-center gap-1.5">
                <button
                  @click.stop="selectedExamForDetail = exam"
                  class="p-1.5 text-slate-400 hover:text-[#4F35F3] hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer"
                  title="View Detailed Report"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
                <button
                  @click.stop="exportSingleExam(exam)"
                  class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors cursor-pointer"
                  title="Export Exam CSV"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="filteredSummaries.length === 0">
            <td colspan="7" class="px-6 py-8 text-center text-slate-400 text-xs">
              No exam summary found matching your filters.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination & View All matching screenshot -->
    <div class="p-4 border-t border-slate-100 flex items-center justify-between text-xs bg-slate-50/30">
      <span class="text-[11px] font-medium text-[#71819B] pl-2">
        Showing 1 to {{ filteredSummaries.length }} of {{ rawSummaries.length }} exams
      </span>
      <button
        @click="showAll = !showAll"
        class="text-[12px] font-bold text-[#4F35F3] hover:text-indigo-800 transition-colors flex items-center gap-1.5 pr-2 cursor-pointer"
      >
        <span>{{ showAll ? 'Show Fewer Reports' : 'View All Reports' }}</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
        </svg>
      </button>
    </div>

    <!-- Detailed Exam Report Modal -->
    <div
      v-if="selectedExamForDetail"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150"
    >
      <div class="bg-white border border-[#E6EBF3] rounded-2xl w-full max-w-xl shadow-2xl p-6 relative">
        <div class="flex items-start justify-between pb-4 border-b border-slate-100">
          <div>
            <span class="inline-block px-2.5 py-0.5 rounded text-[10px] font-bold uppercase mb-1" :class="selectedExamForDetail.typeClass">
              {{ selectedExamForDetail.type }}
            </span>
            <h3 class="text-base font-bold text-[#17243A]">{{ selectedExamForDetail.title }}</h3>
            <p class="text-xs text-[#71819B]">{{ selectedExamForDetail.code }} • Academic Performance Report</p>
          </div>
          <button
            @click="selectedExamForDetail = null"
            class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-5">
          <div class="p-3 bg-indigo-50/50 rounded-xl border border-indigo-100/60 text-center">
            <span class="block text-[10px] font-bold text-slate-500 uppercase">Avg Score</span>
            <span class="text-lg font-black text-[#4F35F3]">{{ selectedExamForDetail.avgScore }}%</span>
          </div>
          <div class="p-3 bg-emerald-50/50 rounded-xl border border-emerald-100/60 text-center">
            <span class="block text-[10px] font-bold text-slate-500 uppercase">Pass Rate</span>
            <span class="text-lg font-black text-emerald-600">{{ selectedExamForDetail.passRate }}%</span>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-100 text-center">
            <span class="block text-[10px] font-bold text-slate-500 uppercase">Top Score</span>
            <span class="text-lg font-black text-slate-800">{{ selectedExamForDetail.highScore }}%</span>
          </div>
          <div class="p-3 bg-rose-50/50 rounded-xl border border-rose-100/60 text-center">
            <span class="block text-[10px] font-bold text-slate-500 uppercase">Lowest</span>
            <span class="text-lg font-black text-rose-600">{{ selectedExamForDetail.lowScore }}%</span>
          </div>
        </div>

        <div class="space-y-2 text-xs bg-slate-50 p-3.5 rounded-xl border border-slate-100">
          <div class="flex justify-between">
            <span class="text-slate-500">Total Enrolled Participants:</span>
            <span class="font-bold text-slate-800">{{ selectedExamForDetail.students }} Students</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-500">Academic Standing:</span>
            <span class="font-bold text-emerald-600">Above Department Average (+4.2%)</span>
          </div>
        </div>

        <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
          <button
            @click="exportSingleExam(selectedExamForDetail)"
            class="px-4 py-2 border border-[#4F35F3] text-[#4F35F3] hover:bg-[#EEF0FF] text-xs font-bold rounded-xl transition-colors flex items-center gap-2 cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
            Export CSV
          </button>
          <button
            @click="selectedExamForDetail = null"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
