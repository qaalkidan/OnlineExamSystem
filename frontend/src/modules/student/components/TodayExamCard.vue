<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import type { UpcomingExam } from '../types'

const props = defineProps<{
  exam: UpcomingExam
}>()

const emit = defineEmits<{
  (e: 'start-exam', id: number): void
}>()

// ─── Reactive clock ────────────────────────────────────────────────────────
const currentTime = ref(new Date())
let timerInterval: number | null = null

onMounted(() => {
  timerInterval = window.setInterval(() => {
    currentTime.value = new Date()
  }, 1000)
})
onUnmounted(() => {
  if (timerInterval !== null) { clearInterval(timerInterval); timerInterval = null }
})

// ─── Date / time helpers ───────────────────────────────────────────────────
// ─── Phase & Completion checks ─────────────────────────────────────────────
const isSubmitted = computed(() => {
  const status = props.exam.attemptStatus as string | undefined | null
  return status === 'submitted' || status === 'graded' || status === 'published'
})

// ─── Date / time helpers ───────────────────────────────────────────────────
const startDateTime = computed((): Date => {
  const raw = props.exam.scheduledAt || props.exam.scheduledDate
  if (!raw) return currentTime.value
  const d = new Date(raw)
  return isNaN(d.getTime()) ? currentTime.value : d
})

const endDateTime = computed((): Date => {
  const raw = props.exam.scheduledAt || props.exam.scheduledDate
  if (!raw) {
    const started = props.exam.attemptStartedAt ? new Date(props.exam.attemptStartedAt).getTime() : currentTime.value.getTime()
    return new Date(started + props.exam.durationMinutes * 60 * 1000)
  }
  const start = startDateTime.value
  return new Date(start.getTime() + props.exam.durationMinutes * 60 * 1000)
})

const now     = computed(() => currentTime.value.getTime())
const startMs = computed(() => startDateTime.value.getTime())
const endMs   = computed(() => endDateTime.value.getTime())
const TEN_MIN = 10 * 60 * 1000

// ─── Phase flags ───────────────────────────────────────────────────────────
const isContinue = computed(() => props.exam.attemptStatus === 'in_progress')
const isReady   = computed(() => {
  if (isSubmitted.value) return false
  const raw = props.exam.scheduledAt || props.exam.scheduledDate
  if (!raw) return false
  return now.value >= startMs.value - TEN_MIN && now.value < startMs.value
})
const isOngoing = computed(() => {
  if (isSubmitted.value) return false
  if (isContinue.value) return true
  const raw = props.exam.scheduledAt || props.exam.scheduledDate
  if (!raw) return true // Immediate published exam is available now
  return now.value >= startMs.value && now.value < endMs.value
})
const isVisible = computed(() => !isSubmitted.value && (isReady.value || isOngoing.value))

// ─── Countdown / remaining ─────────────────────────────────────────────────
const countdownMs       = computed(() => Math.max(0, startMs.value - now.value))
const timeRemainingMs   = computed(() => Math.max(0, endMs.value - now.value))

const fmt = (ms: number) => {
  const mins = Math.floor(ms / 60_000)
  const secs = Math.floor((ms % 60_000) / 1000)
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
}

const formattedCountdown      = computed(() => fmt(countdownMs.value))
const formattedTimeRemaining  = computed(() => fmt(timeRemainingMs.value))

// Percentage of exam time remaining (for progress arc)
const progressPct = computed(() => {
  const total = props.exam.durationMinutes * 60_000
  if (!total) return 0
  return Math.max(0, Math.min(100, (timeRemainingMs.value / total) * 100))
})

const formatTime = (d: Date) =>
  !d.getTime() ? 'TBD' : d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })

const formatDate = (d: Date) =>
  !d.getTime() ? '' : d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
</script>

