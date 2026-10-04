<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorStudentStore, type Student } from '../store/instructorStudentStore'

const router = useRouter()
const store = useInstructorStudentStore()

const activeDropdown = ref<number | null>(null)
const searchQuery = ref('')
const selectedSection = ref('All Sections')
const selectedYear = ref('All Academic Years')
const selectedStatus = ref('All Status')

// Modals state
const showImportModal = ref(false)
const showAnnouncementModal = ref(false)
const showPrintAttendanceModal = ref(false)

// Announcement form
const announcementTitle = ref('')
const announcementType = ref('Exam Reminder')
const announcementMessage = ref('')
const isSendingAnnouncement = ref(false)

// Import form
const importFile = ref<File | null>(null)
const parsedImportStudents = ref<Array<{ name: string; email: string; id_no: string; gender: string }>>([])
const isImporting = ref(false)
const importError = ref<string | null>(null)

// Toast feedback
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'success'
})

const showToastNotification = (message: string, type: 'success' | 'error' | 'info' = 'success') => {
  toast.value = { show: true, message, type }
  setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

const toggleDropdown = (id: number) => {
  activeDropdown.value = activeDropdown.value === id ? null : id
}

onMounted(() => {
  store.fetchStudents()
})

// Filtered student list
const filteredStudents = computed(() => {
  return store.students.filter(student => {
    const q = searchQuery.value.trim().toLowerCase()
    const matchesSearch = !q ||
      (student.name && student.name.toLowerCase().includes(q)) ||
      (student.email && student.email.toLowerCase().includes(q)) ||
      (student.id_number && student.id_number.toLowerCase().includes(q))

    const matchesStatus = selectedStatus.value === 'All Status' ||
      (student.status && student.status.toLowerCase() === selectedStatus.value.toLowerCase())

    return matchesSearch && matchesStatus
  })
})

const resetFilters = () => {
  searchQuery.value = ''
  selectedSection.value = 'All Sections'
  selectedYear.value = 'All Academic Years'
  selectedStatus.value = 'All Status'
}

// Styling helpers
const getStatusStyles = (status: string) => {
  if (status === 'Active') return 'bg-emerald-50 text-emerald-600'
  if (status === 'Completed All Exams') return 'bg-blue-50 text-blue-600'
  if (status === 'Pending Exams') return 'bg-orange-50 text-orange-500'
  if (status === 'At Risk') return 'bg-rose-50 text-rose-600'
  return 'bg-slate-50 text-slate-500'
}

const getDotColor = (status: string) => {
  if (status === 'Active') return 'bg-emerald-500'
  if (status === 'Completed All Exams') return 'bg-blue-500'
  if (status === 'Pending Exams') return 'bg-orange-500'
  if (status === 'At Risk') return 'bg-rose-500'
  return 'bg-slate-400'
}

// Actions
const handleExport = async () => {
  try {
    showToastNotification('Preparing student list export...', 'info')
    await store.exportStudents()
    showToastNotification('Student list exported successfully!', 'success')
  } catch (err: any) {
    showToastNotification('Failed to export student list. Please try again.', 'error')
  }
}

const handleGenerateReport = () => {
  try {
    store.downloadReport()
    showToastNotification('Comprehensive student performance report generated and downloaded.', 'success')
  } catch (err: any) {
    showToastNotification('Failed to generate report.', 'error')
  }
}

const handleDownloadSingleReport = (student: Student) => {
  activeDropdown.value = null
  const rows = [
    ['Field', 'Value'],
    ['Student ID', student.id_number],
    ['Full Name', student.name],
    ['Email', student.email],
    ['Gender', student.gender],
    ['Course Name', store.courseOverview.course_name],
    ['Course Code', store.courseOverview.course_code],
    ['Section', store.courseOverview.section],
    ['Semester', store.courseOverview.semester],
    ['Academic Year', store.courseOverview.academic_year],
    ['Instructor', store.courseOverview.instructor],
    ['Exams Taken', student.exams_taken],
    ['Average Score (%)', `${student.average_score}%`],
    ['Academic Status', student.status]
  ]
  const csvContent = '\uFEFF' + rows.map(r => `"${r[0]}","${r[1]}"`).join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `Student_Report_${student.id_number.replace(/[^a-zA-Z0-9]/g, '_')}.csv`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
  showToastNotification(`Report downloaded for ${student.name}`, 'success')
}

const handlePrintSingleReport = (student: Student) => {
  activeDropdown.value = null
  const printWindow = window.open('', '_blank', 'width=800,height=600')
  if (!printWindow) return
  printWindow.document.write(`
    <html>
      <head>
        <title>Student Performance Report - ${student.name}</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 30px; color: #1e293b; }
          h2 { color: #1e1b4b; margin-bottom: 4px; }
          .sub { color: #64748b; font-size: 13px; margin-bottom: 24px; }
          table { width: 100%; border-collapse: collapse; margin-top: 16px; }
          th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
          th { background: #f8fafc; color: #475569; font-weight: 600; width: 35%; }
          .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; background: #e0e7ff; color: #4338ca; }
          .footer { margin-top: 40px; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        </style>
      </head>
      <body>
        <h2>Wollo University - Student Academic Report</h2>
        <div class="sub">Generated on ${new Date().toLocaleDateString()} | Instructor: ${store.courseOverview.instructor}</div>
        <table>
          <tr><th>Full Name</th><td><strong>${student.name}</strong></td></tr>
          <tr><th>Student ID</th><td>${student.id_number}</td></tr>
          <tr><th>Email Address</th><td>${student.email}</td></tr>
          <tr><th>Gender</th><td>${student.gender}</td></tr>
          <tr><th>Course</th><td>${store.courseOverview.course_name} (${store.courseOverview.course_code})</td></tr>
          <tr><th>Section & Year</th><td>${store.courseOverview.section} (${store.courseOverview.academic_year})</td></tr>
          <tr><th>Semester</th><td>${store.courseOverview.semester}</td></tr>
          <tr><th>Exams Taken</th><td>${student.exams_taken}</td></tr>
          <tr><th>Average Score</th><td><strong>${student.average_score}%</strong></td></tr>
          <tr><th>Academic Status</th><td><span class="badge">${student.status}</span></td></tr>
        </table>
        <div class="footer">Confidential Academic Record &bull; Online Examination and Evaluation System</div>
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

// Attendance print
const handlePrintAttendance = () => {
  showPrintAttendanceModal.value = true
}

const triggerPrintRoster = () => {
  window.print()
}

// Announcement submit
const handleSendAnnouncement = async () => {
  if (!announcementTitle.value.trim() || !announcementMessage.value.trim()) {
    showToastNotification('Please fill in both title and message.', 'error')
    return
  }

  isSendingAnnouncement.value = true
  try {
    await store.sendAnnouncement({
      title: `[${announcementType.value}] ${announcementTitle.value.trim()}`,
      message: announcementMessage.value.trim()
    })
    showToastNotification('Class announcement posted and broadcasted successfully!', 'success')
    announcementTitle.value = ''
    announcementMessage.value = ''
    showAnnouncementModal.value = false
  } catch (err: any) {
    showToastNotification(err.response?.data?.message || 'Failed to send announcement.', 'error')
  } finally {
    isSendingAnnouncement.value = false
  }
}

// Import handling
const handleFileUpload = (event: Event) => {
  const input = event.target as HTMLInputElement
  if (!input.files || input.files.length === 0) return
  const file = input.files[0]
  importFile.value = file
  importError.value = null

  const reader = new FileReader()
  reader.onload = (e) => {
    try {
      const text = e.target?.result as string
      const lines = text.split(/\r\n|\n/).filter(line => line.trim().length > 0)
      if (lines.length < 2) {
        importError.value = 'CSV file is empty or does not contain header and student rows.'
        return
      }

      // Expected header: Full Name, Email, ID Number, Gender
      const headerLine = lines[0].toLowerCase()
      const isHeaderPresent = headerLine.includes('name') || headerLine.includes('email')
      const startIndex = isHeaderPresent ? 1 : 0

      const parsed: Array<{ name: string; email: string; id_no: string; gender: string }> = []
      for (let i = startIndex; i < lines.length; i++) {
        const parts = lines[i].split(',').map(s => s.trim().replace(/^["']|["']$/g, ''))
        if (parts.length >= 2) {
          parsed.push({
            name: parts[0] || 'Unknown',
            email: parts[1] || '',
            id_no: parts[2] || `WU/STU/${1000 + i}`,
            gender: parts[3] || 'Female'
          })
        }
      }

      if (parsed.length === 0) {
        importError.value = 'Could not parse any valid student records. Please check the CSV format.'
      } else {
        parsedImportStudents.value = parsed
      }
    } catch (err) {
      importError.value = 'Error reading CSV file. Please ensure it is standard comma-separated text.'
    }
  }
  reader.readAsText(file)
}

const downloadSampleImportCsv = () => {
  const csv = "Full Name,Email,ID Number,Gender\nDawit Yohannes,dawit.y@student.wollo.edu.et,WU/2026/SE/099,Male\nBethlehem Tadesse,bethlehem.t@student.wollo.edu.et,WU/2026/SE/100,Female"
  const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'sample_students_import_template.csv'
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

const submitImportStudents = async () => {
  if (parsedImportStudents.value.length === 0) {
    importError.value = 'No student records selected for import.'
    return
  }

  isImporting.value = true
  importError.value = null
  try {
    await store.importStudents(parsedImportStudents.value)
    showToastNotification(`Successfully imported ${parsedImportStudents.value.length} students!`, 'success')
    showImportModal.value = false
    parsedImportStudents.value = []
    importFile.value = null
  } catch (err: any) {
    importError.value = err.response?.data?.message || 'Failed to import students.'
  } finally {
    isImporting.value = false
  }
}
</script>

<template>
  <div class="max-w-[1500px] mx-auto px-4 py-2 relative">

    <!-- Toast Notification -->
    <div
      v-if="toast.show"
      class="fixed top-6 right-8 z-50 flex items-center gap-3 px-5 py-3 rounded-2xl shadow-xl border text-sm font-semibold transition-all transform duration-300"
      :class="{
        'bg-emerald-600 text-white border-emerald-500 shadow-emerald-200': toast.type === 'success',
        'bg-rose-600 text-white border-rose-500 shadow-rose-200': toast.type === 'error',
        'bg-indigo-600 text-white border-indigo-500 shadow-indigo-200': toast.type === 'info',
      }"
    >
      <svg v-if="toast.type === 'success'" class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
      <svg v-else-if="toast.type === 'error'" class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
      <svg v-else class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      <span>{{ toast.message }}</span>
    </div>

    <!-- Header Actions (Top Right) -->
    <div class="flex flex-col md:flex-row md:items-center justify-end gap-3 mb-6 -mt-12 absolute right-8 top-6 z-40 hidden lg:flex">
      <button
        @click="showImportModal = true"
        class="flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-[#5138ed] text-xs font-bold rounded-xl hover:bg-indigo-50 transition-colors bg-white shadow-xs cursor-pointer"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
        Import Students
      </button>
      <button
        @click="handleExport"
        :disabled="store.isExporting"
        class="flex items-center gap-2 px-4 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl shadow-sm shadow-indigo-200 hover:bg-[#4530d1] transition-colors cursor-pointer disabled:opacity-50"
      >
        <svg v-if="!store.isExporting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
        {{ store.isExporting ? 'Exporting...' : 'Export Students' }}
      </button>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
      
      <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
        </div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase">Total Students</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-1">{{ store.stats.total_students }}</span>
          <span class="text-[10px] font-medium text-slate-400">All enrolled students</span>
        </div>
      </div>

      <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500 shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
        </div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase">Active Students</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-1">{{ store.stats.active_students }}</span>
          <span class="text-[10px] font-medium text-slate-400">{{ store.stats.total_students > 0 ? Math.round((store.stats.active_students / store.stats.total_students) * 100) : 0 }}% of total students</span>
        </div>
      </div>

      <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
        </div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase">Average Score</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-1">{{ store.stats.average_score }}%</span>
          <span class="text-[10px] font-medium text-slate-400">Class average</span>
        </div>
      </div>

      <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500 shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase">Top Performers</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-1">{{ store.stats.top_performers }}</span>
          <span class="text-[10px] font-medium text-slate-400">Students &ge; 85%</span>
        </div>
      </div>

      <div class="bg-white border border-slate-100 rounded-2xl p-4 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div class="flex flex-col">
          <span class="text-[10px] font-bold text-slate-500 uppercase">Average Attendance</span>
          <span class="text-2xl font-black text-slate-800 leading-none my-1">{{ store.stats.average_attendance ?? store.studentProgress.average_attendance ?? 0 }}%</span>
          <span class="text-[10px] font-medium text-slate-400">Overall class attendance</span>
        </div>
      </div>

    </div>

    <!-- Main Content Layout -->
    <div class="flex flex-col gap-6">

      <!-- Main Column (Table) -->
      <div class="w-full flex flex-col">
        
        <!-- Search & Filter Bar -->
        <div class="bg-white border border-slate-100 rounded-2xl px-4 py-2.5 shadow-sm flex items-center gap-4 flex-wrap mb-4">
          <div class="relative flex-1 min-w-[200px]">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              class="w-full pl-9 pr-3 py-2 border-0 bg-transparent text-sm placeholder-slate-400 focus:outline-none"
              placeholder="Search by name, ID number or email..."
            >
          </div>
          <div class="w-px h-6 bg-slate-200"></div>
          <select v-model="selectedSection" class="appearance-none bg-transparent text-[13px] font-semibold text-slate-700 focus:outline-none pr-6 cursor-pointer">
            <option>All Sections</option>
            <option :value="store.courseOverview.section">{{ store.courseOverview.section }}</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 -ml-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          <div class="w-px h-6 bg-slate-200 ml-2"></div>
          <select v-model="selectedYear" class="appearance-none bg-transparent text-[13px] font-semibold text-slate-700 focus:outline-none pr-6 cursor-pointer">
            <option>All Academic Years</option>
            <option :value="store.courseOverview.academic_year">{{ store.courseOverview.academic_year }}</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 -ml-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          <div class="w-px h-6 bg-slate-200 ml-2"></div>
          <select v-model="selectedStatus" class="appearance-none bg-transparent text-[13px] font-semibold text-slate-700 focus:outline-none pr-6 cursor-pointer">
            <option>All Status</option>
            <option>Active</option>
            <option>Completed All Exams</option>
            <option>Pending Exams</option>
            <option>At Risk</option>
          </select>
          <svg class="w-3.5 h-3.5 text-slate-400 -ml-5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          <button
            @click="resetFilters"
            class="ml-auto flex items-center gap-1.5 px-3 py-1.5 text-[#5138ed] text-[12px] font-bold rounded-lg hover:bg-indigo-50 transition-colors cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            Reset Filter
          </button>
        </div>

        <!-- Students Table -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm flex-1 flex flex-col">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
              <thead>
                <tr class="text-[9px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                  <th class="py-4 pl-6 pr-3">Student</th>
                  <th class="py-4 px-3">ID Number</th>
                  <th class="py-4 px-3">Email</th>
                  <th class="py-4 px-3">Gender</th>
                  <th class="py-4 px-3 w-48">Exam Progress</th>
                  <th class="py-4 px-3">Status</th>
                  <th class="py-4 px-6 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="store.isLoading" class="border-b border-slate-50">
                  <td colspan="7" class="py-10 text-center text-slate-500 text-sm">
                    <div class="flex items-center justify-center gap-2">
                      <svg class="w-5 h-5 animate-spin text-[#5138ed]" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                      <span>Loading students from database...</span>
                    </div>
                  </td>
                </tr>
                <tr v-else-if="filteredStudents.length === 0" class="border-b border-slate-50">
                  <td colspan="7" class="py-10 text-center text-slate-500 text-sm">
                    No students found matching your criteria.
                  </td>
                </tr>
                <tr v-else v-for="student in filteredStudents" :key="student.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0 group">
                  <td class="py-4 pl-6 pr-3">
                    <div class="flex items-center gap-3">
                      <div class="w-8 h-8 rounded-full bg-indigo-50 text-[#5138ed] flex items-center justify-center font-bold text-xs uppercase border border-indigo-100">
                        {{ student.name ? student.name.substring(0, 2) : 'ST' }}
                      </div>
                      <span class="text-[13px] font-bold text-slate-800">{{ student.name }}</span>
                    </div>
                  </td>
                  <td class="py-4 px-3 text-[12px] font-semibold text-slate-600">{{ student.id_number }}</td>
                  <td class="py-4 px-3 text-[12px] font-medium text-slate-500">{{ student.email }}</td>
                  <td class="py-4 px-3 text-[12px] font-semibold text-slate-600">{{ student.gender }}</td>
                  <td class="py-4 px-3">
                    <div class="flex flex-col gap-1 w-full max-w-[160px]">
                      <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-500">{{ student.exams_taken }} Exams Taken</span>
                      </div>
                      <div class="flex items-center gap-2">
                        <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                          <div class="h-full rounded-full transition-all duration-500 bg-[#5138ed]" :style="`width: ${Math.min(100, student.average_score)}%`"></div>
                        </div>
                        <span class="text-[10px] font-bold text-slate-700 w-8 text-right">{{ student.average_score }}%</span>
                      </div>
                    </div>
                  </td>
                  <td class="py-4 px-3">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[10px] font-bold" :class="getStatusStyles(student.status)">
                      <span class="w-1.5 h-1.5 rounded-full" :class="getDotColor(student.status)"></span>
                      {{ student.status }}
                    </span>
                  </td>
                  <td class="py-4 px-6">
                    <div class="flex items-center justify-end gap-1 opacity-100 transition-opacity">
                      <button @click="router.push(`/instructor/students/${student.id}`)" class="w-7 h-7 rounded-lg flex items-center justify-center text-[#5138ed] hover:bg-indigo-50 transition-colors cursor-pointer" title="View Details">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                      </button>
                      <button @click="router.push(`/instructor/students/${student.id}/results`)" class="w-7 h-7 rounded-lg flex items-center justify-center text-[#5138ed] hover:bg-indigo-50 transition-colors cursor-pointer" title="Exam Results">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                      </button>
                      <button @click="showAnnouncementModal = true" class="w-7 h-7 rounded-lg flex items-center justify-center text-[#5138ed] hover:bg-indigo-50 transition-colors cursor-pointer" title="Send Message">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                      </button>
                      <div class="relative">
                        <button @click="toggleDropdown(student.id)" class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-50 transition-colors cursor-pointer" title="More Options">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div v-if="activeDropdown === student.id" class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50 overflow-hidden">
                          <button
                            @click="handleDownloadSingleReport(student)"
                            class="w-full text-left px-4 py-2.5 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2 cursor-pointer"
                          >
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Download Student Report
                          </button>
                          <button
                            @click="handlePrintSingleReport(student)"
                            class="w-full text-left px-4 py-2.5 text-[12px] font-bold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2 cursor-pointer"
                          >
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            Print Student Report
                          </button>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="mt-auto p-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[11px] text-slate-500 font-medium">Showing {{ filteredStudents.length }} of {{ store.students.length }} students</span>
            <div class="flex items-center gap-1">
              <button class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-[11px] shadow-sm">1</button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 hover:bg-slate-50 transition-colors"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
            </div>
          </div>
        </div>

      </div> <!-- End Main Column (Table) -->

      <!-- Bottom Section: Course Overview, Progress, Quick Actions -->
      <div class="w-full grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- Course Overview -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center gap-2 mb-4">
            <div class="w-6 h-6 rounded-md bg-indigo-50 text-[#5138ed] flex items-center justify-center"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg></div>
            <h3 class="text-[13px] font-bold text-slate-800">Course Overview</h3>
          </div>
          
          <div class="space-y-3">
            <div class="flex flex-col">
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Course Name</span>
              <span class="text-[12px] font-bold text-slate-800">{{ store.courseOverview.course_name }}</span>
            </div>
            <div class="flex flex-col">
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Course Code</span>
              <span class="text-[12px] font-bold text-slate-800">{{ store.courseOverview.course_code }}</span>
            </div>
            <div class="flex flex-col">
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Section</span>
              <span class="text-[12px] font-bold text-slate-800">{{ store.courseOverview.section }}</span>
            </div>
            <div class="flex flex-col">
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Semester</span>
              <span class="text-[12px] font-bold text-slate-800">{{ store.courseOverview.semester }}</span>
            </div>
            <div class="flex flex-col">
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Academic Year</span>
              <span class="text-[12px] font-bold text-slate-800">{{ store.courseOverview.academic_year }}</span>
            </div>
            <div class="flex flex-col">
              <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">Instructor</span>
              <span class="text-[12px] font-bold text-slate-800">{{ store.courseOverview.instructor }}</span>
            </div>
          </div>
        </div>

        <!-- Student Progress Summary -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center gap-2 mb-5">
            <div class="w-6 h-6 rounded-md bg-indigo-50 text-[#5138ed] flex items-center justify-center"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg></div>
            <h3 class="text-[13px] font-bold text-slate-800">Student Progress</h3>
          </div>
          
          <div class="space-y-4">
            <div class="flex flex-col gap-1">
              <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-600">Completed Exams</span>
                <span class="text-[10px] font-bold text-slate-800">{{ store.studentProgress.completed_exams_percent }}%</span>
              </div>
              <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.completed_exams_percent)}%` }"></div>
              </div>
            </div>
            
            <div class="flex flex-col gap-1">
              <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-600">Pending Exams</span>
                <span class="text-[10px] font-bold text-slate-800">{{ store.studentProgress.pending_exams_percent }}%</span>
              </div>
              <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-orange-400 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.pending_exams_percent)}%` }"></div>
              </div>
            </div>

            <div class="flex flex-col gap-1">
              <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-600">Average Attendance</span>
                <span class="text-[10px] font-bold text-slate-800">{{ store.studentProgress.average_attendance }}%</span>
              </div>
              <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.average_attendance)}%` }"></div>
              </div>
            </div>

            <div class="flex flex-col gap-1">
              <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-600">Average Exam Score</span>
                <span class="text-[10px] font-bold text-slate-800">{{ store.studentProgress.average_exam_score }}%</span>
              </div>
              <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-[#5138ed] rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.average_exam_score)}%` }"></div>
              </div>
            </div>

            <div class="flex flex-col gap-1">
              <div class="flex justify-between items-center">
                <span class="text-[10px] font-bold text-slate-600">Students At Risk</span>
                <span class="text-[10px] font-bold text-slate-800">{{ store.studentProgress.students_at_risk_percent }}%</span>
              </div>
              <div class="h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
                <div class="h-full bg-rose-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.students_at_risk_percent)}%` }"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition-shadow">
          <div class="flex items-center gap-2 mb-4">
            <div class="w-6 h-6 rounded-md bg-indigo-50 text-[#5138ed] flex items-center justify-center"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
            <h3 class="text-[13px] font-bold text-slate-800">Quick Actions</h3>
          </div>
          <div class="space-y-2">
            <button
              @click="showImportModal = true"
              class="w-full flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 transition-colors group text-left cursor-pointer"
            >
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg></div>
              <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Import Students</span>
            </button>
            <div class="h-px w-full bg-slate-50"></div>
            <button
              @click="handleExport"
              :disabled="store.isExporting"
              class="w-full flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 transition-colors group text-left cursor-pointer disabled:opacity-50"
            >
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg></div>
              <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">{{ store.isExporting ? 'Exporting List...' : 'Export Student List' }}</span>
            </button>
            <div class="h-px w-full bg-slate-50"></div>
            <button
              @click="handlePrintAttendance"
              class="w-full flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 transition-colors group text-left cursor-pointer"
            >
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg></div>
              <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Print Attendance</span>
            </button>
            <div class="h-px w-full bg-slate-50"></div>
            <button
              @click="handleGenerateReport"
              class="w-full flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 transition-colors group text-left cursor-pointer"
            >
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div>
              <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Generate Student Report</span>
            </button>
            <div class="h-px w-full bg-slate-50"></div>
            <button
              @click="showAnnouncementModal = true"
              class="w-full flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 transition-colors group text-left cursor-pointer"
            >
              <div class="text-[#5138ed]"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg></div>
              <span class="text-[11px] font-bold text-slate-700 group-hover:text-[#5138ed] transition-colors">Send Announcement</span>
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. IMPORT STUDENTS MODAL -->
    <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 class="text-base font-bold text-slate-800">Import Students</h3>
            <p class="text-xs text-slate-500 mt-0.5">Enroll students into <span class="font-bold text-[#5138ed]">{{ store.courseOverview.course_name }} ({{ store.courseOverview.section }})</span></p>
          </div>
          <button @click="showImportModal = false" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="py-4 space-y-4">
          <div class="flex items-center justify-between bg-indigo-50/60 p-3.5 rounded-xl border border-indigo-100">
            <div class="flex items-center gap-2.5">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              <span class="text-xs font-medium text-slate-700">Need the correct column format?</span>
            </div>
            <button @click="downloadSampleImportCsv" class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer">
              Download Template CSV
            </button>
          </div>

          <div class="border-2 border-dashed border-slate-200 hover:border-[#5138ed] transition-colors rounded-2xl p-6 text-center">
            <input type="file" accept=".csv" @change="handleFileUpload" id="file-upload" class="hidden" />
            <label for="file-upload" class="cursor-pointer flex flex-col items-center">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
              </div>
              <span class="text-sm font-bold text-slate-800">Click to upload CSV</span>
              <span class="text-xs text-slate-400 mt-1">Columns: Full Name, Email, ID Number, Gender</span>
              <span v-if="importFile" class="mt-2 text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                Selected: {{ importFile.name }}
              </span>
            </label>
          </div>

          <div v-if="importError" class="p-3 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 text-xs font-medium">
            {{ importError }}
          </div>

          <div v-if="parsedImportStudents.length > 0" class="space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-700">
              <span>Preview ({{ parsedImportStudents.length }} students found)</span>
              <span class="text-emerald-600">Ready to Enroll</span>
            </div>
            <div class="max-h-40 overflow-y-auto border border-slate-100 rounded-xl divide-y divide-slate-100 text-xs">
              <div v-for="(s, idx) in parsedImportStudents.slice(0, 10)" :key="idx" class="p-2.5 flex items-center justify-between">
                <div>
                  <span class="font-bold text-slate-800">{{ s.name }}</span>
                  <span class="text-slate-400 ml-2 font-mono text-[11px]">{{ s.id_no }}</span>
                </div>
                <span class="text-slate-500 font-mono text-[11px]">{{ s.email }}</span>
              </div>
              <div v-if="parsedImportStudents.length > 10" class="p-2 text-center text-slate-400 font-medium">
                ...and {{ parsedImportStudents.length - 10 }} more
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-auto">
          <button @click="showImportModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer">
            Cancel
          </button>
          <button
            @click="submitImportStudents"
            :disabled="parsedImportStudents.length === 0 || isImporting"
            class="px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors cursor-pointer disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="isImporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            {{ isImporting ? 'Importing Students...' : 'Enroll Students Now' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 2. SEND ANNOUNCEMENT MODAL -->
    <div v-if="showAnnouncementModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-100 flex flex-col">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 class="text-base font-bold text-slate-800">Send Class Announcement</h3>
            <p class="text-xs text-slate-500 mt-0.5">Broadcast message to <span class="font-bold text-[#5138ed]">{{ store.courseOverview.course_name }} ({{ store.courseOverview.section }})</span></p>
          </div>
          <button @click="showAnnouncementModal = false" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="py-4 space-y-4">
          <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Announcement Type</label>
            <select v-model="announcementType" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]">
              <option>Exam Reminder</option>
              <option>Course Material Update</option>
              <option>Class Schedule Change</option>
              <option>General Notice</option>
              <option>Urgent Alert</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Subject / Title</label>
            <input
              v-model="announcementTitle"
              type="text"
              placeholder="e.g., Midterm Exam Schedule and Guidelines"
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed]"
            />
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1">Message Content</label>
            <textarea
              v-model="announcementMessage"
              rows="4"
              placeholder="Enter announcement details, instructions, or notes for your students..."
              class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#5138ed] resize-none"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
          <button @click="showAnnouncementModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer">
            Cancel
          </button>
          <button
            @click="handleSendAnnouncement"
            :disabled="isSendingAnnouncement || !announcementTitle.trim() || !announcementMessage.trim()"
            class="px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors cursor-pointer disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="isSendingAnnouncement" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            {{ isSendingAnnouncement ? 'Broadcasting...' : 'Broadcast to Class' }}
          </button>
        </div>
      </div>
    </div>

    <!-- 3. PRINT ATTENDANCE MODAL & PRINTABLE VIEW -->
    <div v-if="showPrintAttendanceModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
      <div class="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 class="text-base font-bold text-slate-800">Print Attendance Sheet</h3>
            <p class="text-xs text-slate-500 mt-0.5">Roster for {{ store.courseOverview.course_name }} ({{ store.courseOverview.course_code }}) - {{ store.courseOverview.section }}</p>
          </div>
          <button @click="showPrintAttendanceModal = false" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Printable Document Container -->
        <div class="py-4 overflow-y-auto flex-1 printable-roster">
          <div class="border border-slate-200 rounded-2xl p-6 bg-white shadow-xs">
            <div class="text-center pb-4 border-b border-slate-200">
              <h2 class="text-base font-bold uppercase tracking-wider text-slate-800">Wollo University</h2>
              <h3 class="text-xs font-semibold text-slate-600">College of Computing and Informatics &bull; Department of Software Engineering</h3>
              <div class="mt-2 text-sm font-black text-[#5138ed] uppercase tracking-wide">Course Attendance Roster</div>
            </div>

            <!-- Details Header -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 py-4 text-xs border-b border-slate-200">
              <div><span class="text-slate-400 font-bold block text-[10px] uppercase">Course:</span> <strong class="text-slate-800">{{ store.courseOverview.course_name }} ({{ store.courseOverview.course_code }})</strong></div>
              <div><span class="text-slate-400 font-bold block text-[10px] uppercase">Section:</span> <strong class="text-slate-800">{{ store.courseOverview.section }}</strong></div>
              <div><span class="text-slate-400 font-bold block text-[10px] uppercase">Instructor:</span> <strong class="text-slate-800">{{ store.courseOverview.instructor }}</strong></div>
              <div><span class="text-slate-400 font-bold block text-[10px] uppercase">Academic Year & Term:</span> <strong class="text-slate-800">{{ store.courseOverview.academic_year }} - {{ store.courseOverview.semester }}</strong></div>
            </div>

            <!-- Roster Table -->
            <table class="w-full text-left border-collapse mt-4 text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold text-[10px] uppercase">
                  <th class="p-2 w-8 text-center">#</th>
                  <th class="p-2 w-28">ID Number</th>
                  <th class="p-2">Student Name</th>
                  <th class="p-2 w-16">Gender</th>
                  <th class="p-2 w-14 text-center border-l border-slate-200">D 1</th>
                  <th class="p-2 w-14 text-center border-l border-slate-200">D 2</th>
                  <th class="p-2 w-14 text-center border-l border-slate-200">D 3</th>
                  <th class="p-2 w-14 text-center border-l border-slate-200">D 4</th>
                  <th class="p-2 w-24 text-center border-l border-slate-200">Signature</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="(student, index) in store.students" :key="student.id" class="h-9">
                  <td class="p-2 text-center text-slate-400 font-mono text-[11px]">{{ index + 1 }}</td>
                  <td class="p-2 font-mono text-slate-700 font-semibold">{{ student.id_number }}</td>
                  <td class="p-2 font-bold text-slate-800">{{ student.name }}</td>
                  <td class="p-2 text-slate-600">{{ student.gender }}</td>
                  <td class="border-l border-slate-200"></td>
                  <td class="border-l border-slate-200"></td>
                  <td class="border-l border-slate-200"></td>
                  <td class="border-l border-slate-200"></td>
                  <td class="border-l border-slate-200"></td>
                </tr>
              </tbody>
            </table>

            <div class="flex justify-between items-center mt-12 pt-4 border-t border-slate-200 text-xs text-slate-500">
              <div>Instructor Signature: _______________________</div>
              <div>Date: {{ new Date().toLocaleDateString() }}</div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-auto">
          <button @click="showPrintAttendanceModal = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer">
            Close
          </button>
          <button
            @click="triggerPrintRoster"
            class="px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors cursor-pointer flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print Attendance Sheet
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style>
@media print {
  body * {
    visibility: hidden;
  }
  .printable-roster, .printable-roster * {
    visibility: visible;
  }
  .printable-roster {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 20px;
  }
}
</style>
