<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import apiClient from '../../../core/api/apiClient'
import { useSemesterLockStore } from '../store/semesterLockStore'

const lockStore = useSemesterLockStore()

const loading = ref(true)
const submitting = ref(false)
const showConfirmModal = ref(false)
const toastMessage = ref<{ text: string; type: 'success' | 'error' | 'info' } | null>(null)

const showToast = (text: string, type: 'success' | 'error' | 'info' = 'success') => {
  toastMessage.value = { text, type }
  setTimeout(() => {
    toastMessage.value = null
  }, 4000)
}

// 4 Top Academic Info Cards
const academicInfo = ref([
  {
    id: 'academic_year',
    label: 'Academic Year',
    value: '-',
    sub: 'Current academic year',
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
    color: 'text-[#4F35F3]',
    bg: 'bg-indigo-50 border-indigo-100/80'
  },
  {
    id: 'semester',
    label: 'Semester',
    value: '-',
    sub: 'Current semester',
    icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    color: 'text-purple-600',
    bg: 'bg-purple-50 border-purple-100/80'
  },
  {
    id: 'department',
    label: 'Department',
    value: '-',
    sub: 'Your department',
    icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    color: 'text-[#0EA5E9]',
    bg: 'bg-sky-50 border-sky-100/80'
  },
  {
    id: 'section',
    label: 'Section',
    value: '-',
    sub: 'Your teaching section',
    icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    color: 'text-blue-600',
    bg: 'bg-blue-50 border-blue-100/80'
  },
])

// Checklist Data
const checklistData = ref<Record<string, any>>({})
const isAllCompleted = ref(false)

const checklist = computed(() => [
  {
    id: 'academic_schedule',
    title: 'Academic Schedule',
    desc: 'Create and manage your course schedule.',
    date: checklistData.value.academic_schedule?.date || '-',
    time: checklistData.value.academic_schedule?.time || '-',
    completed: checklistData.value.academic_schedule?.completed || false,
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
  },
  {
    id: 'exams',
    title: 'Exams',
    desc: 'Create and manage exams for your courses.',
    date: checklistData.value.exams?.date || '-',
    time: checklistData.value.exams?.time || '-',
    completed: checklistData.value.exams?.completed || false,
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
  },
  {
    id: 'student_info',
    title: 'Student Information',
    desc: 'Manage student enrollment and personal information.',
    date: checklistData.value.student_info?.date || '-',
    time: checklistData.value.student_info?.time || '-',
    completed: checklistData.value.student_info?.completed || false,
    icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'
  },
  {
    id: 'results',
    title: 'Results',
    desc: 'Publish and manage final results.',
    date: checklistData.value.results?.date || '-',
    time: checklistData.value.results?.time || '-',
    completed: checklistData.value.results?.completed || false,
    icon: 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z'
  },
])

// Summary Stats
const summaryStats = ref([
  {
    id: 'total_students',
    label: 'Total Students',
    value: '-',
    sub: 'Enrolled students',
    icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    color: 'text-blue-600',
    bg: 'bg-blue-50 border-blue-100/80'
  },
  {
    id: 'courses',
    label: 'Courses',
    value: '-',
    sub: 'Assigned courses',
    icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    color: 'text-purple-600',
    bg: 'bg-purple-50 border-purple-100/80'
  },
  {
    id: 'exams_conducted',
    label: 'Exams Conducted',
    value: '-',
    sub: 'Total exams',
    icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    color: 'text-[#10B981]',
    bg: 'bg-emerald-50 border-emerald-100/80'
  },
  {
    id: 'results_submitted',
    label: 'Results Submitted',
    value: '-',
    sub: 'Students evaluated',
    icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    color: 'text-[#F59E0B]',
    bg: 'bg-amber-50 border-amber-100/80'
  },
])

const submissionData = ref<any>({
  status: 'pending',
  is_locked: false,
  submitted_at: null,
  approved_at: null,
  reopened_at: null,
  reopen_reason: null,
  remarks: null
})

