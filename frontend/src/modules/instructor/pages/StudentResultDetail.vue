<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import apiClient from '../../../core/api/apiClient'
import { useSemesterLockStore } from '../store/semesterLockStore'

const route = useRoute()
const router = useRouter()
const lockStore = useSemesterLockStore()

const isLoading    = ref(true)
const isSaving     = ref(false)
const isPublishing = ref(false)
const showSuccessModal = ref(false)
const lastAction   = ref<'save' | 'publish'>('save')
const savedResult  = ref<any>(null)
const errorMsg = ref('')

const prevStudentId = ref<number | null>(null)
const nextStudentId = ref<number | null>(null)

// Data from API
const studentInfo = ref<any>(null)
const examInfo    = ref<any>(null)
const attempt     = ref<any>(null)
const questions   = ref<any[]>([])
const breakdown   = ref<any>(null)

// Manual scores assigned by instructor: { question_id: score }
const manualScores = ref<Record<string, number>>({})

const activeTab = ref('all')

/* ─── Fetch ──────────────────────────────────────────────────────── */
const fetchStudentResult = async () => {
  const examId    = route.params.examId as string
  const studentId = route.params.studentId as string

  if (!examId || !studentId) return;

  isLoading.value = true
  errorMsg.value  = ''
  try {
    const res  = await apiClient.get(`/instructor/results/${examId}/student/${studentId}`)
    const data = res.data?.data
    if (data) {
      studentInfo.value = data.student
      examInfo.value    = data.exam
      attempt.value     = data.attempt
      questions.value   = data.questions || []
      breakdown.value   = data.breakdown
      prevStudentId.value = data.prev_student_id ?? null
      nextStudentId.value = data.next_student_id ?? null

      // Pre-fill manual scores from existing attempt data
      manualScores.value = {}
      questions.value.forEach((q: any) => {
        if (!q.is_auto && q.manual_score !== null) {
          manualScores.value[q.id] = q.manual_score
        }
      })
    }
  } catch (err: any) {
    errorMsg.value = err?.response?.data?.error || 'Failed to load student result.'
    console.error('fetchStudentResult error:', err)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await lockStore.fetchLockStatus()
  fetchStudentResult()
})

watch(
  () => route.params.studentId,
  (newId) => {
    if (newId && route.name === 'InstructorStudentResultDetail') {
      fetchStudentResult()
    }
  }
)

/* ─── Tabs ───────────────────────────────────────────────────────── */
const tabs = computed(() => {
  const mcqCount    = questions.value.filter(q => q.type === 'multiple_choice').length
  const tfCount     = questions.value.filter(q => q.type === 'true_false').length
  const shortCount  = questions.value.filter(q => q.type === 'short_answer').length
  const essayCount  = questions.value.filter(q => q.type === 'essay').length
  return [
    { key: 'all',   label: 'All Questions',  count: questions.value.length },
    { key: 'mcq',   label: 'MCQ / True-False', count: mcqCount + tfCount },
    { key: 'short', label: 'Short Answer',   count: shortCount },
    { key: 'essay', label: 'Essay',          count: essayCount },
  ]
})

const filteredQuestions = computed(() => {
  if (activeTab.value === 'all')   return questions.value
  if (activeTab.value === 'mcq')   return questions.value.filter(q => q.type === 'multiple_choice' || q.type === 'true_false')
  if (activeTab.value === 'short') return questions.value.filter(q => q.type === 'short_answer')
  if (activeTab.value === 'essay') return questions.value.filter(q => q.type === 'essay')
  return questions.value
})

/* ─── Helpers ────────────────────────────────────────────────────── */
const typeLabel = (type: string) => {
  const map: Record<string, string> = {
    multiple_choice: 'MCQ (Single Choice)',
    true_false:      'True / False',
    short_answer:    'Short Answer',
    essay:           'Essay Question',
  }
  return map[type] ?? type
}

const typeBadgeColor = (type: string) => {
  if (type === 'multiple_choice') return 'bg-blue-100 text-blue-600'
  if (type === 'true_false')      return 'bg-purple-100 text-purple-600'
  if (type === 'short_answer')    return 'bg-amber-100 text-amber-600'
  return 'bg-slate-100 text-slate-500'
}

