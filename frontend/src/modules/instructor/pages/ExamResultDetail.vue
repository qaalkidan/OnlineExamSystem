<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useInstructorResultStore } from '../store/instructorResultStore'
import { useSemesterLockStore } from '../store/semesterLockStore'

const route = useRoute()
const router = useRouter()
const resultStore = useInstructorResultStore()
const lockStore = useSemesterLockStore()

const examId = Number(route.params.examId)

const isLoading = ref(true)

// Exam & Summary detail state from backend
const examDetail = ref<any>({
  id: examId,
  title: 'Exam Results',
  type: 'Exam',
  course_code: '',
  course_name: '',
  scheduled_at: new Date().toISOString(),
  duration_minutes: 0,
  total_marks: 0,
  total_students: 0,
  submitted_count: 0,
  submitted_pct: 0,
  is_published: false,
})

const summary = ref({
  total_students: 0,
  submitted: 0,
  submitted_pct: 0,
  graded: 0,
  graded_pct: 0,
  pending: 0,
  pending_pct: 0,
  absent: 0,
  absent_pct: 0,
})

const scoreDistribution = ref({
  A: 0,
  B: 0,
  C: 0,
  D: 0,
  F: 0,
})

// Filter & Tab state
const searchQuery = ref('')
const selectedStatus = ref('All Status')
const selectedGrade = ref('All Grades')
const selectedSection = ref('All Sections')
const activeTab = ref('all')

const students = ref<any[]>([])

const fetchExamDetails = async () => {
  const targetId = Number(route.params.examId) || examId
  isLoading.value = true
  try {
    const response = await apiClient.get(`/instructor/results/${targetId}`)
    const data = response.data?.data
    if (data) {
      if (data.exam) examDetail.value = data.exam
      if (data.summary) summary.value = data.summary
      if (data.score_distribution) scoreDistribution.value = data.score_distribution
      students.value = data.students || []
    }
  } catch (err) {
    console.warn('Backend API call failed:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await lockStore.fetchLockStatus()
  fetchExamDetails()
})

const tabs = computed(() => [
  { key: 'all', label: 'All Students', count: summary.value.total_students },
  { key: 'submitted', label: 'Submitted', count: summary.value.submitted },
  { key: 'graded', label: 'Graded', count: summary.value.graded },
  { key: 'pending', label: 'Pending Grading', count: summary.value.pending },
  { key: 'absent', label: 'Absent', count: summary.value.absent },
])

const filteredStudents = computed(() => {
  return students.value.filter(student => {
    // Tab filter
    if (activeTab.value === 'submitted' && student.status === 'Absent') return false
    if (activeTab.value === 'graded' && student.status !== 'Graded') return false
    if (activeTab.value === 'pending' && student.status !== 'Pending') return false
    if (activeTab.value === 'absent' && student.status !== 'Absent') return false

    // Search query filter
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase()
      const matchName = student.name?.toLowerCase().includes(q)
      const matchId = student.studentId?.toLowerCase().includes(q)
      if (!matchName && !matchId) return false
    }

    // Status dropdown filter
    if (selectedStatus.value !== 'All Status' && student.status !== selectedStatus.value) {
      return false
    }

    // Grade dropdown filter
    if (selectedGrade.value !== 'All Grades' && !student.grade?.startsWith(selectedGrade.value)) {
      return false
    }

    return true
  })
})

const getStatusClass = (status: string) => {
  if (status === 'Published') return 'text-emerald-600'
  if (status === 'Graded')    return 'text-blue-600'
  if (status === 'Pending')   return 'text-orange-500'
  if (status === 'Absent')    return 'text-rose-500'
  return 'text-slate-500'
}

// Selection & Publish state
const selectedStudentIds = ref<number[]>([])
const showPublishModal = ref<boolean>(false)

const toggleSelectStudent = (student: any) => {
  if (lockStore.isLocked) return
  if (student.status !== 'Graded') return
  const idx = selectedStudentIds.value.indexOf(student.id)
  if (idx > -1) {
    selectedStudentIds.value.splice(idx, 1)
  } else {
    selectedStudentIds.value.push(student.id)
  }
}

const isStudentSelected = (studentId: number) => {
  return selectedStudentIds.value.includes(studentId)
}

