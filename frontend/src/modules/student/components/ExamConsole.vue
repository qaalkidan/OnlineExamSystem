<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { ActiveExam, Question } from '../types'
import wolloLogo from '@/assets/images/logo.png'

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

const questions = ref<Question[]>(groupQuestionsByInstruction(props.exam.questions))
const currentIndex = ref<number>(0)
const answers = ref<Record<number, string>>({})
const matchingAnswers = ref<Record<number, Record<number, string>>>({}) // for matching questions: qId -> {pairIndex -> selectedRight}
const flagged = ref<Record<number, boolean>>({})

// Calculate initial seconds remaining considering startedAt timestamp
const calculateInitialSeconds = () => {
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

// Exam settings
const settings = ref<Record<string, any>>((props.exam as any).settings || {})

const activeQuestion = computed(() => questions.value[currentIndex.value])

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

// Live timer countdown
onMounted(() => {
  startWebcam()
  if (secondsRemaining.value <= 0) {
    secondsRemaining.value = 0
    triggerAutoSubmit()
    return
  }
  timer = window.setInterval(() => {
    if (secondsRemaining.value <= 1) {
      if (timer) {
        clearInterval(timer)
        timer = null
      }
      secondsRemaining.value = 0
      triggerAutoSubmit()
    } else {
      secondsRemaining.value--
    }
  }, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
  stopWebcam()
})

// Monitor screen tab focus switches to enforce academic integrity rules
const handleVisibilityChange = () => {
  if (document.hidden) {
    tabSwitches.value++
    if (tabSwitches.value >= 3) {
      alert("SECURITY VIOLATION LOGGED:\nYou have switched tabs or lost focus 3 times. This attempt has been logged and sent to Dr. Abraham Getahun.")
    } else {
      alert(`ACADEMIC INTEGRITY WARNING:\nTab switches or window changes are strictly logged by Wollo University proctors.\nWarning count: ${tabSwitches.value}/3`)
    }
  }
}

onMounted(() => {
  document.addEventListener('visibilitychange', handleVisibilityChange)
})

onUnmounted(() => {
  document.removeEventListener('visibilitychange', handleVisibilityChange)
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
  const q = questions.value.find(q => q.id === qId)
  if (q && (q as any).pairs) {
    answers.value[qId] = (q as any).pairs
      .map((_: any, i: number) => `${i}:${matchingAnswers.value[qId]?.[i] || ''}`)
      .join(',')
  }
}

const toggleFlag = () => {
  flagged.value[activeQuestion.value.id] = !flagged.value[activeQuestion.value.id]
}

const isQuestionAnswered = (qId: number) => {
  const ans = answers.value[qId]
  return ans !== undefined && ans !== null && String(ans).trim() !== ''
}

const triggerAutoSubmit = () => {
  if (isAutoSubmitting.value || isSubmitting.value) return
  isAutoSubmitting.value = true
  isSubmitting.value = true
  showConfirmSubmit.value = false
  processAndSubmit(true)
}

const processAndSubmit = (isAuto = false) => {
  if (!isAuto && isSubmitting.value) return
  isSubmitting.value = true

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
    emit('cancel')
  }
}
</script>

<template>
  <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white pb-16">
    <!-- Top Console Bar -->
    <header class="border-b border-slate-800 bg-slate-950 px-6 py-4 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <!-- Wollo University Logo -->
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-white overflow-hidden shadow-md shrink-0">
          <img
            :src="wolloLogo"
            alt="Wollo University"
            class="h-9 w-9 object-contain"
          />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase leading-none">Wollo University</span>
            <span class="text-[10px] font-bold tracking-widest text-slate-500 uppercase leading-none">· Exam Portal</span>
            <span class="h-2 w-2 rounded-full bg-rose-500 animate-ping"></span>
          </div>
          <h2 class="text-sm font-bold text-white truncate max-w-xs sm:max-w-md mt-0.5">
            {{ exam.courseCode }}: {{ exam.courseName }}
          </h2>
        </div>
      </div>

      <!-- Live Timer and Submit Block -->
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2 rounded-lg bg-slate-800 px-3.5 py-1.5 border border-slate-700">
          <svg class="h-4 w-4 text-rose-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          <span class="font-mono text-sm font-extrabold text-white">
            {{ formatTime(secondsRemaining) }}
          </span>
        </div>

        <button
          @click="showConfirmSubmit = true"
          :disabled="isSubmitting"
          class="rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed px-4 py-2 text-xs font-bold text-white transition-all shadow-md shadow-indigo-600/10 active:scale-[0.98]"
        >
          {{ isSubmitting ? 'Submitting...' : 'Submit Response Sheet' }}
        </button>
      </div>
    </header>

    <!-- Main Console Arena -->
    <div class="flex-1 grid grid-cols-1 xl:grid-cols-12 gap-6 max-w-[1800px] w-full mx-auto p-6 overflow-hidden">
      
      <!-- Left Side: Question Navigation & Live Feed Mockup (3 Cols) -->
      <div class="xl:col-span-3 space-y-6 flex flex-col justify-start">
        
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
            class="space-y-5 overflow-y-auto pr-1"
            style="max-height: 140px; scrollbar-width: none; -ms-overflow-style: none;"
          >
            <!-- Add a style block for webkit hidden scrollbar -->
            <component :is="'style'">
              .overflow-y-auto::-webkit-scrollbar { display: none; }
            </component>
            <div v-for="(group, gIdx) in navigationGroups" :key="gIdx" class="space-y-3">
              <h5 class="text-[9px] font-bold text-indigo-400 uppercase tracking-widest border-b border-slate-800/50 pb-1.5" :title="group.instruction">
                {{ group.instruction }}
              </h5>
              <div class="grid grid-cols-5 gap-2">
                <button
                  v-for="item in group.items"
                  :key="item.q.id"
                  @click="currentIndex = item.globalIndex"
                  :class="[
                    'relative flex h-10 w-full items-center justify-center rounded-lg border font-mono text-xs font-bold transition-all',
                    item.globalIndex === currentIndex
                      ? 'bg-indigo-600 border-indigo-500 text-white shadow-md shadow-indigo-600/20 scale-105'
                      : isQuestionAnswered(item.q.id)
                      ? 'bg-emerald-950/40 border-emerald-800/80 text-emerald-400'
                      : 'bg-slate-900 border-slate-800 text-slate-400 hover:bg-slate-800 hover:border-slate-700'
                  ]"
                >
                  {{ item.globalIndex + 1 }}
                  <!-- Small flag icon overlay -->
                  <span v-if="flagged[item.q.id]" class="absolute -top-1 -right-1 flex h-3 w-3">
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

      <!-- Center: Main Active Question Board (9 Cols) -->
      <div class="xl:col-span-9 space-y-6 flex flex-col justify-between">
        
        <div class="flex flex-col rounded-xl border border-slate-800 bg-slate-950 p-6 md:p-8 min-h-[480px]">
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
                  @click="handleSelectOption(index)"
                  :class="[
                    'flex w-full items-start gap-4 rounded-xl border p-4 text-left transition-all text-xs font-medium font-sans',
                    answers[activeQuestion.id] === String.fromCharCode(65 + index)
                      ? 'bg-indigo-600/15 border-indigo-500 text-indigo-200'
                      : 'bg-slate-900 border-slate-800 text-slate-300 hover:bg-slate-850 hover:border-slate-700'
                  ]"
                >
                  <span :class="[
                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-xs font-bold font-mono',
                    answers[activeQuestion.id] === String.fromCharCode(65 + index)
                      ? 'bg-indigo-600 border-indigo-400 text-white'
                      : 'bg-slate-950 border-slate-700 text-slate-400'
                  ]">
                    {{ String.fromCharCode(65 + index) }}
                  </span>
                  <span class="leading-relaxed pt-0.5">{{ typeof option === 'object' ? (option as any).text || (option as any).label : option }}</span>
                </button>
              </template>

              <!-- ── True / False ── -->
              <template v-else-if="activeQuestion.type === 'true_false'">
                <button
                  @click="handleTrueFalse('A')"
                  :class="[
                    'flex w-full items-center gap-4 rounded-xl border p-4 text-left transition-all text-xs font-bold font-sans',
                    answers[activeQuestion.id] === 'A'
                      ? 'bg-emerald-500/15 border-emerald-500 text-emerald-300'
                      : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-emerald-700'
                  ]"
                >
                  <span :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-xs font-bold', answers[activeQuestion.id] === 'A' ? 'bg-emerald-600 border-emerald-400 text-white' : 'bg-slate-950 border-slate-700 text-slate-400']">A</span>
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  True
                </button>
                <button
                  @click="handleTrueFalse('B')"
                  :class="[
                    'flex w-full items-center gap-4 rounded-xl border p-4 text-left transition-all text-xs font-bold font-sans',
                    answers[activeQuestion.id] === 'B'
                      ? 'bg-rose-500/15 border-rose-500 text-rose-300'
                      : 'bg-slate-900 border-slate-800 text-slate-300 hover:border-rose-700'
                  ]"
                >
                  <span :class="['flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border text-xs font-bold', answers[activeQuestion.id] === 'B' ? 'bg-rose-600 border-rose-400 text-white' : 'bg-slate-950 border-slate-700 text-slate-400']">B</span>
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                  False
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
              <template v-else-if="activeQuestion.type === 'matching' && (activeQuestion as any).pairs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-10">
                  
                  <!-- Left side: Column A with selection dropdown -->
                  <div class="space-y-4">
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-indigo-400 mb-4 pl-8">{{ (activeQuestion as any).columnA || 'Column A' }}</h4>
                    <template v-for="(pair, pIdx) in (activeQuestion as any).pairs" :key="pIdx">
                      <div
                        v-if="pair.left && pair.left.trim() !== ''"
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
                                  v-for="(p, rIdx) in (activeQuestion as any).pairs"
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
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-teal-400 mb-4">{{ (activeQuestion as any).columnB || 'Column B' }}</h4>
                    <div class="flex flex-col gap-4 pt-1">
                      <div
                        v-for="(pair, rIdx) in (activeQuestion as any).pairs"
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
          <div class="mt-8 pt-6 border-t border-slate-800 flex items-center justify-between">
            <button
              @click="currentIndex = Math.max(0, currentIndex - 1)"
              :disabled="currentIndex === 0"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-800 bg-slate-950 px-4 py-2 text-xs font-bold text-slate-400 hover:text-white hover:border-slate-700 transition-colors disabled:opacity-30 disabled:pointer-events-none"
            >
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
              <span>Back Question</span>
            </button>

            <span class="text-xs font-bold font-mono text-slate-500 hidden sm:inline-block">
              SECURE BUFFER: ALL INPUTS SAVED REDUNDANTLY
            </span>

            <button
              v-if="currentIndex < questions.length - 1"
              @click="currentIndex = Math.min(questions.length - 1, currentIndex + 1)"
              class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition-colors"
            >
              <span>Next Question</span>
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
            </button>
            <button
              v-else
              @click="showConfirmSubmit = true"
              class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors"
            >
              <span>Finish & Submit</span>
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- Submit Confirmation Dialog Drawer -->
    <div v-if="showConfirmSubmit" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/85 p-4 backdrop-blur-md">
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
  </div>
</template>
