<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import apiClient from '../../../core/api/apiClient'

const loading = ref(true)

const academicInfo = ref([
  { id: 'academic_year', label: 'Academic Year', value: '-', sub: 'Current academic year', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', color: 'text-indigo-500', bg: 'bg-indigo-50' },
  { id: 'semester', label: 'Semester', value: '-', sub: 'Current semester', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', color: 'text-purple-500', bg: 'bg-purple-50' },
  { id: 'department', label: 'Department', value: '-', sub: 'Your department', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', color: 'text-sky-500', bg: 'bg-sky-50' },
  { id: 'section', label: 'Section', value: '-', sub: 'Your teaching section', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', color: 'text-blue-500', bg: 'bg-blue-50' },
])

const checklistData = ref<Record<string, any>>({})
const isAllCompleted = ref(false)

const checklist = computed(() => [
  { id: 'academic_schedule', title: 'Academic Schedule', desc: 'Create and manage your course schedule.', date: checklistData.value.academic_schedule?.date || '-', time: checklistData.value.academic_schedule?.time || '-', completed: checklistData.value.academic_schedule?.completed || false, icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
  { id: 'exams', title: 'Exams', desc: 'Create and manage exams for your courses.', date: checklistData.value.exams?.date || '-', time: checklistData.value.exams?.time || '-', completed: checklistData.value.exams?.completed || false, icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { id: 'student_info', title: 'Student Information', desc: 'Manage student enrollment and personal information.', date: checklistData.value.student_info?.date || '-', time: checklistData.value.student_info?.time || '-', completed: checklistData.value.student_info?.completed || false, icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { id: 'results', title: 'Results', desc: 'Publish and manage final results.', date: checklistData.value.results?.date || '-', time: checklistData.value.results?.time || '-', completed: checklistData.value.results?.completed || false, icon: 'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z' },
])

const summaryStats = ref([
  { id: 'total_students', label: 'Total Students', value: '-', sub: 'Enrolled students', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', color: 'text-blue-500', bg: 'bg-blue-50' },
  { id: 'courses', label: 'Courses', value: '-', sub: 'Assigned courses', icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253', color: 'text-purple-500', bg: 'bg-purple-50' },
  { id: 'exams_conducted', label: 'Exams Conducted', value: '-', sub: 'Total exams', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', color: 'text-emerald-500', bg: 'bg-emerald-50' },
  { id: 'results_submitted', label: 'Results Submitted', value: '-', sub: 'Students with results', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', color: 'text-orange-500', bg: 'bg-orange-50' },
])

const submissionData = ref<any>({ status: 'pending' })
const submitting = ref(false)

const fetchData = async () => {
  try {
    const res = await apiClient.get('/instructor/semester-submission/status')
    const data = res.data.data
    
    // Update Academic Info
    academicInfo.value[0].value = data.academic_info.academic_year || '-'
    academicInfo.value[1].value = data.academic_info.semester || '-'
    academicInfo.value[2].value = data.academic_info.department || '-'
    academicInfo.value[3].value = data.academic_info.section || '-'
    
    // Update Checklist & Stats
    checklistData.value = data.checklist
    isAllCompleted.value = data.is_all_completed
    
    summaryStats.value[0].value = data.summary_stats.total_students.toString()
    summaryStats.value[1].value = data.summary_stats.courses.toString()
    summaryStats.value[2].value = data.summary_stats.exams_conducted.toString()
    summaryStats.value[3].value = data.summary_stats.results_submitted.toString()
    
    // Update Submission Status
    submissionData.value = data.submission
    
  } catch (err) {
    console.error('Failed to fetch semester submission data', err)
  } finally {
    loading.value = false
  }
}

const submitRecords = async () => {
  if (submitting.value) return
  submitting.value = true
  try {
    const res = await apiClient.post('/instructor/semester-submission/submit')
    submissionData.value.status = res.data.data.status
    submissionData.value.submitted_at = res.data.data.submitted_at
  } catch (err) {
    console.error('Failed to submit records', err)
    alert('Failed to submit semester records.')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchData()
})
</script>

<template>
  <div class="space-y-6 max-w-[1400px] mx-auto">
    
    <!-- Page Header -->
    <div class="flex items-start gap-4 mb-8">
      <div class="w-12 h-12 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0 border border-indigo-100">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
      </div>
      <div class="pt-1">
        <h1 class="text-xl sm:text-[22px] font-bold text-slate-800 leading-tight">Semester Submission</h1>
        <p class="text-[13px] font-medium text-slate-500 mt-1">Complete your semester activities and submit your records to the department head.</p>
      </div>
    </div>

    <!-- 4 Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div v-for="info in academicInfo" :key="info.label" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-center gap-4">
        <div :class="[info.bg, info.color, 'w-12 h-12 rounded-xl flex items-center justify-center shrink-0']">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="info.icon"></path>
          </svg>
        </div>
        <div>
          <p class="text-[12px] font-bold text-slate-500 mb-0.5">{{ info.label }}</p>
          <h3 class="text-[15px] font-bold text-slate-800 leading-tight">{{ info.value }}</h3>
          <p class="text-[11px] text-slate-400 font-medium mt-1">{{ info.sub }}</p>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      
      <!-- Left Column -->
      <div class="xl:col-span-2 space-y-6">
        
        <!-- Checklist Card -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
              <div class="w-6 h-6 rounded-full bg-[#5138ed] text-white flex items-center justify-center shrink-0">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
              </div>
              <div>
                <h3 class="text-[15px] font-bold text-slate-800 leading-tight">Academic Completion Checklist</h3>
                <p class="text-[12px] font-medium text-slate-500 mt-0.5">Complete all required activities before submitting your semester records.</p>
              </div>
            </div>
            <span v-if="isAllCompleted" class="px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-[11px] font-bold flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
              All Completed
            </span>
            <span v-else class="px-3 py-1 bg-amber-50 text-amber-600 rounded-full text-[11px] font-bold flex items-center gap-1">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              In Progress
            </span>
          </div>

          <div class="space-y-4 relative">
            <div v-if="loading" class="absolute inset-0 bg-white/50 backdrop-blur-[1px] flex items-center justify-center z-10">
              <div class="w-6 h-6 border-2 border-[#5138ed] border-t-transparent rounded-full animate-spin"></div>
            </div>
            
            <div v-for="item in checklist" :key="item.id" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors">
              <div class="flex items-center gap-4">
                <div :class="[item.completed ? 'text-[#5138ed]' : 'text-slate-400', 'w-10 h-10 rounded-xl bg-white border border-slate-100 flex items-center justify-center shadow-sm shrink-0']">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path>
                  </svg>
                </div>
                <div>
                  <h4 class="text-[13px] font-bold text-slate-800">{{ item.title }}</h4>
                  <p class="text-[11px] text-slate-500 font-medium mt-0.5">{{ item.desc }}</p>
                </div>
              </div>
              <div class="flex items-center gap-4 sm:gap-6 pl-14 sm:pl-0">
                <span v-if="item.completed" class="flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-600 rounded-md text-[11px] font-bold">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  Completed
                </span>
                <span v-else class="flex items-center gap-1.5 px-2.5 py-1 bg-slate-100 text-slate-500 rounded-md text-[11px] font-bold">
                  Pending
                </span>
                <div class="text-right">
                  <p class="text-[11px] font-bold text-slate-600">{{ item.date }}</p>
                  <p class="text-[10px] font-medium text-slate-400">{{ item.time }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Final Submit Banner -->
          <div v-if="isAllCompleted && submissionData.status === 'pending'" class="mt-6 bg-emerald-50 rounded-xl p-4 sm:p-5 border border-emerald-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
              <div class="w-10 h-10 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
              </div>
              <div>
                <h4 class="text-[14px] font-bold text-emerald-800">You have completed all required activities for this semester.</h4>
                <p class="text-[12px] font-medium text-emerald-600 mt-0.5">You can now submit your semester records to the department head.</p>
              </div>
            </div>
            <button @click="submitRecords" :disabled="submitting" class="min-h-[44px] flex items-center justify-center gap-2 px-5 py-2.5 bg-[#5138ed] text-white rounded-xl text-[13px] font-bold hover:bg-indigo-600 disabled:opacity-50 transition-colors shadow-sm shrink-0 w-full sm:w-auto">
              <svg v-if="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
              <svg v-else class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
              {{ submitting ? 'Submitting...' : 'Submit Semester Records' }}
            </button>
          </div>
        </div>

        <!-- Semester Summary -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-500 flex items-center justify-center border border-slate-100 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            </div>
            <div>
              <h3 class="text-[15px] font-bold text-slate-800 leading-tight">Semester Summary</h3>
              <p class="text-[12px] font-medium text-slate-500 mt-0.5">Overview of your semester academic activities and records.</p>
            </div>
          </div>
          
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div v-for="stat in summaryStats" :key="stat.label" class="p-4 rounded-xl border border-slate-100 bg-white flex flex-col justify-center shadow-sm">
              <div class="flex items-center gap-3 mb-3">
                <div :class="[stat.bg, stat.color, 'w-8 h-8 rounded-lg flex items-center justify-center shrink-0']">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"></path>
                  </svg>
                </div>
                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider leading-tight">{{ stat.label }}</p>
              </div>
              <h3 class="text-[24px] font-bold text-slate-800 leading-none mb-1">{{ stat.value }}</h3>
              <p class="text-[10px] text-slate-400 font-medium">{{ stat.sub }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column -->
      <div class="space-y-6">
        
        <!-- Submission Status -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
          <div class="p-6 bg-gradient-to-br from-[#2a1b7a] to-[#5138ed] text-white relative">
            <div v-if="loading" class="absolute inset-0 bg-[#2a1b7a]/50 backdrop-blur-sm z-10 flex items-center justify-center">
              <div class="w-6 h-6 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div class="flex items-start justify-between relative z-20">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <div>
                  <h3 class="text-[15px] font-bold text-white">Submission Status</h3>
                  <p class="text-[12px] font-medium text-white/80 mt-0.5 max-w-[200px]">
                    {{ submissionData.status === 'pending' ? 'Your semester records are ready for submission.' : (submissionData.status === 'submitted' ? 'Your records are under review.' : 'Your records are approved.') }}
                  </p>
                </div>
              </div>
              <span class="px-3 py-1 bg-white/20 backdrop-blur-sm rounded-full text-[11px] font-bold capitalize">{{ submissionData.status || 'Pending' }}</span>
            </div>
            <p class="text-[12px] text-white/70 mt-4 leading-relaxed font-medium relative z-20">
              After submission, you will not be able to make any changes to your records.
            </p>
          </div>
          
          <div class="p-6 relative">
            <div v-if="loading" class="absolute inset-0 bg-white/50 backdrop-blur-[1px] z-10 flex items-center justify-center"></div>
            <div class="relative">
              <!-- Line -->
              <div class="absolute left-4 top-4 bottom-4 w-px bg-slate-100"></div>
              
              <div class="space-y-6 relative">
                <!-- Step 1: Activities Completed -->
                <div class="flex items-start gap-4">
                  <div :class="[isAllCompleted ? 'bg-emerald-500 text-white' : 'bg-slate-50 border border-slate-200 text-slate-400', 'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  </div>
                  <div class="pt-1.5">
                    <h4 :class="[isAllCompleted ? 'text-slate-800' : 'text-slate-400', 'text-[13px] font-bold leading-tight']">Academic Activities Completed</h4>
                    <p class="text-[11px] font-medium text-slate-500 mt-0.5">{{ isAllCompleted ? checklistData.results?.date + ' • ' + checklistData.results?.time : 'Not completed yet' }}</p>
                  </div>
                </div>
                
                <!-- Step 2: Ready for Submission -->
                <div class="flex items-start gap-4">
                  <div :class="[isAllCompleted && submissionData.status === 'pending' ? 'bg-[#5138ed] text-white' : (submissionData.status !== 'pending' ? 'bg-emerald-500 text-white' : 'bg-slate-50 border border-slate-200 text-slate-400'), 'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="submissionData.status !== 'pending'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                  </div>
                  <div class="pt-1.5">
                    <h4 :class="[isAllCompleted ? 'text-slate-800' : 'text-slate-400', 'text-[13px] font-bold leading-tight']">Ready for Submission</h4>
                    <p class="text-[11px] font-medium text-slate-500 mt-0.5">{{ isAllCompleted ? 'All required activities are completed' : 'Waiting for completion' }}</p>
                  </div>
                </div>
                
                <!-- Step 3: Submitted -->
                <div class="flex items-start gap-4">
                  <div :class="[submissionData.status === 'submitted' ? 'bg-[#5138ed] text-white' : (submissionData.status === 'approved' ? 'bg-emerald-500 text-white' : 'bg-slate-50 border border-slate-200 text-slate-400'), 'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path v-if="submissionData.status === 'approved'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                      <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                  </div>
                  <div class="pt-1.5">
                    <h4 :class="[submissionData.status !== 'pending' ? 'text-slate-800' : 'text-slate-400', 'text-[13px] font-bold leading-tight']">Submitted</h4>
                    <p class="text-[11px] font-medium text-slate-500 mt-0.5">{{ submissionData.status !== 'pending' ? submissionData.submitted_at : 'Not submitted yet' }}</p>
                  </div>
                </div>
                
                <!-- Step 4: Approved -->
                <div class="flex items-start gap-4">
                  <div :class="[submissionData.status === 'approved' ? 'bg-emerald-500 text-white' : 'bg-slate-50 border border-slate-200 text-slate-400', 'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm ring-4 ring-white z-10']">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                  </div>
                  <div class="pt-1.5">
                    <h4 :class="[submissionData.status === 'approved' ? 'text-slate-800' : 'text-slate-400', 'text-[13px] font-bold leading-tight']">Approved by Department Head</h4>
                    <p class="text-[11px] font-medium text-slate-500 mt-0.5">{{ submissionData.status === 'approved' ? submissionData.approved_at : 'Not approved yet' }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Important Information -->
        <div class="bg-indigo-50/50 rounded-2xl border border-indigo-100 p-6">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-full bg-white text-indigo-500 flex items-center justify-center shadow-sm shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <h3 class="text-[14px] font-bold text-indigo-900">Important Information</h3>
          </div>
          <ul class="space-y-3">
            <li class="flex items-start gap-2">
              <span class="w-1 h-1 rounded-full bg-indigo-400 mt-2 shrink-0"></span>
              <p class="text-[12px] font-medium text-slate-600 leading-relaxed">Once you submit your records, you will not be able to edit your student information, grades, or results.</p>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1 h-1 rounded-full bg-indigo-400 mt-2 shrink-0"></span>
              <p class="text-[12px] font-medium text-slate-600 leading-relaxed">The department head will review and approve your submission.</p>
            </li>
            <li class="flex items-start gap-2">
              <span class="w-1 h-1 rounded-full bg-indigo-400 mt-2 shrink-0"></span>
              <p class="text-[12px] font-medium text-slate-600 leading-relaxed">If there are any issues, you will be notified and asked to resubmit if necessary.</p>
            </li>
          </ul>
          
          <div class="mt-8 text-center border-t border-indigo-100/50 pt-5">
            <p class="text-[12px] italic text-slate-500 font-medium font-serif">Together for a better academic future</p>
            <p class="text-[13px] font-bold text-indigo-900 mt-1">Wollo University</p>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
