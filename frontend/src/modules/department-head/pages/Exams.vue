<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import apiClient from '../../../core/api/apiClient'

// ── State ──
const search = ref('')
const semesterFilter = ref('all')
const yearFilter = ref('all')
const examTypeFilter = ref('all')
const statusFilter = ref('all')
const currentPage = ref(1)
const perPage = 10
const isLoading = ref(false)
const isLoadingDetail = ref(false)

const currentView = ref<'list' | 'detail' | 'questions'>('list')
const selectedExam = ref<any>(null)

const allExams = ref<any[]>([])
const availableSemesters = ref<string[]>([])
const availableYears = ref<string[]>([])
const availableTypes = ref<string[]>([])

const statsData = ref({
  total: 0,
  total_change: '↑ 5 this semester',
  upcoming: 0,
  upcoming_change: '↑ 3 this week',
  completed: 0,
  completed_change: '↑ 7 this semester',
  cancelled: 0,
  cancelled_change: 'No change',
})

// Today's Date for Header
const todayFormatted = computed(() => {
  return new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
})
const dayName = computed(() => {
  return new Date().toLocaleDateString('en-US', { weekday: 'long' })
})

// ── Fetch Exams ──
const fetchExams = async () => {
  isLoading.value = true
  try {
    const res = await apiClient.get('/dept-head/exams')
    allExams.value = res.data?.data || []
    if (res.data?.stats) {
      statsData.value = { ...statsData.value, ...res.data.stats }
    }
    if (res.data?.semesters) {
      availableSemesters.value = res.data.semesters
    }
    if (res.data?.years) {
      availableYears.value = res.data.years
    }
    if (res.data?.exam_types) {
      availableTypes.value = res.data.exam_types
    }
  } catch (err) {
    console.error('Failed to fetch department exams:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchExams()
})

const openDetail = async (exam: any) => {
  selectedExam.value = exam
  currentView.value = 'detail'
  isLoadingDetail.value = true
  try {
    const res = await apiClient.get(`/dept-head/exams/${exam.id}`)
    if (res.data?.data) {
      selectedExam.value = res.data.data
    }
  } catch (err) {
    console.error('Failed to load exam details:', err)
  } finally {
    isLoadingDetail.value = false
  }
}

const backToList = () => {
  currentView.value = 'list'
}

const backToDetail = () => {
  currentView.value = 'detail'
}

// ── Filtered & Paginated ──
const filtered = computed(() => {
  return allExams.value.filter(e => {
    const q = search.value.trim().toLowerCase()
    const matchSearch = !q ||
      (e.title && e.title.toLowerCase().includes(q)) ||
      (e.code && e.code.toLowerCase().includes(q)) ||
      (e.courseName && e.courseName.toLowerCase().includes(q)) ||
      (e.courseCode && e.courseCode.toLowerCase().includes(q)) ||
      (e.instructor_name && e.instructor_name.toLowerCase().includes(q))

    const matchSemester = semesterFilter.value === 'all' || e.semester === semesterFilter.value
    const matchYear = yearFilter.value === 'all' || e.year === yearFilter.value
    const matchType = examTypeFilter.value === 'all' || (e.type && e.type.toLowerCase() === examTypeFilter.value.toLowerCase())
    const matchStatus = statusFilter.value === 'all' ||
      (e.status && e.status.toLowerCase() === statusFilter.value.toLowerCase()) ||
      (e.raw_status && e.raw_status.toLowerCase() === statusFilter.value.toLowerCase())

    return matchSearch && matchSemester && matchYear && matchType && matchStatus
  })
})

watch([search, semesterFilter, yearFilter, examTypeFilter, statusFilter], () => {
  currentPage.value = 1
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)))
const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