const formatDate = (iso: string | null) => {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
}
const formatTime = (iso: string | null) => {
  if (!iso) return '—'
  const d = new Date(iso)
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const statusColor = (s: string) => {
  if (s === 'Graded')   return 'bg-emerald-50 text-emerald-600'
  if (s === 'Submitted') return 'bg-blue-50 text-blue-600'
  return 'bg-amber-50 text-amber-600'
}

/* ─── Computed live scores (updates as instructor enters marks) ─── */
const liveAutoScore = computed(() =>
  questions.value.filter(q => q.is_auto).reduce((acc: number, q: any) => acc + (q.scored ?? 0), 0)
)
const liveAutoTotal = computed(() =>
  questions.value.filter(q => q.is_auto).reduce((acc: number, q: any) => acc + (q.marks ?? 0), 0)
)
const liveManualScore = computed(() =>
  Object.values(manualScores.value).reduce((acc: number, v) => acc + Number(v || 0), 0)
)
const liveManualTotal = computed(() =>
  questions.value.filter(q => !q.is_auto).reduce((acc: number, q: any) => acc + (q.marks ?? 0), 0)
)
const liveFinalScore = computed(() => liveAutoScore.value + liveManualScore.value)
const liveTotalMarks = computed(() => attempt.value?.total_marks ?? 0)
const livePct = computed(() =>
  liveTotalMarks.value > 0 ? ((liveFinalScore.value / liveTotalMarks.value) * 100).toFixed(1) : '0.0'
)
const liveGrade = computed(() => {
  const p = parseFloat(livePct.value)
  if (p >= 90) return 'A+'
  if (p >= 85) return 'A'
  if (p >= 80) return 'A-'
  if (p >= 75) return 'B+'
  if (p >= 70) return 'B'
  if (p >= 65) return 'C+'
  if (p >= 60) return 'C'
  if (p >= 50) return 'D'
  return 'F'
})

/* ─── Save (grade only, no publish) ────────────────────────────── */
const saveGrades = async (publish = false) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice(publish ? 'publish results' : 'save grades')
    return
  }

  const examId    = route.params.examId as string
  const studentId = route.params.studentId as string

  if (publish) {
    isPublishing.value = true
  } else {
    isSaving.value = true
  }

  try {
    const endpoint = publish
      ? `/instructor/results/${examId}/student/${studentId}/publish`
      : `/instructor/results/${examId}/student/${studentId}/save`

    const res = await apiClient.post(endpoint, {
      manual_scores: manualScores.value,
    })

    // Set result summary for the modal
    savedResult.value = {
      studentName:  studentInfo.value?.name,
      examTitle:    examInfo.value?.title,
      totalMarks:   liveTotalMarks.value,
      finalScore:   liveFinalScore.value,
      percentage:   livePct.value,
      grade:        liveGrade.value,
      savedOn:      new Date().toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }),
    }

    if (publish) {
      lastAction.value = 'publish'
      showSuccessModal.value = true
    } else {
      // Update local attempt state
      if (attempt.value) {
        attempt.value.final_score  = liveFinalScore.value
        attempt.value.percentage   = parseFloat(livePct.value)
        attempt.value.grade        = liveGrade.value
        attempt.value.status       = 'Graded'
      }
      lastAction.value = 'save'
      showSuccessModal.value = true
    }
  } catch (err: any) {
    alert(err?.response?.data?.error || 'Failed to save grades.')
  } finally {
    isSaving.value     = false
    isPublishing.value = false
  }
}

/**
 * Group the filtered questions by their instruction text,
 * preserving the order they appear in the sorted list.
 * Returns: [{ instruction: string, questions: Question[] }]
 */
const groupedFilteredQuestions = computed(() => {
  const groups: { instruction: string; questions: any[] }[] = []
  const seen: Record<string, number> = {}

  for (const q of filteredQuestions.value) {
    const inst = (q.instruction ?? '').trim()
    if (!Object.prototype.hasOwnProperty.call(seen, inst)) {
      seen[inst] = groups.length
      groups.push({ instruction: inst, questions: [] })
    }
    groups[seen[inst]].questions.push(q)
  }
  return groups
})
</script>

