<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { ActiveExam, Question } from '../types'
import wolloLogo from '@/assets/images/logo.png'
import ScientificCalculator from './ScientificCalculator.vue'
import { useStudentExamStore } from '../store/studentExamStore'

const props = defineProps<{
  exam: ActiveExam
}>()

const emit = defineEmits<{
  (e: 'cancel'): void
  (e: 'submit-exam', answers: Record<number, string>, scores: number, percentage: number, isAuto?: boolean): void
}>()

const groupQuestionsByInstruction = (qs: Question[]) => {
  const grouped: Question[] = []
  const seenInstructions = new Set<string>()
  const noInstructionQs: Question[] = []

  qs.forEach(q => {
    const inst = (q as any).instruction || ''
    if (!inst) {
      noInstructionQs.push(q)
    } else if (!seenInstructions.has(inst)) {
      seenInstructions.add(inst)
      grouped.push(...qs.filter(x => ((x as any).instruction || '') === inst))
    }
  })
  
  return [...grouped, ...noInstructionQs]
}

const isShuffleQuestions = computed(() => {
  const s = (props.exam as any).settings || {}
  return Boolean(s.shuffleQuestions ?? s.shuffle_questions)
})

const questions = ref<Question[]>(
  isShuffleQuestions.value ? props.exam.questions : groupQuestionsByInstruction(props.exam.questions)
)
const currentIndex = ref<number>(0)
const answers = ref<Record<number, string>>({})
const matchingAnswers = ref<Record<number, Record<number, string>>>({}) // for matching questions: qId -> {pairIndex -> selectedRight}
const flagged = ref<Record<number, boolean>>({})

const examStore = useStudentExamStore()

// Restore saved answers from backend (for reconnecting students)
const restoreSavedAnswers = () => {
  const saved = (props.exam as any).savedAnswers
  if (saved && typeof saved === 'object' && Object.keys(saved).length > 0) {
    Object.entries(saved).forEach(([qId, ans]) => {
      const numId = Number(qId)
      const strAns = String(ans)
      answers.value[numId] = strAns
      if (strAns.includes(':')) {
        if (!matchingAnswers.value[numId]) matchingAnswers.value[numId] = {}
        strAns.split(',').forEach(part => {
          const [idx, val] = part.split(':')
          if (idx !== undefined && val !== undefined) {
            matchingAnswers.value[numId][Number(idx)] = val.trim()
          }
        })
      }
    })
  }
}

// Calculate initial seconds remaining using authoritative server values when available
const calculateInitialSeconds = () => {
  // 1st priority: server-provided remaining_seconds (accurate, includes extra time)
  const remaining = (props.exam as any).remainingSeconds
  if (remaining !== null && remaining !== undefined && !isNaN(Number(remaining))) {
    return Math.max(0, Number(remaining))
  }
  // 2nd priority: calculate from adjustedDeadline (includes extra time)
  const adjustedDeadline = (props.exam as any).adjustedDeadline
  if (adjustedDeadline) {
    const deadlineMs = new Date(adjustedDeadline).getTime()
    if (!isNaN(deadlineMs)) {
      return Math.max(0, Math.floor((deadlineMs - Date.now()) / 1000))
    }
  }
  // 3rd priority: calculate from startedAt + original duration (no extra time)
  const startedAt = (props.exam as any).startedAt
  if (startedAt) {
    const startedMs = new Date(startedAt).getTime()
    if (!isNaN(startedMs)) {
      const elapsedSeconds = Math.floor((Date.now() - startedMs) / 1000)
      const totalSeconds = props.exam.durationMinutes * 60
      return Math.max(0, totalSeconds - elapsedSeconds)
    }
  }
  return props.exam.durationMinutes * 60
}

const secondsRemaining = ref<number>(calculateInitialSeconds())
const showConfirmSubmit = ref<boolean>(false)
const isSubmitting = ref<boolean>(false)
const isAutoSubmitting = ref<boolean>(false)
const tabSwitches = ref<number>(0)

// ── Connection recovery state ───────────────────────────────────────────────
const isOffline = ref<boolean>(false)
const isCancelled = ref<boolean>((props.exam as any).isCancelled ?? false)
const isPaused = ref<boolean>(false)
const offlineMessage = ref<string>('Connection lost. Please reconnect. Your unsynchronised answers are preserved.')
const disconnectedAt = ref<string | null>(null)
let heartbeatInterval: number | null = null
let reconnectVerifyTimeout: number | null = null

const startHeartbeat = () => {
  stopHeartbeat()
  heartbeatInterval = window.setInterval(runHeartbeat, 30_000)
}

const stopHeartbeat = () => {
  if (heartbeatInterval) { clearInterval(heartbeatInterval); heartbeatInterval = null }
}

const runHeartbeat = async () => {
  if (isSubmitting.value || isAutoSubmitting.value || isOffline.value) return
  const examId = (props.exam as any).id
  const result = await examStore.sendHeartbeat(examId, { ...answers.value })
  if (!result) return

  // Sync authoritative timer if drift > 5s
  if (result.remaining_seconds !== undefined && result.remaining_seconds !== null) {
    const serverRemaining = Math.max(0, Number(result.remaining_seconds))
    if (Math.abs(serverRemaining - secondsRemaining.value) > 5) {
      secondsRemaining.value = serverRemaining
    }
  }

  // Handle exam cancellation
  if (result.is_cancelled) {
    isCancelled.value = true
    stopHeartbeat()
    if (timer) { clearInterval(timer); timer = null }
    return
  }

  // Handle exam paused
  if (result.is_paused) {
    if (!isPaused.value) {
      isPaused.value = true
      if (timer) { clearInterval(timer); timer = null }
      stopHeartbeat()
    }
    return
  }

  // Resume if exam was unpaused
  if (isPaused.value && !result.is_paused) {
    isPaused.value = false
    secondsRemaining.value = Math.max(0, Number(result.remaining_seconds ?? secondsRemaining.value))
    startTimer()
    startHeartbeat()
  }
}

const handleOffline = () => {
  if (isOffline.value) return
  isOffline.value = true
  disconnectedAt.value = new Date().toISOString()
  offlineMessage.value = 'Connection lost. Please reconnect. Your unsynchronised answers are preserved.'
  stopHeartbeat()
  if (timer) { clearInterval(timer); timer = null }
}

const handleOnline = () => {
  if (reconnectVerifyTimeout) clearTimeout(reconnectVerifyTimeout)
  offlineMessage.value = 'Reconnecting… verifying connection with server…'
  // Small delay to let network settle, then verify with real backend call
  reconnectVerifyTimeout = window.setTimeout(async () => {
    const examId = (props.exam as any).id
    const result = await examStore.sendReconnect(
      examId,
      { ...answers.value },
      disconnectedAt.value ?? new Date().toISOString()
    )
    if (result) {
      isOffline.value = false
      disconnectedAt.value = null
      if (result.remaining_seconds !== undefined && result.remaining_seconds !== null) {
        secondsRemaining.value = Math.max(0, Number(result.remaining_seconds))
      }
      if (!isCancelled.value && !isPaused.value) {
        startTimer()
        startHeartbeat()
      }
    } else {
      offlineMessage.value = 'Still disconnected. Please check your internet connection.'
    }
  }, 2000)
}

// Exam settings safely parsed
const settings = ref<Record<string, any>>((props.exam as any).settings || {})

const parsedSettings = computed(() => {
  let s = (props.exam as any)?.settings || settings.value
  if (typeof s === 'string') {
    try { s = JSON.parse(s) } catch { s = {} }
  }
  return s && typeof s === 'object' ? s : {}
})

