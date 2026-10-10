<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorStudentStore, type Student } from '../store/instructorStudentStore'
import { useSemesterLockStore } from '../store/semesterLockStore'

const router = useRouter()
const store = useInstructorStudentStore()
const lockStore = useSemesterLockStore()

// Dropdown & Filter State
const activeDropdown = ref<number | null>(null)
const searchQuery = ref('')
const selectedSection = ref('All Sections')
const selectedYear = ref('All Academic Years')
const selectedStatus = ref('All Status')
const perPage = ref(10)
const currentPage = ref(1)

// Modals State
const showImportModal = ref(false)
const showAnnouncementModal = ref(false)
const showPrintAttendanceModal = ref(false)

// Announcement Form
const announcementTitle = ref('')
const announcementType = ref('Exam Reminder')
const announcementMessage = ref('')
const targetedStudent = ref<Student | null>(null)
const isSendingAnnouncement = ref(false)

// Import Form
const importFile = ref<File | null>(null)
const parsedImportStudents = ref<Array<{ name: string; email: string; id_no: string; gender: string }>>([])
const isImporting = ref(false)
const importError = ref<string | null>(null)

// Toast Feedback
const toast = ref<{ show: boolean; message: string; type: 'success' | 'error' | 'info' }>({
  show: false,
  message: '',
  type: 'success'
})

let toastTimeout: any = null
const showToastNotification = (message: string, type: 'success' | 'error' | 'info' = 'success') => {
  if (toastTimeout) clearTimeout(toastTimeout)
  toast.value = { show: true, message, type }
  toastTimeout = setTimeout(() => {
    toast.value.show = false
  }, 4000)
}

// Close dropdown on outside click
const handleGlobalClick = (event: MouseEvent) => {
  const target = event.target as HTMLElement
  if (!target.closest('.student-action-menu')) {
    activeDropdown.value = null
  }
}

onMounted(() => {
  store.fetchStudents()
  lockStore.fetchLockStatus()
  document.addEventListener('click', handleGlobalClick)
})

onUnmounted(() => {
  document.removeEventListener('click', handleGlobalClick)
})

const toggleDropdown = (id: number, event?: MouseEvent) => {
  event?.stopPropagation()
  activeDropdown.value = activeDropdown.value === id ? null : id
}

// Available Sections & Years for dropdowns
const availableSections = computed(() => {
  const set = new Set<string>()
  if (store.courseOverview.section) set.add(store.courseOverview.section)
  return Array.from(set)
})

const availableYears = computed(() => {
  const set = new Set<string>()
  if (store.courseOverview.academic_year) set.add(store.courseOverview.academic_year)
  return Array.from(set)
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

// Reset page when filters change
watch([searchQuery, selectedSection, selectedYear, selectedStatus, perPage], () => {
  currentPage.value = 1
})

// Pagination
const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredStudents.value.length / perPage.value))
})

const paginatedStudents = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredStudents.value.slice(start, start + perPage.value)
})

const startItemIndex = computed(() => {
  if (filteredStudents.value.length === 0) return 0
  return (currentPage.value - 1) * perPage.value + 1
})

const endItemIndex = computed(() => {
  return Math.min(currentPage.value * perPage.value, filteredStudents.value.length)
})

const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedSection.value = 'All Sections'
  selectedYear.value = 'All Academic Years'
  selectedStatus.value = 'All Status'
  currentPage.value = 1
}

const isFilterActive = computed(() => {
  return searchQuery.value !== '' ||
    selectedSection.value !== 'All Sections' ||
    selectedYear.value !== 'All Academic Years' ||
    selectedStatus.value !== 'All Status'
})

// Helpers for initials and colors
const getInitials = (name: string) => {
  if (!name) return 'ST'
  const parts = name.trim().split(/\s+/)
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

const getProgressBarColor = (score: number) => {
  if (score >= 80) return 'bg-emerald-500'
  if (score >= 60) return 'bg-[#5138ed]'
  if (score >= 40) return 'bg-amber-500'
  return 'bg-rose-500'
}

const getStatusBadgeStyles = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'active':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200/80'
    case 'completed all exams':
      return 'bg-blue-50 text-blue-700 border-blue-200/80'
    case 'pending exams':
      return 'bg-amber-50 text-amber-700 border-amber-200/80'
    case 'at risk':
      return 'bg-rose-50 text-rose-700 border-rose-200/80'
    default:
      return 'bg-slate-50 text-slate-700 border-slate-200/80'
  }
}

const getStatusDotColor = (status: string) => {
  switch (status?.toLowerCase()) {
    case 'active':
      return 'bg-emerald-500 ring-emerald-200'
    case 'completed all exams':
      return 'bg-blue-500 ring-blue-200'
    case 'pending exams':
      return 'bg-amber-500 ring-amber-200'
    case 'at risk':
      return 'bg-rose-500 ring-rose-200'
    default:
      return 'bg-slate-400 ring-slate-200'
  }
}

// Modals & Actions
const openImportModal = () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('import students')
    return
  }
  importFile.value = null
  parsedImportStudents.value = []
  importError.value = null
  showImportModal.value = true
}

