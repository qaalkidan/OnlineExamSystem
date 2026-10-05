<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'

const router = useRouter()

const searchQuery = ref('')
const selectedDepartment = ref('All Departments')
const selectedStatus = ref('All Statuses')

const isLoading = ref(true)
const submissions = ref<any[]>([])
const stats = ref({
  totalAcademicYears: 0,
  totalDepartments: 0,
  totalCourses: 0,
  totalInstructors: 0
})

const fetchSubmissions = async () => {
  isLoading.value = true
  try {
    const response = await apiClient.get('/dept-head/semester-submissions')
    submissions.value = response.data.submissions || []
    stats.value = response.data.stats || stats.value
  } catch (error) {
    console.error('Failed to fetch semester submissions:', error)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchSubmissions()
})

const filteredSubmissions = computed(() => {
  return submissions.value.filter(sub => {
    const q = searchQuery.value.toLowerCase().trim()
    const matchesSearch = !q || sub.academicYear.toLowerCase().includes(q) || sub.department.toLowerCase().includes(q)
    const matchesDept = selectedDepartment.value === 'All Departments' || sub.department === selectedDepartment.value
    const matchesStatus = selectedStatus.value === 'All Statuses' || sub.status === selectedStatus.value
    return matchesSearch && matchesDept && matchesStatus
  })
})

const refreshData = () => {
  fetchSubmissions()
}
</script>