const isCalculatorAllowed = computed(() => {
  const s = parsedSettings.value
  const val = s.allowCalculator ?? s.allow_calculator
  return val === true || val === 1 || val === '1' || val === 'true'
})

const isFullscreenEnabled = computed(() => {
  const s = parsedSettings.value
  const val = s.enableFullscreenMode ?? s.enable_fullscreen_mode
  return val === true || val === 1 || val === '1' || val === 'true'
})

const isTabMonitoringEnabled = computed(() => {
  const s = parsedSettings.value
  const val = s.enableBrowserTabMonitoring ?? s.enable_browser_tab_monitoring
  return val === true || val === 1 || val === '1' || val === 'true'
})

const isShowReviewScreenEnabled = computed(() => {
  const s = parsedSettings.value
  const val = s.showReviewScreen ?? s.show_review_screen
  if (val === undefined || val === null) return true
  return val === true || val === 1 || val === '1' || val === 'true'
})

// Review Screen State
const showReviewModal = ref<boolean>(false)
const reviewFilter = ref<'all' | 'unanswered' | 'flagged'>('all')

const filteredReviewQuestions = computed(() => {
  return questions.value.map((q, idx) => ({ q, index: idx })).filter(({ q }) => {
    if (reviewFilter.value === 'unanswered') {
      return !isQuestionAnswered(q.id)
    }
    if (reviewFilter.value === 'flagged') {
      return Boolean(flagged.value[q.id])
    }
    return true
  })
})

const answeredQuestionsCount = computed(() => {
  return questions.value.filter((q: any) => isQuestionAnswered(q.id)).length
})

const unansweredQuestionsCount = computed(() => {
  return Math.max(0, questions.value.length - answeredQuestionsCount.value)
})

const flaggedQuestionsCount = computed(() => {
  return Object.values(flagged.value).filter(Boolean).length
})

const jumpToQuestionFromReview = (index: number) => {
  showReviewModal.value = false
  goToQuestion(index)
}

const handleInitiateSubmit = () => {
  if (isShowReviewScreenEnabled.value) {
    showReviewModal.value = true
  } else {
    showConfirmSubmit.value = true
  }
}

const getAnswerSummaryText = (q: Question): string => {
  const ans = answers.value[q.id]
  if (!isQuestionAnswered(q.id)) return 'Not answered yet'
  if (q.type === 'multiple_choice' || q.type === 'multiple-choice') {
    return `Selected: Option ${ans}`
  }
  if (q.type === 'true_false' || q.type === 'true-false') {
    return ans === 'A' ? 'Selected: True' : 'Selected: False'
  }
  if (q.type === 'matching') {
    const pairs = (q as any).pairs || (q as any).options || []
    const answeredCount = Object.keys(matchingAnswers.value[q.id] || {}).length
    return `${answeredCount} of ${pairs.length} pairs matched`
  }
  const str = String(ans).trim()
  return str.length > 35 ? `Answer: "${str.slice(0, 35)}..."` : `Answer: "${str}"`
}

// Fullscreen & Proctor Monitoring
const isFullscreen = ref<boolean>(false)
const showFullscreenPrompt = ref<boolean>(false)
const showFullscreenExitWarning = ref<boolean>(false)
const fullscreenExits = ref<number>(0)

const showTabSwitchWarning = ref<boolean>(false)
const lastSwitchType = ref<'tab' | 'window'>('tab')

const enterFullscreen = async () => {
  try {
    const docEl = document.documentElement as any
    if (docEl.requestFullscreen) {
      await docEl.requestFullscreen()
    } else if (docEl.webkitRequestFullscreen) {
      await docEl.webkitRequestFullscreen()
    } else if (docEl.msRequestFullscreen) {
      await docEl.msRequestFullscreen()
    }
    isFullscreen.value = true
    showFullscreenPrompt.value = false
    showFullscreenExitWarning.value = false
  } catch (err) {
    console.warn("Fullscreen request error:", err)
    showFullscreenPrompt.value = false
  }
}

const exitFullscreen = () => {
  try {
    if (document.fullscreenElement) {
      document.exitFullscreen().catch(() => {})
    }
  } catch {}
}

const handleFullscreenChange = () => {
  const inFull = Boolean(document.fullscreenElement || (document as any).webkitFullscreenElement)
  isFullscreen.value = inFull
  if (isFullscreenEnabled.value && !inFull && !isSubmitting.value && !isCancelled.value && !isOffline.value) {
    fullscreenExits.value++
    showFullscreenExitWarning.value = true
  }
}

const handleVisibilityChange = () => {
  if (!isTabMonitoringEnabled.value) return
  if (document.hidden) {
    tabSwitches.value++
    lastSwitchType.value = 'tab'
    showTabSwitchWarning.value = true
  }
}

const handleWindowBlur = () => {
  if (!isTabMonitoringEnabled.value) return
  if (isSubmitting.value || isCancelled.value || showFullscreenPrompt.value) return
  tabSwitches.value++
  lastSwitchType.value = 'window'
  showTabSwitchWarning.value = true
}

const activeQuestion = computed(() => questions.value[currentIndex.value])

const activePairs = computed(() => {
  if (!activeQuestion.value) return []
  const q = activeQuestion.value as any
  if (Array.isArray(q.pairs) && q.pairs.length > 0) return q.pairs
  if (Array.isArray(q.question_data?.pairs) && q.question_data.pairs.length > 0) return q.question_data.pairs
  if (Array.isArray(q.options) && q.options.length > 0 && (q.options[0]?.left !== undefined || q.options[0]?.right !== undefined)) {
    return q.options
  }
  const colA = q.column_a || q.question_data?.column_a || []
  const colB = q.column_b || q.question_data?.column_b || []
  if (Array.isArray(colA) && colA.length > 0) {
    const maxLen = Math.max(colA.length, Array.isArray(colB) ? colB.length : 0)
    const result: { left: string; right: string }[] = []
    for (let i = 0; i < maxLen; i++) {
      const leftObj = colA[i]
      const leftText = typeof leftObj === 'object' ? (leftObj?.text || leftObj?.label || '') : String(leftObj || '')
      const rightObj = Array.isArray(colB) ? colB[i] : null
      const rightText = typeof rightObj === 'object' ? (rightObj?.text || rightObj?.label || '') : String(rightObj || '')
      if (leftText || rightText) {
        result.push({ left: leftText, right: rightText })
      }
    }
    return result
  }
  return []
})

const navigationGroups = computed(() => {
  const groups: { instruction: string; items: { q: Question; globalIndex: number }[] }[] = []
  
  questions.value.forEach((q, idx) => {
    const inst = (q as any).instruction || 'General Questions'
    let group = groups.find(g => g.instruction === inst)
    if (!group) {
      group = { instruction: inst, items: [] }
      groups.push(group)
    }
    group.items.push({ q, globalIndex: idx })
  })
  
  return groups
})

let timer: number | null = null

const webcamVideo = ref<HTMLVideoElement | null>(null)
const mediaStream = ref<MediaStream | null>(null)
const isCameraActive = ref(false)

const startWebcam = async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false })
    mediaStream.value = stream
    if (webcamVideo.value) {
      webcamVideo.value.srcObject = stream
      isCameraActive.value = true
    }
  } catch (err) {
    console.error("Webcam access denied or error:", err)
    alert("Camera access is required for proctoring. Please allow camera permissions.")
  }
}

