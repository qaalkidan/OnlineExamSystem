<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorResultStore } from '../../store/instructorResultStore'
import { useSemesterLockStore } from '../../store/semesterLockStore'

const router = useRouter()
const resultStore = useInstructorResultStore()
const lockStore = useSemesterLockStore()

const toastMessage = ref<string | null>(null)

const showToast = (msg: string) => {
  toastMessage.value = msg
  setTimeout(() => {
    toastMessage.value = null
  }, 3500)
}

const handleContinueGrading = () => {
  // Find first exam with pending grading
  const pendingExam = resultStore.results.find(r => (r.status as string) === 'Pending' || r.status === 'Pending Grading' || r.graded_count < r.submitted_count)
  if (pendingExam) {
    router.push({ name: 'ExamResultDetail', params: { examId: pendingExam.id } })
  } else if (resultStore.results.length > 0) {
    router.push({ name: 'ExamResultDetail', params: { examId: resultStore.results[0].id } })
  } else {
    showToast('No active examinations available to grade.')
  }
}

const handleViewPendingStudents = () => {
  const pendingExam = resultStore.results.find(r => r.submitted_count < r.total_students)
  if (pendingExam) {
    router.push({ name: 'ExamResultDetail', params: { examId: pendingExam.id } })
  } else {
    router.push('/instructor/students')
  }
}

const handlePublishResults = () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('publish results')
    return
  }
  const unpublishedExam = resultStore.results.find(r => !r.is_published && r.graded_count > 0)
  if (unpublishedExam) {
    router.push({ name: 'ExamResultDetail', params: { examId: unpublishedExam.id } })
  } else {
    showToast('All evaluated examinations are already published or no exams are ready to publish.')
  }
}

const handleExportResults = () => {
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
    showToast('Cohort results successfully exported to CSV.')
  } catch (err) {
    showToast('Failed to export results.')
  }
}

const handlePrintSummary = () => {
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
  <div class="bg-white border border-[#E6EBF3] rounded-2xl shadow-xs p-5 hover:shadow-md transition-shadow duration-200 flex flex-col justify-between relative">
    
    <!-- Toast Popup -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform -translate-y-1 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform -translate-y-1 opacity-0"
    >
      <div
        v-if="toastMessage"
        class="absolute -top-3 left-4 right-4 bg-[#17243A] text-white px-3 py-2 rounded-xl text-xs font-semibold shadow-xl z-20 text-center"
      >
        {{ toastMessage }}
      </div>
    </transition>

    <!-- Header -->
    <div class="flex items-center justify-between pb-3 mb-3 border-b border-[#E6EBF3]">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-purple-50 border border-purple-100/60 text-[#4F35F3] flex items-center justify-center shadow-2xs">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-[#17243A]">Quick Actions</h3>
          <p class="text-[11px] text-[#71819B] font-medium">Results management shortcuts</p>
        </div>
      </div>
    </div>

    <!-- Actions List -->
    <div class="space-y-1.5 flex-1 flex flex-col justify-between">
      
      <!-- Continue Grading -->
      <button
        @click="handleContinueGrading"
        class="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 transition-colors group cursor-pointer border border-transparent hover:border-[#E6EBF3]"
      >
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-indigo-50 text-[#4F35F3] flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
          </div>
          <div class="flex flex-col items-start min-w-0">
            <span class="text-xs font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors truncate">Continue Grading</span>
            <span class="text-[11px] text-[#71819B] truncate">Evaluate submitted attempts</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-[#4F35F3] transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </button>

      <!-- View Pending Students -->
      <button
        @click="handleViewPendingStudents"
        class="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 transition-colors group cursor-pointer border border-transparent hover:border-[#E6EBF3]"
      >
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
          </div>
          <div class="flex flex-col items-start min-w-0">
            <span class="text-xs font-bold text-[#17243A] group-hover:text-emerald-600 transition-colors truncate">View Pending Students</span>
            <span class="text-[11px] text-[#71819B] truncate">Review missing submissions</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </button>

      <!-- Publish Results -->
      <button
        @click="handlePublishResults"
        class="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 transition-colors group cursor-pointer border border-transparent hover:border-[#E6EBF3]"
      >
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
          </div>
          <div class="flex flex-col items-start min-w-0">
            <span class="text-xs font-bold text-[#17243A] group-hover:text-amber-600 transition-colors truncate">Publish Exam Results</span>
            <span class="text-[11px] text-[#71819B] truncate">Make grades visible to cohort</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-amber-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </button>

      <!-- Export Results -->
      <button
        @click="handleExportResults"
        class="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 transition-colors group cursor-pointer border border-transparent hover:border-[#E6EBF3]"
      >
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-sky-50 text-[#0EA5E9] flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
          </div>
          <div class="flex flex-col items-start min-w-0">
            <span class="text-xs font-bold text-[#17243A] group-hover:text-[#0EA5E9] transition-colors truncate">Export Results CSV</span>
            <span class="text-[11px] text-[#71819B] truncate">Download course spreadsheet</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-[#0EA5E9] transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </button>

      <!-- Print Result Summary -->
      <button
        @click="handlePrintSummary"
        class="w-full flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 transition-colors group cursor-pointer border border-transparent hover:border-[#E6EBF3]"
      >
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:scale-105 transition-transform shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
          </div>
          <div class="flex flex-col items-start min-w-0">
            <span class="text-xs font-bold text-[#17243A] group-hover:text-rose-600 transition-colors truncate">Print Result Summary</span>
            <span class="text-[11px] text-[#71819B] truncate">Formatted evaluation report</span>
          </div>
        </div>
        <svg class="w-4 h-4 text-slate-300 group-hover:text-rose-600 transition-colors shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
      </button>

    </div>
  </div>
</template>