const completedChecklistCount = computed(() => {
  return checklist.value.filter(item => item.completed).length
})

const completionPercentage = computed(() => {
  return Math.round((completedChecklistCount.value / checklist.value.length) * 100)
})

const statusBadgeStyles = computed(() => {
  const status = (submissionData.value.status || 'pending').toLowerCase()
  switch (status) {
    case 'approved':
      return 'bg-emerald-50 text-emerald-700 border-emerald-200/80'
    case 'submitted':
    case 'under_review':
      return 'bg-blue-50 text-blue-700 border-blue-200/80'
    case 'correction_required':
    case 'rejected':
      return 'bg-rose-50 text-rose-700 border-rose-200/80'
    case 'reopened':
      return 'bg-amber-50 text-amber-700 border-amber-200/80'
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200/80'
  }
})

const statusText = computed(() => {
  const status = (submissionData.value.status || 'pending').toLowerCase()
  switch (status) {
    case 'approved':
      return 'Approved by Department Head'
    case 'submitted':
      return 'Submitted — Under Review'
    case 'correction_required':
      return 'Correction Required'
    case 'rejected':
      return 'Submission Rejected'
    case 'reopened':
      return 'Semester Reopened for Editing'
    default:
      return isAllCompleted.value ? 'Ready for Submission' : 'Activities in Progress'
  }
})

const fetchData = async () => {
  loading.value = true
  try {
    const res = await apiClient.get('/instructor/semester-submission/status')
    const data = res.data.data
    
    // Academic Info
    academicInfo.value[0].value = data.academic_info?.academic_year || '-'
    academicInfo.value[1].value = data.academic_info?.semester || '-'
    academicInfo.value[2].value = data.academic_info?.department || '-'
    academicInfo.value[3].value = data.academic_info?.section || '-'
    
    // Checklist & Stats
    checklistData.value = data.checklist || {}
    isAllCompleted.value = Boolean(data.is_all_completed)
    
    if (data.summary_stats) {
      summaryStats.value[0].value = (data.summary_stats.total_students ?? 0).toString()
      summaryStats.value[1].value = (data.summary_stats.courses ?? 0).toString()
      summaryStats.value[2].value = (data.summary_stats.exams_conducted ?? 0).toString()
      summaryStats.value[3].value = (data.summary_stats.results_submitted ?? 0).toString()
    }
    
    // Submission Status
    if (data.submission) {
      submissionData.value = data.submission
    }
  } catch (err) {
    console.error('Failed to fetch semester submission data', err)
    showToast('Failed to load current semester submission records.', 'error')
  } finally {
    loading.value = false
  }
}

const submitRecords = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    const res = await apiClient.post('/instructor/semester-submission/submit')
    if (res.data?.data) {
      submissionData.value.status = res.data.data.status
      submissionData.value.submitted_at = res.data.data.submitted_at
      submissionData.value.is_locked = res.data.data.is_locked
    }
    showConfirmModal.value = false
    showToast('Semester records submitted successfully! Academic records are now locked.', 'success')
    // Refresh global lock store so all navigation and layout components update immediately
    await lockStore.fetchLockStatus(true)
    await fetchData()
  } catch (err: any) {
    console.error('Failed to submit records', err)
    showToast(err.response?.data?.message || 'Failed to submit semester records. Please try again.', 'error')
  } finally {
    submitting.value = false
  }
}