const stopWebcam = () => {
  if (mediaStream.value) {
    mediaStream.value.getTracks().forEach(track => track.stop())
    mediaStream.value = null
    isCameraActive.value = false
  }
}


// Live timer countdown — extracted so it can be called after reconnect/resume
const startTimer = () => {
  if (timer) return // already running
  if (secondsRemaining.value <= 0) {
    triggerAutoSubmit()
    return
  }
  timer = window.setInterval(() => {
    if (secondsRemaining.value <= 1) {
      if (timer) { clearInterval(timer); timer = null }
      secondsRemaining.value = 0
      triggerAutoSubmit()
    } else {
      secondsRemaining.value--
    }
  }, 1000)
}

onMounted(() => {
  // Restore any answers saved on backend (for reconnected students)
  restoreSavedAnswers()

  startWebcam()

  // Start countdown (skips if cancelled/paused from initial load)
  if (!isCancelled.value && !isPaused.value) {
    if (secondsRemaining.value <= 0) {
      secondsRemaining.value = 0
      triggerAutoSubmit()
    } else {
      startTimer()
    }
  }

  // Start heartbeat
  startHeartbeat()

  // Register connection monitoring events
  window.addEventListener('offline', handleOffline)
  window.addEventListener('online', handleOnline)

  // Fullscreen enforcement
  if (isFullscreenEnabled.value) {
    document.addEventListener('fullscreenchange', handleFullscreenChange)
    document.addEventListener('webkitfullscreenchange', handleFullscreenChange)
    if (!document.fullscreenElement && !(document as any).webkitFullscreenElement) {
      showFullscreenPrompt.value = true
    }
  }

  // Browser Tab & Window monitoring
  if (isTabMonitoringEnabled.value) {
    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('blur', handleWindowBlur)
  }
})

onUnmounted(() => {
  if (timer) { clearInterval(timer); timer = null }
  stopWebcam()
  stopHeartbeat()
  if (reconnectVerifyTimeout) clearTimeout(reconnectVerifyTimeout)
  window.removeEventListener('offline', handleOffline)
  window.removeEventListener('online', handleOnline)

  if (isFullscreenEnabled.value) {
    document.removeEventListener('fullscreenchange', handleFullscreenChange)
    document.removeEventListener('webkitfullscreenchange', handleFullscreenChange)
    exitFullscreen()
  }

  if (isTabMonitoringEnabled.value) {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    window.removeEventListener('blur', handleWindowBlur)
  }
})


// --- Security Settings Enforcement ---
const handleRightClick = (e: MouseEvent) => {
  if (settings.value.disableRightClick || settings.value.disable_right_click) {
    e.preventDefault()
    return false
  }
}

const handleCopy = (e: ClipboardEvent) => {
  if (settings.value.disableCopyPaste || settings.value.disable_copy_paste) {
    e.preventDefault()
  }
}

const handlePaste = (e: ClipboardEvent) => {
  if (settings.value.disableCopyPaste || settings.value.disable_copy_paste) {
    e.preventDefault()
  }
}

onMounted(() => {
  document.addEventListener('contextmenu', handleRightClick)
  document.addEventListener('copy', handleCopy as EventListener)
  document.addEventListener('paste', handlePaste as EventListener)
})

onUnmounted(() => {
  document.removeEventListener('contextmenu', handleRightClick)
  document.removeEventListener('copy', handleCopy as EventListener)
  document.removeEventListener('paste', handlePaste as EventListener)
})

const formatTime = (secs: number) => {
  const hours = Math.floor(secs / 3600)
  const minutes = Math.floor((secs % 3600) / 60)
  const seconds = secs % 60
  return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
}

let lastNavTime = 0

