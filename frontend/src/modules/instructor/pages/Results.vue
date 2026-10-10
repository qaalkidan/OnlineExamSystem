<script setup lang="ts">
import { onMounted } from 'vue'
import { useInstructorResultStore } from '../store/instructorResultStore'

import ResultsStats from '../components/results/ResultsStats.vue'
import ResultsTable from '../components/results/ResultsTable.vue'
import OverallPerformanceWidget from '../components/results/OverallPerformanceWidget.vue'
import PerformanceSummaryWidget from '../components/results/PerformanceSummaryWidget.vue'
import ResultsQuickActionsWidget from '../components/results/ResultsQuickActionsWidget.vue'

const resultStore = useInstructorResultStore()

onMounted(() => {
  resultStore.fetchResults()
})

const handleExportAll = () => {
  try {
    const rows = [
      ['Exam Title', 'Exam Type', 'Scheduled Date', 'Total Enrolled', 'Submitted Count', 'Graded Count', 'Average Score (%)', 'Published', 'Status'],
      ...resultStore.results.map(r => [
        `"${(r.title || '').replace(/"/g, '""')}"`,
        `"${(r.type || '').replace(/"/g, '""')}"`,
        `"${(r.scheduled_at || '').replace(/"/g, '""')}"`,
        r.total_students,
        r.submitted_count,
        r.graded_count,
        r.average_score !== null ? `"${r.average_score}%"` : '"N/A"',
        r.is_published ? '"Yes"' : '"No"',
        `"${(r.status || '').replace(/"/g, '""')}"`
      ])
    ]
    const csvContent = '\uFEFF' + rows.map(row => row.join(',')).join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Wollo_University_Results_${(resultStore.course.code || 'Course').replace(/[^a-zA-Z0-9]/g, '_')}_${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (err) {
    console.error('Export error:', err)
  }
}

const handlePrintAll = () => {
  const printWindow = window.open('', '_blank', 'width=900,height=700')
  if (!printWindow) return
  printWindow.document.write(`
    <html>
      <head>
        <title>Examination Results Summary - ${resultStore.course.name}</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 32px; color: #17243A; }
          .header { border-bottom: 2px solid #4F35F3; padding-bottom: 16px; margin-bottom: 20px; }
          h2 { color: #17243A; margin: 0 0 4px 0; font-size: 18px; text-transform: uppercase; }
          .sub { color: #71819B; font-size: 12px; }
          .meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 16px 0; font-size: 12px; background: #f8fafc; padding: 12px; border-radius: 8px; }
          table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 12px; }
          th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #E6EBF3; }
          th { background: #f1f5f9; color: #475569; font-weight: bold; }
          .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-weight: bold; font-size: 11px; }
          .footer { margin-top: 40px; font-size: 11px; color: #94a3b8; border-top: 1px solid #E6EBF3; padding-top: 16px; display: flex; justify-content: space-between; }
        </style>
      </head>
      <body>
        <div class="header">
          <h2>Wollo University &bull; Examination Performance Summary</h2>
          <div class="sub">Generated on ${new Date().toLocaleDateString()} | Instructor: ${resultStore.course.instructor_name}</div>
        </div>
        <div class="meta">
          <div><strong>Course:</strong> ${resultStore.course.name} (${resultStore.course.code})</div>
          <div><strong>Department:</strong> ${resultStore.course.department}</div>
          <div><strong>Semester:</strong> ${resultStore.course.semester}</div>
          <div><strong>Academic Year:</strong> ${resultStore.course.academic_year}</div>
        </div>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Exam Title</th>
              <th>Type</th>
              <th>Date</th>
              <th>Enrolled</th>
              <th>Submitted</th>
              <th>Avg Score</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            ${resultStore.results.map((r, i) => `
              <tr>
                <td>${i + 1}</td>
                <td><strong>${r.title}</strong></td>
                <td>${r.type}</td>
                <td>${new Date(r.scheduled_at).toLocaleDateString()}</td>
                <td>${r.total_students}</td>
                <td>${r.submitted_count}</td>
                <td>${r.average_score !== null ? r.average_score + '%' : 'N/A'}</td>
                <td>${r.status}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
        <div class="footer">
          <span>Official Evaluation Record &bull; Wollo University Online Examination System</span>
          <span>Page 1 of 1</span>
        </div>
      </body>
    </html>
  `)
  printWindow.document.close()
  printWindow.focus()
  setTimeout(() => {
    printWindow.print()
    printWindow.close()
  }, 250)
}
</script>

<template>
  <div class="max-w-[1440px] mx-auto space-y-6">
    
    <!-- Header Area -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl sm:text-[26px] font-black text-[#17243A] tracking-tight leading-tight">
            Results Dashboard
          </h1>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-[#4F35F3] border border-indigo-100">
            {{ resultStore.course.code || 'Course Results' }}
          </span>
        </div>
        <p class="text-xs sm:text-sm text-[#71819B] mt-1 font-medium">
          Review student performance and manage examination results.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Refresh Button -->
        <button
          @click="resultStore.fetchResults()"
          :disabled="resultStore.isLoading"
          class="w-10 h-10 rounded-xl border border-[#E6EBF3] bg-white hover:bg-slate-50 hover:border-slate-300 text-slate-600 flex items-center justify-center transition-colors cursor-pointer shadow-2xs"
          title="Refresh Exam Results"
        >
          <svg
            class="w-4 h-4 text-slate-500"
            :class="{ 'animate-spin': resultStore.isLoading }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>

        <!-- Print Summary Button -->
        <button
          @click="handlePrintAll"
          class="flex items-center justify-center gap-2 bg-white hover:bg-slate-50 border border-[#E6EBF3] hover:border-slate-300 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-2xs transition-colors min-h-[44px] cursor-pointer"
          title="Print Results Summary Report"
        >
          <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          <span>Print Summary</span>
        </button>

        <!-- Export CSV Button -->
        <button
          @click="handleExportAll"
          class="flex items-center justify-center gap-2 bg-[#4F35F3] hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-xs hover:shadow transition-all min-h-[44px] cursor-pointer"
          title="Export All Results to CSV"
        >
          <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <span>Export Results</span>
        </button>
      </div>
    </div>

    <!-- Error Alert Card -->
    <div
      v-if="resultStore.error"
      class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 text-xs sm:text-sm font-medium flex items-center justify-between gap-3 shadow-2xs"
    >
      <div class="flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ resultStore.error }}</span>
      </div>
      <button
        @click="resultStore.fetchResults()"
        class="px-3 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-800 rounded-lg text-xs font-bold transition-colors cursor-pointer"
      >
        Retry
      </button>
    </div>

    <!-- Course Information Card -->
    <div class="bg-white border border-[#E6EBF3] rounded-2xl p-4 sm:p-6 shadow-xs hover:shadow-md transition-shadow duration-200 flex flex-col lg:flex-row lg:items-center justify-between gap-5">
      <!-- Left: Icon & Details -->
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-[#EEF0FF] border border-indigo-100/80 flex items-center justify-center text-[#4F35F3] shrink-0 shadow-2xs">
          <svg class="w-7 h-7 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
        </div>
        <div>
          <div class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider mb-1">Course Information</div>
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h2 class="text-lg sm:text-xl font-black text-[#17243A]">{{ resultStore.course.name }}</h2>
            <span class="px-2 py-0.5 text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60 rounded-md">
              {{ resultStore.course.status || 'Active' }}
            </span>
          </div>
          <div class="flex flex-wrap items-center gap-3 text-xs sm:text-sm text-[#71819B] font-medium">
            <span>Code: <strong class="text-slate-700 font-mono font-bold">{{ resultStore.course.code }}</strong></span>
            <span>&bull;</span>
            <span>Instructor: <strong class="text-slate-700 font-bold">{{ resultStore.course.instructor_name }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Right: Course Attributes -->
      <div class="flex flex-wrap items-center gap-4 sm:gap-7 pt-4 lg:pt-0 border-t lg:border-t-0 border-[#E6EBF3]">
        <!-- Semester -->
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-slate-50 border border-[#E6EBF3] flex items-center justify-center text-[#4F35F3]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[10px] font-bold text-[#71819B] uppercase">Semester</span>
            <span class="text-xs sm:text-[13px] font-bold text-[#17243A]">{{ resultStore.course.semester }}</span>
          </div>
        </div>

        <!-- Academic Year -->
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-slate-50 border border-[#E6EBF3] flex items-center justify-center text-[#4F35F3]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[10px] font-bold text-[#71819B] uppercase">Academic Year</span>
            <span class="text-xs sm:text-[13px] font-bold text-[#17243A]">{{ resultStore.course.academic_year }}</span>
          </div>
        </div>

        <!-- Department -->
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-slate-50 border border-[#E6EBF3] flex items-center justify-center text-[#4F35F3]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
          </div>
          <div class="flex flex-col">
            <span class="text-[10px] font-bold text-[#71819B] uppercase">Department</span>
            <span class="text-xs sm:text-[13px] font-bold text-[#17243A]">{{ resultStore.course.department }}</span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Main Content Area -->
    <div class="space-y-6">
      
      <!-- Top Section: Stats Cards & Full-Width Results Table -->
      <div class="w-full space-y-4">
        <ResultsStats />
        <ResultsTable />
      </div>

      <!-- Bottom Section: 3-Column Grid (Grading Progress, Pending Manual Grading, Quick Actions) -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 lg:gap-6">
        <OverallPerformanceWidget class="h-full" />
        <PerformanceSummaryWidget class="h-full" />
        <ResultsQuickActionsWidget class="h-full md:col-span-2 lg:col-span-1" />
      </div>

    </div>
  </div>
</template>