const openStudentAnnouncement = (student?: Student) => {
  if (student) {
    targetedStudent.value = student
    announcementTitle.value = `Notice for ${student.name} (${student.id_number})`
  } else {
    targetedStudent.value = null
    announcementTitle.value = ''
  }
  announcementMessage.value = ''
  showAnnouncementModal.value = true
}

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
    showToastNotification('Comprehensive student performance report downloaded.', 'success')
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
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 32px; color: #1e293b; line-height: 1.5; }
          .header { border-bottom: 2px solid #5138ed; padding-bottom: 16px; margin-bottom: 24px; }
          h2 { color: #1e1b4b; margin: 0 0 6px 0; font-size: 20px; }
          .sub { color: #64748b; font-size: 13px; }
          table { width: 100%; border-collapse: collapse; margin-top: 20px; }
          th, td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
          th { background: #f8fafc; color: #475569; font-weight: 600; width: 35%; }
          .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-weight: bold; font-size: 12px; background: #e0e7ff; color: #4338ca; }
          .footer { margin-top: 48px; font-size: 11px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 16px; display: flex; justify-content: space-between; }
        </style>
      </head>
      <body>
        <div class="header">
          <h2>Wollo University &bull; Student Academic Performance Report</h2>
          <div class="sub">Generated on ${new Date().toLocaleDateString()} | Instructor: ${store.courseOverview.instructor}</div>
        </div>
        <table>
          <tr><th>Full Name</th><td><strong>${student.name}</strong></td></tr>
          <tr><th>Student ID</th><td><code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">${student.id_number}</code></td></tr>
          <tr><th>Email Address</th><td>${student.email}</td></tr>
          <tr><th>Gender</th><td>${student.gender}</td></tr>
          <tr><th>Course</th><td>${store.courseOverview.course_name} (${store.courseOverview.course_code})</td></tr>
          <tr><th>Section & Year</th><td>${store.courseOverview.section} (${store.courseOverview.academic_year})</td></tr>
          <tr><th>Semester</th><td>${store.courseOverview.semester}</td></tr>
          <tr><th>Exams Taken</th><td>${student.exams_taken}</td></tr>
          <tr><th>Average Score</th><td><strong style="color:#5138ed;font-size:15px;">${student.average_score}%</strong></td></tr>
          <tr><th>Academic Status</th><td><span class="badge">${student.status}</span></td></tr>
        </table>
        <div class="footer">
          <span>Official Academic Record &bull; Wollo University Online Examination System</span>
          <span>Confidential</span>
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

const handlePrintAttendance = () => {
  showPrintAttendanceModal.value = true
}

const triggerPrintRoster = () => {
  window.print()
}

const handleSendAnnouncement = async () => {
  if (!announcementTitle.value.trim() || !announcementMessage.value.trim()) {
    showToastNotification('Please fill in both subject and message.', 'error')
    return
  }

  isSendingAnnouncement.value = true
  try {
    const prefix = targetedStudent.value ? `[Direct: ${targetedStudent.value.name}]` : `[${announcementType.value}]`
    await store.sendAnnouncement({
      title: `${prefix} ${announcementTitle.value.trim()}`,
      message: announcementMessage.value.trim()
    })
    showToastNotification(
      targetedStudent.value
        ? `Message sent to ${targetedStudent.value.name} successfully!`
        : 'Class announcement broadcasted successfully!',
      'success'
    )
    announcementTitle.value = ''
    announcementMessage.value = ''
    targetedStudent.value = null
    showAnnouncementModal.value = false
  } catch (err: any) {
    showToastNotification(err.response?.data?.message || 'Failed to send announcement.', 'error')
  } finally {
    isSendingAnnouncement.value = false
  }
}

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
  <div class="max-w-[1440px] mx-auto space-y-6">

    <!-- Toast Notification -->
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform translate-y-2 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform translate-y-2 opacity-0"
    >
      <div
        v-if="toast.show"
        class="fixed top-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-sm font-semibold transition-all max-w-[calc(100vw-2.5rem)]"
        :class="{
          'bg-emerald-600 text-white border-emerald-500 shadow-emerald-200/50': toast.type === 'success',
          'bg-rose-600 text-white border-rose-500 shadow-rose-200/50': toast.type === 'error',
          'bg-[#5138ed] text-white border-indigo-400 shadow-indigo-200/50': toast.type === 'info',
        }"
      >
        <svg v-if="toast.type === 'success'" class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        <svg v-else-if="toast.type === 'error'" class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        <svg v-else class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>{{ toast.message }}</span>
      </div>
    </transition>

    <!-- Page Header + Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-2xl sm:text-[26px] font-black text-slate-900 tracking-tight leading-tight">
            Students
          </h1>
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-[#5138ed] border border-indigo-100/80">
            {{ store.courseOverview.section || 'All Sections' }}
          </span>
        </div>
        <p class="text-xs sm:text-sm text-slate-500 mt-1 font-medium">
          View and monitor students enrolled in your assigned courses.
        </p>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Re-fetch / Refresh button -->
        <button
          @click="store.fetchStudents()"
          :disabled="store.isLoading"
          class="w-10 h-10 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300 text-slate-600 flex items-center justify-center transition-colors cursor-pointer shadow-2xs"
          title="Refresh Student List"
        >
          <svg class="w-4 h-4 text-slate-500" :class="{ 'animate-spin': store.isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>

        <!-- Import Students (Lock Aware) -->
        <button
          v-if="!lockStore.isLocked"
          @click="openImportModal"
          class="flex items-center justify-center gap-2 bg-white hover:bg-indigo-50/60 border border-slate-200 hover:border-indigo-200 text-[#5138ed] px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-2xs hover:shadow-xs transition-all min-h-[44px] cursor-pointer"
        >
          <svg class="w-4 h-4 text-[#5138ed] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span>Import Students</span>
        </button>

        <!-- Semester Locked Indicator Pill -->
        <div
          v-else
          @click="lockStore.promptLockedNotice('import students')"
          class="flex items-center justify-center gap-2 bg-emerald-50 hover:bg-emerald-100/80 border border-emerald-200/80 text-emerald-800 px-4 py-2.5 rounded-xl font-bold text-xs shadow-2xs cursor-pointer transition-colors min-h-[44px]"
          title="Student enrollments are locked after semester submission. Click for details."
        >
          <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span>Enrollment Locked</span>
        </div>

        <!-- Export Students -->
        <button
          @click="handleExport"
          :disabled="store.isExporting"
          class="flex items-center justify-center gap-2 bg-[#5138ed] hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-xs hover:shadow transition-all min-h-[44px] cursor-pointer disabled:opacity-50"
        >
          <svg v-if="!store.isExporting" class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
          </svg>
          <svg v-else class="w-4 h-4 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
          </svg>
          <span>{{ store.isExporting ? 'Exporting...' : 'Export Students' }}</span>
        </button>
      </div>
    </div>

    <!-- Polished Semester Lock Banner (When Locked) -->
    <div
      v-if="lockStore.isLocked"
      class="bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 border border-emerald-200/90 rounded-2xl p-4 sm:p-5 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div class="flex items-start gap-3.5 min-w-0">
        <div class="w-10 h-10 rounded-xl bg-emerald-100/90 border border-emerald-200 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 shadow-2xs">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
        </div>
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <h3 class="text-sm font-bold text-emerald-950">
              Semester Locked — Read-Only Access
            </h3>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-200/60 text-emerald-900 border border-emerald-300/60 uppercase tracking-wider">
              {{ lockStore.statusLabel }}
            </span>
          </div>
          <p class="text-xs text-emerald-800/90 leading-relaxed max-w-3xl">
            Student enrollments and academic records are finalized for the current semester. Student roster modifications are disabled.
          </p>
          <div class="flex flex-wrap items-center gap-3 text-[11px] text-emerald-700/80 font-medium mt-2 pt-2 border-t border-emerald-200/60">
            <span>Academic Year: <strong class="text-emerald-900">{{ lockStore.academicYear }}</strong></span>
            <span>•</span>
            <span>Semester: <strong class="text-emerald-900">{{ lockStore.semester }}</strong></span>
            <span v-if="lockStore.submittedAt">•</span>
            <span v-if="lockStore.submittedAt">Submitted: <strong class="text-emerald-900">{{ lockStore.submittedAt }}</strong></span>
          </div>
        </div>
      </div>

      <router-link
        to="/instructor/semester-submission"
        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition-colors shrink-0 min-h-[40px]"
      >
        <span>View Submission Details</span>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
      </router-link>
    </div>

    <!-- Dev Mode Banner (If offline fallback) -->
    <div
      v-if="store.usingMockData"
      class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-4 py-3 text-xs sm:text-sm font-medium flex items-center justify-between gap-3 shadow-2xs"
    >
      <div class="flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span><strong>Dev Mode:</strong> Backend is offline — showing mock student data.</span>
      </div>
      <button @click="store.fetchStudents()" class="text-xs font-bold underline hover:text-amber-900 cursor-pointer">
        Retry
      </button>
    </div>

    <!-- Error Banner (Real API Error) -->
    <div
      v-if="store.error"
      class="bg-rose-50 border border-rose-200 text-rose-700 rounded-2xl p-4 text-xs sm:text-sm font-medium flex items-center justify-between gap-3 shadow-2xs"
    >
      <div class="flex items-center gap-2.5">
        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ store.error }}</span>
      </div>
      <button
        @click="store.fetchStudents()"
        class="px-3 py-1.5 bg-rose-100 hover:bg-rose-200 text-rose-800 rounded-lg text-xs font-bold transition-colors cursor-pointer"
      >
        Retry
      </button>
    </div>

    <!-- 5 Summary KPI Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3.5 sm:gap-4">
      
      <!-- 1. Total Students -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex items-center gap-3.5 group">
        <div class="w-12 h-12 rounded-xl bg-indigo-50 border border-indigo-100/60 flex items-center justify-center text-[#5138ed] shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Students</span>
          <div v-if="store.isLoading" class="h-7 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-2xl font-black text-slate-800 leading-tight tracking-tight my-0.5">
            {{ store.stats.total_students }}
          </span>
          <span class="text-[11px] font-medium text-slate-500 truncate">All enrolled students</span>
        </div>
      </div>

      <!-- 2. Active Students -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex items-center gap-3.5 group">
        <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100/60 flex items-center justify-center text-emerald-600 shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Active Students</span>
          <div v-if="store.isLoading" class="h-7 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-2xl font-black text-slate-800 leading-tight tracking-tight my-0.5">
            {{ store.stats.active_students }}
          </span>
          <span class="text-[11px] font-medium text-emerald-600 truncate">
            {{ store.stats.total_students > 0 ? Math.round((store.stats.active_students / store.stats.total_students) * 100) : 0 }}% of total
          </span>
        </div>
      </div>

      <!-- 3. Average Score -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex items-center gap-3.5 group">
        <div class="w-12 h-12 rounded-xl bg-blue-50 border border-blue-100/60 flex items-center justify-center text-blue-600 shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Average Score</span>
          <div v-if="store.isLoading" class="h-7 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-2xl font-black text-slate-800 leading-tight tracking-tight my-0.5">
            {{ store.stats.average_score }}%
          </span>
          <span class="text-[11px] font-medium text-slate-500 truncate">Class average</span>
        </div>
      </div>

      <!-- 4. Top Performers -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex items-center gap-3.5 group">
        <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100/60 flex items-center justify-center text-amber-600 shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Top Performers</span>
          <div v-if="store.isLoading" class="h-7 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-2xl font-black text-slate-800 leading-tight tracking-tight my-0.5">
            {{ store.stats.top_performers }}
          </span>
          <span class="text-[11px] font-medium text-amber-600 truncate">Students &ge; 85%</span>
        </div>
      </div>

      <!-- 5. Average Attendance -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex items-center gap-3.5 group col-span-2 md:col-span-1">
        <div class="w-12 h-12 rounded-xl bg-purple-50 border border-purple-100/60 flex items-center justify-center text-purple-600 shrink-0 group-hover:scale-105 transition-transform">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Avg Attendance</span>
          <div v-if="store.isLoading" class="h-7 w-12 bg-slate-100 animate-pulse rounded my-1"></div>
          <span v-else class="text-2xl font-black text-slate-800 leading-tight tracking-tight my-0.5">
            {{ store.stats.average_attendance ?? store.studentProgress.average_attendance ?? 0 }}%
          </span>
          <span class="text-[11px] font-medium text-slate-500 truncate">Class attendance</span>
        </div>
      </div>

    </div>

    <!-- Main Table Area -->
    <div class="space-y-4">

      <!-- Unified Search & Filter Toolbar -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-3 sm:p-4 shadow-xs flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3 flex-1 min-w-[280px]">
          
          <!-- Search input -->
          <div class="relative flex-1 min-w-[220px]">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
              <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              v-model="searchQuery"
              type="text"
              class="w-full pl-9 pr-8 py-2 bg-slate-50/80 hover:bg-slate-50 focus:bg-white border border-slate-200 rounded-xl text-xs sm:text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all"
              placeholder="Search by name, ID number or email..."
            />
            <button
              v-if="searchQuery"
              @click="searchQuery = ''"
              class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
          </div>

          <!-- Section dropdown -->
          <div class="relative">
            <select
              v-model="selectedSection"
              class="appearance-none bg-slate-50/80 hover:bg-slate-50 border border-slate-200 rounded-xl pl-3.5 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all cursor-pointer"
            >
              <option>All Sections</option>
              <option v-for="sec in availableSections" :key="sec" :value="sec">{{ sec }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>

          <!-- Academic Year dropdown -->
          <div class="relative">
            <select
              v-model="selectedYear"
              class="appearance-none bg-slate-50/80 hover:bg-slate-50 border border-slate-200 rounded-xl pl-3.5 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all cursor-pointer"
            >
              <option>All Academic Years</option>
              <option v-for="yr in availableYears" :key="yr" :value="yr">{{ yr }}</option>
            </select>
            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>

          <!-- Status dropdown -->
          <div class="relative">
            <select
              v-model="selectedStatus"
              class="appearance-none bg-slate-50/80 hover:bg-slate-50 border border-slate-200 rounded-xl pl-3.5 pr-8 py-2 text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] transition-all cursor-pointer"
            >
              <option>All Status</option>
              <option>Active</option>
              <option>Completed All Exams</option>
              <option>Pending Exams</option>
              <option>At Risk</option>
            </select>
            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-slate-400">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>

          <!-- Reset Filter Button -->
          <button
            v-if="isFilterActive"
            @click="resetFilters"
            class="flex items-center gap-1.5 px-3 py-2 text-[#5138ed] hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold transition-colors cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            <span>Reset Filters</span>
          </button>
        </div>

        <!-- Filter Metrics & Per Page -->
        <div class="flex items-center gap-3 ml-auto text-xs font-medium text-slate-500">
          <span class="hidden sm:inline">
            Showing <strong class="text-slate-800 font-bold">{{ filteredStudents.length }}</strong> of {{ store.students.length }} enrolled
          </span>
          <div class="flex items-center gap-1.5">
            <span class="text-[11px] text-slate-400">Per page:</span>
            <select
              v-model="perPage"
              class="bg-slate-50 border border-slate-200 rounded-lg px-2 py-1 text-xs font-bold text-slate-700 cursor-pointer focus:outline-none"
            >
              <option :value="10">10</option>
              <option :value="20">20</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Students Table (Desktop View) -->
      <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden flex flex-col">
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-left border-collapse whitespace-nowrap">
            <thead>
              <tr class="bg-slate-50/70 border-b border-slate-200/80 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                <th class="py-3.5 pl-6 pr-4">Student</th>
                <th class="py-3.5 px-4">ID Number</th>
                <th class="py-3.5 px-4">Email</th>
                <th class="py-3.5 px-4">Gender</th>
                <th class="py-3.5 px-4 w-48">Exam Progress</th>
                <th class="py-3.5 px-4">Status</th>
                <th class="py-3.5 pr-6 pl-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              
              <!-- Loading Skeleton Rows -->
              <tr v-if="store.isLoading" v-for="i in 5" :key="`skel-${i}`" class="animate-pulse">
                <td class="py-4 pl-6 pr-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-100"></div>
                    <div class="space-y-1.5">
                      <div class="h-3.5 w-28 bg-slate-100 rounded"></div>
                      <div class="h-2.5 w-16 bg-slate-100 rounded"></div>
                    </div>
                  </div>
                </td>
                <td class="py-4 px-4"><div class="h-4 w-24 bg-slate-100 rounded"></div></td>
                <td class="py-4 px-4"><div class="h-4 w-32 bg-slate-100 rounded"></div></td>
                <td class="py-4 px-4"><div class="h-4 w-12 bg-slate-100 rounded"></div></td>
                <td class="py-4 px-4"><div class="h-4 w-32 bg-slate-100 rounded"></div></td>
                <td class="py-4 px-4"><div class="h-5 w-20 bg-slate-100 rounded-full"></div></td>
                <td class="py-4 pr-6 pl-4 text-right"><div class="h-7 w-20 bg-slate-100 rounded-lg ml-auto"></div></td>
              </tr>

              <!-- Empty State -->
              <tr v-else-if="filteredStudents.length === 0">
                <td colspan="7" class="py-16 text-center">
                  <div class="max-w-sm mx-auto flex flex-col items-center">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100/60 flex items-center justify-center text-[#5138ed] mb-3">
                      <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                      </svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800 mb-1">No students found</h3>
                    <p class="text-xs text-slate-500 mb-4">
                      {{ isFilterActive ? 'No students match your active filters. Try adjusting your search query.' : 'There are no students enrolled in this course yet.' }}
                    </p>
                    <button
                      v-if="isFilterActive"
                      @click="resetFilters"
                      class="px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-[#5138ed] rounded-xl text-xs font-bold transition-colors cursor-pointer"
                    >
                      Reset All Filters
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Student Rows -->
              <tr
                v-else
                v-for="student in paginatedStudents"
                :key="student.id"
                class="hover:bg-slate-50/70 transition-colors group"
              >
                <!-- Student Avatar & Name -->
                <td class="py-3.5 pl-6 pr-4">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-50 to-indigo-100/80 text-[#5138ed] flex items-center justify-center font-bold text-xs uppercase border border-indigo-200/60 shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
                      {{ getInitials(student.name) }}
                    </div>
                    <div class="flex flex-col min-w-0">
                      <router-link
                        :to="`/instructor/students/${student.id}`"
                        class="text-xs sm:text-[13px] font-bold text-slate-800 hover:text-[#5138ed] transition-colors truncate"
                      >
                        {{ student.name }}
                      </router-link>
                      <span class="text-[10px] text-slate-400 font-medium truncate">Enrolled Student</span>
                    </div>
                  </div>
                </td>

                <!-- ID Number -->
                <td class="py-3.5 px-4">
                  <span class="font-mono text-xs font-semibold text-slate-700 bg-slate-50 px-2.5 py-1 rounded-md border border-slate-200/60 inline-block shadow-2xs">
                    {{ student.id_number }}
                  </span>
                </td>

                <!-- Email -->
                <td class="py-3.5 px-4 text-xs font-medium text-slate-600">
                  <a :href="`mailto:${student.email}`" class="hover:text-[#5138ed] hover:underline transition-colors">
                    {{ student.email }}
                  </a>
                </td>

                <!-- Gender -->
                <td class="py-3.5 px-4 text-xs font-medium text-slate-600">
                  <span class="inline-flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full" :class="student.gender === 'Female' ? 'bg-pink-400' : 'bg-blue-400'"></span>
                    {{ student.gender }}
                  </span>
                </td>

                <!-- Exam Progress -->
                <td class="py-3.5 px-4">
                  <div class="flex flex-col gap-1 w-full max-w-[170px]">
                    <div class="flex items-center justify-between text-[10px]">
                      <span class="font-bold text-slate-600">{{ student.exams_taken }} Exams Taken</span>
                      <span class="font-bold text-slate-800">{{ student.average_score }}%</span>
                    </div>
                    <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden shadow-inner">
                      <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="getProgressBarColor(student.average_score)"
                        :style="`width: ${Math.min(100, student.average_score)}%`"
                      ></div>
                    </div>
                  </div>
                </td>

                <!-- Status -->
                <td class="py-3.5 px-4">
                  <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold border shadow-2xs"
                    :class="getStatusBadgeStyles(student.status)"
                  >
                    <span
                      class="w-1.5 h-1.5 rounded-full ring-2"
                      :class="getStatusDotColor(student.status)"
                    ></span>
                    {{ student.status }}
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-3.5 pr-6 pl-4 text-right">
                  <div class="flex items-center justify-end gap-1 relative student-action-menu">
                    <!-- Profile -->
                    <button
                      @click="router.push(`/instructor/students/${student.id}`)"
                      class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 transition-colors cursor-pointer"
                      title="View Student Profile"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                      </svg>
                    </button>

                    <!-- Results -->
                    <button
                      @click="router.push(`/instructor/students/${student.id}/results`)"
                      class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors cursor-pointer"
                      title="View Exam Results"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                      </svg>
                    </button>

                    <!-- Message -->
                    <button
                      @click="openStudentAnnouncement(student)"
                      class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-colors cursor-pointer"
                      title="Send Announcement / Note"
                    >
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                      </svg>
                    </button>

                    <!-- More Options Dropdown -->
                    <div class="relative">
                      <button
                        @click="toggleDropdown(student.id, $event)"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
                        title="More Options"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                        </svg>
                      </button>

                      <!-- Dropdown Menu -->
                      <transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="transform scale-95 opacity-0"
                        enter-to-class="transform scale-100 opacity-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="transform scale-100 opacity-100"
                        leave-to-class="transform scale-95 opacity-0"
                      >
                        <div
                          v-if="activeDropdown === student.id"
                          class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-slate-100 py-1.5 z-50 overflow-hidden"
                        >
                          <button
                            @click="handleDownloadSingleReport(student)"
                            class="w-full text-left px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                          >
                            <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            <span>Download Student CSV</span>
                          </button>
                          <button
                            @click="handlePrintSingleReport(student)"
                            class="w-full text-left px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                          >
                            <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                            <span>Print Student Report</span>
                          </button>
                          <div class="h-px bg-slate-100 my-1"></div>
                          <button
                            @click="router.push(`/instructor/students/${student.id}/results`)"
                            class="w-full text-left px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors flex items-center gap-2.5 cursor-pointer"
                          >
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            <span>View Full Results</span>
                          </button>
                        </div>
                      </transition>
                    </div>

                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Card View (For screens < md) -->
        <div class="md:hidden divide-y divide-slate-100">
          <div v-if="store.isLoading" class="py-12 text-center text-slate-500 text-xs">
            <svg class="w-6 h-6 animate-spin text-[#5138ed] mx-auto mb-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            Loading students...
          </div>
          <div v-else-if="filteredStudents.length === 0" class="py-12 text-center text-slate-500 text-xs">
            No students found matching your criteria.
          </div>
          <div
            v-else
            v-for="student in paginatedStudents"
            :key="`m-${student.id}`"
            class="p-4 space-y-3"
          >
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center font-bold text-xs uppercase border border-indigo-100 shrink-0">
                  {{ getInitials(student.name) }}
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-bold text-slate-800 truncate">{{ student.name }}</p>
                  <p class="text-[11px] font-mono text-slate-500">{{ student.id_number }}</p>
                </div>
              </div>
              <span
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold border shrink-0"
                :class="getStatusBadgeStyles(student.status)"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="getStatusDotColor(student.status)"></span>
                {{ student.status }}
              </span>
            </div>

            <div class="text-xs text-slate-500 font-medium">
              {{ student.email }} &bull; {{ student.gender }}
            </div>

            <div class="space-y-1">
              <div class="flex items-center justify-between text-[11px]">
                <span class="font-bold text-slate-600">{{ student.exams_taken }} Exams Taken</span>
                <span class="font-bold text-slate-800">{{ student.average_score }}%</span>
              </div>
              <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full"
                  :class="getProgressBarColor(student.average_score)"
                  :style="`width: ${Math.min(100, student.average_score)}%`"
                ></div>
              </div>
            </div>

            <!-- Touch Actions -->
            <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100">
              <button
                @click="router.push(`/instructor/students/${student.id}`)"
                class="min-h-[44px] flex items-center justify-center gap-1.5 text-xs font-bold text-[#5138ed] bg-indigo-50/80 hover:bg-indigo-100 rounded-xl transition-colors"
              >
                Profile
              </button>
              <button
                @click="router.push(`/instructor/students/${student.id}/results`)"
                class="min-h-[44px] flex items-center justify-center gap-1.5 text-xs font-bold text-emerald-700 bg-emerald-50/80 hover:bg-emerald-100 rounded-xl transition-colors"
              >
                Results
              </button>
              <button
                @click="openStudentAnnouncement(student)"
                class="min-h-[44px] flex items-center justify-center gap-1.5 text-xs font-bold text-amber-700 bg-amber-50/80 hover:bg-amber-100 rounded-xl transition-colors"
              >
                Message
              </button>
            </div>
          </div>
        </div>

        <!-- Dynamic Pagination Footer -->
        <div class="p-3.5 sm:p-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/40">
          <span class="text-xs text-slate-500 font-medium">
            Showing <strong class="text-slate-800 font-bold">{{ startItemIndex }}</strong> to <strong class="text-slate-800 font-bold">{{ endItemIndex }}</strong> of <strong class="text-slate-800 font-bold">{{ filteredStudents.length }}</strong> students
          </span>

          <div class="flex items-center gap-1.5">
            <!-- Previous Button -->
            <button
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="w-8 h-8 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs"
              title="Previous Page"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
            </button>

            <!-- Page Number Buttons -->
            <button
              v-for="p in totalPages"
              :key="`page-${p}`"
              @click="goToPage(p)"
              class="w-8 h-8 flex items-center justify-center rounded-xl font-bold text-xs transition-colors cursor-pointer shadow-2xs"
              :class="currentPage === p ? 'bg-[#5138ed] text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-600 hover:bg-slate-50'"
            >
              {{ p }}
            </button>

            <!-- Next Button -->
            <button
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage === totalPages"
              class="w-8 h-8 flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-slate-800 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed shadow-2xs"
              title="Next Page"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>

      </div>

    </div>

    <!-- Bottom Section: Course Overview, Student Progress, Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 lg:gap-6 pt-2">
      
      <!-- 1. Course Overview Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex flex-col">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100/60 text-[#5138ed] flex items-center justify-center shadow-2xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900">Course Overview</h3>
              <p class="text-[11px] text-slate-500 font-medium">Assigned course details</p>
            </div>
          </div>
          <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-indigo-50 text-[#5138ed] border border-indigo-100 font-mono">
            {{ store.courseOverview.course_code }}
          </span>
        </div>
        
        <div class="space-y-3 divide-y divide-slate-100">
          <div class="flex justify-between items-center pt-2 first:pt-0">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Course Name</span>
            <span class="text-xs font-bold text-slate-800 text-right">{{ store.courseOverview.course_name }}</span>
          </div>
          <div class="flex justify-between items-center pt-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Course Code</span>
            <span class="text-xs font-mono font-bold text-slate-800">{{ store.courseOverview.course_code }}</span>
          </div>
          <div class="flex justify-between items-center pt-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Section</span>
            <span class="text-xs font-bold text-slate-800">{{ store.courseOverview.section }}</span>
          </div>
          <div class="flex justify-between items-center pt-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Semester</span>
            <span class="text-xs font-bold text-slate-800">{{ store.courseOverview.semester }}</span>
          </div>
          <div class="flex justify-between items-center pt-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Academic Year</span>
            <span class="text-xs font-bold text-slate-800">{{ store.courseOverview.academic_year }}</span>
          </div>
          <div class="flex justify-between items-center pt-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">Instructor</span>
            <span class="text-xs font-bold text-slate-800">{{ store.courseOverview.instructor }}</span>
          </div>
        </div>
      </div>

      <!-- 2. Student Progress Summary Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex flex-col">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-blue-50 border border-blue-100/60 text-blue-600 flex items-center justify-center shadow-2xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900">Student Progress</h3>
              <p class="text-[11px] text-slate-500 font-medium">Aggregated cohort benchmarks</p>
            </div>
          </div>
          <span class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-100">
            Active
          </span>
        </div>
        
        <div class="space-y-3.5 my-auto">
          <!-- Completed Exams -->
          <div class="space-y-1">
            <div class="flex justify-between items-center text-xs">
              <span class="font-semibold text-slate-600">Completed Exams</span>
              <span class="font-bold text-slate-800">{{ store.studentProgress.completed_exams_percent }}%</span>
            </div>
            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.completed_exams_percent)}%` }"></div>
            </div>
          </div>
          
          <!-- Pending Exams -->
          <div class="space-y-1">
            <div class="flex justify-between items-center text-xs">
              <span class="font-semibold text-slate-600">Pending Exams</span>
              <span class="font-bold text-slate-800">{{ store.studentProgress.pending_exams_percent }}%</span>
            </div>
            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-amber-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.pending_exams_percent)}%` }"></div>
            </div>
          </div>

          <!-- Average Attendance -->
          <div class="space-y-1">
            <div class="flex justify-between items-center text-xs">
              <span class="font-semibold text-slate-600">Average Attendance</span>
              <span class="font-bold text-slate-800">{{ store.studentProgress.average_attendance }}%</span>
            </div>
            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-blue-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.average_attendance)}%` }"></div>
            </div>
          </div>

          <!-- Average Exam Score -->
          <div class="space-y-1">
            <div class="flex justify-between items-center text-xs">
              <span class="font-semibold text-slate-600">Average Exam Score</span>
              <span class="font-bold text-slate-800">{{ store.studentProgress.average_exam_score }}%</span>
            </div>
            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-[#5138ed] rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.average_exam_score)}%` }"></div>
            </div>
          </div>

          <!-- Students At Risk -->
          <div class="space-y-1">
            <div class="flex justify-between items-center text-xs">
              <span class="font-semibold text-slate-600">Students At Risk</span>
              <span class="font-bold text-rose-600">{{ store.studentProgress.students_at_risk_percent }}%</span>
            </div>
            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
              <div class="h-full bg-rose-500 rounded-full transition-all duration-500" :style="{ width: `${Math.min(100, store.studentProgress.students_at_risk_percent)}%` }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Quick Actions Card -->
      <div class="bg-white border border-slate-200/80 rounded-2xl p-5 shadow-xs hover:shadow-md transition-shadow duration-200 flex flex-col md:col-span-2 lg:col-span-1">
        <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-50 border border-amber-100/60 text-amber-600 flex items-center justify-center shadow-2xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900">Quick Actions</h3>
              <p class="text-[11px] text-slate-500 font-medium">Common student workflows</p>
            </div>
          </div>
        </div>

        <div class="space-y-1.5 flex-1 flex flex-col justify-between">
          <!-- Import Students -->
          <button
            @click="openImportModal"
            class="w-full flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-colors group text-left cursor-pointer border border-transparent hover:border-slate-100"
          >
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-xs font-bold text-slate-800 group-hover:text-[#5138ed] transition-colors block">Import Students</span>
              <span class="text-[11px] text-slate-400 font-medium">Batch enroll via CSV file</span>
            </div>
          </button>

          <!-- Export Student List -->
          <button
            @click="handleExport"
            :disabled="store.isExporting"
            class="w-full flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-colors group text-left cursor-pointer border border-transparent hover:border-slate-100 disabled:opacity-50"
          >
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-xs font-bold text-slate-800 group-hover:text-emerald-600 transition-colors block">Export Student Roster</span>
              <span class="text-[11px] text-slate-400 font-medium">{{ store.isExporting ? 'Generating export file...' : 'Download roster CSV' }}</span>
            </div>
          </button>

          <!-- Print Attendance -->
          <button
            @click="handlePrintAttendance"
            class="w-full flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-colors group text-left cursor-pointer border border-transparent hover:border-slate-100"
          >
            <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-xs font-bold text-slate-800 group-hover:text-purple-600 transition-colors block">Print Attendance Sheet</span>
              <span class="text-[11px] text-slate-400 font-medium">Official Wollo roster template</span>
            </div>
          </button>

          <!-- Generate Student Report -->
          <button
            @click="handleGenerateReport"
            class="w-full flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-colors group text-left cursor-pointer border border-transparent hover:border-slate-100"
          >
            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-xs font-bold text-slate-800 group-hover:text-blue-600 transition-colors block">Generate Cohort Report</span>
              <span class="text-[11px] text-slate-400 font-medium">Comprehensive academic analysis</span>
            </div>
          </button>

          <!-- Send Announcement -->
          <button
            @click="openStudentAnnouncement()"
            class="w-full flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 transition-colors group text-left cursor-pointer border border-transparent hover:border-slate-100"
          >
            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-xs font-bold text-slate-800 group-hover:text-amber-600 transition-colors block">Broadcast Announcement</span>
              <span class="text-[11px] text-slate-400 font-medium">Broadcast reminder to class</span>
            </div>
          </button>
        </div>
      </div>

    </div>

    <!-- ==================== MODALS ==================== -->

    <!-- 1. IMPORT STUDENTS MODAL -->
    <div
      v-if="showImportModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-7 shadow-2xl border border-slate-100 flex flex-col max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 class="text-base sm:text-lg font-bold text-slate-900">Import Students</h3>
            <p class="text-xs text-slate-500 mt-0.5">
              Enroll students into <span class="font-bold text-[#5138ed]">{{ store.courseOverview.course_name }} ({{ store.courseOverview.section }})</span>
            </p>
          </div>
          <button
            @click="showImportModal = false"
            class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="py-5 space-y-4">
          <!-- Template CSV Banner -->
          <div class="flex items-center justify-between bg-indigo-50/70 p-3.5 rounded-2xl border border-indigo-100">
            <div class="flex items-center gap-2.5">
              <svg class="w-5 h-5 text-[#5138ed] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              <span class="text-xs font-medium text-slate-700">Need the formatted template?</span>
            </div>
            <button
              @click="downloadSampleImportCsv"
              class="text-xs font-bold text-[#5138ed] hover:underline cursor-pointer shrink-0"
            >
              Download Sample CSV
            </button>
          </div>

          <!-- Drag and Drop / File Input -->
          <div class="border-2 border-dashed border-slate-200 hover:border-[#5138ed] transition-colors rounded-2xl p-6 text-center bg-slate-50/30">
            <input type="file" accept=".csv" @change="handleFileUpload" id="file-upload" class="hidden" />
            <label for="file-upload" class="cursor-pointer flex flex-col items-center">
              <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-3 shadow-2xs">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
              </div>
              <span class="text-sm font-bold text-slate-800">Click to upload or drag CSV</span>
              <span class="text-xs text-slate-400 mt-1">Expected: Full Name, Email, ID Number, Gender</span>
              <span v-if="importFile" class="mt-2 text-xs font-bold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200">
                Selected: {{ importFile.name }}
              </span>
            </label>
          </div>

          <!-- Error Alert -->
          <div v-if="importError" class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            <span>{{ importError }}</span>
          </div>

          <!-- Parsed Preview -->
          <div v-if="parsedImportStudents.length > 0" class="space-y-2">
            <div class="flex items-center justify-between text-xs font-bold text-slate-700">
              <span>Preview ({{ parsedImportStudents.length }} students found)</span>
              <span class="text-emerald-600 font-bold">Ready to Enroll</span>
            </div>
            <div class="max-h-44 overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100 text-xs">
              <div
                v-for="(s, idx) in parsedImportStudents.slice(0, 10)"
                :key="idx"
                class="p-2.5 flex items-center justify-between"
              >
                <div>
                  <span class="font-bold text-slate-800">{{ s.name }}</span>
                  <span class="text-slate-400 ml-2 font-mono text-[11px]">{{ s.id_no }}</span>
                </div>
                <span class="text-slate-500 font-mono text-[11px]">{{ s.email }}</span>
              </div>
              <div v-if="parsedImportStudents.length > 10" class="p-2 text-center text-slate-400 font-medium bg-slate-50">
                ...and {{ parsedImportStudents.length - 10 }} more
              </div>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 mt-auto">
          <button
            @click="showImportModal = false"
            class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitImportStudents"
            :disabled="parsedImportStudents.length === 0 || isImporting"
            class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="isImporting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            <span>{{ isImporting ? 'Importing Students...' : 'Enroll Students Now' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 2. SEND ANNOUNCEMENT MODAL -->
    <div
      v-if="showAnnouncementModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl border border-slate-100 flex flex-col">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 class="text-base sm:text-lg font-bold text-slate-900">
              {{ targetedStudent ? 'Send Student Message' : 'Broadcast Class Announcement' }}
            </h3>
            <p class="text-xs text-slate-500 mt-0.5">
              <template v-if="targetedStudent">
                Sending direct message to <span class="font-bold text-[#5138ed]">{{ targetedStudent.name }} ({{ targetedStudent.id_number }})</span>
              </template>
              <template v-else>
                Broadcast message to <span class="font-bold text-[#5138ed]">{{ store.courseOverview.course_name }} ({{ store.courseOverview.section }})</span>
              </template>
            </p>
          </div>
          <button
            @click="showAnnouncementModal = false"
            class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <div class="py-5 space-y-4">
          <div v-if="!targetedStudent">
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1.5">Announcement Type</label>
            <select
              v-model="announcementType"
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] cursor-pointer"
            >
              <option>Exam Reminder</option>
              <option>Course Material Update</option>
              <option>Class Schedule Change</option>
              <option>General Notice</option>
              <option>Urgent Alert</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1.5">Subject / Title</label>
            <input
              v-model="announcementTitle"
              type="text"
              placeholder="e.g., Midterm Exam Schedule and Guidelines"
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed]"
            />
          </div>

          <div>
            <label class="block text-[11px] font-bold text-slate-600 uppercase mb-1.5">Message Content</label>
            <textarea
              v-model="announcementMessage"
              rows="4"
              placeholder="Enter announcement details, instructions, or notes for your students..."
              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] resize-none"
            ></textarea>
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
          <button
            @click="showAnnouncementModal = false"
            class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="handleSendAnnouncement"
            :disabled="isSendingAnnouncement || !announcementTitle.trim() || !announcementMessage.trim()"
            class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="isSendingAnnouncement" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            <span>{{ isSendingAnnouncement ? 'Sending...' : (targetedStudent ? 'Send Message' : 'Broadcast to Class') }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- 3. PRINT ATTENDANCE MODAL & PRINTABLE VIEW -->
    <div
      v-if="showPrintAttendanceModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-3xl max-w-4xl w-full p-6 sm:p-7 shadow-2xl border border-slate-100 flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <div>
            <h3 class="text-base sm:text-lg font-bold text-slate-900">Print Attendance Sheet</h3>
            <p class="text-xs text-slate-500 mt-0.5">
              Official roster for {{ store.courseOverview.course_name }} ({{ store.courseOverview.course_code }}) &bull; Section {{ store.courseOverview.section }}
            </p>
          </div>
          <button
            @click="showPrintAttendanceModal = false"
            class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 hover:text-slate-700 hover:bg-slate-200 flex items-center justify-center cursor-pointer transition-colors"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
        </div>

        <!-- Printable Document Container -->
        <div class="py-4 overflow-y-auto flex-1 printable-roster">
          <div class="border border-slate-200 rounded-2xl p-6 bg-white shadow-2xs">
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
              <div><span class="text-slate-400 font-bold block text-[10px] uppercase">Term:</span> <strong class="text-slate-800">{{ store.courseOverview.academic_year }} &bull; {{ store.courseOverview.semester }}</strong></div>
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
          <button
            @click="showPrintAttendanceModal = false"
            class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
          >
            Close
          </button>
          <button
            @click="triggerPrintRoster"
            class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            <span>Print Attendance Sheet</span>
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