<template>
  <div v-if="isVisible" class="exam-alert-card" :class="isOngoing ? 'ongoing' : 'ready'">

    <!-- ── Animated background blobs ──────────────────────────── -->
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>

    <!-- ── Grid dots overlay ───────────────────────────────────── -->
    <div class="grid-overlay"></div>

    <!-- ── Top ribbon ─────────────────────────────────────────── -->
    <div class="ribbon">
      <span v-if="isOngoing && isContinue" class="ribbon-dot amber"></span>
      <span v-else-if="isOngoing" class="ribbon-dot green"></span>
      <span v-else class="ribbon-dot red blink"></span>

      <span v-if="isOngoing && isContinue" class="ribbon-label">⚡ EXAM IN PROGRESS — RESUME NOW</span>
      <span v-else-if="isOngoing" class="ribbon-label">🔴 LIVE EXAM — STARTED</span>
      <span v-else class="ribbon-label">⏳ EXAM STARTING SOON — GET READY</span>

      <span class="ribbon-right">
        {{ formatDate(startDateTime) }}
      </span>
    </div>

    <!-- ── Main content ────────────────────────────────────────── -->
    <div class="card-body">

      <!-- Left: Exam identity -->
      <div class="exam-identity">
        <!-- Icon -->
        <div class="exam-icon" :class="isOngoing ? (isContinue ? 'icon-amber' : 'icon-green') : 'icon-red'">
          <!-- Ongoing: play icon; Ready: clock icon -->
          <svg v-if="isOngoing" viewBox="0 0 24 24" fill="currentColor">
            <path d="M8 5v14l11-7z"/>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>

        <div class="exam-text">
          <p class="exam-course">{{ exam.courseCode }} &mdash; {{ exam.courseName }}</p>
          <h2 class="exam-title">{{ exam.examType }}</h2>
          <div class="exam-meta">
            <span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              {{ formatTime(startDateTime) }} – {{ formatTime(endDateTime) }}
            </span>
            <span>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              {{ exam.durationMinutes }} minutes
            </span>
            <span v-if="exam.totalMarks">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              {{ exam.totalMarks }} marks
            </span>
          </div>
        </div>
      </div>

      <!-- Center: Timer display -->
      <div class="timer-section">
        <template v-if="isReady">
          <p class="timer-label">Starts In</p>
          <div class="timer-display countdown">{{ formattedCountdown }}</div>
          <p class="timer-sub">mm : ss</p>
        </template>
        <template v-else-if="isOngoing">
          <!-- Circular progress ring -->
          <div class="progress-ring-wrap">
            <svg class="progress-ring" viewBox="0 0 80 80">
              <circle class="ring-track" cx="40" cy="40" r="33" />
              <circle
                class="ring-fill"
                cx="40" cy="40" r="33"
                :style="{
                  strokeDashoffset: 207.3 - (207.3 * progressPct / 100),
                  stroke: timeRemainingMs < 60_000 ? '#ef4444' : (isContinue ? '#f59e0b' : '#22c55e')
                }"
              />
            </svg>
            <div class="ring-inner">
              <p class="ring-time" :class="timeRemainingMs < 60_000 ? 'urgent' : ''">
                {{ formattedTimeRemaining }}
              </p>
              <p class="ring-sublabel">left</p>
            </div>
          </div>
        </template>
      </div>

      <!-- Right: Action button -->
      <div class="action-section">
        <template v-if="isReady">
          <div class="preparing-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Prepare yourself
          </div>
          <p class="prep-hint">The exam starts in less than 10 minutes. Make sure you're ready!</p>
        </template>

        <template v-else-if="isOngoing">
          <button
            v-if="isContinue"
            class="start-btn amber-btn"
            @click="emit('start-exam', exam.id)"
          >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            Continue Exam
          </button>
          <button
            v-else
            class="start-btn green-btn"
            @click="emit('start-exam', exam.id)"
          >
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
            Start Exam Now
          </button>
          <p v-if="isContinue" class="action-hint">Your progress is saved. Continue where you left off.</p>
          <p v-else class="action-hint">Good luck! Your answers are auto-saved.</p>
        </template>
      </div>

    </div>

  </div>
</template>

<style scoped>
/* ── Base card ─────────────────────────────────────────────────────────────── */
.exam-alert-card {
  position: relative;
  overflow: hidden;
  border-radius: 20px;
  padding: 0;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px -10px rgba(0,0,0,0.35);
  isolation: isolate;
  transition: box-shadow 0.3s;
}
.exam-alert-card:hover {
  box-shadow: 0 28px 80px -10px rgba(0,0,0,0.4);
  transform: translateY(-1px);
  transition: transform 0.2s, box-shadow 0.2s;
}

