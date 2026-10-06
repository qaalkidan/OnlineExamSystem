<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { initialStudentProfile } from '../data/mockData'
import type { StudentProfile, RecentResult } from '../types'
import { useStudentExamStore } from '../store/studentExamStore'
import ExamConsole from '../components/ExamConsole.vue'

const examStore = useStudentExamStore()
const router    = useRouter()
const profile   = ref<StudentProfile>({ ...initialStudentProfile })

const activeExam = computed(() => examStore.activeExam)

// Modal state
const showModal       = ref(false)
const isAutoSubmitted = ref(false)
const resultData      = ref<RecentResult | null>(null)
const examDisplayName = ref('')

const TYPE_LABELS: Record<string, string> = {
  multiple_choice: 'Multiple Choice',
  true_false:      'True / False',
  matching:        'Matching',
  short_answer:    'Short Answer',
  fill_blank:      'Fill in the Blank',
  essay:           'Essay',
}

const TYPE_ICONS: Record<string, any> = {
  multiple_choice: { bg: 'bg-indigo-50', text: 'text-indigo-600', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
  true_false:      { bg: 'bg-blue-50', text: 'text-blue-500', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  short_answer:    { bg: 'bg-amber-50', text: 'text-amber-500', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  matching:        { bg: 'bg-pink-50', text: 'text-pink-500', icon: 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4' },
  fill_blank:      { bg: 'bg-orange-50', text: 'text-orange-500', icon: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' }
}

const detailedBreakdown = computed(() => {
  if (!resultData.value) return []
  
  const stats: Record<string, { totalQs: number, correctQs: number, marksEarned: number, marksTotal: number, hasPendingQs: boolean }> = {}
  
  for (const q of resultData.value.questionsReview) {
    const type = q.type || 'unknown'
    if (!stats[type]) {
      stats[type] = { totalQs: 0, correctQs: 0, marksEarned: 0, marksTotal: 0, hasPendingQs: false }
    }
    if (q.type === 'matching' && q.totalCount) {
      stats[type].totalQs += (q.totalCount || 1)
      if (q.correctCount) {
        stats[type].correctQs += q.correctCount
      }
    } else {
      stats[type].totalQs++
      if (q.isCorrect === true) stats[type].correctQs++
    }
    
    if (q.gradingStatus === 'pending') {
      stats[type].hasPendingQs = true
    }
    stats[type].marksEarned += (q.earnedMarks || 0)
    stats[type].marksTotal += (q.marks || 0)
  }
  
  return Object.entries(stats).map(([type, data]) => {
    let pct = 0
    if (data.marksTotal > 0 && !data.hasPendingQs) {
      pct = Math.round((data.marksEarned / data.marksTotal) * 100)
    }
    return {
      type,
      ...data,
      percentage: pct
    }
  })
})

onMounted(async () => {
  if (!activeExam.value) await examStore.fetchExams()
  const status = (activeExam.value as any)?.attemptStatus
  if (!activeExam.value || status === 'submitted' || status === 'graded' || status === 'published') {
    router.replace('/student')
  }
})

const handleExamCompleted = async (
  answers: Record<number, string>,
  _scored: number,
  _pct: number,
  isAuto = false
) => {
  if (!activeExam.value) return
  isAutoSubmitted.value = !!isAuto
  examDisplayName.value = activeExam.value.courseName
  const examId = activeExam.value.id
  try {
    const result = await examStore.submitExam(examId, answers)
    profile.value.creditsCompleted += 5
    profile.value.cgpa = Math.min(4.00, Number((profile.value.cgpa + 0.02).toFixed(2)))
    resultData.value = result
    showModal.value  = true
  } catch (err) {
    console.error('Submit exam error', err)
    router.replace('/student')
  }
}

const handleCancel = () => router.push('/student')
const goHome       = () => { showModal.value = false; router.push('/student') }
const goToExams    = () => { showModal.value = false; router.push('/student/results') }

const getPctColor = (pct: number, isPending: boolean) => {
  if (isPending) return 'bg-slate-100 text-slate-500'
  if (pct >= 85) return 'bg-emerald-100 text-emerald-700'
  if (pct >= 60) return 'bg-indigo-100 text-indigo-700'
  if (pct >= 50) return 'bg-amber-100 text-amber-700'
  return 'bg-pink-100 text-pink-700'
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans antialiased">

    <ExamConsole
      v-if="activeExam && !showModal"
      :exam="activeExam"
      @cancel="handleCancel"
      @submit-exam="handleExamCompleted"
    />

    <div v-else-if="!showModal" class="flex items-center justify-center min-h-screen">
      <div class="text-center space-y-3">
        <div class="w-10 h-10 border-4 border-slate-200 border-t-slate-600 rounded-full animate-spin mx-auto"></div>
        <p class="text-sm text-slate-400">Loading exam...</p>
      </div>
    </div>

    <!-- ── Result Modal ───────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div
          v-if="showModal && resultData"
          class="fixed inset-0 z-[9999] flex items-center justify-center p-3 sm:p-4"
          style="background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);"
          @click.self="goHome"
        >
          <div class="result-card relative w-full max-w-[540px] max-h-[92vh] overflow-y-auto bg-white rounded-3xl shadow-2xl p-4 sm:p-6 sm:p-7">
            
            <!-- Close Button with 44px touch target -->
            <button @click="goHome" class="absolute top-3.5 right-3.5 sm:top-5 sm:right-5 w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/20" aria-label="Close modal">
              <svg class="w-4 h-4 sm:w-3.5 sm:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- Top Header (Checkmark & Title) -->
            <div class="text-center mt-1 mb-4 sm:mb-5">
              <div class="relative inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-500 mx-auto mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <!-- Simple confetti dots around it -->
                <div class="absolute -top-1 -left-3 w-1.5 h-1.5 rounded-full bg-amber-400"></div>
                <div class="absolute top-3 -right-3 w-1 h-1 rounded-full bg-indigo-400"></div>
                <div class="absolute -bottom-1 right-0 w-1.5 h-1.5 rounded-full bg-pink-400"></div>
                <div class="absolute bottom-1 -left-2 w-1 h-1 rounded-full bg-blue-400"></div>
              </div>
              <h2 class="text-lg sm:text-xl font-black text-slate-900 leading-tight">
                {{ isAutoSubmitted ? 'Time Expired — Exam Submitted Automatically!' : 'Exam Submitted Successfully!' }}
              </h2>
              <p class="text-xs sm:text-[13px] text-slate-500 mt-1 leading-relaxed">
                {{ isAutoSubmitted ? 'Your exam time limit has ended. Your answers were safely recorded and submitted.' : 'Your exam has been submitted. Here are your results.' }}
              </p>
            </div>

            <!-- Score / Result Box -->
            <div class="flex flex-col sm:flex-row border border-slate-100 rounded-2xl p-3 sm:p-4 bg-slate-50/50 mb-5 sm:mb-6 gap-3 sm:gap-0">
              <div class="flex-1 text-center border-b sm:border-b-0 sm:border-r border-slate-200 pb-3 sm:pb-0 sm:py-1">
                <p class="text-xs font-semibold text-slate-500 mb-0.5">Your Score</p>
                <p class="text-2xl sm:text-3xl font-black" :class="resultData.hasPending ? 'text-amber-500' : 'text-emerald-500'">
                  {{ resultData.hasPending ? 'Pending' : resultData.percentage + '%' }}
                </p>
                <p class="text-xs font-bold text-slate-700 mt-1">
                  {{ resultData.autoScore ?? resultData.score }} / {{ resultData.autoTotal ?? resultData.totalMarks }} Marks
                </p>
              </div>
              <div class="flex-1 text-center flex flex-col items-center justify-center sm:py-1">
                <p class="text-xs font-semibold text-slate-500 mb-0.5">Result</p>
                <div 
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold mb-1 mt-1"
                  :class="resultData.hasPending ? 'bg-amber-100 text-amber-700' : (resultData.status === 'Passed' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700')"
                >
                  {{ resultData.hasPending ? 'Review Pending ⏳' : (resultData.status === 'Passed' ? 'Passed 🏆' : 'Failed ❌') }}
                </div>
                <p class="text-[11px] text-slate-500 font-medium mt-1 px-2">
                  {{ resultData.hasPending ? 'Final result pending instructor review.' : (resultData.status === 'Passed' ? 'Great job! You have passed.' : 'Keep practicing, try again next time.') }}
                </p>
              </div>
            </div>

            <!-- Breakdown Table with Horizontal Scroll Container -->
            <div class="mb-5">
              <h3 class="text-[13px] font-bold text-slate-900 mb-2.5">Score Breakdown by Question Type</h3>
              
              <div class="overflow-x-auto -mx-1 px-1">
                <div class="min-w-[380px]">
                  <!-- Table Header -->
                  <div class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr] text-center text-[11px] font-semibold text-slate-500 pb-2 border-b border-slate-100">
                    <div class="text-left pl-1">Question Type</div>
                    <div>Total Qs</div>
                    <div>Correct</div>
                    <div>Obtained</div>
                    <div>Percentage</div>
                  </div>

                  <!-- Table Rows -->
                  <div class="space-y-0.5 mt-1">
                    <div
                      v-for="(row, idx) in detailedBreakdown"
                      :key="idx"
                      class="grid grid-cols-[2fr_1fr_1fr_1fr_1fr] text-center items-center py-1.5"
                    >
                      <!-- Type -->
                      <div class="flex items-center gap-2 text-left pl-1">
                        <div class="w-6 h-6 rounded-md flex items-center justify-center shrink-0" :class="(TYPE_ICONS[row.type] || TYPE_ICONS.multiple_choice).bg + ' ' + (TYPE_ICONS[row.type] || TYPE_ICONS.multiple_choice).text">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="(TYPE_ICONS[row.type] || TYPE_ICONS.multiple_choice).icon"/>
                          </svg>
                        </div>
                        <span class="text-[11px] font-bold text-slate-800 truncate">{{ TYPE_LABELS[row.type] || row.type }}</span>
                      </div>
                      <!-- Total Questions -->
                      <div class="text-[11px] font-medium text-slate-600">{{ row.totalQs }}</div>
                      <!-- Correct -->
                      <div class="text-[11px] font-bold" :class="row.hasPendingQs ? 'text-slate-400' : 'text-emerald-500'">
                        {{ row.hasPendingQs ? '-' : row.correctQs }}
                      </div>
                      <!-- Obtained -->
                      <div class="text-[11px] font-medium text-slate-600">
                        {{ row.marksEarned }} / {{ row.marksTotal }}
                      </div>
                      <!-- Percentage -->
                      <div class="flex justify-center">
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded" :class="getPctColor(row.percentage, row.hasPendingQs)">
                          {{ row.hasPendingQs ? 'Pending' : row.percentage + '%' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Info Note -->
            <div class="bg-indigo-50/70 border border-indigo-100 rounded-xl p-3 flex gap-2.5 mb-5 items-start">
              <svg class="w-4 h-4 text-indigo-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
              </svg>
              <p class="text-[11px] text-indigo-700 font-medium leading-relaxed" v-if="resultData.hasPending">
                Your exam contains <strong>Short Answer</strong> or <strong>Fill in the Blank</strong> questions which require manual grading. Your final grade will be updated once the instructor publishes the results.
              </p>
              <p class="text-[11px] text-indigo-700 font-medium leading-relaxed" v-else>
                You can view the detailed results and explanation in your exam history once the instructor publishes them.
              </p>
            </div>

            <!-- Action Buttons with 44px min-height -->
            <div class="flex flex-col sm:flex-row gap-2.5 sm:gap-3">
              <button
                @click="goToExams"
                class="flex-1 min-h-[44px] py-2.5 px-4 rounded-xl border-2 border-indigo-100 text-indigo-600 font-bold text-[13px] hover:bg-indigo-50 transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                View My Exams
              </button>
              <button
                @click="goHome"
                class="flex-1 min-h-[44px] py-2.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[13px] transition-colors flex items-center justify-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Go to Dashboard
              </button>
            </div>

          </div>
        </div>
      </Transition>
    </Teleport>

  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from,  .fade-leave-to      { opacity: 0; }

.result-card {
  animation: scale-up 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
}
@keyframes scale-up {
  from { transform: scale(0.95); opacity: 0; }
  to   { transform: scale(1);    opacity: 1; }
}
</style>

