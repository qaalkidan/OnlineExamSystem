<script setup lang="ts">
import { ref } from 'vue'
import { useInstructorReportStore } from '../../store/instructorReportStore'

const reportStore = useInstructorReportStore()

const showCustomModal = ref(false)
const showScheduleModal = ref(false)
const showToast = ref(false)
const toastMessage = ref('')

const customCourse = ref('CS 301 - Database Systems')
const customFormat = ref('CSV')
const customMetric = ref('all')
const scheduleFreq = ref('Weekly')
const scheduleEmail = ref('fitsum.gashaw@wu.edu.et')

const triggerToast = (msg: string) => {
  toastMessage.value = msg
  showToast.value = true
  setTimeout(() => {
    showToast.value = false
  }, 3500)
}

const handleExportAll = () => {
  try {
    const rows = [
      ['Wollo University - Official Academic Reports & Analytics Record'],
      ['Generated At', new Date().toISOString()],
      ['Academic Term', '2028 Second Semester'],
      [''],
      ['Summary KPI', 'Value'],
      ['Total Exams Conducted', reportStore.stats.total_exams],
      ['Total Students Assessed', reportStore.stats.total_students],
      ['Cohort Average Score (%)', `${reportStore.stats.average_score}%`],
      ['Cohort Pass Rate (%)', `${reportStore.stats.pass_rate}%`],
      ['Cohort Fail Rate (%)', `${reportStore.stats.fail_rate}%`],
      ['Highest Recorded Score (%)', `${reportStore.stats.top_score}%`],
      [''],
      ['Exam Title', 'Exam Type', 'Students', 'Average Score', 'Pass Rate', 'Fail Rate'],
      ['Database Systems Mid Exam', 'Mid Exam', '98', '82.6%', '78.3%', '21.7%'],
      ['Web Programming Quiz 1', 'Quiz', '95', '81.4%', '76.5%', '23.5%'],
      ['Operating Systems Mid Exam', 'Mid Exam', '88', '79.8%', '74.2%', '25.8%'],
      ['Software Engineering Quiz 1', 'Quiz', '90', '76.3%', '72.1%', '27.9%'],
      ['Data Structures Final Exam', 'Final Exam', '92', '75.6%', '70.4%', '29.6%'],
      ['Theory of Computation Final', 'Final Exam', '65', '58.2%', '45.1%', '54.9%']
    ]
    const csvContent = '\uFEFF' + rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Wollo_University_Academic_Reports_Export_${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
    triggerToast('All reports data exported successfully as CSV.')
  } catch (e) {
    console.error(e)
  }
}

const handleGenerateCustom = () => {
  showCustomModal.value = false
  triggerToast(`Custom report generated for ${customCourse.value} (${customFormat.value}).`)
}

const handleSaveSchedule = () => {
  showScheduleModal.value = false
  triggerToast(`Report digest scheduled: ${scheduleFreq.value} dispatch to ${scheduleEmail.value}.`)
}

const handleShareReport = () => {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(window.location.href)
    triggerToast('Academic report link copied to clipboard.')
  } else {
    triggerToast('Report ready to share.')
  }
}
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all relative">
    <h2 class="text-[14px] font-bold text-[#17243A] mb-4">Quick Actions</h2>
    
    <div class="grid grid-cols-2 gap-3">
      
      <!-- Generate Custom Report -->
      <button
        @click="showCustomModal = true"
        class="flex flex-col items-center justify-center gap-2 p-3.5 border border-[#E6EBF3] rounded-xl hover:bg-[#EEF0FF] hover:border-indigo-200 transition-all group text-center cursor-pointer shadow-2xs"
      >
        <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-[#4F35F3] group-hover:scale-110 transition-transform">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
        </div>
        <span class="text-[10px] font-bold text-[#71819B] group-hover:text-[#4F35F3] leading-tight">
          Generate Custom Report
        </span>
      </button>

      <!-- Export All Data -->
      <button
        @click="handleExportAll"
        class="flex flex-col items-center justify-center gap-2 p-3.5 border border-[#E6EBF3] rounded-xl hover:bg-[#EEF0FF] hover:border-indigo-200 transition-all group text-center cursor-pointer shadow-2xs"
      >
        <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
        </div>
        <span class="text-[10px] font-bold text-[#71819B] group-hover:text-[#4F35F3] leading-tight">
          Export All Data
        </span>
      </button>

      <!-- Schedule Report -->
      <button
        @click="showScheduleModal = true"
        class="flex flex-col items-center justify-center gap-2 p-3.5 border border-[#E6EBF3] rounded-xl hover:bg-[#EEF0FF] hover:border-indigo-200 transition-all group text-center cursor-pointer shadow-2xs"
      >
        <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600 group-hover:scale-110 transition-transform">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <span class="text-[10px] font-bold text-[#71819B] group-hover:text-[#4F35F3] leading-tight">
          Schedule Report
        </span>
      </button>

      <!-- Share Report -->
      <button
        @click="handleShareReport"
        class="flex flex-col items-center justify-center gap-2 p-3.5 border border-[#E6EBF3] rounded-xl hover:bg-[#EEF0FF] hover:border-indigo-200 transition-all group text-center cursor-pointer shadow-2xs"
      >
        <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
          </svg>
        </div>
        <span class="text-[10px] font-bold text-[#71819B] group-hover:text-[#4F35F3] leading-tight">
          Share Report
        </span>
      </button>

    </div>

    <!-- Toast Notification -->
    <div
      v-if="showToast"
      class="absolute bottom-4 left-4 right-4 bg-[#17243A] text-white text-xs px-3 py-2 rounded-xl shadow-xl flex items-center gap-2 z-50 animate-in fade-in duration-200"
    >
      <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
      <span class="text-[11px] font-medium truncate">{{ toastMessage }}</span>
    </div>

    <!-- Custom Report Modal -->
    <div
      v-if="showCustomModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150"
    >
      <div class="bg-white border border-[#E6EBF3] rounded-2xl w-full max-w-md shadow-2xl p-6 relative">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <h3 class="text-base font-bold text-[#17243A]">Generate Custom Report</h3>
          <button
            @click="showCustomModal = false"
            class="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div class="space-y-4 my-5 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Target Course</label>
            <select v-model="customCourse" class="w-full border border-[#E6EBF3] rounded-xl p-2.5 bg-white">
              <option>CS 301 - Database Systems</option>
              <option>CS 204 - Web Programming</option>
              <option>CS 202 - Data Structures</option>
              <option>CS 305 - Operating Systems</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Included Metrics</label>
            <select v-model="customMetric" class="w-full border border-[#E6EBF3] rounded-xl p-2.5 bg-white">
              <option value="all">Comprehensive (Scores, Pass Rates, Cohort Breakdown)</option>
              <option value="scores">Score Distributions Only</option>
              <option value="attendance">Student Participation & Attempts</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Output Format</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                @click="customFormat = 'CSV'"
                class="py-2 px-3 border rounded-xl font-bold text-center cursor-pointer"
                :class="customFormat === 'CSV' ? 'border-[#4F35F3] bg-[#EEF0FF] text-[#4F35F3]' : 'border-slate-200 text-slate-600'"
              >
                CSV (.csv)
              </button>
              <button
                type="button"
                @click="customFormat = 'PDF'"
                class="py-2 px-3 border rounded-xl font-bold text-center cursor-pointer"
                :class="customFormat === 'PDF' ? 'border-[#4F35F3] bg-[#EEF0FF] text-[#4F35F3]' : 'border-slate-200 text-slate-600'"
              >
                PDF Document (.pdf)
              </button>
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
          <button
            @click="showCustomModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
          >
            Cancel
          </button>
          <button
            @click="handleGenerateCustom"
            class="px-4 py-2 bg-[#4F35F3] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl flex items-center gap-1.5"
          >
            Generate Report
          </button>
        </div>
      </div>
    </div>

    <!-- Schedule Report Modal -->
    <div
      v-if="showScheduleModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-150"
    >
      <div class="bg-white border border-[#E6EBF3] rounded-2xl w-full max-w-md shadow-2xl p-6 relative">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <h3 class="text-base font-bold text-[#17243A]">Schedule Recurring Report</h3>
          <button
            @click="showScheduleModal = false"
            class="text-slate-400 hover:text-slate-600 p-1 rounded-lg"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>

        <div class="space-y-4 my-5 text-xs">
          <div>
            <label class="block font-bold text-slate-700 mb-1">Dispatch Frequency</label>
            <select v-model="scheduleFreq" class="w-full border border-[#E6EBF3] rounded-xl p-2.5 bg-white">
              <option value="Weekly">Weekly Digest (Every Monday 08:00 AM)</option>
              <option value="Monthly">Monthly Summary (1st of each month)</option>
              <option value="End of Term">End of Semester Final Summary</option>
            </select>
          </div>

          <div>
            <label class="block font-bold text-slate-700 mb-1">Instructor Notification Email</label>
            <input
              v-model="scheduleEmail"
              type="email"
              class="w-full border border-[#E6EBF3] rounded-xl p-2.5 bg-white text-slate-800"
            />
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
          <button
            @click="showScheduleModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
          >
            Cancel
          </button>
          <button
            @click="handleSaveSchedule"
            class="px-4 py-2 bg-[#4F35F3] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl"
          >
            Save Schedule
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