<template>
  <div class="space-y-5 sm:space-y-6 min-w-0 max-w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 bg-indigo-50 text-[#5138ed] rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
        </div>
        <div class="min-w-0">
          <h2 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight truncate">Semester Submissions</h2>
          <p class="text-xs sm:text-sm text-slate-500 font-medium truncate">View and manage instructor semester submissions grouped by academic year.</p>
        </div>
      </div>

      <button
        @click="router.push({ name: 'DeptHeadSemesterSubmissions' })"
        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#5138ed] text-white rounded-xl text-xs sm:text-[13px] font-bold hover:bg-[#432dd4] transition-all shadow-xs min-h-[44px] cursor-pointer shrink-0"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
        All Instructor Submissions
      </button>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
      <!-- Total Academic Years -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 flex items-center gap-4 shadow-xs">
        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-0.5 truncate">Total Academic Years</span>
          <span class="text-xl sm:text-2xl font-black text-slate-800">
            <span v-if="isLoading" class="animate-pulse h-6 bg-slate-200 rounded w-8 inline-block"></span>
            <span v-else>{{ stats.totalAcademicYears }}</span>
          </span>
          <span class="text-[11px] font-medium text-slate-500 mt-1 truncate">Available academic years</span>
        </div>
      </div>

      <!-- Total Departments -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 flex items-center gap-4 shadow-xs">
        <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-0.5 truncate">Total Departments</span>
          <span class="text-xl sm:text-2xl font-black text-slate-800">
            <span v-if="isLoading" class="animate-pulse h-6 bg-slate-200 rounded w-8 inline-block"></span>
            <span v-else>{{ stats.totalDepartments }}</span>
          </span>
          <span class="text-[11px] font-medium text-slate-500 mt-1 truncate">Departments</span>
        </div>
      </div>

      <!-- Total Courses -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 flex items-center gap-4 shadow-xs">
        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-0.5 truncate">Total Courses</span>
          <span class="text-xl sm:text-2xl font-black text-slate-800">
            <span v-if="isLoading" class="animate-pulse h-6 bg-slate-200 rounded w-8 inline-block"></span>
            <span v-else>{{ stats.totalCourses }}</span>
          </span>
          <span class="text-[11px] font-medium text-slate-500 mt-1 truncate">Across all departments</span>
        </div>
      </div>

      <!-- Total Instructors -->
      <div class="bg-white rounded-xl border border-slate-200 p-4 sm:p-5 flex items-center gap-4 shadow-xs">
        <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        </div>
        <div class="flex flex-col min-w-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-0.5 truncate">Total Instructors</span>
          <span class="text-xl sm:text-2xl font-black text-slate-800">
            <span v-if="isLoading" class="animate-pulse h-6 bg-slate-200 rounded w-8 inline-block"></span>
            <span v-else>{{ stats.totalInstructors }}</span>
          </span>
          <span class="text-[11px] font-medium text-slate-500 mt-1 truncate">Active instructors</span>
        </div>
      </div>
    </div>

    <!-- Main List Container -->
    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs flex flex-col min-w-0">
      <!-- Filter Bar -->
      <div class="p-3.5 sm:p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3 bg-slate-50/50">
        <div class="relative w-full md:w-80">
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Search by year, department..." 
            class="w-full pl-9 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-xs sm:text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors"
          />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
          <div class="relative flex-1 sm:flex-none min-w-[140px]">
            <select v-model="selectedDepartment" class="w-full appearance-none pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-lg text-xs sm:text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer">
              <option>All Departments</option>
              <option>Computer Science</option>
              <option>Mathematics</option>
              <option>Physics</option>
              <option>Electrical Engineering</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
          
          <div class="relative flex-1 sm:flex-none min-w-[130px]">
            <select v-model="selectedStatus" class="w-full appearance-none pl-3 pr-8 py-2 bg-white border border-slate-200 rounded-lg text-xs sm:text-[13px] text-slate-600 font-medium focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] cursor-pointer">
              <option>All Statuses</option>
              <option>Pending</option>
              <option>Submitted</option>
              <option>Not Submitted</option>
            </select>
            <svg class="w-4 h-4 text-slate-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
          
          <button @click="refreshData" class="px-3.5 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs sm:text-[13px] font-semibold hover:bg-slate-50 hover:text-slate-800 transition-colors flex items-center justify-center gap-1.5 shadow-xs min-h-[38px] cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span>Refresh</span>
          </button>
        </div>
      </div>

      <!-- Desktop & Tablet Table (Hidden on Mobile) -->
      <div class="hidden md:block overflow-x-auto min-w-0 w-full">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max">
          <thead>
            <tr class="bg-white text-[11px] font-bold text-slate-500 uppercase tracking-wider border-b border-slate-200">
              <th class="py-4 px-6 font-semibold">Academic Year</th>
              <th class="py-4 px-6 font-semibold">Department</th>
              <th class="py-4 px-6 font-semibold">Courses (Count)</th>
              <th class="py-4 px-6 font-semibold">Instructors (Count)</th>
              <th class="py-4 px-6 font-semibold">Submitted <svg class="w-3.5 h-3.5 inline ml-1 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></th>
              <th class="py-4 px-6 font-semibold">Status</th>
              <th class="py-4 px-6 font-semibold text-center">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-if="isLoading">
              <td colspan="7" class="py-12 text-center text-slate-400">
                <div class="flex flex-col items-center gap-3">
                  <div class="animate-spin w-8 h-8 border-2 border-[#5138ed] border-t-transparent rounded-full"></div>
                  <span class="text-sm font-semibold">Loading semester submissions...</span>
                </div>
              </td>
            </tr>
            <tr v-else-if="filteredSubmissions.length === 0">
              <td colspan="7" class="py-12 text-center text-slate-400">
                <div class="flex flex-col items-center gap-3">
                  <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                  <span class="text-sm font-semibold">No submissions found.</span>
                </div>
              </td>
            </tr>
            <tr v-else v-for="sub in filteredSubmissions" :key="sub.id" class="hover:bg-slate-50/50 transition-colors">
              <td class="py-4 px-6">
                <div class="flex flex-col">
                  <span class="text-[13px] font-bold text-slate-800">{{ sub.academicYear }}</span>
                  <span class="text-[11px] font-medium text-slate-500">{{ sub.semester }}</span>
                </div>
              </td>
              <td class="py-4 px-6">
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 bg-indigo-50 text-[#5138ed] rounded flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"></path></svg>
                  </div>
                  <span class="text-[13px] font-semibold text-slate-700">{{ sub.department }}</span>
                </div>
              </td>
              <td class="py-4 px-6">
                <div class="flex flex-col">
                  <span class="text-[13px] font-bold text-slate-800">{{ sub.coursesCount }}</span>
                  <span class="text-[11px] font-medium text-slate-500">Courses</span>
                </div>
              </td>
              <td class="py-4 px-6">
                <div class="flex flex-col">
                  <span class="text-[13px] font-bold text-slate-800">{{ sub.instructorsCount }}</span>
                  <span class="text-[11px] font-medium text-slate-500">Instructors</span>
                </div>
              </td>
              <td class="py-4 px-6">
                <div class="flex flex-col gap-1 w-32">
                  <div class="flex items-center justify-between mb-1">
                    <span class="text-[12px] font-bold text-slate-700">{{ sub.submittedCount }} / {{ sub.totalCount }}</span>
                  </div>
                  <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                    <div 
                      class="h-full rounded-full transition-all duration-300"
                      :class="sub.submittedCount === sub.totalCount ? 'bg-[#21c55e]' : (sub.submittedCount === 0 ? 'bg-transparent' : 'bg-[#10b981]')"
                      :style="`width: ${sub.totalCount > 0 ? (sub.submittedCount / sub.totalCount) * 100 : 0}%`"
                    ></div>
                  </div>
                  <span class="text-[10px] font-medium text-slate-500 mt-0.5">{{ Math.round((sub.totalCount > 0 ? (sub.submittedCount / sub.totalCount) : 0) * 100) }}% submitted</span>
                </div>
              </td>
              <td class="py-4 px-6">
                <span v-if="sub.status === 'Pending'" class="inline-flex items-center justify-center min-w-[100px] gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-600 border border-amber-100">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  Pending
                </span>
                <span v-else-if="sub.status === 'Submitted'" class="inline-flex items-center justify-center min-w-[100px] gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold bg-emerald-50 text-[#10b981] border border-emerald-100">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                  Submitted
                </span>
                <span v-else class="inline-flex items-center justify-center min-w-[100px] gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-bold bg-rose-50 text-rose-600 border border-rose-100">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                  Not Submitted
                </span>
              </td>
              <td class="py-4 px-6 text-center">
                <button
                  @click="router.push({ name: 'DeptHeadSemesterSubmissionDetail', params: { id: sub.id } })"
                  class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-indigo-50/50 text-[#5138ed] border border-indigo-100/50 hover:bg-indigo-50 hover:border-indigo-100 rounded-lg text-[11px] font-bold transition-all shadow-xs min-h-[36px] cursor-pointer"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  View Details
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile Card Layout (Visible on Mobile only < 768px) -->
      <div class="md:hidden divide-y divide-slate-100 bg-white">
        <div v-if="isLoading" class="py-12 text-center text-slate-400">
          <div class="flex flex-col items-center gap-3">
            <div class="animate-spin w-8 h-8 border-2 border-[#5138ed] border-t-transparent rounded-full"></div>
            <span class="text-sm font-semibold">Loading semester submissions...</span>
          </div>
        </div>
        <div v-else-if="filteredSubmissions.length === 0" class="py-12 text-center text-slate-400">
          <div class="flex flex-col items-center gap-3">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            <span class="text-sm font-semibold">No submissions found.</span>
          </div>
        </div>
        <div 
          v-else 
          v-for="sub in filteredSubmissions" 
          :key="sub.id" 
          class="p-4 space-y-3 hover:bg-slate-50/50 transition-colors"
        >
          <!-- Card Header with Year & Notification Badge -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-slate-800">{{ sub.academicYear }}</span>
              <span v-if="sub.totalCount - sub.submittedCount > 0" class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-100">
                🔔 {{ sub.totalCount - sub.submittedCount }}
              </span>
            </div>
            <!-- Status Badge -->
            <span v-if="sub.status === 'Pending'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600 border border-amber-100">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
              Pending
            </span>
            <span v-else-if="sub.status === 'Submitted'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-[#10b981] border border-emerald-100">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
              Submitted
            </span>
            <span v-else class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-100">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
              Not Submitted
            </span>
          </div>

          <!-- Department and Semester -->
          <div class="text-xs text-slate-600">
            <p class="font-bold text-slate-800">{{ sub.department }}</p>
            <p class="text-slate-400 mt-0.5">{{ sub.semester }} • {{ sub.coursesCount }} Courses • {{ sub.instructorsCount }} Instructors</p>
          </div>

          <!-- Progress -->
          <div class="space-y-1 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500 font-medium">Submitted</span>
              <span class="font-bold text-slate-800">{{ sub.submittedCount }} / {{ sub.totalCount }} Instructors</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
              <div 
                class="h-full rounded-full transition-all duration-300"
                :class="sub.submittedCount === sub.totalCount ? 'bg-[#21c55e]' : (sub.submittedCount === 0 ? 'bg-transparent' : 'bg-[#10b981]')"
                :style="`width: ${sub.totalCount > 0 ? (sub.submittedCount / sub.totalCount) * 100 : 0}%`"
              ></div>
            </div>
          </div>

          <!-- Action Button -->
          <div class="pt-1">
            <button
              @click="router.push({ name: 'DeptHeadSemesterSubmissionDetail', params: { id: sub.id } })"
              class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-50 text-[#5138ed] border border-indigo-100 hover:bg-indigo-100 rounded-xl text-xs font-bold transition-all min-h-[44px] cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
              View Details
            </button>
          </div>
        </div>
      </div>
      
      <!-- Footer / Pagination -->
      <div class="px-4 sm:px-6 py-3.5 sm:py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white">
        <span class="text-xs font-medium text-slate-500 text-center sm:text-left">Showing 1 to {{ filteredSubmissions.length }} of {{ filteredSubmissions.length }} academic years</span>
        <div class="flex items-center gap-1.5">
          <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 bg-white hover:bg-slate-50 hover:text-slate-600 transition-colors cursor-pointer min-h-[36px] min-w-[36px]">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path></svg>
          </button>
          <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-[#5138ed] text-white text-xs font-bold shadow-xs transition-colors min-h-[36px] min-w-[36px]">
            1
          </button>
          <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 bg-white hover:bg-slate-50 hover:text-slate-600 transition-colors cursor-pointer min-h-[36px] min-w-[36px]">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Additional component-specific styles if needed */
</style>