const nextQuestion = (e?: Event) => {
  if (e) {
    if (e.type === 'pointerup' || e.type === 'touchend') {
      e.preventDefault()
    }
    e.stopPropagation()
  }

  const now = Date.now()
  if (now - lastNavTime < 300) return
  lastNavTime = now

  if (document.activeElement && document.activeElement instanceof HTMLElement) {
    document.activeElement.blur()
  }

  if (currentIndex.value < questions.value.length - 1) {
    currentIndex.value++
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const prevQuestion = (e?: Event) => {
  if (e) {
    if (e.type === 'pointerup' || e.type === 'touchend') {
      e.preventDefault()
    }
    e.stopPropagation()
  }

  const now = Date.now()
  if (now - lastNavTime < 300) return
  lastNavTime = now

  if (document.activeElement && document.activeElement instanceof HTMLElement) {
    document.activeElement.blur()
  }

  if (currentIndex.value > 0) {
    currentIndex.value--
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const goToQuestion = (index: number, e?: Event) => {
  if (e) {
    if (e.type === 'pointerup' || e.type === 'touchend') {
      e.preventDefault()
    }
    e.stopPropagation()
  }

  const now = Date.now()
  if (now - lastNavTime < 300) return
  lastNavTime = now

  if (document.activeElement && document.activeElement instanceof HTMLElement) {
    document.activeElement.blur()
  }

  if (index >= 0 && index < questions.value.length) {
    currentIndex.value = index
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
}

const handleSelectOption = (index: number) => {
  answers.value[activeQuestion.value.id] = String.fromCharCode(65 + index)
}

const handleTrueFalse = (value: 'A' | 'B') => {
  answers.value[activeQuestion.value.id] = value
}

const handleMatchingAnswer = (qId: number, pairIndex: number, rightValue: string) => {
  if (!matchingAnswers.value[qId]) matchingAnswers.value[qId] = {}
  matchingAnswers.value[qId][pairIndex] = rightValue
  // Serialize matching answers as "0:RightA,1:RightB,..." for submission
  const pairs = activePairs.value
  if (pairs && pairs.length > 0) {
    answers.value[qId] = pairs
      .map((_: any, i: number) => `${i}:${matchingAnswers.value[qId]?.[i] || ''}`)
      .join(',')
  } else {
    answers.value[qId] = Object.entries(matchingAnswers.value[qId])
      .map(([i, val]) => `${i}:${val}`)
      .join(',')
  }
}

const toggleFlag = () => {
  flagged.value[activeQuestion.value.id] = !flagged.value[activeQuestion.value.id]
}

const isQuestionAnswered = (qId: number) => {
  const ans = answers.value[qId]
  if (ans === undefined || ans === null) return false
  const str = String(ans).trim()
  if (str === '') return false
  if (str.includes(':')) {
    return str.split(',').some(part => {
      const val = part.split(':')[1]
      return val !== undefined && val.trim() !== ''
    })
  }
  return true
}

const triggerAutoSubmit = () => {
  if (isAutoSubmitting.value || isSubmitting.value) return
  isAutoSubmitting.value = true
  isSubmitting.value = true
  showConfirmSubmit.value = false
  showReviewModal.value = false
  if (isFullscreenEnabled.value) {
    exitFullscreen()
  }
  processAndSubmit(true)
}

const processAndSubmit = (isAuto = false) => {
  if (!isAuto && isSubmitting.value) return
  isSubmitting.value = true
  if (isFullscreenEnabled.value) {
    exitFullscreen()
  }

  let correctCount = 0
  questions.value.forEach(q => {
    if (q.type === 'multiple-choice') {
      if (answers.value[q.id] === q.correctAnswer) {
        correctCount += 1
      }
    } else {
      // Free text: simulate generous grading if they input anything
      if (answers.value[q.id] && answers.value[q.id].length > 10) {
        correctCount += 1
      }
    }
  })

  // Score out of 50
  const scoredMarks = correctCount * 10
  const percentage = (scoredMarks / 50) * 100

  emit('submit-exam', answers.value, scoredMarks, percentage, isAuto)
}

const confirmCancel = () => {
  if (confirm("Are you sure you want to exit the Exam Console? Your current draft options will be saved, but the countdown continues.")) {
    if (isFullscreenEnabled.value) {
      exitFullscreen()
    }
    emit('cancel')
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white pb-16">

    <!-- ── Exam Cancelled Overlay ─────────────────────────────────────────── -->
    <Transition name="fade">
      <div v-if="isCancelled" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/90 backdrop-blur-sm">
        <div class="max-w-md w-full mx-4 bg-slate-900 border border-red-500/40 rounded-2xl p-8 text-center shadow-2xl">
          <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M5.636 5.636a9 9 0 000 12.728M9 10h.01M15 10h.01M9.172 14.828A4 4 0 0114.828 14.828" />
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-red-400 mb-3">Exam Cancelled</h2>
          <p class="text-slate-300 mb-2">This examination has been cancelled by an administrator.</p>
          <p class="text-slate-400 text-sm">Any answers you submitted before cancellation have been preserved. Please contact your instructor for further instructions.</p>
        </div>
      </div>
    </Transition>

    <!-- ── Exam Paused Overlay ────────────────────────────────────────────── -->
    <Transition name="fade">
      <div v-if="isPaused && !isCancelled" class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/85 backdrop-blur-sm">
        <div class="max-w-md w-full mx-4 bg-slate-900 border border-amber-500/40 rounded-2xl p-8 text-center shadow-2xl">
          <div class="w-16 h-16 rounded-full bg-amber-500/20 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-amber-400 mb-3">Exam Paused</h2>
          <p class="text-slate-300 mb-2">The examination has been temporarily paused by the administrator.</p>
          <p class="text-slate-400 text-sm">Please wait. The exam will resume automatically and your remaining time will be adjusted accordingly.</p>
          <div class="mt-5 flex items-center justify-center gap-2 text-amber-400 text-sm">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
            Waiting for administrator to resume…
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Offline / Connection Lost Overlay ─────────────────────────────── -->
    <Transition name="fade">
      <div v-if="isOffline && !isCancelled" class="fixed inset-0 z-[190] flex items-center justify-center bg-slate-950/80 backdrop-blur-sm">
        <div class="max-w-md w-full mx-4 bg-slate-900 border border-orange-500/40 rounded-2xl p-8 text-center shadow-2xl">
          <div class="w-16 h-16 rounded-full bg-orange-500/20 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M2 2l20 20M8.464 8.464A5 5 0 006.11 12m9.9 4H12m0 0l-3-3m3 3l3-3" />
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-orange-400 mb-3">Connection Lost</h2>
          <p class="text-slate-300 mb-3">{{ offlineMessage }}</p>
          <p class="text-slate-400 text-sm">The timer is paused locally. Once you reconnect, the system will calculate your interruption time and notify your instructor automatically.</p>
          <div class="mt-5 flex items-center justify-center gap-2 text-orange-400 text-sm">
            <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
            Waiting for connection…
          </div>
        </div>
      </div>
    </Transition>

    <!-- Top Console Bar -->
    <header class="border-b border-slate-800 bg-slate-950 px-3 sm:px-6 py-3 sm:py-4 flex flex-wrap items-center justify-between gap-2.5 sm:gap-4">
      <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
        <!-- Wollo University Logo -->
        <div class="flex h-9 w-9 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-white overflow-hidden shadow-md shrink-0">
          <img
            :src="wolloLogo"
            alt="Wollo University"
            class="h-8 w-8 sm:h-9 sm:w-9 object-contain"
          />
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-1.5 sm:gap-2">
            <span class="text-[9px] sm:text-[10px] font-black tracking-widest text-slate-400 uppercase leading-none">Wollo University</span>
            <span class="hidden xs:inline text-[9px] sm:text-[10px] font-bold tracking-widest text-slate-500 uppercase leading-none">· Exam Portal</span>
            <span class="h-2 w-2 rounded-full bg-rose-500 animate-ping shrink-0"></span>
          </div>
          <h2 class="text-xs sm:text-sm font-bold text-white truncate max-w-[200px] xs:max-w-xs sm:max-w-md mt-0.5">
            {{ exam.courseCode }}: {{ exam.courseName }}
          </h2>
        </div>
      </div>

      <!-- Live Timer and Submit Block -->
      <div class="flex items-center gap-2 sm:gap-4 shrink-0">
        <div class="flex items-center gap-1.5 sm:gap-2 rounded-lg bg-slate-800 px-2.5 sm:px-3.5 py-1.5 border border-slate-700 min-h-[38px] sm:min-h-[40px]">
          <svg class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-rose-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span class="font-mono text-xs sm:text-sm font-extrabold text-white">
            {{ formatTime(secondsRemaining) }}
          </span>
        </div>

        <button
          @click="handleInitiateSubmit"
          :disabled="isSubmitting"
          class="rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed px-3 sm:px-4 py-2 text-xs font-bold text-white transition-all shadow-md shadow-indigo-600/10 active:scale-[0.98] min-h-[38px] sm:min-h-[40px] flex items-center justify-center"
        >
          <span class="hidden sm:inline">{{ isSubmitting ? 'Submitting...' : 'Submit Response Sheet' }}</span>
          <span class="sm:hidden">{{ isSubmitting ? 'Submitting...' : 'Submit' }}</span>
        </button>
      </div>
    </header>

    <!-- Main Console Arena -->
    <div class="flex-1 grid grid-cols-1 xl:grid-cols-12 gap-4 sm:gap-6 max-w-[1800px] w-full mx-auto p-3 sm:p-6 overflow-hidden">
      
      <!-- Left Side: Question Navigation & Live Feed Mockup (3 Cols, below question on small screens) -->
      <div class="order-2 xl:order-1 xl:col-span-3 space-y-4 sm:space-y-6 flex flex-col justify-start">
        
        <!-- Visual Proctoring System -->
        <div class="rounded-xl border border-slate-800 bg-slate-950 p-4 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
              <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span>
              Webcam Proctor Active
            </span>
            <span class="text-[9px] font-mono font-bold text-emerald-400 uppercase bg-emerald-950/50 px-1.5 py-0.5 rounded">
              AI MATCHED 100%
            </span>
          </div>

          <!-- Simulated Live Frame -->
          <div class="relative aspect-video rounded-lg overflow-hidden bg-slate-800 border border-slate-700 flex items-center justify-center">
            
            <!-- Real Webcam Video Feed -->
            <video ref="webcamVideo" autoplay muted playsinline class="absolute inset-0 w-full h-full object-cover z-0"></video>

            <!-- Overlay graphics to simulate biometric analysis -->
            <div class="absolute inset-0 border border-indigo-500/30 m-4 rounded pointer-events-none z-10 shadow-[inset_0_0_20px_rgba(99,102,241,0.15)]"></div>
            <div class="absolute top-2 left-2 text-[8px] font-mono text-slate-400 bg-black/60 px-1.5 py-0.5 rounded z-10 backdrop-blur-sm">
              SECURE_ID: Kalkidan M.
            </div>
            <div class="absolute bottom-2 right-2 flex items-center gap-1 text-[8px] font-mono text-slate-400 bg-black/60 px-1.5 py-0.5 rounded z-10 backdrop-blur-sm">
              <span class="h-1 w-1 rounded-full bg-red-500 animate-pulse"></span>
              REC 1080p
            </div>

            <!-- Vector Mock Face / Camera Silhouette (Shown while loading) -->
            <div v-if="!isCameraActive" class="flex flex-col items-center text-slate-400 z-10 bg-slate-900/80 absolute inset-0 justify-center">
              <div class="h-16 w-16 rounded-full border-2 border-indigo-500/50 flex items-center justify-center bg-slate-900 relative">
                <div class="h-8 w-8 rounded-full bg-indigo-500/10 border border-indigo-500/30"></div>
                <!-- Facial recognition reticle lines -->
                <div class="absolute -top-1 -left-1 h-3 w-3 border-t-2 border-l-2 border-emerald-400"></div>
                <div class="absolute -top-1 -right-1 h-3 w-3 border-t-2 border-r-2 border-emerald-400"></div>
                <div class="absolute -bottom-1 -left-1 h-3 w-3 border-b-2 border-l-2 border-emerald-400"></div>
                <div class="absolute -bottom-1 -right-1 h-3 w-3 border-b-2 border-r-2 border-emerald-400"></div>
              </div>
              <span class="text-[9px] font-mono uppercase tracking-widest text-slate-400 mt-2">Initializing Camera...</span>
            </div>
          </div>
          
          <p class="text-[10px] text-slate-500 text-center leading-relaxed font-sans">
            Head positioning, ambient voice feeds, and multiple-monitors are audited continuously.
          </p>
        </div>

        <!-- Question Navigator Board -->
        <div class="rounded-xl border border-slate-800 bg-slate-950 p-5 space-y-4">
          <div>
            <h4 class="text-xs font-bold text-white uppercase tracking-wider">Question Navigation</h4>
            <p class="text-[11px] text-slate-500 mt-0.5">Jump directly to any section</p>
          </div>

          <div 
            class="space-y-5 overflow-y-auto pr-1 hide-scrollbar"
            style="max-height: 140px; scrollbar-width: none; -ms-overflow-style: none;"
          >
            <div v-for="(group, gIdx) in navigationGroups" :key="gIdx" class="space-y-3">
              <h5 class="text-[9px] font-bold text-indigo-400 uppercase tracking-widest border-b border-slate-800/50 pb-1.5" :title="group.instruction">
                {{ group.instruction }}
              </h5>
              <div class="grid grid-cols-5 gap-2">
                <button
                  v-for="item in group.items"
                  :key="item.q.id"
                  type="button"
                  @click="goToQuestion(item.globalIndex, $event)"
                  @touchend="goToQuestion(item.globalIndex, $event)"
                  :class="[
                    'relative flex h-10 w-full items-center justify-center rounded-lg border font-mono text-xs font-bold transition-all cursor-pointer touch-manipulation select-none',
                    item.globalIndex === currentIndex
                      ? 'bg-indigo-600 border-indigo-500 text-white shadow-md shadow-indigo-600/20 scale-105'
                      : isQuestionAnswered(item.q.id)
                      ? 'bg-emerald-950/40 border-emerald-800/80 text-emerald-400'
                      : 'bg-slate-900 border-slate-800 text-slate-400 hover:bg-slate-800 hover:border-slate-700'
                  ]"
                >
                  <span class="pointer-events-none">{{ item.globalIndex + 1 }}</span>
                  <!-- Small flag icon overlay -->
                  <span v-if="flagged[item.q.id]" class="absolute -top-1 -right-1 flex h-3 w-3 pointer-events-none">
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-amber-500 text-[8px] items-center justify-center text-slate-950 font-bold">!</span>
                  </span>
                </button>
              </div>
            </div>
          </div>

          <hr class="border-slate-800" />

          <!-- Navigator Legends -->
          <div class="space-y-2 text-[10px] font-bold uppercase tracking-wider text-slate-400">
            <div class="flex items-center gap-2">
              <span class="h-3 w-3 rounded bg-emerald-950/80 border border-emerald-800"></span>
              <span>Completed / Answered</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="h-3 w-3 rounded bg-slate-900 border border-slate-800"></span>
              <span>Unvisited / Empty</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="relative h-3 w-3 rounded bg-slate-900 border border-slate-800 flex items-center justify-center text-amber-500 font-black text-[9px]">!</span>
              <span>Flagged for review</span>
            </div>
          </div>
        </div>

        <!-- Quick Exit trigger (Moved from right side) -->
        <button
          @click="confirmCancel"
          class="w-full rounded-xl border border-rose-950 bg-rose-950/10 text-rose-400 py-2.5 text-xs font-bold hover:bg-rose-950/30 transition-all border-dashed mt-auto"
        >
          Suspend Session & Go Back
        </button>

      </div>

      <!-- Center: Main Active Question Board (9 Cols, top on mobile) -->
      <div class="order-1 xl:order-2 xl:col-span-9 space-y-4 sm:space-y-6 flex flex-col justify-between">
        
        <div class="flex flex-col rounded-xl border border-slate-800 bg-slate-950 p-4 sm:p-6 md:p-8 min-h-[480px]">
          <div class="flex-1 space-y-6">
          <template v-if="activeQuestion">
            <!-- Question Header meta -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-4">
              <div class="space-y-1">
                <span class="text-[10px] font-mono uppercase tracking-widest text-indigo-400 font-bold">
                  Section A: Core Evaluation
                </span>
                <h3 class="text-base font-black text-white">
                  Question {{ currentIndex + 1 }} of {{ questions.length }}
                </h3>
              </div>
              <div class="flex items-center gap-2">
                <span class="rounded bg-slate-800 px-2 py-1 text-[10px] font-mono text-slate-300 border border-slate-700">
                  Value: {{ activeQuestion.marks || 0 }} Marks
                </span>
                <button
                  @click="toggleFlag"
                  :class="[
                    'inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold transition-all border',
                    flagged[activeQuestion.id]
                      ? 'bg-amber-500/10 border-amber-500 text-amber-400'
                      : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-300'
                  ]"
                >
                  <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"></path></svg>
                  <span>{{ flagged[activeQuestion.id] ? 'Flagged' : 'Flag Question' }}</span>
                </button>
              </div>
            </div>

            <!-- The Question Statement -->
            <div class="space-y-1">
              <!-- Instruction badge -->
              <p v-if="(activeQuestion as any).instruction" class="text-xs font-semibold text-indigo-400 uppercase tracking-widest mb-1 flex items-center gap-1.5">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ (activeQuestion as any).instruction }}
              </p>
              <div class="text-base font-medium leading-relaxed text-slate-100 font-sans prose prose-invert prose-p:my-2 prose-ul:my-2" v-html="activeQuestion.text">
              </div>
            </div>

            <!-- Answer Controls -->
            <div class="mt-8 space-y-3">

              <!-- ── Multiple Choice ── -->
              <template v-if="activeQuestion.type === 'multiple-choice' && activeQuestion.options">
                <button
                  v-for="(option, index) in activeQuestion.options"
                  :key="index"
                  type="button"
                  @click="handleSelectOption(index)"
                  :class="[
                    'flex w-full items-start gap-4 rounded-xl border p-4 text-left transition-all text-xs font-medium font-sans cursor-pointer touch-manipulation select-none',
                    answers[activeQuestion.id] === String.fromCharCode(65 + index)
                      ? 'bg-indigo-600/15 border-indigo-500 text-indigo-200'
                      : 'bg-slate-900 border-slate-800 text-slate-300 hover:bg-slate-850 hover:border-slate-700'
                  ]"
                >
                  <span :class="[
                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-xs font-bold font-mono pointer-events-none',
                    answers[activeQuestion.id] === String.fromCharCode(65 + index)
                      ? 'bg-indigo-600 border-indigo-400 text-white'
                      : 'bg-slate-950 border-slate-700 text-slate-400'
                  ]">
                    {{ String.fromCharCode(65 + index) }}
                  </span>
                  <span class="leading-relaxed pt-0.5 pointer-events-none">{{ typeof option === 'object' ? (option as any).text || (option as any).label : option }}</span>
                </button>
              </template>

              <!-- ── True / False ── -->
              <template v-else-if="activeQuestion.type === 'true_false'">
                <button
                  type="button"
                  @click="handleTrueFalse('A')"
                  :class="[
                    'flex w-full items-center gap-4 rounded-xl border p-4 text-left transition-all text-xs font-bold font-sans cursor-pointer touch-manipulation select-none',
                    answers[activeQuestion.id] === 'A'
                      ? 'bg-emerald-500/15 border-emerald-500 text-emerald-300'
                      : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-emerald-700'
                  ]"
                >
                  <span :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-xs font-bold pointer-events-none', answers[activeQuestion.id] === 'A' ? 'bg-emerald-600 border-emerald-400 text-white' : 'bg-slate-950 border-slate-700 text-slate-400']">A</span>
                  <svg class="h-4 w-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  <span class="pointer-events-none">True</span>
                </button>
                <button
                  type="button"
                  @click="handleTrueFalse('B')"
                  :class="[
                    'flex w-full items-center gap-4 rounded-xl border p-4 text-left transition-all text-xs font-bold font-sans cursor-pointer touch-manipulation select-none',
                    answers[activeQuestion.id] === 'B'
                      ? 'bg-rose-500/15 border-rose-500 text-rose-300'
                      : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-rose-700'
                  ]"
                >
                  <span :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-xs font-bold pointer-events-none', answers[activeQuestion.id] === 'B' ? 'bg-rose-600 border-rose-400 text-white' : 'bg-slate-950 border-slate-700 text-slate-400']">B</span>
                  <svg class="h-4 w-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                  <span class="pointer-events-none">False</span>
                </button>
              </template>

              <!-- ── Fill in the Blank ── -->
              <template v-else-if="activeQuestion.type === 'fill_blank'">
                <div class="space-y-2">
                  <p class="text-xs text-purple-400 font-bold uppercase tracking-wider mb-3">Type the missing word or phrase below:</p>
                  <input
                    type="text"
                    v-model="answers[activeQuestion.id]"
                    placeholder="Enter your answer here..."
                    class="w-full rounded-xl border border-slate-700 bg-slate-900 px-5 py-4 text-sm font-sans text-slate-100 placeholder-slate-600 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 focus:outline-none transition-colors"
                  />
                  <p class="text-[10px] text-slate-500 font-mono px-1">Answer is auto-saved as you type.</p>
                </div>
              </template>

              <!-- ── Matching ── -->
              <template v-else-if="activeQuestion.type === 'matching' && activePairs.length > 0">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-10">
                  
                  <!-- Left side: Column A with selection dropdown -->
                  <div class="space-y-4">
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-4 pl-8">{{ (activeQuestion as any).columnA || (activeQuestion as any).question_data?.column_a_title || 'Column A' }}</h4>
                    <template v-for="(pair, pIdx) in activePairs" :key="pIdx">
                      <div
                        v-if="pair.left && String(pair.left).trim() !== ''"
                        class="flex items-center gap-3"
                      >
                        <!-- Number Identifier -->
                        <span class="text-xs font-bold text-slate-500 w-5 text-right shrink-0">{{ Number(pIdx) + 1 }}.</span>
                        
                        <!-- Column A box -->
                        <div class="flex-1 flex items-center justify-between rounded-xl border border-indigo-800/60 bg-indigo-950/30 pl-4 pr-2 py-2 text-xs font-semibold text-indigo-100 min-h-[48px] shadow-sm">
                          <!-- Use v-html to parse rich text tags properly -->
                          <span class="pr-3 leading-relaxed prose prose-invert prose-p:my-0 prose-sm" v-html="pair.left"></span>
                          
                            <!-- Identifier Dropdown (A, B, C...) -->
                            <div class="relative shrink-0 w-[52px]">
                              <select
                                :value="matchingAnswers[activeQuestion.id]?.[Number(pIdx)] || ''"
                                @change="handleMatchingAnswer(activeQuestion.id, Number(pIdx), ($event.target as HTMLSelectElement).value)"
                                class="w-full appearance-none rounded-lg border border-indigo-500/40 bg-indigo-900/50 pl-3 pr-6 py-2 text-[11px] font-black font-mono text-white focus:border-indigo-400 focus:bg-indigo-800 transition-colors cursor-pointer"
                              >
                                <option value="" disabled>-</option>
                                <option
                                  v-for="(_, rIdx) in activePairs"
                                  :key="'opt-'+rIdx"
                                  :value="String.fromCharCode(65 + Number(rIdx))"
                                >
                                  {{ String.fromCharCode(65 + Number(rIdx)) }}
                                </option>
                              </select>
                              <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                      </div>
                    </template>
                  </div>

                  <!-- Right side: Column B options display -->
                  <div class="space-y-4">
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-teal-400 mb-4">{{ (activeQuestion as any).columnB || (activeQuestion as any).question_data?.column_b_title || 'Column B' }}</h4>
                    <div class="flex flex-col gap-4 pt-1">
                      <div
                        v-for="(pair, rIdx) in activePairs"
                        :key="'right-'+rIdx"
                        class="flex items-center gap-4 text-xs bg-slate-900/50 p-2 pr-4 rounded-xl border border-slate-800/50"
                      >
                        <!-- Letter Identifier (A, B, C...) -->
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-800 border-2 border-slate-700 font-black font-mono text-[13px] text-white shadow-sm">
                          {{ String.fromCharCode(65 + Number(rIdx)) }}
                        </span>
                        <!-- Column B text parsed as HTML -->
                        <span class="font-medium text-slate-300 leading-relaxed prose prose-invert prose-p:my-0 prose-sm" v-html="pair.right"></span>
                      </div>
                    </div>
                  </div>

                </div>
              </template>

              <!-- ── Short Answer / Essay / Default ── -->
              <template v-else>
                <div class="space-y-2">
                  <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-2">Write your response below:</p>
                  <textarea
                    :rows="activeQuestion.type === 'short_answer' ? 4 : 8"
                    v-model="answers[activeQuestion.id]"
                    :placeholder="activeQuestion.type === 'short_answer' ? 'Write a concise, clear answer...' : 'Provide a detailed, well-structured response...'"
                    class="w-full rounded-xl border border-slate-800 bg-slate-900 p-4 text-xs font-sans text-slate-100 placeholder-slate-600 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                  ></textarea>
                  <div class="flex items-center justify-between text-[10px] text-slate-500 font-semibold font-mono px-1">
                    <span>Answer saved automatically</span>
                    <span>Characters: {{ (answers[activeQuestion.id] || '').length }}/1500</span>
                  </div>
                </div>
              </template>

            </div>
          </template>

          <div v-else class="flex flex-col items-center justify-center text-center h-full space-y-4 py-20">
             <div class="rounded-full bg-slate-900 p-4 border border-slate-800">
               <svg class="h-8 w-8 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
             </div>
             <div>
               <h3 class="text-xl font-bold text-white">No Questions Found</h3>
               <p class="text-slate-400 mt-2 max-w-md mx-auto">This exam does not contain any questions. It may have been published prematurely. Please contact your instructor.</p>
             </div>
          </div>
          </div> <!-- End flex-1 space-y-6 -->

          <!-- Bottom Question Controls (Moved Inside Card) -->
          <div class="mt-8 pt-6 border-t border-slate-800 flex items-center justify-between gap-2">
            <button
              type="button"
              @click="prevQuestion"
              @touchend="prevQuestion"
              :disabled="currentIndex === 0"
              class="inline-flex items-center gap-1.5 rounded-xl border border-slate-800 bg-slate-950 px-3.5 sm:px-4 py-2.5 text-xs font-bold text-slate-400 hover:text-white hover:border-slate-700 active:bg-slate-900 transition-colors disabled:opacity-30 disabled:pointer-events-none min-h-[44px] cursor-pointer touch-manipulation select-none"
            >
              <svg class="h-4 w-4 shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
              <span class="pointer-events-none">Back<span class="hidden sm:inline"> Question</span></span>
            </button>

            <span class="text-xs font-bold font-mono text-slate-500 hidden md:inline-block">
              SECURE BUFFER: ALL INPUTS SAVED REDUNDANTLY
            </span>

            <button
              v-if="currentIndex < questions.length - 1"
              type="button"
              @click="nextQuestion"
              @touchend="nextQuestion"
              class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 sm:px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 active:bg-indigo-800 transition-colors min-h-[44px] cursor-pointer touch-manipulation select-none"
            >
              <span class="pointer-events-none">Next<span class="hidden sm:inline"> Question</span></span>
              <svg class="h-4 w-4 shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
            <button
              v-else
              type="button"
              @click="handleInitiateSubmit"
              class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 sm:px-4 py-2.5 text-xs font-bold text-white hover:bg-emerald-700 transition-colors min-h-[44px] cursor-pointer touch-manipulation select-none"
            >
              <span>Finish & Submit</span>
              <svg class="h-4 w-4 shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- ── Interactive Examination Review Screen Modal ──────────────────────── -->
    <Transition name="fade">
      <div
        v-if="showReviewModal"
        class="fixed inset-0 z-[220] flex items-center justify-center bg-slate-950/90 p-3 sm:p-6 backdrop-blur-md overflow-y-auto"
      >
        <div class="relative w-full max-w-4xl max-h-[92vh] flex flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl text-slate-100 overflow-hidden my-auto">
          <!-- Modal Header -->
          <div class="flex items-center justify-between border-b border-slate-800 bg-slate-950/80 px-6 py-4 shrink-0">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/15 border border-indigo-500/30 text-indigo-400">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
              </div>
              <div>
                <h3 class="text-base font-bold text-white">Examination Review Sheet</h3>
                <p class="text-xs text-slate-400">Review your responses before final submission. Click on any question to revise it.</p>
              </div>
            </div>

            <div class="flex items-center gap-3">
              <div class="flex items-center gap-2 rounded-lg bg-slate-800/80 px-3 py-1.5 border border-slate-700">
                <svg class="h-3.5 w-3.5 text-rose-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span class="font-mono text-xs font-bold text-white">{{ formatTime(secondsRemaining) }}</span>
              </div>
              <button
                @click="showReviewModal = false"
                class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white transition-colors"
                title="Return to Exam"
              >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
              </button>
            </div>
          </div>

          <!-- Quick Stats Summary Row -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-4 sm:p-6 border-b border-slate-800/60 bg-slate-900/60 shrink-0">
            <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-3 text-center">
              <p class="text-[11px] font-medium text-slate-400">Total Questions</p>
              <p class="text-xl font-bold text-white mt-0.5">{{ questions.length }}</p>
            </div>
            <div class="rounded-xl border border-emerald-900/40 bg-emerald-950/20 p-3 text-center">
              <p class="text-[11px] font-medium text-emerald-400">Answered</p>
              <p class="text-xl font-bold text-emerald-400 mt-0.5">{{ answeredQuestionsCount }}</p>
            </div>
            <div class="rounded-xl border border-rose-900/40 bg-rose-950/20 p-3 text-center">
              <p class="text-[11px] font-medium text-rose-400">Unanswered</p>
              <p class="text-xl font-bold text-rose-400 mt-0.5">{{ unansweredQuestionsCount }}</p>
            </div>
            <div class="rounded-xl border border-amber-900/40 bg-amber-950/20 p-3 text-center">
              <p class="text-[11px] font-medium text-amber-400">Flagged</p>
              <p class="text-xl font-bold text-amber-400 mt-0.5">{{ flaggedQuestionsCount }}</p>
            </div>
          </div>

          <!-- Filter Tabs -->
          <div class="flex items-center gap-2 px-6 pt-4 pb-2 border-b border-slate-800/40 shrink-0">
            <button
              @click="reviewFilter = 'all'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
              :class="reviewFilter === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-800/60 text-slate-400 hover:text-white'"
            >
              All ({{ questions.length }})
            </button>
            <button
              @click="reviewFilter = 'unanswered'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
              :class="reviewFilter === 'unanswered' ? 'bg-rose-600 text-white' : 'bg-slate-800/60 text-slate-400 hover:text-white'"
            >
              Unanswered ({{ unansweredQuestionsCount }})
            </button>
            <button
              @click="reviewFilter = 'flagged'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors"
              :class="reviewFilter === 'flagged' ? 'bg-amber-600 text-white' : 'bg-slate-800/60 text-slate-400 hover:text-white'"
            >
              Flagged ({{ flaggedQuestionsCount }})
            </button>
          </div>

          <!-- Question List (Scrollable Area) -->
          <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-3">
            <div
              v-for="{ q, index } in filteredReviewQuestions"
              :key="q.id"
              class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 hover:border-slate-700 transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3"
            >
              <div class="flex items-start gap-3 min-w-0">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-800 border border-slate-700 font-mono text-xs font-bold text-white">
                  {{ index + 1 }}
                </span>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2 mb-1">
                    <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-slate-800 text-slate-400">
                      {{ q.type ? q.type.replace('_', ' ') : 'Question' }}
                    </span>
                    <span
                      v-if="flagged[q.id]"
                      class="text-[10px] font-bold px-2 py-0.5 rounded bg-amber-500/15 border border-amber-500/30 text-amber-400 flex items-center gap-1"
                    >
                      🚩 Flagged
                    </span>
                  </div>
                  <p class="text-xs text-slate-300 line-clamp-1">
                    {{ q.text?.replace(/<[^>]*>?/gm, '') || 'Question prompt' }}
                  </p>
                  <p class="text-[11px] mt-1 font-mono" :class="isQuestionAnswered(q.id) ? 'text-emerald-400' : 'text-rose-400'">
                    {{ isQuestionAnswered(q.id) ? '✓ ' + getAnswerSummaryText(q) : '⚠️ Unanswered' }}
                  </p>
                </div>
              </div>

              <button
                @click="jumpToQuestionFromReview(index)"
                class="shrink-0 self-end sm:self-center px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 hover:text-white transition-colors flex items-center gap-1.5"
              >
                <span>{{ isQuestionAnswered(q.id) ? 'Review / Edit' : 'Answer Now' }}</span>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
              </button>
            </div>

            <div v-if="filteredReviewQuestions.length === 0" class="text-center py-12 text-slate-500">
              <p class="text-sm">No questions match the current filter.</p>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-800 bg-slate-950/80 px-6 py-4 shrink-0">
            <button
              @click="showReviewModal = false"
              class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-700 bg-slate-800 hover:bg-slate-700 text-xs font-bold text-slate-200 hover:text-white transition-colors"
            >
              ← Return to Examination
            </button>

            <button
              @click="() => { showReviewModal = false; showConfirmSubmit = true }"
              class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-xs font-bold text-white transition-all shadow-lg shadow-emerald-600/20 flex items-center justify-center gap-2"
            >
              <span>Proceed to Final Submit</span>
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- ── Fullscreen Entry Prompt Modal ───────────────────────────────────── -->
    <Transition name="fade">
      <div
        v-if="showFullscreenPrompt"
        class="fixed inset-0 z-[250] flex items-center justify-center bg-slate-950/95 p-4 backdrop-blur-md"
      >
        <div class="relative w-full max-w-md rounded-2xl border border-indigo-500/40 bg-slate-900 p-8 text-center shadow-2xl">
          <div class="w-16 h-16 rounded-full bg-indigo-500/20 flex items-center justify-center mx-auto mb-5 text-indigo-400">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-white mb-2">Fullscreen Mode Required</h3>
          <p class="text-xs text-slate-300 leading-relaxed mb-6">
            This examination is configured to require Fullscreen Mode. In order to begin and maintain examination integrity, please enter fullscreen mode now.
          </p>
          <button
            type="button"
            @click="enterFullscreen"
            class="w-full rounded-xl bg-indigo-600 hover:bg-indigo-700 py-3 px-4 text-xs font-bold text-white shadow-lg shadow-indigo-600/20 transition-all flex items-center justify-center gap-2"
          >
            <span>Enter Fullscreen & Begin Exam</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
          </button>
        </div>
      </div>
    </Transition>

    <!-- ── Fullscreen Exit Warning Modal ────────────────────────────────────── -->
    <Transition name="fade">
      <div
        v-if="showFullscreenExitWarning"
        class="fixed inset-0 z-[250] flex items-center justify-center bg-slate-950/95 p-4 backdrop-blur-md"
      >
        <div class="relative w-full max-w-md rounded-2xl border border-rose-500/50 bg-slate-900 p-8 text-center shadow-2xl">
          <div class="w-16 h-16 rounded-full bg-rose-500/20 flex items-center justify-center mx-auto mb-5 text-rose-400">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-rose-400 mb-2">Security Alert: Fullscreen Exited!</h3>
          <p class="text-xs text-slate-300 leading-relaxed mb-4">
            You have exited fullscreen mode. Wollo University proctoring tracks fullscreen exits and logs all disruptions.
          </p>
          <div class="bg-rose-950/40 border border-rose-900/40 rounded-xl p-3 mb-6 font-mono text-xs text-rose-300">
            Exit Violations Logged: <strong class="text-white">{{ fullscreenExits }}</strong>
          </div>
          <button
            type="button"
            @click="enterFullscreen"
            class="w-full rounded-xl bg-rose-600 hover:bg-rose-700 py-3 px-4 text-xs font-bold text-white shadow-lg shadow-rose-600/20 transition-all flex items-center justify-center gap-2"
          >
            <span>Re-enter Fullscreen Immediately</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
          </button>
        </div>
      </div>
    </Transition>

    <!-- ── Tab / Window Switch Warning Modal ────────────────────────────────── -->
    <Transition name="fade">
      <div
        v-if="showTabSwitchWarning"
        class="fixed inset-0 z-[240] flex items-center justify-center bg-slate-950/90 p-4 backdrop-blur-md"
      >
        <div class="relative w-full max-w-md rounded-2xl border border-amber-500/50 bg-slate-900 p-8 text-center shadow-2xl">
          <div class="w-16 h-16 rounded-full bg-amber-500/20 flex items-center justify-center mx-auto mb-5 text-amber-400">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <h3 class="text-xl font-bold text-amber-400 mb-2">
            {{ lastSwitchType === 'window' ? 'Window Switch Detected!' : 'Tab Switch Detected!' }}
          </h3>
          <p class="text-xs text-slate-300 leading-relaxed mb-4">
            Academic Integrity Warning: Moving away from the examination window or switching browser tabs is strictly monitored.
          </p>
          <div class="bg-amber-950/40 border border-amber-900/40 rounded-xl p-3 mb-6 font-mono text-xs text-amber-300">
            Switch Violations Logged: <strong class="text-white">{{ tabSwitches }}</strong> / 3
            <p v-if="tabSwitches >= 3" class="text-rose-400 text-[11px] mt-1 font-bold">
              Maximum warning threshold reached. This incident is recorded in your proctor report.
            </p>
          </div>
          <button
            type="button"
            @click="showTabSwitchWarning = false"
            class="w-full rounded-xl bg-amber-600 hover:bg-amber-700 py-3 px-4 text-xs font-bold text-white shadow-lg shadow-amber-600/20 transition-all"
          >
            I Understand, Return to Exam
          </button>
        </div>
      </div>
    </Transition>

    <!-- Submit Confirmation Dialog Drawer -->
    <div v-if="showConfirmSubmit" class="fixed inset-0 z-[230] flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-md">
      <div class="relative w-full max-w-lg rounded-2xl border border-slate-800 bg-slate-900 p-6 md:p-8 shadow-2xl text-slate-100 animate-in fade-in zoom-in-95">
        <h3 class="text-xl font-extrabold text-white">Academic Response Submission</h3>
        <p class="mt-2 text-xs text-slate-400">
          You are about to submit your academic sheets for <strong class="text-white">{{ exam.courseCode }}</strong>.
        </p>

        <hr class="my-5 border-slate-800" />

        <div class="space-y-3 bg-slate-950/60 p-4 rounded-xl border border-slate-800 font-mono text-xs">
          <div class="flex justify-between text-slate-400">
            <span>Total Questions:</span>
            <span class="text-white font-bold">{{ questions.length }}</span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>Questions Attempted:</span>
            <span class="text-emerald-400 font-bold">
              {{ questions.filter((q: any) => isQuestionAnswered(q.id)).length }} 
              <span v-if="questions.filter((q: any) => isQuestionAnswered(q.id)).length === questions.length">(All)</span>
            </span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>Flagged Questions:</span>
            <span class="text-amber-400 font-bold">
              {{ Object.values(flagged).filter(Boolean).length }} flagged
            </span>
          </div>
          <div class="flex justify-between text-slate-400">
            <span>Proctor Violations logged:</span>
            <span :class="tabSwitches > 0 ? 'text-rose-400' : 'text-slate-400'">
              {{ tabSwitches }} issues logged
            </span>
          </div>
        </div>

        <div class="rounded-lg bg-amber-950/40 border border-amber-900/30 p-3 text-[11px] text-amber-400 font-sans mt-5 font-bold">
          ⚠️ This is a pre-submission checklist. Your exam has NOT been graded yet. Your score will be calculated AFTER you click "Confirm Final Submit".
        </div>

        <div class="mt-6 flex gap-3">
          <button
            @click="showConfirmSubmit = false"
            class="flex-1 rounded-xl border border-slate-800 bg-slate-950 py-3 text-xs font-bold text-slate-400 hover:text-white hover:bg-slate-900 transition-colors"
          >
            Resume Exam
          </button>
          <button
            @click="() => { showConfirmSubmit = false; processAndSubmit() }"
            :disabled="isSubmitting"
            class="flex-1 rounded-xl bg-emerald-600 py-3 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50 transition-all shadow-lg shadow-emerald-600/10"
          >
            {{ isSubmitting ? 'Submitting...' : 'Confirm Final Submit' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Scientific Calculator Widget (conditionally rendered when instructor allows calculator in exam settings) -->
    <ScientificCalculator :allowed="isCalculatorAllowed" />
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
.hide-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>