const displayPages = computed(() => {
  const tp = totalPages.value
  if (tp <= 7) return Array.from({ length: tp }, (_, i) => i + 1)
  if (currentPage.value <= 4) return [1, 2, 3, 4, 5, '...', tp]
  if (currentPage.value >= tp - 3) return [1, '...', tp - 4, tp - 3, tp - 2, tp - 1, tp]
  return [1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', tp]
})

// ── Stats Cards ──
const stats = computed(() => [
  {
    label: 'Total Exams',
    value: String(statsData.value.total),
    change: statsData.value.total_change || '↑ 5 this semester',
    bg: 'bg-indigo-50',
    ic: 'text-[#5138ed]',
    color: 'text-emerald-500',
    icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'
  },
  {
    label: 'Upcoming Exams',
    value: String(statsData.value.upcoming),
    change: statsData.value.upcoming_change || '↑ 3 this week',
    bg: 'bg-emerald-50',
    ic: 'text-emerald-500',
    color: 'text-emerald-500',
    icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
  },
  {
    label: 'Completed Exams',
    value: String(statsData.value.completed),
    change: statsData.value.completed_change || '↑ 7 this semester',
    bg: 'bg-sky-50',
    ic: 'text-sky-500',
    color: 'text-emerald-500',
    icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
  },
  {
    label: 'Cancelled Exams',
    value: String(statsData.value.cancelled),
    change: statsData.value.cancelled_change || 'No change',
    bg: 'bg-rose-50',
    ic: 'text-rose-500',
    color: 'text-slate-500',
    icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'
  },
])

// ── Badges ──
const typeBadge = (t: string) => {
  const type = (t || '').toLowerCase()
  if (type === 'midterm') return 'bg-indigo-50 text-[#5138ed]'
  if (type === 'final') return 'bg-amber-50 text-amber-500'
  if (type === 'quiz') return 'bg-emerald-50 text-emerald-500'
  return 'bg-purple-50 text-purple-600'
}

const statusBadge = (s: string) => {
  const status = (s || '').toLowerCase()
  if (status === 'scheduled') return 'bg-sky-50 text-sky-500'
  if (status === 'published') return 'bg-emerald-50 text-emerald-500'
  if (status === 'completed') return 'bg-slate-100 text-slate-500'
  if (status === 'cancelled' || status === 'canceled') return 'bg-rose-50 text-rose-500'
  if (status === 'draft') return 'bg-amber-50 text-amber-500'
  return 'bg-sky-50 text-sky-500'
}

const qTypeBadge = (t: string) => {
  const type = (t || '').toLowerCase()
  if (type === 'mcq' || type === 'multiple_choice' || type === 'multiple choice') return 'text-sky-500 bg-sky-50'
  if (type === 'true/false' || type === 'true_false' || type === 'true false') return 'text-emerald-500 bg-emerald-50'
  if (type === 'short answer' || type === 'short_answer') return 'text-amber-500 bg-amber-50'
  if (type === 'matching') return 'text-sky-600 bg-sky-50'
  if (type === 'fill in the blanks' || type === 'fill_in_the_blank' || type === 'fill_in_blank') return 'text-[#5138ed] bg-indigo-50'
  return 'text-slate-600 bg-slate-100'
}

const isOptionCorrect = (opt: any, optIdx: number, q: any) => {
  if (typeof opt === 'object' && opt && opt.is_correct) return true
  const letter = String.fromCharCode(65 + optIdx)
  if (q.correct_answer === letter) return true
  const text = typeof opt === 'string' ? opt : (opt.text || '')
  if (q.correct_answer && q.correct_answer === text) return true
  return false
}

// ── Export Results / Print ──
const exportResults = () => {
  window.print()
}
</script>

<template>
  <div class="space-y-6">

    <!-- ══════════════════════════ EXAM DETAILS VIEW ══════════════════════════ -->
    <template v-if="currentView === 'detail'">
      <!-- Top header with back button -->
      <div class="flex items-center justify-between mb-4 sm:mb-6">
        <div class="flex items-center gap-3">
          <button @click="backToList" class="w-9 h-9 min-h-[36px] rounded-lg flex items-center justify-center border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-all shrink-0" title="Back to Exams">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
          </button>
          <h1 class="text-lg sm:text-[20px] font-bold text-slate-800">Exam Details</h1>
        </div>
      </div>

      <div class="flex flex-col xl:flex-row items-start gap-6">
        <!-- Main Content (Left) -->
        <div class="flex-1 w-full space-y-6 min-w-0">
          
          <!-- Exam Title Card -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
              <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <h2 class="text-lg sm:text-[20px] font-bold text-slate-800">{{ selectedExam?.title }}</h2>
                <span :class="[statusBadge(selectedExam?.status), 'text-[11px] font-bold px-2.5 py-1 rounded-md capitalize']">{{ selectedExam?.status }}</span>
                <span class="text-[12px] font-semibold text-[#5138ed] bg-indigo-50 px-2.5 py-1 rounded-md">{{ selectedExam?.code }}</span>
              </div>
              <button @click="backToList" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Exam List
              </button>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6 pt-6 border-t border-slate-100">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Course</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.courseName }} <span class="text-slate-400 font-medium ml-1">({{ selectedExam?.courseCode }})</span></p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Exam Date</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.date }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Total Questions</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.questions }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Instructor</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.instructorName || 'Dr. Abebe Kebede' }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Total Marks</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.marks }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Start Time</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.time }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Exam Type</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.type }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">Duration</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.duration }}</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <div>
                  <p class="text-[11px] font-bold text-slate-500 mb-0.5">End Time</p>
                  <p class="text-[13px] font-semibold text-slate-800">{{ selectedExam?.endTime || 'TBD' }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Exam Behavior -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
              <h3 class="text-[16px] font-bold text-slate-800">Exam Behavior</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-5 sm:gap-y-6 gap-x-6 sm:gap-x-12">
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.shuffleQuestions ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.shuffleQuestions ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Shuffle Questions</p>
                  <p class="text-[11px] text-slate-500">Randomize the order of questions for each student</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.examReviewGroup ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.examReviewGroup ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Exam Review Group</p>
                  <p class="text-[11px] text-slate-500">Allow students to review answers before submission</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.shuffleAnswers ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.shuffleAnswers ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Shuffle Answer Options</p>
                  <p class="text-[11px] text-slate-500">Randomize the order of answer options</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.allowBacktracking ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.allowBacktracking ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Allow Backtracking</p>
                  <p class="text-[11px] text-slate-500">Students can go back to previous questions</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.showOneQuestionAtATime ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.showOneQuestionAtATime ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Show One Question at a Time</p>
                  <p class="text-[11px] text-slate-500">Students see one question at a time</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.autoSubmit ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.autoSubmit ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Auto Submit on Time Finish</p>
                  <p class="text-[11px] text-slate-500">Automatically submit when time is up</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Security Settings -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
              <svg class="w-5 h-5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
              <h3 class="text-[16px] font-bold text-slate-800">Security Settings</h3>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-y-5 sm:gap-y-6 gap-x-6 sm:gap-x-12">
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.enableFullscreenMode ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.enableFullscreenMode ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Enable Fullscreen Mode</p>
                  <p class="text-[11px] text-slate-500">Prevent students from leaving the exam screen</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.enableBrowserTabMonitoring ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.enableBrowserTabMonitoring ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Enable Browser Tab Monitoring</p>
                  <p class="text-[11px] text-slate-500">Detect if student switches tab or window</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.disableRightClick ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.disableRightClick ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Disable Right Click</p>
                  <p class="text-[11px] text-slate-500">Prevent right click on the exam screen</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.allowCalculator ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.allowCalculator ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Allow Calculator</p>
                  <p class="text-[11px] text-slate-500">Provide an on-screen calculator for students</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.disableCopyPaste ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.disableCopyPaste ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Disable Copy & Paste</p>
                  <p class="text-[11px] text-slate-500">Prevent copy and paste operations</p>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div :class="[selectedExam?.settings?.webcamMonitoring ? 'bg-[#5138ed]' : 'bg-slate-200', 'w-10 h-6 rounded-full relative shrink-0 transition-colors']">
                  <div :class="[selectedExam?.settings?.webcamMonitoring ? 'right-1' : 'left-1', 'w-4 h-4 bg-white rounded-full absolute top-1 transition-all']"></div>
                </div>
                <div>
                  <p class="text-[13px] font-bold text-slate-700">Webcam Monitoring</p>
                  <p class="text-[11px] text-slate-500">Record or monitor exam using webcam</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Created Exam (Questions Preview) -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
              <div>
                <h3 class="text-[15px] font-bold text-slate-800">Created Exam (Questions Preview)</h3>
                <p class="text-[12px] text-slate-500">Total Questions: {{ selectedExam?.questionsList?.length || selectedExam?.questions || 0 }} | Total Marks: {{ selectedExam?.marks || 0 }}</p>
              </div>
              <button @click="currentView = 'questions'" class="text-[12px] font-bold text-[#5138ed] hover:text-indigo-700 self-start sm:self-auto py-1">View All Questions</button>
            </div>
            
            <div class="overflow-x-auto min-w-0 w-full">
              <table class="w-full">
                <thead>
                  <tr class="border-b border-slate-100">
                    <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider w-16">#</th>
                    <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider w-40">Question Type</th>
                    <th class="text-left px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Question</th>
                    <th class="text-center px-4 py-3 text-[11px] font-bold text-slate-400 uppercase tracking-wider w-24">Marks</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                  <tr v-if="!selectedExam?.questionsList || selectedExam.questionsList.length === 0">
                    <td colspan="4" class="px-4 py-8 text-center text-[13px] text-slate-400">
                      No questions added to this exam yet.
                    </td>
                  </tr>
                  <tr v-for="(q, idx) in (selectedExam?.questionsList || []).slice(0, 4)" :key="q.id || idx" class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-4 text-[13px] font-bold text-slate-600">{{ Number(idx) + 1 }}</td>
                    <td class="px-4 py-4"><span :class="[qTypeBadge(q.type), 'text-[12px] font-bold px-2 py-1 rounded']">{{ q.type }}</span></td>
                    <td class="px-4 py-4 text-[13px] text-slate-700 min-w-[200px]">{{ q.text }}</td>
                    <td class="px-4 py-4 text-[13px] font-bold text-slate-600 text-center">{{ q.marks }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div v-if="(selectedExam?.questionsList?.length || 0) > 4" @click="currentView = 'questions'" class="mt-4 border border-slate-100 rounded-xl py-3 flex justify-center hover:bg-slate-50 cursor-pointer transition-colors min-h-[44px] items-center">
              <span class="text-[12px] font-bold text-[#5138ed]">Show More ({{ (selectedExam?.questionsList?.length || 0) - 4 }} Questions)</span>
            </div>
          </div>
        </div>

        <!-- Right Sidebar -->
        <div class="w-full xl:w-[320px] shrink-0 space-y-6">
          
          <!-- Exam Summary -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
            <h3 class="text-[15px] font-bold text-slate-800 mb-5">Exam Summary</h3>
            
            <div class="flex gap-4 mb-6">
              <div class="w-12 h-12 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
              </div>
              <div class="min-w-0">
                <h4 class="text-[14px] font-bold text-slate-800 leading-tight truncate">{{ selectedExam?.title }}</h4>
                <p class="text-[11px] font-medium text-slate-400 mt-1">{{ selectedExam?.code }}</p>
              </div>
            </div>

            <div class="space-y-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  Status
                </div>
                <span :class="[statusBadge(selectedExam?.status), 'text-[11px] font-bold px-2 py-0.5 rounded capitalize']">{{ selectedExam?.status }}</span>
              </div>
              
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                  Exam Type
                </div>
                <span class="text-[12px] font-bold text-[#5138ed]">{{ selectedExam?.type }}</span>
              </div>
              
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                  Created By
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ selectedExam?.createdByName || 'Super Admin' }}</span>
              </div>
              
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                  Created Date
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ selectedExam?.createdAtFormatted || 'May 10, 2026 10:30 AM' }}</span>
              </div>

              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                  Last Updated
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ selectedExam?.updatedAtFormatted || 'May 15, 2026 02:15 PM' }}</span>
              </div>

              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-[12px] text-slate-500 font-medium">
                  <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                  Total Attempts
                </div>
                <span class="text-[12px] font-bold text-slate-700">{{ selectedExam?.totalAttempts || 0 }}</span>
              </div>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white rounded-2xl p-4 sm:p-6 border border-slate-100 shadow-sm">
            <h3 class="text-[15px] font-bold text-slate-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-2 gap-3">
              <button @click="currentView = 'questions'" class="min-h-[44px] flex flex-col items-center justify-center gap-2 py-3 rounded-xl border border-slate-200 hover:border-indigo-200 hover:bg-indigo-50 text-slate-600 hover:text-[#5138ed] transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                <span class="text-[11px] font-bold">Preview Exam</span>
              </button>
              <button @click="exportResults" class="min-h-[44px] flex flex-col items-center justify-center gap-2 py-3 rounded-xl border border-slate-200 hover:border-emerald-200 hover:bg-emerald-50 text-slate-600 hover:text-emerald-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                <span class="text-[11px] font-bold">Export Results</span>
              </button>
            </div>
          </div>
          
        </div>
      </div>
    </template>

    <!-- ══════════════════════════ ALL QUESTIONS VIEW ══════════════════════════ -->
    <template v-else-if="currentView === 'questions'">
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3 sm:gap-4">
          <div class="w-12 h-12 rounded-xl bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
          </div>
          <div>
            <h1 class="text-lg sm:text-[20px] font-bold text-slate-800">All Questions</h1>
            <p class="text-[13px] text-slate-500 mt-0.5">View all questions in this exam.</p>
          </div>
        </div>
      </div>
      
      <!-- Breadcrumb -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 text-[12px] sm:text-[13px] font-medium text-slate-500">
          <span class="hover:text-slate-800 cursor-pointer" @click="currentView = 'list'">Exams</span>
          <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <span class="hover:text-slate-800 cursor-pointer" @click="currentView = 'list'">Exam List</span>
          <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <span class="hover:text-slate-800 cursor-pointer" @click="backToDetail">Exam Details</span>
          <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
          <span class="text-slate-800 font-bold">All Questions</span>
        </div>
        <button @click="backToDetail" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 px-4 py-2 text-[13px] font-bold text-slate-600 border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm bg-white">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
          Back to Exam Details
        </button>
      </div>

      <!-- Content Card -->
      <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 sm:p-6 lg:p-8">
        <div class="flex items-center gap-4 text-[14px] sm:text-[15px] font-bold text-slate-800 mb-6 sm:mb-8 pb-4 border-b border-slate-100">
          <span>Total Questions: {{ selectedExam?.questionsList?.length || selectedExam?.questions || 0 }}</span>
          <div class="w-px h-4 bg-slate-300"></div>
          <span>Total Marks: {{ selectedExam?.marks || 0 }}</span>
        </div>

        <div v-if="!selectedExam?.questionsList || selectedExam.questionsList.length === 0" class="py-12 text-center text-slate-400">
          No questions available to display for this exam.
        </div>

        <div v-else class="space-y-5 sm:space-y-6">
          <div v-for="(q, idx) in selectedExam.questionsList" :key="q.id || idx" class="border border-slate-100 rounded-xl p-4 sm:p-6">
            <div class="flex items-center justify-between mb-4">
              <div class="flex items-center gap-2 sm:gap-3">
                <span class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] font-bold text-[13px] flex items-center justify-center">Q{{ Number(idx) + 1 }}</span>
                <span :class="[qTypeBadge(q.full_type || q.type), 'text-[11px] sm:text-[12px] font-bold px-2.5 py-1.5 rounded-lg']">{{ q.full_type || q.type }}</span>
              </div>
              <span class="text-[12px] font-bold text-[#5138ed] bg-indigo-50 px-2.5 py-1.5 rounded-lg">{{ q.marks }} Marks</span>
            </div>
            
            <p class="text-[12px] font-bold text-slate-500 mb-2">Question</p>
            <p class="text-[14px] font-bold text-slate-800 mb-5">{{ q.text }}</p>
            
            <!-- Options if MCQ -->
            <template v-if="q.options && q.options.length">
              <p class="text-[12px] font-bold text-slate-500 mb-3">Options</p>
              <div class="space-y-3 mb-6">
                <div v-for="(opt, optIdx) in q.options" :key="optIdx" class="flex items-center gap-3">
                  <div :class="[isOptionCorrect(opt, Number(optIdx), q) ? 'border-[5px] border-[#5138ed]' : 'border border-slate-300', 'w-4 h-4 rounded-full bg-white shrink-0']"></div>
                  <span :class="[isOptionCorrect(opt, Number(optIdx), q) ? 'font-bold text-slate-800' : 'font-medium text-slate-600', 'text-[13px]']">
                    {{ String.fromCharCode(65 + Number(optIdx)) }}. {{ typeof opt === 'string' ? opt : (opt.text || opt.clause || opt.desc || JSON.stringify(opt)) }}
                  </span>
                </div>
              </div>
            </template>

            <!-- True / False -->
            <template v-else-if="q.raw_type === 'true_false'">
              <p class="text-[12px] font-bold text-slate-500 mb-3">Answer</p>
              <div class="flex items-center gap-8 mb-6">
                <div class="flex items-center gap-3">
                  <div :class="[String(q.correct_answer).toLowerCase() === 'true' ? 'border-[5px] border-[#5138ed]' : 'border border-slate-300', 'w-4 h-4 rounded-full bg-white shrink-0']"></div>
                  <span class="text-[13px] font-bold text-slate-800">True</span>
                </div>
                <div class="flex items-center gap-3">
                  <div :class="[String(q.correct_answer).toLowerCase() === 'false' ? 'border-[5px] border-[#5138ed]' : 'border border-slate-300', 'w-4 h-4 rounded-full bg-white shrink-0']"></div>
                  <span class="text-[13px] font-bold text-slate-800">False</span>
                </div>
              </div>
            </template>

            <!-- Expected answer for short answer / fill in blank -->
            <template v-else-if="q.raw_type === 'fill_in_the_blank' || q.raw_type === 'fill_in_blank'">
              <div class="flex items-center gap-2 mb-4">
                <p class="text-[13px] font-medium text-slate-600">Expected Answer:</p>
                <span class="inline-block text-[12px] font-bold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg">{{ q.expected_answer || q.correct_answer || 'N/A' }}</span>
              </div>
            </template>

            <template v-else-if="q.raw_type === 'short_answer'">
              <div v-if="q.correct_answer" class="mb-4">
                <span class="inline-block text-[12px] font-bold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg">Expected Answer: {{ q.correct_answer }}</span>
              </div>
            </template>
            
            <div v-if="q.instruction" class="pt-2">
              <p class="text-[12px] font-bold text-slate-500 mb-1">Instruction</p>
              <p class="text-[13px] text-slate-600">{{ q.instruction }}</p>
            </div>
          </div>
        </div>

        <div class="text-center mt-8 sm:mt-10">
          <p class="text-[13px] text-slate-500 font-medium">
            Showing 1 to {{ selectedExam?.questionsList?.length || 0 }} of {{ selectedExam?.questionsList?.length || selectedExam?.questions || 0 }} questions
          </p>
        </div>
      </div>
    </template>

    <!-- ══════════════════════════ LIST VIEW ══════════════════════════ -->
    <template v-else>
      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h1 class="text-xl sm:text-[22px] font-bold text-slate-800">Exams</h1>
          <p class="text-[13px] text-slate-500 mt-0.5 sm:mt-1">Manage and monitor department exams.</p>
        </div>
        <div class="flex sm:flex-col items-center sm:items-end justify-between border-t sm:border-t-0 pt-2 sm:pt-0 border-slate-100">
          <div class="flex items-center gap-2 text-[13px] font-semibold text-slate-700">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
            {{ todayFormatted }}
          </div>
          <p class="text-[12px] text-slate-500 sm:mt-0.5">{{ dayName }}</p>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">
        <div v-for="s in stats" :key="s.label" class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 sm:p-6 flex items-center gap-4 sm:gap-5 hover:shadow-md transition-shadow">
          <div :class="[s.bg, 'w-12 sm:w-14 h-12 sm:h-14 rounded-2xl flex items-center justify-center shrink-0']">
            <svg class="w-6 h-6" :class="s.ic" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="s.icon"></path></svg>
          </div>
          <div>
            <p class="text-[12px] font-semibold text-slate-500">{{ s.label }}</p>
            <p class="text-xl sm:text-[24px] font-bold text-slate-800 leading-tight mt-0.5">{{ s.value }}</p>
            <p :class="[s.color, 'text-[11px] font-bold mt-1']">{{ s.change }}</p>
          </div>
        </div>
      </div>

      <!-- Table Card -->
      <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden min-w-0 max-w-full">
        
        <!-- Filter Row -->
        <div class="flex flex-col lg:flex-row lg:items-center gap-3 px-4 sm:px-6 py-4 border-b border-slate-100">
          <!-- Search -->
          <div class="relative flex-1 w-full lg:max-w-sm">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input v-model="search" type="text" placeholder="Search exams by title, course or code..." class="w-full pl-9 pr-4 py-2.5 min-h-[44px] text-[13px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] placeholder:text-slate-400">
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 flex-1 w-full lg:w-auto">
            <div class="relative">
              <select v-model="semesterFilter" class="w-full pl-4 pr-8 py-2.5 min-h-[44px] text-[13px] font-medium border border-slate-200 rounded-xl text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed]">
                <option value="all">All Semesters</option>
                <option v-for="sem in availableSemesters" :key="sem" :value="sem">{{ sem }}</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>

            <div class="relative">
              <select v-model="yearFilter" class="w-full pl-4 pr-8 py-2.5 min-h-[44px] text-[13px] font-medium border border-slate-200 rounded-xl text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed]">
                <option value="all">All Years</option>
                <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>

            <div class="relative">
              <select v-model="examTypeFilter" class="w-full pl-4 pr-8 py-2.5 min-h-[44px] text-[13px] font-medium border border-slate-200 rounded-xl text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed]">
                <option value="all">All Exam Types</option>
                <option v-for="t in (availableTypes.length ? availableTypes : ['Midterm', 'Final', 'Quiz'])" :key="t" :value="t">{{ t }}</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>

            <div class="relative">
              <select v-model="statusFilter" class="w-full pl-4 pr-8 py-2.5 min-h-[44px] text-[13px] font-medium border border-slate-200 rounded-xl text-slate-600 bg-white appearance-none focus:outline-none focus:border-[#5138ed]">
                <option value="all">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="published">Published</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="draft">Draft</option>
              </select>
              <svg class="w-3.5 h-3.5 absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>

          <!-- Filter Button -->
          <button @click="currentPage = 1" class="w-full sm:w-auto min-h-[44px] flex items-center justify-center gap-2 text-[13px] font-bold text-[#5138ed] border border-indigo-200 hover:bg-indigo-50 px-5 py-2.5 rounded-xl transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
            Filter
          </button>

        </div>

        <!-- Loading State -->
        <div v-if="isLoading" class="p-12 text-center text-slate-500">
          <div class="inline-block animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mb-3"></div>
          <p class="text-[14px] font-medium">Loading department exams...</p>
        </div>

        <!-- Desktop / Tablet Table -->
        <div v-else class="hidden md:block overflow-x-auto min-w-0 w-full">
          <table class="w-full">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50/50">
                <th class="text-left px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Exam Title</th>
                <th class="text-left px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Course</th>
                <th class="text-left px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Exam Type</th>
                <th class="text-left px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Date & Time</th>
                <th class="text-center px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Duration</th>
                <th class="text-center px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Questions</th>
                <th class="text-center px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Marks</th>
                <th class="text-center px-4 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Status</th>
                <th class="text-center px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
              <tr v-if="filtered.length === 0">
                <td colspan="9" class="px-6 py-12 text-center text-slate-400">
                  <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                  <p class="text-[14px] font-semibold text-slate-600">No exams found</p>
                  <p class="text-[12px] text-slate-400 mt-1">Try adjusting your filters or search terms.</p>
                </td>
              </tr>
              <tr v-for="exam in paginated" :key="exam.id" class="hover:bg-slate-50/40 transition-colors group">
                <td class="px-6 py-4">
                  <span class="block text-[13px] font-bold text-slate-800">{{ exam.title }}</span>
                  <span class="block text-[11px] font-medium text-slate-400 mt-0.5">{{ exam.code }}</span>
                </td>
                <td class="px-4 py-4">
                  <span class="block text-[13px] font-semibold text-slate-700">{{ exam.courseName }}</span>
                  <span class="block text-[11px] font-medium text-slate-400 mt-0.5">{{ exam.courseCode }}</span>
                </td>
                <td class="px-4 py-4">
                  <span :class="[typeBadge(exam.type), 'text-[11px] font-bold px-2.5 py-1 rounded-md']">{{ exam.type }}</span>
                </td>
                <td class="px-4 py-4">
                  <div class="flex items-start gap-2">
                    <svg class="w-4 h-4 text-slate-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <div>
                      <span class="block text-[12px] font-bold text-slate-700">{{ exam.date }}</span>
                      <span class="block text-[11px] font-medium text-slate-500 mt-0.5">{{ exam.time }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="text-[12px] font-semibold text-slate-700">{{ exam.duration }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="text-[13px] font-semibold text-slate-700">{{ exam.questions }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span class="text-[13px] font-semibold text-slate-700">{{ exam.marks }}</span>
                </td>
                <td class="px-4 py-4 text-center">
                  <span :class="[statusBadge(exam.status), 'text-[11px] font-bold px-2.5 py-1 rounded-md capitalize']">{{ exam.status }}</span>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="openDetail(exam)" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#5138ed] bg-indigo-50 hover:bg-indigo-100 transition-colors" title="View Details">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards View -->
        <div v-if="!isLoading" class="md:hidden divide-y divide-slate-100">
          <div v-if="filtered.length === 0" class="px-4 py-12 text-center text-slate-400">
            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            <p class="text-[14px] font-semibold text-slate-600">No exams found</p>
            <p class="text-[12px] text-slate-400 mt-1">Try adjusting your filters or search terms.</p>
          </div>
          <div v-for="exam in paginated" :key="'m-'+exam.id" class="p-4 space-y-3">
            <div class="flex items-start justify-between gap-2">
              <div>
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-slate-100 text-slate-700 mb-1">
                  {{ exam.code }}
                </span>
                <h3 class="text-[15px] font-bold text-slate-800 leading-snug">{{ exam.title }}</h3>
                <p class="text-[12px] text-slate-500 font-medium">{{ exam.courseName }} <span class="text-slate-400">({{ exam.courseCode }})</span></p>
              </div>
              <span :class="[statusBadge(exam.status), 'text-[11px] font-bold px-2 py-0.5 rounded-md capitalize shrink-0']">
                {{ exam.status }}
              </span>
            </div>

            <!-- Meta details grid -->
            <div class="grid grid-cols-2 gap-2 text-[12px] bg-slate-50/70 p-3 rounded-xl">
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Exam Type</span>
                <span :class="[typeBadge(exam.type), 'inline-block text-[10px] font-bold px-2 py-0.5 rounded mt-0.5']">{{ exam.type }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Duration</span>
                <span class="font-medium text-slate-700">{{ exam.duration }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Date & Time</span>
                <span class="font-medium text-slate-700 block">{{ exam.date }}</span>
                <span class="text-slate-400 text-[11px]">{{ exam.time }}</span>
              </div>
              <div>
                <span class="text-slate-400 block text-[10px] uppercase font-bold">Questions / Marks</span>
                <span class="font-medium text-slate-700">{{ exam.questions }} Qs / {{ exam.marks }} M</span>
              </div>
            </div>

            <!-- Mobile Action Button -->
            <button 
              @click="openDetail(exam)" 
              class="w-full min-h-[44px] flex items-center justify-center gap-2 px-4 py-2.5 text-[13px] font-bold text-[#5138ed] bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition-colors"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
              View Exam Details
            </button>
          </div>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-6 py-4 sm:py-5 border-t border-slate-100 bg-white">
          <p class="text-[12px] sm:text-[13px] text-slate-500 font-medium text-center sm:text-left">
            Showing {{ filtered.length === 0 ? 0 : (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, filtered.length) }} of {{ filtered.length }} exams
          </p>
          <div class="flex items-center gap-1.5 sm:gap-2">
            <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" class="w-9 h-9 min-h-[36px] rounded-lg flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <template v-for="p in displayPages" :key="p">
              <span v-if="p === '...'" class="w-9 h-9 flex items-center justify-center text-slate-400 text-[13px]">...</span>
              <button v-else @click="currentPage = (p as number)" :class="[currentPage === p ? 'bg-[#5138ed] text-white border border-[#5138ed]' : 'text-slate-500 border border-slate-200 hover:bg-slate-50', 'w-9 h-9 min-h-[36px] rounded-lg text-[13px] font-bold transition-colors']">{{ p }}</button>
            </template>
            <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" class="w-9 h-9 min-h-[36px] rounded-lg flex items-center justify-center text-slate-500 border border-slate-200 hover:bg-slate-50 disabled:opacity-40 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </button>
          </div>
        </div>

      </div>

    </template>

  </div>
</template>