const isPublishing = ref(false)

const publishSelectedResults = async () => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('publish results')
    return
  }
  if (selectedStudentIds.value.length === 0) return
  
  isPublishing.value = true
  try {
    for (const studentId of selectedStudentIds.value) {
      await apiClient.post(`/instructor/results/${examId}/student/${studentId}/publish`)
    }
    // Refresh the data to show updated statuses
    await fetchExamDetails()
    showPublishModal.value = true
  } catch (error) {
    console.error('Failed to publish selected results:', error)
    alert('An error occurred while publishing results. Please try again.')
  } finally {
    isPublishing.value = false
  }
}

const resetFilters = () => {
  searchQuery.value = ''
  selectedStatus.value = 'All Status'
  selectedGrade.value = 'All Grades'
  selectedSection.value = 'All Sections'
  activeTab.value = 'all'
}
</script>

<template>
  <div class="max-w-[1500px] mx-auto">

    <!-- Breadcrumb & Actions -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-slate-800">Exam Results - Students</h1>
        <p class="text-[13px] text-slate-500 mt-1">View and manage student results for this exam.</p>
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-[12px] text-slate-400 mt-2">
          <router-link to="/instructor/results" class="hover:text-[#5138ed] transition-colors">Results Dashboard</router-link>
          <span>&gt;</span>
          <span class="text-slate-600 font-medium">{{ examDetail.course_code }}</span>
          <span>&gt;</span>
          <span class="text-slate-600 font-medium">Student Results</span>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button @click="router.push('/instructor/results')" class="flex items-center gap-2 px-4 py-2 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Results Dashboard
        </button>
        <button class="flex items-center gap-2 px-4 py-2 bg-[#5138ed] text-white text-sm font-semibold rounded-xl hover:bg-[#4530d1] transition-colors shadow-sm shadow-indigo-200">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
          Export Results
        </button>
      </div>
    </div>

    <!-- Exam Info Card -->
    <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm mb-6">
      <div class="flex items-start gap-5 mb-5">
        <div class="w-14 h-14 rounded-2xl bg-indigo-50 flex items-center justify-center text-[#5138ed] shrink-0">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </div>
        <div>
          <div class="flex items-center gap-3 mb-1">
            <h2 class="text-xl font-extrabold text-slate-800">{{ examDetail.title }}</h2>
            <span class="px-2.5 py-0.5 text-[10px] font-bold bg-indigo-50 text-[#5138ed] rounded-md">{{ examDetail.type }}</span>
          </div>
        </div>
      </div>

      <!-- Exam metadata row -->
      <div class="flex flex-wrap items-center gap-8 text-[12px]">
        <div class="flex flex-col"><span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Course</span><span class="text-slate-700 font-bold">{{ examDetail.course_name }}<template v-if="examDetail.course_code"> ({{ examDetail.course_code }})</template></span></div>
        <div class="flex flex-col"><span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Exam Type</span><span class="text-slate-700 font-bold">{{ examDetail.type }}</span></div>
        <div class="flex flex-col"><span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Date</span><span class="text-slate-700 font-bold">{{ new Date(examDetail.scheduled_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) }} — {{ new Date(examDetail.scheduled_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}</span></div>
        <div class="flex flex-col"><span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Duration</span><span class="text-slate-700 font-bold">{{ examDetail.duration_minutes }} min</span></div>
        <div class="flex flex-col"><span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Total Marks</span><span class="text-slate-700 font-bold">{{ examDetail.total_marks }}</span></div>
        <div class="flex flex-col"><span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Students</span><span class="text-slate-700 font-bold">{{ examDetail.total_students }}</span></div>
        <div class="flex flex-col"><span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Submitted</span><span class="text-emerald-600 font-bold">{{ examDetail.submitted_count }} ({{ examDetail.submitted_pct }}%)</span></div>
        <div class="flex flex-col">
          <span class="text-slate-400 font-bold text-[10px] uppercase mb-0.5">Published</span>
          
          <span v-if="examDetail.publish_status === 'Published'" class="text-emerald-600 font-bold flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg> Published
          </span>
          <span v-else-if="examDetail.publish_status === 'Graded'" class="text-blue-600 font-bold flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Graded
          </span>
          <span v-else-if="examDetail.publish_status === 'Pending'" class="text-orange-500 font-bold flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Pending
          </span>
          <span v-else class="text-rose-500 font-bold flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg> Not Published
          </span>
        </div>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="space-y-6">

      <!-- Top Section: Search, Filters, Tabs & Full-Width Students Table -->
      <div class="w-full">
        <!-- Search & Filters -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-4 p-4">
          <div class="flex items-center gap-3 flex-nowrap overflow-x-auto">
            <div class="relative min-w-[200px] flex-1">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
              </div>
              <input type="text" v-model="searchQuery" class="block w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl leading-5 bg-slate-50 placeholder-slate-400 focus:outline-none focus:bg-white focus:ring-2 focus:ring-[#5138ed]/20 focus:border-[#5138ed] text-sm transition-colors" placeholder="Search students by name or ID...">
            </div>
            <div class="relative shrink-0">
              <select v-model="selectedStatus" class="appearance-none border border-slate-200 rounded-xl text-sm pl-3 pr-8 py-2 text-slate-600 focus:outline-none focus:border-[#5138ed] bg-white w-[120px]">
                <option>All Status</option><option>Graded</option><option>Pending</option><option>Absent</option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>
            </div>
            <div class="relative shrink-0">
              <select v-model="selectedGrade" class="appearance-none border border-slate-200 rounded-xl text-sm pl-3 pr-8 py-2 text-slate-600 focus:outline-none focus:border-[#5138ed] bg-white w-[120px]">
                <option>All Grades</option><option>A</option><option>B</option><option>C</option><option>D</option><option>F</option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>
            </div>
            <div class="relative shrink-0">
              <select v-model="selectedSection" class="appearance-none border border-slate-200 rounded-xl text-sm pl-3 pr-8 py-2 text-slate-600 focus:outline-none focus:border-[#5138ed] bg-white w-[130px]">
                <option>All Sections</option><option>Section A</option><option>Section B</option>
              </select>
              <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></div>
            </div>
            <button class="flex items-center gap-1.5 px-4 py-2 border border-slate-200 text-[#5138ed] text-sm font-semibold rounded-xl hover:bg-indigo-50 transition-colors shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
              Filter
            </button>
            <button @click="resetFilters" class="flex items-center gap-1.5 px-4 py-2 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
              Reset
            </button>
          </div>
        </div>

        <!-- Tabs & Publish Action Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
          <div class="flex items-center gap-1 overflow-x-auto pb-1">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
              class="px-4 py-2 text-[12px] font-bold rounded-xl whitespace-nowrap transition-colors"
              :class="activeTab === tab.key ? 'bg-[#5138ed] text-white shadow-sm shadow-indigo-200' : 'text-slate-600 hover:bg-slate-100'">
              {{ tab.label }} ({{ tab.count }})
            </button>
          </div>

          <!-- Publish Selected Results Button / Locked Indicator -->
          <div v-if="lockStore.isLocked" class="px-4 py-2 bg-amber-50 border border-amber-200 text-amber-700 rounded-xl text-[12px] font-bold flex items-center gap-2 shrink-0">
            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            <span>Semester Locked (Read-Only)</span>
          </div>
          <button
            v-else
            @click="publishSelectedResults"
            :disabled="selectedStudentIds.length === 0 || isPublishing"
            :class="[
              'px-4 py-2 rounded-xl text-[12px] font-bold flex items-center gap-2 transition-all shrink-0',
              selectedStudentIds.length > 0 && !isPublishing
                ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm cursor-pointer'
                : 'bg-slate-200 text-slate-400 cursor-not-allowed opacity-70'
            ]"
            :title="selectedStudentIds.length === 0 ? 'Select at least one graded student to publish' : 'Publish selected results'"
          >
            <svg v-if="isPublishing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
            </svg>
            <span v-if="isPublishing">Publishing...</span>
            <span v-else>Publish Selected Results {{ selectedStudentIds.length > 0 ? `(${selectedStudentIds.length})` : '' }}</span>
          </button>
        </div>

        <!-- Students Table -->
        <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
              <thead>
                <tr class="text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                  <th class="py-3 pl-4 pr-2 font-semibold">#</th>
                  <th class="py-3 px-3 font-semibold">Student ID</th>
                  <th class="py-3 px-3 font-semibold">Student Name</th>
                  <th class="py-3 px-3 font-semibold">Section</th>
                  <th class="py-3 px-3 font-semibold">Submitted On</th>
                  <th class="py-3 px-3 font-semibold text-center">Auto Score<br><span class="text-[8px] text-slate-300 normal-case">(MCQ)</span></th>
                  <th class="py-3 px-3 font-semibold text-center">Manual Score<br><span class="text-[8px] text-slate-300 normal-case">(Subjective)</span></th>
                  <th class="py-3 px-3 font-semibold text-center">Final Score<br><span class="text-[8px] text-slate-300 normal-case">(Out of {{ examDetail.total_marks }})</span></th>
                  <th class="py-3 px-3 font-semibold text-center">Status</th>
                  <th class="py-3 px-3 font-semibold text-center">Actions</th>
                  <th class="py-3 px-3 font-semibold text-center">Select</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(student, index) in filteredStudents" :key="student.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0">
                  <td class="py-3 pl-4 pr-2 text-[12px] font-bold text-slate-500">{{ index + 1 }}</td>
                  <td class="py-3 px-3 text-[12px] font-medium text-slate-600">{{ student.studentId }}</td>
                  <td class="py-3 px-3 text-[12px] font-bold text-slate-800">{{ student.name }}</td>
                  <td class="py-3 px-3 text-[12px] font-medium text-slate-600">{{ student.section }}</td>
                  <td class="py-3 px-3">
                    <div class="flex flex-col">
                      <span class="text-[12px] font-medium text-slate-700">{{ new Date(student.submittedOn).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) }}</span>
                      <span class="text-[10px] text-slate-400">{{ new Date(student.submittedOn).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}</span>
                    </div>
                  </td>
                  <td class="py-3 px-3 text-[12px] font-bold text-slate-700 text-center">{{ student.autoScore }} / {{ student.autoTotal }}</td>
                  <td class="py-3 px-3 text-[12px] font-bold text-slate-700 text-center">{{ student.manualScore }} / {{ student.manualTotal }}</td>
                  <td class="py-3 px-3 text-[12px] font-bold text-slate-800 text-center">{{ student.finalScore }} / {{ student.totalMarks }}</td>
                  <td class="py-3 px-3 text-center"><span class="text-[11px] font-bold" :class="getStatusClass(student.status)">{{ student.status }}</span></td>
                  <td class="py-3 px-3 text-center">
                    <div class="flex items-center justify-center gap-1 text-slate-400">
                      <button @click="router.push({ name: 'InstructorStudentResultDetail', params: { examId: route.params.examId, studentId: student.id } })" class="p-1 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="View"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg></button>
                      <button v-if="!lockStore.isLocked" class="p-1 hover:text-blue-500 hover:bg-blue-50 rounded-lg transition-colors" title="Edit Grade"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                      <span v-else class="text-[10px] text-slate-400 px-1 py-0.5 bg-slate-100 rounded">Locked</span>
                    </div>
                  </td>
                  <!-- Select Circle Column (Graded Only) -->
                  <td class="py-3 px-3 text-center">
                    <button
                      @click="toggleSelectStudent(student)"
                      :disabled="lockStore.isLocked || student.status !== 'Graded'"
                      :class="[
                        'inline-flex items-center justify-center transition-all focus:outline-none',
                        !lockStore.isLocked && student.status === 'Graded' ? 'cursor-pointer' : 'cursor-not-allowed opacity-40'
                      ]"
                      :title="lockStore.isLocked ? 'Publishing is disabled (Semester Locked)' : (student.status === 'Graded' ? (isStudentSelected(student.id) ? 'Deselect student' : 'Select student for publishing') : 'Only graded students can be selected for publishing')"
                    >
                      <!-- Selected Checked Circle -->
                      <div
                        v-if="isStudentSelected(student.id)"
                        class="w-5 h-5 rounded-full bg-[#5138ed] border-2 border-[#5138ed] flex items-center justify-center text-white shadow-xs"
                      >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                      </div>

                      <!-- Unselected Graded Circle -->
                      <div
                        v-else-if="student.status === 'Graded'"
                        class="w-5 h-5 rounded-full border-2 border-slate-300 hover:border-[#5138ed] bg-white transition-colors"
                      ></div>

                      <!-- Ungraded / Absent Disabled Circle -->
                      <div
                        v-else
                        class="w-5 h-5 rounded-full border-2 border-slate-200 bg-slate-100"
                      ></div>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="p-4 border-t border-slate-100 flex items-center justify-between">
            <span class="text-[12px] text-slate-500 font-medium">Showing 1 to {{ filteredStudents.length }} of {{ summary.total_students }} students</span>
            <div class="flex items-center gap-1">
              <button class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-400 disabled:opacity-50" disabled><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-[11px] shadow-sm">1</button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-50 text-slate-600 font-bold text-[11px]">2</button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-50 text-slate-600 font-bold text-[11px]">3</button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 text-[11px]">…</button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-slate-50 text-slate-600 font-bold text-[11px]">13</button>
              <button class="w-7 h-7 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></button>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Section: 3-Column Grid for Exam Summary, Score Distribution & Quick Actions/Info Banner -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        <!-- 1. Exam Summary Card -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm h-full flex flex-col justify-between">
          <h3 class="text-[13px] font-bold text-slate-800 mb-3">Exam Summary</h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 font-medium">Total Students</span><span class="text-[12px] font-bold text-slate-800">{{ summary.total_students }}</span></div>
            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 font-medium">Submitted</span><span class="text-[12px] font-bold text-[#5138ed]">{{ summary.submitted }} ({{ summary.submitted_pct }}%)</span></div>
            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 font-medium">Graded</span><span class="text-[12px] font-bold text-[#5138ed]">{{ summary.graded }} ({{ summary.graded_pct }}%)</span></div>
            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 font-medium">Pending Grading</span><span class="text-[12px] font-bold text-[#5138ed]">{{ summary.pending }} ({{ summary.pending_pct }}%)</span></div>
            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 font-medium">Absent</span><span class="text-[12px] font-bold text-[#5138ed]">{{ summary.absent }} ({{ summary.absent_pct }}%)</span></div>
          </div>
        </div>

        <!-- 2. Score Distribution (Final Score) Card -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm h-full flex flex-col justify-between">
          <h3 class="text-[13px] font-bold text-slate-800 mb-3">Score Distribution (Final Score)</h3>
          <div class="flex flex-col items-center mb-3">
            <div class="relative w-28 h-28 mb-2">
              <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="40" fill="none" stroke="#f1f5f9" stroke-width="12"></circle>
                <circle cx="50" cy="50" r="40" fill="none" stroke="#10b981" stroke-width="12" stroke-dasharray="56.6 251.3" stroke-dashoffset="0"></circle>
                <circle cx="50" cy="50" r="40" fill="none" stroke="#3b82f6" stroke-width="12" stroke-dasharray="72.4 251.3" stroke-dashoffset="-56.6"></circle>
                <circle cx="50" cy="50" r="40" fill="none" stroke="#f59e0b" stroke-width="12" stroke-dasharray="62.3 251.3" stroke-dashoffset="-129"></circle>
                <circle cx="50" cy="50" r="40" fill="none" stroke="#fb923c" stroke-width="12" stroke-dasharray="36.2 251.3" stroke-dashoffset="-191.3"></circle>
                <circle cx="50" cy="50" r="40" fill="none" stroke="#ef4444" stroke-width="12" stroke-dasharray="24.1 251.3" stroke-dashoffset="-227.5"></circle>
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-[18px] font-black text-slate-800 leading-none">{{ summary.total_students }}</span>
                <span class="text-[8px] font-bold text-slate-400 mt-0.5">Students</span>
              </div>
            </div>
          </div>
          <div class="space-y-1.5">
            <div class="flex items-center justify-between"><div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-emerald-500"></div><span class="text-[10px] text-slate-600">A (80-100%)</span></div><span class="text-[10px] font-bold text-slate-700">{{ scoreDistribution.A }} ({{ (scoreDistribution.A / summary.total_students * 100).toFixed(1) }}%)</span></div>
            <div class="flex items-center justify-between"><div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div><span class="text-[10px] text-slate-600">B (70-79%)</span></div><span class="text-[10px] font-bold text-slate-700">{{ scoreDistribution.B }} ({{ (scoreDistribution.B / summary.total_students * 100).toFixed(1) }}%)</span></div>
            <div class="flex items-center justify-between"><div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-500"></div><span class="text-[10px] text-slate-600">C (60-69%)</span></div><span class="text-[10px] font-bold text-slate-700">{{ scoreDistribution.C }} ({{ (scoreDistribution.C / summary.total_students * 100).toFixed(1) }}%)</span></div>
            <div class="flex items-center justify-between"><div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-orange-400"></div><span class="text-[10px] text-slate-600">D (50-59%)</span></div><span class="text-[10px] font-bold text-slate-700">{{ scoreDistribution.D }} ({{ (scoreDistribution.D / summary.total_students * 100).toFixed(1) }}%)</span></div>
            <div class="flex items-center justify-between"><div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-rose-500"></div><span class="text-[10px] text-slate-600">F (0-49%)</span></div><span class="text-[10px] font-bold text-slate-700">{{ scoreDistribution.F }} ({{ (scoreDistribution.F / summary.total_students * 100).toFixed(1) }}%)</span></div>
          </div>
        </div>

        <!-- 3. Quick Actions & Info Banner Card -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm h-full flex flex-col justify-between space-y-4">
          <div>
            <h3 class="text-[13px] font-bold text-slate-800 mb-3">Quick Actions</h3>
            <div class="space-y-2">
              <button v-if="!lockStore.isLocked" class="w-full flex items-center gap-2 group">
                <div class="w-6 h-6 rounded-md bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg></div>
                <span class="text-[11px] font-bold text-[#5138ed] group-hover:underline">Continue Grading</span>
              </button>
              <button class="w-full flex items-center gap-2 group">
                <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-500 flex items-center justify-center shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></div>
                <span class="text-[11px] font-bold text-[#5138ed] group-hover:underline">View Pending Students</span>
              </button>
              <button v-if="!lockStore.isLocked" @click="publishSelectedResults" class="w-full flex items-center gap-2 group">
                <div class="w-6 h-6 rounded-md bg-orange-50 text-orange-500 flex items-center justify-center shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg></div>
                <span class="text-[11px] font-bold text-[#5138ed] group-hover:underline">Publish Results</span>
              </button>
              <div v-else class="flex items-center gap-2 py-1 px-2 bg-amber-50 border border-amber-100 rounded-lg text-amber-700 text-[11px] font-bold">
                <svg class="w-3.5 h-3.5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                <span>Grading & Publishing Locked</span>
              </div>
              <button class="w-full flex items-center gap-2 group">
                <div class="w-6 h-6 rounded-md bg-blue-50 text-blue-500 flex items-center justify-center shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg></div>
                <span class="text-[11px] font-bold text-[#5138ed] group-hover:underline">Download Results (Excel)</span>
              </button>
              <button class="w-full flex items-center gap-2 group">
                <div class="w-6 h-6 rounded-md bg-rose-50 text-rose-500 flex items-center justify-center shrink-0"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg></div>
                <span class="text-[11px] font-bold text-[#5138ed] group-hover:underline">Print Result Summary</span>
              </button>
            </div>
          </div>

          <!-- Info Banner -->
          <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 flex items-start gap-2.5">
            <div class="w-6 h-6 rounded-lg bg-white flex items-center justify-center text-blue-500 shrink-0 shadow-xs">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <p class="text-[10px] text-blue-700 font-medium leading-relaxed">Results will be visible to students only after publishing.</p>
          </div>
        </div>

      </div>

    </div>

    <!-- Publish Success Modal -->
    <div v-if="showPublishModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-[480px] relative overflow-hidden flex flex-col items-center p-8 border border-slate-100">
        <button @click="showPublishModal = false" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-600 mb-4 shadow-sm border-4 border-white">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
          </svg>
        </div>

        <h3 class="text-xl font-extrabold text-slate-800 mb-2">Results Published!</h3>
        <p class="text-xs text-slate-500 font-medium mb-6 text-center">
          Successfully published examination results for <span class="font-bold text-slate-800">{{ selectedStudentIds.length }} graded student(s)</span>.
        </p>

        <button @click="selectedStudentIds = []; showPublishModal = false" class="w-full py-3 bg-[#5138ed] text-white text-xs font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors">
          Got It
        </button>
      </div>
    </div>

  </div>
</template>