const printArchiveReport = () => {
  const printWindow = window.open('', '_blank', 'width=900,height=700')
  if (!printWindow) return
  printWindow.document.write(`
    <html>
      <head>
        <title>Semester Academic Archive - ${academicInfo.value[0].value} (${academicInfo.value[1].value})</title>
        <style>
          body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 36px; color: #17243A; line-height: 1.5; }
          .header { border-bottom: 2px solid #4F35F3; padding-bottom: 16px; margin-bottom: 24px; text-align: center; }
          h2 { color: #17243A; margin: 0 0 6px 0; font-size: 20px; text-transform: uppercase; }
          .sub { color: #71819B; font-size: 13px; }
          .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 20px 0; background: #F6F8FC; padding: 16px; border-radius: 8px; border: 1px solid #E6EBF3; }
          .grid div { font-size: 12px; }
          .grid strong { display: block; color: #71819B; font-size: 10px; text-transform: uppercase; margin-bottom: 2px; }
          table { width: 100%; border-collapse: collapse; margin-top: 24px; font-size: 13px; }
          th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #E6EBF3; }
          th { background: #f8fafc; color: #475569; font-weight: bold; width: 40%; }
          .badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; background: #EEF0FF; color: #4F35F3; }
          .footer { margin-top: 50px; font-size: 11px; color: #94a3b8; border-top: 1px solid #E6EBF3; padding-top: 16px; display: flex; justify-content: space-between; }
        </style>
      </head>
      <body>
        <div class="header">
          <h2>Wollo University &bull; Semester Submission Archive</h2>
          <div class="sub">Office of the Registrar &bull; Department of Software Engineering</div>
        </div>
        <div class="grid">
          <div><strong>Academic Year</strong>${academicInfo.value[0].value}</div>
          <div><strong>Semester</strong>${academicInfo.value[1].value}</div>
          <div><strong>Department</strong>${academicInfo.value[2].value}</div>
          <div><strong>Teaching Section</strong>${academicInfo.value[3].value}</div>
        </div>
        <table>
          <tr><th>Current Submission Status</th><td><span class="badge">${submissionData.value.status || 'Pending'}</span></td></tr>
          <tr><th>Submission Timestamp</th><td>${submissionData.value.submitted_at || 'Not submitted yet'}</td></tr>
          <tr><th>Approval Timestamp</th><td>${submissionData.value.approved_at || 'Not approved yet'}</td></tr>
          <tr><th>Academic Schedule Completed</th><td>${checklistData.value.academic_schedule?.completed ? 'Yes (' + checklistData.value.academic_schedule?.date + ')' : 'Pending'}</td></tr>
          <tr><th>Examinations Conducted</th><td>${checklistData.value.exams?.completed ? 'Yes (' + checklistData.value.exams?.date + ')' : 'Pending'}</td></tr>
          <tr><th>Student Enrollment Verified</th><td>${checklistData.value.student_info?.completed ? 'Yes (' + checklistData.value.student_info?.date + ')' : 'Pending'}</td></tr>
          <tr><th>Exam Results Published</th><td>${checklistData.value.results?.completed ? 'Yes (' + checklistData.value.results?.date + ')' : 'Pending'}</td></tr>
        </table>
        <div class="footer">
          <span>Official Academic Submission Archive &bull; Wollo University Online Examination System</span>
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

onMounted(() => {
  fetchData()
  lockStore.fetchLockStatus()
})
</script>

<template>
  <div class="space-y-6 max-w-[1440px] mx-auto">
    
    <!-- Toast Feedback Notification -->
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform translate-y-2 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform translate-y-2 opacity-0"
    >
      <div
        v-if="toastMessage"
        class="fixed top-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-xl border text-sm font-semibold transition-all max-w-[calc(100vw-2.5rem)]"
        :class="{
          'bg-emerald-600 text-white border-emerald-500 shadow-emerald-200/50': toastMessage.type === 'success',
          'bg-rose-600 text-white border-rose-500 shadow-rose-200/50': toastMessage.type === 'error',
          'bg-[#4F35F3] text-white border-indigo-400 shadow-indigo-200/50': toastMessage.type === 'info',
        }"
      >
        <svg v-if="toastMessage.type === 'success'" class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
        <svg v-else-if="toastMessage.type === 'error'" class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
        <svg v-else class="w-5 h-5 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <span>{{ toastMessage.text }}</span>
      </div>
    </transition>

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
      <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-2xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 border border-indigo-100 shadow-2xs">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
        <div class="pt-0.5">
          <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="text-2xl sm:text-[26px] font-black text-[#17243A] tracking-tight leading-tight">
              Semester Submission
            </h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border shadow-2xs" :class="statusBadgeStyles">
              {{ statusText }}
            </span>
          </div>
          <p class="text-xs sm:text-sm text-[#71819B] mt-1 font-medium">
            Review your academic records and semester submission status.
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-3 flex-wrap">
        <!-- Refresh Button -->
        <button
          @click="fetchData"
          :disabled="loading"
          class="w-10 h-10 rounded-xl border border-[#E6EBF3] bg-white hover:bg-slate-50 hover:border-slate-300 text-slate-600 flex items-center justify-center transition-colors cursor-pointer shadow-2xs"
          title="Refresh Submission Status"
        >
          <svg
            class="w-4 h-4 text-slate-500"
            :class="{ 'animate-spin': loading }"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>

        <!-- Print Archive Summary Button -->
        <button
          @click="printArchiveReport"
          class="flex items-center justify-center gap-2 bg-white hover:bg-slate-50 border border-[#E6EBF3] hover:border-slate-300 text-slate-700 px-4 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-2xs transition-colors min-h-[44px] cursor-pointer"
          title="Print Official Semester Record Summary"
        >
          <svg class="w-4 h-4 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
          </svg>
          <span>Print Archive</span>
        </button>

        <!-- Submit Button in Header (if eligible) -->
        <button
          v-if="isAllCompleted && submissionData.status === 'pending'"
          @click="showConfirmModal = true"
          :disabled="submitting"
          class="flex items-center justify-center gap-2 bg-[#4F35F3] hover:bg-indigo-600 text-white px-5 py-2.5 rounded-xl font-bold text-xs sm:text-sm shadow-xs hover:shadow transition-all min-h-[44px] cursor-pointer"
        >
          <svg class="w-4 h-4 text-white shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
          </svg>
          <span>Submit Records</span>
        </button>
      </div>
    </div>

    <!-- 4 Top Academic Info Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-4 gap-3.5 sm:gap-4">
      <div
        v-for="info in academicInfo"
        :key="info.label"
        class="bg-white rounded-2xl border border-[#E6EBF3] shadow-xs hover:shadow-md transition-shadow duration-200 p-4 sm:p-5 flex items-center gap-3.5 group"
      >
        <div :class="[info.bg, info.color, 'w-11 h-11 sm:w-12 sm:h-12 rounded-xl flex items-center justify-center shrink-0 border group-hover:scale-105 transition-transform']">
          <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="info.icon"></path>
          </svg>
        </div>
        <div class="min-w-0">
          <p class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider truncate mb-0.5">{{ info.label }}</p>
          <div v-if="loading" class="h-6 w-20 bg-slate-100 animate-pulse rounded my-1"></div>
          <h3 v-else class="text-base sm:text-lg font-black text-[#17243A] leading-tight truncate my-0.5">{{ info.value }}</h3>
          <p class="text-[11px] text-[#71819B] font-medium truncate">{{ info.sub }}</p>
        </div>
      </div>
    </div>

    <!-- Main Content Layout (2-Column on XL screens) -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      
      <!-- Left Column: Checklist & Overview -->
      <div class="xl:col-span-2 space-y-6">
        
        <!-- Checklist Card -->
        <div class="bg-white rounded-2xl border border-[#E6EBF3] shadow-xs hover:shadow-md transition-shadow duration-200 p-5 sm:p-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-[#E6EBF3]">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100 text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
              </div>
              <div>
                <h3 class="text-sm sm:text-base font-bold text-[#17243A] leading-tight">
                  Academic Completion Checklist
                </h3>
                <p class="text-xs text-[#71819B] font-medium mt-0.5">
                  Complete all required activities before submitting your semester records.
                </p>
              </div>
            </div>

            <!-- Checklist Status Badge -->
            <div class="flex items-center gap-2">
              <span
                v-if="isAllCompleted"
                class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-full text-xs font-bold flex items-center gap-1.5 shadow-2xs"
              >
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                <span>All Completed</span>
              </span>
              <span
                v-else
                class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200/80 rounded-full text-xs font-bold flex items-center gap-1.5 shadow-2xs"
              >
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ completedChecklistCount }} of {{ checklist.length }} Done ({{ completionPercentage }}%)</span>
              </span>
            </div>
          </div>

          <!-- Checklist Items -->
          <div class="space-y-3 relative">
            <div v-if="loading" class="space-y-3">
              <div v-for="i in 4" :key="`skel-${i}`" class="h-20 bg-slate-50 border border-slate-100 rounded-xl animate-pulse"></div>
            </div>
            
            <div
              v-else
              v-for="item in checklist"
              :key="item.id"
              class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-[#E6EBF3] bg-white hover:bg-slate-50/60 transition-all duration-150 group"
            >
              <div class="flex items-center gap-3.5 min-w-0">
                <div
                  :class="[
                    item.completed ? 'text-[#4F35F3] bg-indigo-50 border-indigo-100' : 'text-slate-400 bg-slate-50 border-slate-200',
                    'w-10 h-10 rounded-xl border flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform'
                  ]"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path>
                  </svg>
                </div>
                <div class="min-w-0">
                  <h4 class="text-xs sm:text-sm font-bold text-[#17243A] truncate">{{ item.title }}</h4>
                  <p class="text-[11px] sm:text-xs text-[#71819B] font-medium mt-0.5 truncate">{{ item.desc }}</p>
                </div>
              </div>

              <div class="flex items-center justify-between sm:justify-end gap-4 sm:gap-6 pl-13 sm:pl-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                <span
                  v-if="item.completed"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-md text-xs font-bold shrink-0"
                >
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  <span>Completed</span>
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-600 rounded-md text-xs font-bold shrink-0"
                >
                  Pending
                </span>

                <div class="text-right shrink-0">
                  <p class="text-xs font-bold text-slate-800">{{ item.date }}</p>
                  <p class="text-[10px] font-semibold text-[#71819B]">{{ item.time }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Ready to Submit Callout Banner -->
          <div
            v-if="isAllCompleted && submissionData.status === 'pending'"
            class="mt-6 bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 rounded-2xl p-4 sm:p-5 border border-emerald-200/90 shadow-2xs flex flex-col md:flex-row md:items-center justify-between gap-4"
          >
            <div class="flex items-center gap-3.5 min-w-0">
              <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shrink-0 shadow-2xs">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
              </div>
              <div>
                <h4 class="text-xs sm:text-sm font-bold text-emerald-950 leading-tight">
                  You have completed all required activities for this semester.
                </h4>
                <p class="text-xs text-emerald-800/90 font-medium mt-0.5">
                  Your academic records are ready to be finalized and submitted to your Department Head.
                </p>
              </div>
            </div>
            <button
              @click="showConfirmModal = true"
              :disabled="submitting"
              class="min-h-[44px] flex items-center justify-center gap-2 px-5 py-2.5 bg-[#4F35F3] hover:bg-indigo-600 text-white rounded-xl text-xs sm:text-sm font-bold disabled:opacity-50 transition-all shadow-xs shrink-0 cursor-pointer w-full md:w-auto"
            >
              <svg v-if="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
              </svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
              </svg>
              <span>{{ submitting ? 'Submitting Records...' : 'Submit Semester Records' }}</span>
            </button>
          </div>
        </div>

        <!-- Semester Summary Stats -->
        <div class="bg-white rounded-2xl border border-[#E6EBF3] shadow-xs hover:shadow-md transition-shadow duration-200 p-5 sm:p-6">
          <div class="flex items-center gap-3 mb-6 pb-4 border-b border-[#E6EBF3]">
            <div class="w-8 h-8 rounded-xl bg-slate-50 text-[#71819B] flex items-center justify-center border border-[#E6EBF3] shrink-0 shadow-2xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
              </svg>
            </div>
            <div>
              <h3 class="text-sm sm:text-base font-bold text-[#17243A] leading-tight">Semester Summary</h3>
              <p class="text-xs text-[#71819B] font-medium mt-0.5">Overview of your semester academic activities and records.</p>
            </div>
          </div>
          
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 sm:gap-4">
            <div
              v-for="stat in summaryStats"
              :key="stat.label"
              class="p-4 rounded-xl border border-[#E6EBF3] bg-white hover:bg-slate-50/60 transition-colors flex flex-col justify-center shadow-2xs"
            >
              <div class="flex items-center gap-2.5 mb-2.5">
                <div :class="[stat.bg, stat.color, 'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 border']">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path>
                  </svg>
                </div>
                <p class="text-[10px] font-bold text-[#71819B] uppercase tracking-wider leading-tight truncate">{{ stat.label }}</p>
              </div>
              <h3 class="text-xl sm:text-2xl font-black text-[#17243A] leading-none mb-1">{{ stat.value }}</h3>
              <p class="text-[11px] text-[#71819B] font-medium truncate">{{ stat.sub }}</p>
            </div>
          </div>
        </div>

        <!-- Reviewer Remarks / Reopen Notes (If present) -->
        <div
          v-if="submissionData.reopen_reason || submissionData.remarks"
          class="bg-amber-50/80 border border-amber-200 rounded-2xl p-5 shadow-2xs"
        >
          <div class="flex items-center gap-2.5 mb-2">
            <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <h4 class="text-xs sm:text-sm font-bold text-amber-900">Department Head Feedback</h4>
          </div>
          <p v-if="submissionData.reopen_reason" class="text-xs text-amber-800 leading-relaxed font-medium">
            <strong>Reopen Reason:</strong> {{ submissionData.reopen_reason }}
          </p>
          <p v-if="submissionData.remarks" class="text-xs text-amber-800 leading-relaxed font-medium mt-1">
            <strong>Remarks:</strong> {{ submissionData.remarks }}
          </p>
        </div>

      </div>

      <!-- Right Column: Submission Status & Policy -->
      <div class="space-y-6">
        
        <!-- Submission Status Panel -->
        <div class="bg-white rounded-2xl border border-[#E6EBF3] shadow-xs hover:shadow-md transition-shadow duration-200 overflow-hidden flex flex-col">
          
          <!-- Hero Header on Card -->
          <div class="p-6 bg-gradient-to-br from-[#1E1160] via-[#2F1D8B] to-[#4F35F3] text-white relative">
            <div class="flex items-start justify-between relative z-10 gap-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur-md border border-white/20 flex items-center justify-center shrink-0 shadow-2xs">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                </div>
                <div class="min-w-0">
                  <h3 class="text-sm sm:text-base font-bold text-white leading-tight">Submission Status</h3>
                  <p class="text-xs text-indigo-100/90 font-medium mt-0.5 truncate">
                    {{ submissionData.status === 'pending' ? 'Semester records ready for submission.' : (submissionData.status === 'submitted' ? 'Records are under Department Head review.' : 'Records are approved & locked.') }}
                  </p>
                </div>
              </div>
              <span class="px-2.5 py-1 bg-white/20 backdrop-blur-md border border-white/30 rounded-full text-[11px] font-bold capitalize shrink-0">
                {{ submissionData.status || 'Pending' }}
              </span>
            </div>

            <p class="text-xs text-indigo-200/90 mt-4 leading-relaxed font-medium relative z-10">
              After submission, you will not be able to make any changes to your records.
            </p>
          </div>
          
          <!-- Stepper Workflow Timeline -->
          <div class="p-6 relative flex-1">
            <div class="relative">
              <!-- Vertical Connecting Line -->
              <div class="absolute left-4 top-4 bottom-4 w-0.5 bg-slate-100"></div>
              
              <div class="space-y-6 relative">
                
                <!-- Stage 1: Activities Completed -->
                <div class="flex items-start gap-4">
                  <div
                    :class="[
                      isAllCompleted ? 'bg-[#10B981] text-white shadow-emerald-200' : 'bg-slate-50 border border-slate-200 text-slate-400',
                      'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10'
                    ]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  </div>
                  <div class="pt-1 min-w-0">
                    <h4 :class="[isAllCompleted ? 'text-[#17243A]' : 'text-slate-400', 'text-xs sm:text-[13px] font-bold leading-tight']">
                      Academic Activities Completed
                    </h4>
                    <p class="text-[11px] font-medium text-[#71819B] mt-0.5">
                      {{ isAllCompleted ? (checklistData.results?.date ? checklistData.results?.date + ' • ' + checklistData.results?.time : 'Completed') : 'Activities in progress' }}
                    </p>
                  </div>
                </div>
                
                <!-- Stage 2: Ready for Submission -->
                <div class="flex items-start gap-4">
                  <div
                    :class="[
                      submissionData.status !== 'pending' ? 'bg-[#10B981] text-white' : (isAllCompleted ? 'bg-[#4F35F3] text-white' : 'bg-slate-50 border border-slate-200 text-slate-400'),
                      'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10'
                    ]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="submissionData.status !== 'pending'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                  </div>
                  <div class="pt-1 min-w-0">
                    <h4 :class="[isAllCompleted ? 'text-[#17243A]' : 'text-slate-400', 'text-xs sm:text-[13px] font-bold leading-tight']">
                      Ready for Submission
                    </h4>
                    <p class="text-[11px] font-medium text-[#71819B] mt-0.5">
                      {{ isAllCompleted ? 'All required activities are completed' : 'Awaiting activity completion' }}
                    </p>
                  </div>
                </div>
                
                <!-- Stage 3: Submitted -->
                <div class="flex items-start gap-4">
                  <div
                    :class="[
                      submissionData.status === 'submitted' ? 'bg-[#4F35F3] text-white' : (submissionData.status === 'approved' ? 'bg-[#10B981] text-white' : 'bg-slate-50 border border-slate-200 text-slate-400'),
                      'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10'
                    ]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                  </div>
                  <div class="pt-1 min-w-0">
                    <h4 :class="[submissionData.status !== 'pending' ? 'text-[#17243A]' : 'text-slate-400', 'text-xs sm:text-[13px] font-bold leading-tight']">
                      Submitted
                    </h4>
                    <p class="text-[11px] font-medium text-[#71819B] mt-0.5">
                      {{ submissionData.status !== 'pending' ? (submissionData.submitted_at || 'Submitted') : 'Not submitted yet' }}
                    </p>
                  </div>
                </div>
                
                <!-- Stage 4: Department Head Approval -->
                <div class="flex items-start gap-4">
                  <div
                    :class="[
                      submissionData.status === 'approved' ? 'bg-[#10B981] text-white' : 'bg-slate-50 border border-slate-200 text-slate-400',
                      'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10'
                    ]"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                  </div>
                  <div class="pt-1 min-w-0">
                    <h4 :class="[submissionData.status === 'approved' ? 'text-[#17243A]' : 'text-slate-400', 'text-xs sm:text-[13px] font-bold leading-tight']">
                      Approved by Department Head
                    </h4>
                    <p class="text-[11px] font-medium text-[#71819B] mt-0.5">
                      {{ submissionData.status === 'approved' ? (submissionData.approved_at || 'Approved') : 'Awaiting review & approval' }}
                    </p>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- Semester Locked Read-Only Notice Panel (When Locked) -->
        <div
          v-if="submissionData.is_locked || lockStore.isLocked"
          class="bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 border border-emerald-200/90 rounded-2xl p-5 shadow-2xs"
        >
          <div class="flex items-start gap-3.5">
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-200 shadow-2xs mt-0.5">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
              </svg>
            </div>
            <div>
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <h4 class="text-xs sm:text-sm font-bold text-emerald-950">
                  Semester Locked — Read-Only Mode
                </h4>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-200/60 text-emerald-900 border border-emerald-300/60 uppercase tracking-wider">
                  {{ lockStore.statusLabel }}
                </span>
              </div>
              <p class="text-xs text-emerald-800/90 leading-relaxed font-medium">
                Academic activities and records are officially locked for {{ academicInfo[0].value }} ({{ academicInfo[1].value }}). Editing student data, questions, or exam schedules is disabled.
              </p>
              <p class="text-[11px] text-emerald-700/90 mt-2 font-medium">
                If a correction is required, please contact your Department Head to request reopening.
              </p>
            </div>
          </div>
        </div>

        <!-- Important Academic Policy Card -->
        <div class="bg-indigo-50/50 rounded-2xl border border-indigo-100/80 p-5 sm:p-6 shadow-2xs">
          <div class="flex items-center gap-2.5 mb-3.5">
            <div class="w-8 h-8 rounded-xl bg-white text-[#4F35F3] flex items-center justify-center shadow-2xs border border-indigo-100/60 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="text-xs sm:text-sm font-bold text-[#17243A]">Academic Policy Guidelines</h3>
          </div>
          <ul class="space-y-2.5">
            <li class="flex items-start gap-2 text-xs text-[#71819B] font-medium leading-relaxed">
              <span class="w-1.5 h-1.5 rounded-full bg-[#4F35F3] mt-1.5 shrink-0"></span>
              <span>Once submitted, all course examinations, student rosters, and grades are locked in read-only mode.</span>
            </li>
            <li class="flex items-start gap-2 text-xs text-[#71819B] font-medium leading-relaxed">
              <span class="w-1.5 h-1.5 rounded-full bg-[#4F35F3] mt-1.5 shrink-0"></span>
              <span>The Department Head reviews the cohort evaluation before certifying academic completion.</span>
            </li>
            <li class="flex items-start gap-2 text-xs text-[#71819B] font-medium leading-relaxed">
              <span class="w-1.5 h-1.5 rounded-full bg-[#4F35F3] mt-1.5 shrink-0"></span>
              <span>If issues are detected, the Department Head can request revisions with feedback notes.</span>
            </li>
          </ul>
          
          <div class="mt-6 text-center border-t border-indigo-100/60 pt-4">
            <p class="text-[11px] italic text-[#71819B] font-medium">Together for a better academic future</p>
            <p class="text-xs font-bold text-[#17243A] mt-0.5">Wollo University &bull; College of Computing</p>
          </div>
        </div>

      </div>

    </div>

    <!-- Submit Confirmation Modal -->
    <div
      v-if="showConfirmModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/50 backdrop-blur-xs"
    >
      <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-100 flex flex-col">
        <div class="flex items-center gap-3.5 pb-4 border-b border-slate-100">
          <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
          </div>
          <div>
            <h3 class="text-base font-bold text-[#17243A]">Submit Semester Records?</h3>
            <p class="text-xs text-[#71819B] mt-0.5">{{ academicInfo[0].value }} &bull; {{ academicInfo[1].value }}</p>
          </div>
        </div>

        <div class="py-4 space-y-3">
          <p class="text-xs text-[#17243A] leading-relaxed font-medium">
            Are you sure you want to finalize and submit your academic records to the Department Head?
          </p>
          <div class="bg-amber-50 border border-amber-200/80 rounded-xl p-3 text-xs text-amber-900 font-medium leading-relaxed">
            <strong>Important Notice:</strong> Once submitted, all exams, questions, student enrollments, and grades will be <strong>LOCKED in read-only mode</strong>. Modifications cannot be made unless reopened by the Department Head.
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button
            @click="showConfirmModal = false"
            class="px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors cursor-pointer"
          >
            Cancel
          </button>
          <button
            @click="submitRecords"
            :disabled="submitting"
            class="px-5 py-2.5 bg-[#4F35F3] hover:bg-indigo-600 text-white text-xs font-bold rounded-xl shadow-xs transition-colors cursor-pointer disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
            <span>{{ submitting ? 'Submitting...' : 'Confirm & Submit Semester' }}</span>
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