/* ── Themes ────────────────────────────────────────────────────────────────── */
.ongoing {
  background: linear-gradient(135deg, #0f2027 0%, #1a3a2a 50%, #0f2c1a 100%);
  border: 1.5px solid rgba(34,197,94,0.4);
}
.ongoing.amber-theme {
  border-color: rgba(245,158,11,0.4);
}
.ready {
  background: linear-gradient(135deg, #1a0a2e 0%, #16213e 50%, #0f3460 100%);
  border: 1.5px solid rgba(139,92,246,0.5);
}

/* ── Animated blobs ───────────────────────────────────────────────────────── */
.blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(60px);
  opacity: 0.18;
  pointer-events: none;
  z-index: 0;
  animation: drift 8s ease-in-out infinite;
}
.ongoing .blob-1 { width: 260px; height: 260px; background: #22c55e; top: -60px; left: -60px; animation-delay: 0s; }
.ongoing .blob-2 { width: 180px; height: 180px; background: #10b981; bottom: -50px; right: 20%; animation-delay: -3s; }
.ongoing .blob-3 { width: 140px; height: 140px; background: #34d399; top: 20px; right: 80px; animation-delay: -5s; }
.ready .blob-1   { width: 280px; height: 280px; background: #8b5cf6; top: -80px; left: -40px; animation-delay: 0s; }
.ready .blob-2   { width: 200px; height: 200px; background: #6366f1; bottom: -60px; right: 15%; animation-delay: -2s; }
.ready .blob-3   { width: 120px; height: 120px; background: #a78bfa; top: 30px; right: 120px; animation-delay: -4s; }

@keyframes drift {
  0%, 100% { transform: translate(0,0) scale(1); }
  33%       { transform: translate(15px,-12px) scale(1.05); }
  66%       { transform: translate(-10px,10px) scale(0.97); }
}

/* ── Grid overlay ─────────────────────────────────────────────────────────── */
.grid-overlay {
  position: absolute;
  inset: 0;
  z-index: 1;
  pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
  background-size: 40px 40px;
}

/* ── Top ribbon ───────────────────────────────────────────────────────────── */
.ribbon {
  position: relative;
  z-index: 10;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 24px;
  background: rgba(255,255,255,0.06);
  border-bottom: 1px solid rgba(255,255,255,0.08);
  backdrop-filter: blur(4px);
}
.ribbon-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.ribbon-dot.green { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
.ribbon-dot.amber { background: #f59e0b; box-shadow: 0 0 8px #f59e0b; }
.ribbon-dot.red   { background: #ef4444; box-shadow: 0 0 8px #ef4444; }
.ribbon-dot.blink { animation: blink 1.2s ease-in-out infinite; }

@keyframes blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.2; }
}

.ribbon-label {
  font-size: 11px; font-weight: 800; letter-spacing: 0.12em;
  text-transform: uppercase; color: rgba(255,255,255,0.9);
}
.ribbon-right {
  margin-left: auto; font-size: 11px; font-weight: 500;
  color: rgba(255,255,255,0.45); letter-spacing: 0.03em;
}

/* ── Card body ────────────────────────────────────────────────────────────── */
.card-body {
  position: relative; z-index: 10;
  display: flex; align-items: center; gap: 24px;
  padding: 28px 32px;
  flex: 1;
}

/* ── Exam identity ────────────────────────────────────────────────────────── */
.exam-identity {
  display: flex; align-items: center; gap: 20px; flex: 1; min-width: 0;
}
.exam-icon {
  flex-shrink: 0;
  width: 64px; height: 64px;
  border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
}
.exam-icon svg { width: 28px; height: 28px; }
.icon-green { background: rgba(34,197,94,0.2);  color: #4ade80;  border: 1.5px solid rgba(34,197,94,0.3); }
.icon-amber { background: rgba(245,158,11,0.2); color: #fbbf24;  border: 1.5px solid rgba(245,158,11,0.3); }
.icon-red   { background: rgba(139,92,246,0.2); color: #a78bfa;  border: 1.5px solid rgba(139,92,246,0.3); }

.exam-text { min-width: 0; }
.exam-course {
  font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
  color: rgba(255,255,255,0.5); margin-bottom: 4px;
}
.exam-title {
  font-size: 22px; font-weight: 900; color: #fff;
  letter-spacing: -0.02em; margin-bottom: 10px;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.exam-meta {
  display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
}
.exam-meta span {
  display: flex; align-items: center; gap: 5px;
  font-size: 12px; font-weight: 500; color: rgba(255,255,255,0.55);
}
.exam-meta svg { width: 13px; height: 13px; }

/* ── Timer section ─────────────────────────────────────────────────────────── */
.timer-section {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  flex-shrink: 0; min-width: 130px;
}
.timer-label {
  font-size: 10px; font-weight: 800; letter-spacing: 0.15em; text-transform: uppercase;
  color: rgba(255,255,255,0.45); margin-bottom: 4px;
}
.timer-display {
  font-family: 'Courier New', monospace;
  font-size: 44px; font-weight: 900; letter-spacing: 0.05em; line-height: 1;
  color: #fff; text-shadow: 0 0 30px rgba(255,255,255,0.2);
}
.timer-sub {
  font-size: 10px; color: rgba(255,255,255,0.3); font-weight: 600; letter-spacing: 0.15em;
}

/* Circular progress ring */
.progress-ring-wrap {
  position: relative; width: 100px; height: 100px;
  display: flex; align-items: center; justify-content: center;
}
.progress-ring {
  position: absolute; inset: 0; width: 100%; height: 100%;
  transform: rotate(-90deg);
}
.ring-track {
  fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 5;
}
.ring-fill {
  fill: none; stroke-width: 5; stroke-linecap: round;
  stroke-dasharray: 207.3;
  transition: stroke-dashoffset 1s linear, stroke 0.5s;
}
.ring-inner {
  display: flex; flex-direction: column; align-items: center; z-index: 2;
}
.ring-time {
  font-family: 'Courier New', monospace;
  font-size: 18px; font-weight: 900; color: #fff; line-height: 1; letter-spacing: 0.05em;
}
.ring-time.urgent { color: #ef4444; animation: pulse-red 0.8s ease-in-out infinite; }
.ring-sublabel { font-size: 10px; color: rgba(255,255,255,0.4); font-weight: 700; letter-spacing: 0.1em; }

@keyframes pulse-red {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0.5; }
}

/* ── Action section ───────────────────────────────────────────────────────── */
.action-section {
  flex-shrink: 0; display: flex; flex-direction: column;
  align-items: center; gap: 10px; min-width: 200px;
}

.start-btn {
  display: flex; align-items: center; justify-content: center; gap: 10px;
  width: 100%; padding: 16px 24px;
  border: none; border-radius: 14px; cursor: pointer;
  font-size: 15px; font-weight: 900; letter-spacing: 0.02em;
  transition: transform 0.15s, box-shadow 0.15s;
  position: relative; overflow: hidden;
}
.start-btn::before {
  content: ''; position: absolute; inset: 0;
  background: rgba(255,255,255,0.1);
  opacity: 0; transition: opacity 0.15s;
}
.start-btn:hover::before { opacity: 1; }
.start-btn:active { transform: scale(0.97); }
.start-btn svg { width: 20px; height: 20px; flex-shrink: 0; }

.green-btn {
  background: linear-gradient(135deg, #22c55e, #16a34a);
  color: #fff;
  box-shadow: 0 8px 24px rgba(34,197,94,0.4), 0 0 0 1px rgba(34,197,94,0.3);
  animation: glow-green 2s ease-in-out infinite;
}
@keyframes glow-green {
  0%, 100% { box-shadow: 0 8px 24px rgba(34,197,94,0.4), 0 0 0 1px rgba(34,197,94,0.3); }
  50%       { box-shadow: 0 8px 40px rgba(34,197,94,0.65), 0 0 0 2px rgba(34,197,94,0.5); }
}

.amber-btn {
  background: linear-gradient(135deg, #f59e0b, #d97706);
  color: #fff;
  box-shadow: 0 8px 24px rgba(245,158,11,0.4), 0 0 0 1px rgba(245,158,11,0.3);
  animation: glow-amber 2s ease-in-out infinite;
}
@keyframes glow-amber {
  0%, 100% { box-shadow: 0 8px 24px rgba(245,158,11,0.4), 0 0 0 1px rgba(245,158,11,0.3); }
  50%       { box-shadow: 0 8px 40px rgba(245,158,11,0.65), 0 0 0 2px rgba(245,158,11,0.5); }
}

.action-hint {
  font-size: 11px; color: rgba(255,255,255,0.4); text-align: center; line-height: 1.4;
}

/* Preparing state */
.preparing-badge {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 18px; border-radius: 12px;
  background: rgba(139,92,246,0.2); border: 1px solid rgba(139,92,246,0.35);
  color: #c4b5fd; font-size: 13px; font-weight: 700;
}
.preparing-badge svg { width: 16px; height: 16px; }
.prep-hint {
  font-size: 11px; color: rgba(255,255,255,0.4); text-align: center; line-height: 1.5;
}

/* ── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1024px) {
  .card-body { padding: 24px; gap: 20px; }
  .timer-display { font-size: 38px; }
}

@media (max-width: 768px) {
  .card-body { flex-direction: column; align-items: stretch; padding: 18px; gap: 18px; }
  .exam-identity { width: 100%; }
  .timer-section { align-self: center; margin: 4px 0; }
  .action-section { align-self: stretch; min-width: 0; width: 100%; }
  .start-btn { width: 100%; min-height: 48px; }
  .preparing-badge { width: 100%; justify-content: center; min-height: 44px; }
  .ribbon { padding: 8px 16px; }
  .ribbon-right { display: none; }
  .exam-title { font-size: 18px; white-space: normal; }
  .exam-icon { width: 52px; height: 52px; }
  .exam-icon svg { width: 24px; height: 24px; }
}

@media (max-width: 480px) {
  .card-body { padding: 14px; gap: 14px; }
  .exam-identity { gap: 12px; }
  .exam-icon { width: 44px; height: 44px; border-radius: 14px; }
  .exam-icon svg { width: 20px; height: 20px; }
  .exam-title { font-size: 16px; }
  .exam-meta { gap: 10px; font-size: 11px; }
  .timer-display { font-size: 32px; }
  .start-btn { font-size: 14px; padding: 12px 18px; }
  .ribbon-label { font-size: 9.5px; }
}
</style>