<template>
  <div class="max-w-[1500px] mx-auto">

    <!-- Loading -->
    <div v-if="isLoading" class="flex flex-col items-center justify-center py-32 gap-4">
      <div class="w-10 h-10 border-4 border-[#5138ed]/20 border-t-[#5138ed] rounded-full animate-spin"></div>
      <p class="text-slate-500 text-sm font-medium">Loading student result…</p>
    </div>

    <!-- Error -->
    <div v-else-if="errorMsg" class="bg-rose-50 border border-rose-200 text-rose-600 rounded-2xl p-6 text-center">
      <p class="font-bold text-base mb-1">Failed to load</p>
      <p class="text-sm">{{ errorMsg }}</p>
    </div>

    <template v-else-if="studentInfo">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-slate-800">Student Result Details</h1>
          <p class="text-[13px] text-slate-500 mt-1">Review student answers and assign marks.</p>
          <div class="flex items-center gap-2 text-[12px] text-slate-400 mt-2 flex-wrap">
            <router-link to="/instructor/results" class="hover:text-[#5138ed] transition-colors">Results Dashboard</router-link>
            <span>&gt;</span>
            <router-link :to="`/instructor/results/${route.params.examId}`" class="hover:text-[#5138ed] transition-colors">{{ examInfo?.title }}</router-link>
            <span>&gt;</span>
            <span class="text-slate-600 font-medium">{{ studentInfo.name }}</span>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button v-if="prevStudentId" @click="router.push(`/instructor/results/${route.params.examId}/student/${prevStudentId}`)"
            class="p-2 border border-slate-200 text-slate-700 rounded-xl hover:bg-slate-50 transition-colors" title="Previous Student">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          
          <button @click="router.push(`/instructor/results/${route.params.examId}`)"
            class="flex items-center gap-2 px-4 py-2 border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl hover:bg-slate-50 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to List
          </button>
          
          <button v-if="nextStudentId" @click="router.push(`/instructor/results/${route.params.examId}/student/${nextStudentId}`)"
            class="p-2 border border-slate-200 text-slate-700 rounded-xl hover:bg-slate-50 transition-colors" title="Next Student">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>

      <!-- Semester Locked Read-Only Notice Banner -->
      <div v-if="lockStore.isLocked" class="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
          </div>
          <div>
            <h4 class="text-xs font-bold text-amber-900">Read-Only Mode: Semester Submitted</h4>
            <p class="text-[11px] text-amber-700 font-medium mt-0.5">Your semester submission has been finalized. Entering or editing grades and publishing results are disabled.</p>
          </div>
        </div>
        <span class="px-2.5 py-1 bg-amber-200/70 text-amber-900 text-[10px] font-black uppercase tracking-wider rounded-lg shrink-0">Locked</span>
      </div>

      <!-- Student Info Card -->
      <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm mb-6 flex flex-wrap lg:flex-nowrap items-center gap-6 lg:gap-10">
        <div class="flex items-center gap-4 flex-1 min-w-[280px]">
          <div class="relative">
            <div class="w-14 h-14 rounded-full bg-indigo-50 text-[#5138ed] font-black text-xl flex items-center justify-center">
              {{ studentInfo.initials || '?' }}
            </div>
            <div class="absolute bottom-0 right-0 w-3.5 h-3.5 border-2 border-white rounded-full"
              :class="attempt?.status === 'Graded' ? 'bg-emerald-500' : attempt?.status === 'Submitted' ? 'bg-blue-500' : 'bg-slate-400'"></div>
          </div>
          <div class="flex flex-col">
            <div class="flex items-center gap-2 mb-1">
              <h2 class="text-lg font-bold text-slate-800">{{ studentInfo.name }}</h2>
              <span class="px-2 py-0.5 text-[9px] font-bold rounded"
                :class="statusColor(attempt?.status ?? '')">{{ attempt?.status ?? 'Absent' }}</span>
            </div>
            <div class="flex items-center gap-3 text-[11px] text-slate-500 font-medium flex-wrap">
              <span>ID: {{ studentInfo.id_no }}</span>
              <span class="w-1 h-1 rounded-full bg-slate-300"></span>
              <span>Section: {{ studentInfo.section || '—' }}</span>
              <span class="w-1 h-1 rounded-full bg-slate-300"></span>
              <span>{{ studentInfo.email }}</span>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-8 shrink-0 flex-wrap">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] font-bold text-slate-400">Submitted On</span>
              <span class="text-[12px] font-bold text-slate-700">{{ formatDate(attempt?.submitted_on) }}</span>
              <span class="text-[10px] text-slate-500">{{ formatTime(attempt?.submitted_on) }}</span>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] font-bold text-slate-400">Time Taken</span>
              <span class="text-[12px] font-bold text-slate-700">{{ attempt?.time_taken ?? '—' }} min</span>
              <span class="text-[10px] text-slate-500">of {{ examInfo?.duration_minutes ?? '—' }} min</span>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center text-slate-500">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] font-bold text-slate-400">Attempt</span>
              <span class="text-[12px] font-bold text-slate-700">First Attempt</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="space-y-6">

        <!-- Tabs + Scores Row + Questions -->
        <div class="w-full">

          <!-- Tabs -->
          <div class="flex items-center gap-6 border-b border-slate-100 mb-6">
            <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
              class="pb-3 text-[13px] font-bold transition-colors relative"
              :class="activeTab === tab.key ? 'text-[#5138ed]' : 'text-slate-500 hover:text-slate-800'">
              {{ tab.label }} ({{ tab.count }})
              <div v-if="activeTab === tab.key" class="absolute bottom-0 left-0 right-0 h-0.5 bg-[#5138ed] rounded-t-full"></div>
            </button>
          </div>

          <!-- Live Score Cards -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center">
              <span class="text-[11px] font-bold text-blue-500 mb-1 flex items-center gap-1">
                <div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div> Auto Score (MCQ)
              </span>
              <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-800">{{ liveAutoScore }}</span>
                <span class="text-[13px] font-bold text-slate-400">/ {{ liveAutoTotal }}</span>
                <span class="text-[11px] font-bold text-blue-500 ml-2">
                  {{ liveAutoTotal > 0 ? ((liveAutoScore / liveAutoTotal) * 100).toFixed(1) : '0.0' }}%
                </span>
              </div>
            </div>
            <div class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center">
              <span class="text-[11px] font-bold text-orange-500 mb-1 flex items-center gap-1">
                <div class="w-1.5 h-1.5 rounded-full bg-orange-500"></div> Manual Score
              </span>
              <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-800">{{ liveManualScore }}</span>
                <span class="text-[13px] font-bold text-slate-400">/ {{ liveManualTotal }}</span>
                <span class="text-[11px] font-bold text-orange-500 ml-2">
                  {{ liveManualTotal > 0 ? ((liveManualScore / liveManualTotal) * 100).toFixed(1) : '0.0' }}%
                </span>
              </div>
            </div>
            <div class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center">
              <span class="text-[11px] font-bold text-emerald-500 mb-1 flex items-center gap-1">
                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Final Score
              </span>
              <div class="flex items-baseline gap-2">
                <span class="text-2xl font-black text-slate-800">{{ liveFinalScore }}</span>
                <span class="text-[13px] font-bold text-slate-400">/ {{ liveTotalMarks }}</span>
                <span class="text-[11px] font-bold text-emerald-500 ml-2">{{ livePct }}%</span>
              </div>
            </div>
            <div class="bg-white border border-slate-100 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center">
              <span class="text-[11px] font-bold text-[#5138ed] mb-1 flex items-center gap-1">
                <div class="w-1.5 h-1.5 rounded-full bg-[#5138ed]"></div> Grade
              </span>
              <span class="text-2xl font-black text-[#5138ed]">{{ liveGrade }}</span>
            </div>
          </div>

          <!-- No attempt notice -->
          <div v-if="!attempt?.id" class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center text-amber-700">
            <p class="font-bold mb-1">No Attempt Recorded</p>
            <p class="text-sm">This student did not submit an exam attempt.</p>
          </div>

          <!-- Question List — grouped by instruction -->
          <div v-else class="space-y-2">
            <template v-for="(group, gIdx) in groupedFilteredQuestions" :key="gIdx">

              <!-- Instruction Group Header -->
              <div class="flex items-center gap-3 mt-6 mb-3 first:mt-0">
                <div class="flex-1 h-px bg-slate-100"></div>
                <div v-if="group.instruction"
                  class="flex items-center gap-2 px-4 py-1.5 bg-indigo-50 border border-indigo-100 rounded-full shrink-0">
                  <svg class="w-3.5 h-3.5 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                  </svg>
                  <span class="text-[11px] font-bold text-[#5138ed] italic">{{ group.instruction }}</span>
                </div>
                <div v-else
                  class="flex items-center gap-2 px-4 py-1.5 bg-slate-50 border border-slate-200 rounded-full shrink-0">
                  <span class="text-[11px] font-medium text-slate-400 italic">No specific instruction</span>
                </div>
                <div class="flex-1 h-px bg-slate-100"></div>
              </div>

              <!-- Questions within this group -->
              <div class="space-y-4">
            <div v-for="q in group.questions" :key="q.id"
              class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">

              <!-- Question Header -->
              <div class="flex items-center justify-between px-6 pt-5 pb-3">
                <div class="flex items-center gap-3">
                  <div class="w-7 h-7 rounded-full flex items-center justify-center text-[12px] font-bold shrink-0"
                    :class="q.is_auto ? (q.is_correct ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600') : 'bg-slate-100 text-slate-500'">
                    {{ q.number }}
                  </div>
                  <span class="text-[12px] font-bold px-2 py-0.5 rounded-md" :class="typeBadgeColor(q.type)">
                    {{ typeLabel(q.type) }}
                  </span>
                </div>
                <div class="flex items-center gap-4 text-[12px] font-bold">
                  <span class="text-slate-500">Marks: {{ q.marks }}</span>
                  <span v-if="q.is_auto"
                    :class="q.is_correct ? 'text-emerald-500' : 'text-rose-500'">
                    Scored: {{ q.scored }} / {{ q.marks }}
                  </span>
                  <div v-else class="flex items-center gap-2">
                    <span class="text-slate-700 font-bold">Score:</span>
                    <input
                      type="number"
                      :min="0"
                      :max="q.marks"
                      :disabled="lockStore.isLocked"
                      v-model.number="manualScores[q.id]"
                      :class="[
                        'w-16 h-8 text-center rounded-lg text-[13px] font-bold transition-all focus:outline-none',
                        lockStore.isLocked
                          ? 'bg-slate-100 border border-slate-300 text-slate-500 cursor-not-allowed'
                          : 'border-2 border-[#5138ed] text-slate-800 focus:ring-2 focus:ring-[#5138ed]/20'
                      ]"
                      :placeholder="`0`"
                    />
                    <span class="text-[12px] font-bold text-slate-400">/ {{ q.marks }}</span>
                  </div>
                </div>
              </div>

              <!-- Question text -->
              <div class="px-6 pb-4">
                <p class="text-[14px] text-slate-800 font-medium mb-5">{{ q.text }}</p>

                <!-- ① MCQ / True-False options -->
                <div v-if="q.options && q.options.length > 0" class="space-y-2">
                  <div v-for="opt in q.options" :key="opt.letter"
                    class="flex items-center gap-3 p-3 rounded-xl border relative transition-all"
                    :class="[
                      opt.letter === q.correct_answer && opt.letter === q.student_answer
                        ? 'border-2 border-emerald-500 bg-emerald-50'
                        : opt.letter === q.correct_answer
                          ? 'border-2 border-emerald-400 bg-emerald-50/60'
                          : opt.letter === q.student_answer && opt.letter !== q.correct_answer
                            ? 'border-2 border-rose-500 bg-rose-50'
                            : 'border-slate-100 bg-slate-50 opacity-60'
                    ]">
                    <div class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0"
                      :class="[
                        opt.letter === q.correct_answer && opt.letter === q.student_answer
                          ? 'bg-emerald-500 text-white'
                          : opt.letter === q.correct_answer
                            ? 'bg-emerald-400 text-white'
                            : opt.letter === q.student_answer
                              ? 'bg-rose-500 text-white'
                              : 'border border-slate-300 text-slate-400'
                      ]">
                      {{ opt.letter }}
                    </div>
                    <span class="text-[13px]"
                      :class="opt.letter === q.correct_answer ? 'font-bold text-emerald-800' : 'text-slate-600'">
                      {{ opt.text }}
                    </span>
                    <span v-if="opt.letter === q.correct_answer && opt.letter === q.student_answer"
                      class="absolute right-4 text-[11px] font-bold text-emerald-600">✓ Correct Answer</span>
                    <span v-else-if="opt.letter === q.correct_answer"
                      class="absolute right-4 text-[11px] font-bold text-emerald-600">Correct Answer</span>
                    <span v-else-if="opt.letter === q.student_answer"
                      class="absolute right-4 text-[11px] font-bold text-rose-600 flex items-center gap-1">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                      Your Answer
                    </span>
                  </div>

                  <!-- MCQ auto-grade footer -->
                  <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-6">
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] font-bold text-slate-500">Correct Answer:</span>
                      <span class="text-[13px] font-black text-slate-800">{{ q.correct_answer }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] font-bold text-slate-500">Student's Answer:</span>
                      <span class="text-[13px] font-black text-slate-800">{{ q.student_answer ?? '—' }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="text-[11px] font-bold text-slate-500">Status:</span>
                      <span v-if="q.is_correct" class="text-[11px] font-bold text-emerald-500 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        Correct
                      </span>
                      <span v-else-if="q.student_answer" class="text-[11px] font-bold text-rose-500 flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                        Incorrect
                      </span>
                      <span v-else class="text-[11px] font-bold text-amber-500">Not Answered</span>
                    </div>
                    <div v-if="q.explanation" class="w-full">
                      <span class="text-[11px] font-bold text-slate-800 mb-1 block">Explanation</span>
                      <p class="text-[11px] text-slate-500 leading-relaxed">{{ q.explanation }}</p>
                    </div>
                  </div>
                </div>

                <!-- ② Matching question -->
                <div v-else-if="q.type === 'matching' && q.matching_pairs" class="space-y-4">

                  <!-- Column A / Column B header display -->
                  <div class="grid grid-cols-2 gap-4">
                    <!-- Column A -->
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                      <div class="flex items-center gap-2 mb-3">
                        <div class="w-6 h-6 rounded-lg bg-blue-500 text-white text-[11px] font-black flex items-center justify-center">A</div>
                        <span class="text-[12px] font-bold text-blue-700">Column A</span>
                      </div>
                      <div class="space-y-2">
                        <div v-for="pair in q.matching_pairs" :key="'a-' + pair.index"
                          class="flex items-center gap-2 bg-white border border-blue-100 rounded-lg px-3 py-2">
                          <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 text-[11px] font-black flex items-center justify-center shrink-0">{{ pair.left_letter }}</span>
                          <span class="text-[13px] text-slate-700 font-medium">{{ pair.left_text }}</span>
                        </div>
                      </div>
                    </div>

                    <!-- Column B -->
                    <div class="bg-purple-50 border border-purple-100 rounded-xl p-4">
                      <div class="flex items-center gap-2 mb-3">
                        <div class="w-6 h-6 rounded-lg bg-purple-500 text-white text-[11px] font-black flex items-center justify-center">B</div>
                        <span class="text-[12px] font-bold text-purple-700">Column B</span>
                      </div>
                      <div class="space-y-2">
                        <div v-for="item in q.column_b" :key="'b-' + item.index"
                          class="flex items-center gap-2 bg-white border border-purple-100 rounded-lg px-3 py-2">
                          <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-700 text-[11px] font-black flex items-center justify-center shrink-0">{{ item.label }}</span>
                          <span class="text-[13px] text-slate-700 font-medium">{{ item.text }}</span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Result table: student match vs correct match -->
                  <div class="border border-slate-100 rounded-xl overflow-hidden">
                    <div class="grid grid-cols-4 bg-slate-50 border-b border-slate-100 px-4 py-2">
                      <span class="text-[11px] font-bold text-slate-500">Col A Item</span>
                      <span class="text-[11px] font-bold text-slate-500">Correct Match (Col B)</span>
                      <span class="text-[11px] font-bold text-slate-500">Student's Match</span>
                      <span class="text-[11px] font-bold text-slate-500 text-center">Result</span>
                    </div>
                    <div v-for="pair in q.matching_pairs" :key="'row-' + pair.index"
                      class="grid grid-cols-4 items-center px-4 py-3 border-b border-slate-50 last:border-0"
                      :class="pair.is_correct ? 'bg-emerald-50/40' : pair.student_match ? 'bg-rose-50/40' : 'bg-amber-50/30'">
                      <!-- Col A Item -->
                      <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 text-[10px] font-black flex items-center justify-center shrink-0">{{ pair.left_letter }}</span>
                        <span class="text-[12px] text-slate-700 font-medium">{{ pair.left_text }}</span>
                      </div>
                      <!-- Correct Match -->
                      <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-black flex items-center justify-center shrink-0">✓</span>
                        <span class="text-[12px] text-emerald-700 font-bold">{{ pair.right_text }}</span>
                      </div>
                      <!-- Student Match -->
                      <div class="flex items-center gap-2">
                        <span v-if="pair.student_match"
                          class="text-[12px] font-bold"
                          :class="pair.is_correct ? 'text-emerald-600' : 'text-rose-600'">
                          {{ pair.student_match }}
                        </span>
                        <span v-else class="text-[12px] text-slate-400 italic">Not answered</span>
                      </div>
                      <!-- Result badge -->
                      <div class="flex justify-center">
                        <span v-if="pair.is_correct"
                          class="px-2 py-0.5 bg-emerald-100 text-emerald-600 text-[10px] font-bold rounded-full flex items-center gap-1">
                          <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                          Correct
                        </span>
                        <span v-else-if="pair.student_match"
                          class="px-2 py-0.5 bg-rose-100 text-rose-600 text-[10px] font-bold rounded-full flex items-center gap-1">
                          <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                          Wrong
                        </span>
                        <span v-else
                          class="px-2 py-0.5 bg-amber-100 text-amber-600 text-[10px] font-bold rounded-full">
                          No Answer
                        </span>
                      </div>
                    </div>
                  </div>

                  <div v-if="q.explanation" class="text-[11px] text-slate-500 pt-1">
                    <span class="font-bold text-slate-700">Explanation:</span> {{ q.explanation }}
                  </div>
                </div>

                <!-- ③ Short Answer / Essay -->
                <div v-else class="mt-2">
                  <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mb-2 block">Student Answer</span>
                  <div class="p-4 bg-slate-50 border border-slate-100 rounded-xl min-h-[80px]">
                    <p v-if="q.student_answer" class="text-[13px] text-slate-700 leading-relaxed whitespace-pre-wrap">{{ q.student_answer }}</p>
                    <p v-else class="text-[12px] text-slate-400 italic">No answer provided</p>
                  </div>
                  <div v-if="q.explanation" class="mt-2 text-[11px] text-slate-500">
                    <span class="font-bold text-slate-700">Explanation:</span> {{ q.explanation }}
                  </div>
                </div>

              </div>
            </div>

              </div><!-- end space-y-4 -->
            </template>

            <!-- No questions in this tab -->
            <div v-if="filteredQuestions.length === 0" class="text-center py-12 text-slate-400 text-sm">
              No questions in this category.
            </div>
          </div>
        </div>

        <!-- Bottom: Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

          <!-- Score Summary -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
            <h3 class="text-[13px] font-bold text-slate-800 mb-4">Score Summary</h3>
            <div class="space-y-3">
              <div class="flex items-center justify-between border-b border-slate-50 pb-2">
                <span class="text-[12px] text-slate-500 font-medium">Total Marks</span>
                <span class="text-[13px] font-bold text-slate-800">{{ liveTotalMarks }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-50 pb-2">
                <span class="text-[12px] text-slate-500 font-medium">Auto Score</span>
                <span class="text-[13px] font-bold text-blue-500">{{ liveAutoScore }} / {{ liveAutoTotal }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-50 pb-2">
                <span class="text-[12px] text-slate-500 font-medium">Manual Score</span>
                <span class="text-[13px] font-bold text-orange-500">{{ liveManualScore }} / {{ liveManualTotal }}</span>
              </div>
              <div class="flex items-center justify-between border-b border-slate-50 pb-2">
                <span class="text-[12px] text-slate-500 font-medium">Final Score</span>
                <span class="text-[13px] font-bold text-emerald-500">{{ liveFinalScore }} / {{ liveTotalMarks }} ({{ livePct }}%)</span>
              </div>
              <div class="flex items-center justify-between pt-1">
                <span class="text-[12px] text-slate-500 font-medium">Grade</span>
                <span class="text-[14px] font-black text-[#5138ed]">{{ liveGrade }}</span>
              </div>
            </div>
          </div>

          <!-- Question Breakdown -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between">
            <h3 class="text-[13px] font-bold text-slate-800 mb-4">Question Breakdown</h3>
            <div class="space-y-2.5">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div><span class="text-[12px] text-slate-600">MCQ / True-False</span></div>
                <span class="text-[12px] font-bold text-slate-800">{{ breakdown?.mcq_count ?? 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-orange-400"></div><span class="text-[12px] text-slate-600">Manual Questions</span></div>
                <span class="text-[12px] font-bold text-slate-800">{{ breakdown?.manual_count ?? 0 }}</span>
              </div>
              <div class="h-px bg-slate-100 my-2"></div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div><span class="text-[12px] text-slate-600">Correct</span></div>
                <span class="text-[12px] font-bold text-slate-800">{{ breakdown?.correct ?? 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div><span class="text-[12px] text-slate-600">Incorrect</span></div>
                <span class="text-[12px] font-bold text-slate-800">{{ breakdown?.incorrect ?? 0 }}</span>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><div class="w-1.5 h-1.5 rounded-full bg-amber-500"></div><span class="text-[12px] text-slate-600">Pending Grading</span></div>
                <span class="text-[12px] font-bold text-slate-800">{{ breakdown?.pending ?? 0 }}</span>
              </div>
            </div>
          </div>

          <!-- Grading Progress -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between space-y-4">
            <div>
              <h3 class="text-[13px] font-bold text-slate-800 mb-4">Grading Progress</h3>
              <div class="flex items-center gap-4">
                <div class="relative w-16 h-16 shrink-0">
                  <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#f1f5f9" stroke-width="12"/>
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#3b82f6" stroke-width="12"
                      stroke-linecap="round"
                      :stroke-dasharray="`${(liveFinalScore / Math.max(liveTotalMarks, 1)) * 251.3} 251.3`"
                      stroke-dashoffset="0"/>
                  </svg>
                  <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-[14px] font-black text-slate-800 leading-none">{{ livePct }}%</span>
                  </div>
                </div>
                <div class="flex flex-col">
                  <span class="text-[12px] font-bold text-slate-800">Graded</span>
                  <span class="text-[11px] text-slate-500 mb-1">{{ liveFinalScore }} / {{ liveTotalMarks }} Marks</span>
                  <span v-if="liveManualTotal > 0 && liveManualScore === 0"
                    class="text-[10px] font-bold text-orange-500 bg-orange-50 px-2 py-0.5 rounded self-start">
                    {{ liveManualTotal }} Manual Marks Pending
                  </span>
                </div>
              </div>
            </div>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 flex items-start gap-2.5">
              <div class="w-6 h-6 rounded-lg bg-white flex items-center justify-center text-blue-500 shrink-0 shadow-sm">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <p class="text-[10px] text-blue-700 font-medium leading-relaxed">
                Results are visible to students only after publishing.
              </p>
            </div>
          </div>

          <!-- Actions Card -->
          <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-sm flex flex-col justify-between space-y-3">
            <h3 class="text-[13px] font-bold text-slate-800 mb-2">Actions</h3>
            <div class="space-y-2.5 flex-1 flex flex-col justify-center">
              <template v-if="!lockStore.isLocked">
                <!-- Publish -->
                <button @click="saveGrades(true)" :disabled="isPublishing || !attempt?.id"
                  class="w-full py-2.5 text-white text-[12px] font-bold rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2 disabled:opacity-50"
                  :class="examInfo?.is_published ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-[#5138ed] hover:bg-[#4530d1]'">
                  <svg v-if="isPublishing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                  {{ examInfo?.is_published ? 'Re-Publish Results' : 'Publish Results' }}
                </button>
                <!-- Save grade only -->
                <button @click="saveGrades(false)" :disabled="isSaving || !attempt?.id"
                  class="w-full py-2.5 bg-white border border-slate-200 text-[#5138ed] text-[12px] font-bold rounded-xl hover:bg-slate-50 transition-colors flex items-center justify-center gap-2 disabled:opacity-50">
                  <svg v-if="isSaving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                  Save Grades (No Publish)
                </button>
              </template>
              <template v-else>
                <div class="p-3.5 bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-[11px] leading-relaxed font-medium space-y-1">
                  <div class="flex items-center gap-1.5 font-bold text-amber-900">
                    <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    <span>Grading Locked</span>
                  </div>
                  <p>Semester submission completed. Grades cannot be entered, edited, or published.</p>
                </div>
              </template>
              <!-- Back -->
              <button @click="router.push(`/instructor/results/${route.params.examId}`)"
                class="w-full py-2.5 bg-white border border-slate-200 text-slate-600 text-[12px] font-bold rounded-xl hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to List
              </button>
            </div>
          </div>

        </div>

      </div>

    </template>

    <!-- Success Modal -->
    <div v-if="showSuccessModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-[480px] relative overflow-hidden flex flex-col items-center p-8 border border-slate-100">
        <button @click="showSuccessModal = false" class="absolute top-5 right-5 text-slate-400 hover:text-slate-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>

        <div class="relative w-32 h-32 flex items-center justify-center mb-2">
          <div class="absolute top-4 left-6 w-1.5 h-1.5 rounded-full bg-[#5138ed]"></div>
          <div class="absolute top-10 right-4 w-2 h-2 rounded-full bg-orange-400"></div>
          <div class="absolute bottom-6 left-2 w-2 h-2 rounded-full bg-emerald-400"></div>
          <div class="absolute bottom-4 right-8 w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
          <div class="absolute top-6 left-1/2 w-1.5 h-1.5 rounded-full bg-amber-500"></div>
          <div class="absolute bottom-1/4 right-1/4 w-2 h-2 rounded-full bg-blue-400"></div>
          <div class="w-16 h-16 bg-emerald-100 rounded-full flex items-center justify-center text-emerald-500 z-10 shadow-sm border-4 border-white">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
          </div>
        </div>

        <h3 class="text-[22px] font-extrabold text-slate-800 mb-2">
          {{ lastAction === 'publish' ? 'Results Published!' : 'Grades Saved!' }}
        </h3>
        <p class="text-[13px] text-slate-500 font-medium mb-8">
          {{ lastAction === 'publish' ? 'The exam result is now visible to the student.' : 'The exam score has been recorded and saved.' }}
        </p>

        <div v-if="savedResult" class="w-full border border-slate-100 rounded-2xl p-5 mb-8 space-y-3 shadow-sm bg-white">
          <div class="flex justify-between"><span class="text-[12px] text-slate-500">Student</span><span class="text-[12px] font-bold text-slate-800">{{ savedResult.studentName }}</span></div>
          <div class="flex justify-between"><span class="text-[12px] text-slate-500">Exam</span><span class="text-[12px] font-bold text-slate-800 text-right">{{ savedResult.examTitle }}</span></div>
          <div class="flex justify-between"><span class="text-[12px] text-slate-500">Total Marks</span><span class="text-[12px] font-bold text-slate-800">{{ savedResult.totalMarks }}</span></div>
          <div class="flex justify-between"><span class="text-[12px] text-slate-500">Final Score</span><span class="text-[12px] font-bold text-slate-800">{{ savedResult.finalScore }} / {{ savedResult.totalMarks }} ({{ savedResult.percentage }}%)</span></div>
          <div class="flex justify-between items-center"><span class="text-[12px] text-slate-500">Grade</span><span class="px-2 py-0.5 bg-indigo-50 text-[#5138ed] font-black text-[11px] rounded-md">{{ savedResult.grade }}</span></div>
          <div class="flex justify-between"><span class="text-[12px] text-slate-500">Saved On</span><span class="text-[12px] font-bold text-slate-800">{{ savedResult.savedOn }}</span></div>
        </div>

        <div class="flex items-center justify-between w-full gap-3">
          <button @click="showSuccessModal = false; router.push(`/instructor/results/${route.params.examId}`)"
            class="flex-1 py-3 bg-white border border-slate-200 text-slate-600 text-[13px] font-bold rounded-xl shadow-sm hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to List
          </button>
          
          <button v-if="nextStudentId" @click="showSuccessModal = false; router.push(`/instructor/results/${route.params.examId}/student/${nextStudentId}`)"
            class="flex-1 py-3 bg-[#5138ed] text-white text-[13px] font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors flex items-center justify-center gap-2">
            Next Student
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
          </button>
          
          <button v-else @click="showSuccessModal = false"
            class="flex-1 py-3 bg-[#5138ed] text-white text-[13px] font-bold rounded-xl shadow-sm hover:bg-[#4530d1] transition-colors flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            Done
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
