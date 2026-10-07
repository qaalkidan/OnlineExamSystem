<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import apiClient from '../../../../core/api/apiClient'

const props = defineProps<{
  request: any | null
  show: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'reviewed'): void
}>()

const adjustment = ref<number>(0)
const notes = ref<string>('')
const isSubmitting = ref<boolean>(false)
const error = ref<string | null>(null)

watch(() => props.show, (val) => {
  if (val) {
    adjustment.value = 0
    notes.value = ''
    error.value = null
    isSubmitting.value = false
  }
})

const suggestedSeconds = computed(() => props.request?.suggested_seconds ?? 0)

const finalSeconds = computed(() => {
  const val = suggestedSeconds.value + adjustment.value
  return Math.max(0, val)
})

const finalMinutes = computed(() => Math.floor(finalSeconds.value / 60))
const finalSecsRemainder = computed(() => finalSeconds.value % 60)

const isOverLimit = computed(() => finalSeconds.value > 1800)

const formatDuration = (seconds: number) => {
  if (!seconds) return '0s'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  if (m === 0) return `${s}s`
  if (s === 0) return `${m}m`
  return `${m}m ${s}s`
}

const formatDateTime = (dt: string | null) => {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  })
}

const adjustedDeadlinePreview = computed(() => {
  const deadline = props.request?.adjusted_deadline || props.request?.exam_attempt?.adjusted_deadline
  if (!deadline || !finalSeconds.value) return null
  const d = new Date(deadline)
  if (isNaN(d.getTime())) return null
  d.setSeconds(d.getSeconds() + adjustment.value)
  return d.toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric',
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  })
})

const submit = async (action: 'approved' | 'rejected' | 'approve' | 'reject') => {
  const normalizedAction = (action === 'approved' || action === 'approve') ? 'approve' : 'reject'
  if (normalizedAction === 'approve' && isOverLimit.value) return
  isSubmitting.value = true
  error.value = null
  try {
    await apiClient.post(`/instructor/recovery-requests/${props.request.id}/review`, {
      action: normalizedAction,
      approved_seconds: normalizedAction === 'approve' ? finalSeconds.value : 0,
      review_notes: notes.value || null,
    })
    emit('reviewed')
    emit('close')
  } catch (err: any) {
    const data = err.response?.data
    if (data?.errors) {
      const firstError = Object.values(data.errors).flat()[0] as string
      error.value = firstError || data.message || 'Failed to submit review'
    } else {
      error.value = data?.message ?? 'Failed to submit review'
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="show && request" class="fixed inset-0 z-[300] flex items-center justify-center p-4 bg-slate-900/70 backdrop-blur-sm">
        <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden">

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-slate-50">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <div>
                <h2 class="text-base font-bold text-slate-800">Review Recovery Request</h2>
                <p class="text-xs text-slate-500">Adjust and approve or reject the extra time</p>
              </div>
            </div>
            <button @click="$emit('close')" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Body -->
          <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">

            <!-- Student / Exam info -->
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div class="bg-slate-50 rounded-xl p-4 space-y-1">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Student</p>
                <p class="font-semibold text-slate-800">{{ request.student?.name || request.student_name || '—' }}</p>
                <p class="text-slate-500">{{ request.student?.username || request.student_id || request.student?.email || request.student_email || '' }}</p>
              </div>
              <div class="bg-slate-50 rounded-xl p-4 space-y-1">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Exam</p>
                <p class="font-semibold text-slate-800">{{ request.exam?.title || request.exam_title || '—' }}</p>
                <p class="text-slate-500">{{ request.exam?.course?.name || request.exam?.course?.code || '' }}</p>
              </div>
            </div>

            <!-- Connection event details -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm space-y-2">
              <p class="font-semibold text-amber-800 mb-1">Connection Event</p>
              <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-slate-700">
                <span class="text-slate-500">Disconnected At:</span>
                <span>{{ request.disconnected_at_formatted || formatDateTime(request.disconnected_at) }}</span>
                <span class="text-slate-500">Reconnected At:</span>
                <span>{{ request.reconnected_at_formatted || formatDateTime(request.reconnected_at) }}</span>
                <span class="text-slate-500">Interruption Duration:</span>
                <span class="font-semibold text-amber-700">{{ formatDuration(request.interruption_seconds) }}</span>
              </div>
            </div>

            <!-- Timing summary -->
            <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-sm space-y-2">
              <p class="font-semibold text-indigo-800 mb-1">Time Allocation</p>
              <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-slate-700">
                <span class="text-slate-500">System Suggested Extra Time:</span>
                <span class="font-semibold">{{ formatDuration(suggestedSeconds) }}</span>
                <span class="text-slate-500">Current Extra Time on Attempt:</span>
                <span>{{ formatDuration(request.extra_time_seconds ?? request.exam_attempt?.extra_time_seconds ?? 0) }}</span>
              </div>
            </div>

            <!-- Adjustment input -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-2">
                Adjust Suggested Time
                <span class="font-normal text-slate-400 ml-1">(positive to add, negative to reduce)</span>
              </label>
              <div class="flex items-center gap-3">
                <button
                  @click="adjustment -= 30"
                  class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-100 transition-colors font-bold text-lg"
                  title="Reduce by 30s"
                >−</button>
                <div class="flex-1 relative">
                  <input
                    v-model.number="adjustment"
                    type="number"
                    step="30"
                    class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-center text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="0"
                  />
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">seconds</span>
                </div>
                <button
                  @click="adjustment += 30"
                  class="w-9 h-9 rounded-lg border border-slate-200 flex items-center justify-center text-slate-600 hover:bg-slate-100 transition-colors font-bold text-lg"
                  title="Add 30s"
                >+</button>
              </div>
              <!-- Quick presets -->
              <div class="flex gap-2 mt-2 flex-wrap">
                <button v-for="s in [0, 60, 120, 180, 300]" :key="s"
                  @click="adjustment = s - suggestedSeconds"
                  class="px-2.5 py-1 text-xs rounded-lg border border-slate-200 text-slate-600 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-700 transition-colors"
                >
                  {{ s === 0 ? 'None' : formatDuration(s) }}
                </button>
              </div>
            </div>

            <!-- Final summary -->
            <div :class="isOverLimit ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200'" class="border rounded-xl p-4 text-sm">
              <div class="flex items-center justify-between">
                <div>
                  <p class="font-semibold" :class="isOverLimit ? 'text-red-700' : 'text-emerald-700'">
                    Final Extra Time to Apply: {{ formatDuration(finalSeconds) }}
                  </p>
                  <p v-if="adjustedDeadlinePreview" class="text-xs mt-1 text-slate-500">
                    New adjusted deadline (preview): {{ adjustedDeadlinePreview }}
                  </p>
                  <p v-if="isOverLimit" class="text-xs mt-1 text-red-600 font-medium">
                    ⚠ Exceeds 30-minute instructor limit (1800s). Reduce the adjustment.
                  </p>
                </div>
                <div class="text-right text-2xl font-bold" :class="isOverLimit ? 'text-red-500' : 'text-emerald-600'">
                  {{ finalMinutes }}:{{ String(finalSecsRemainder).padStart(2, '0') }}
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div>
              <label class="block text-sm font-semibold text-slate-700 mb-1.5">Review Notes <span class="font-normal text-slate-400">(optional)</span></label>
              <textarea
                v-model="notes"
                rows="2"
                class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-slate-800 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 placeholder-slate-400"
                placeholder="Add any notes about your decision…"
              ></textarea>
            </div>

            <!-- Error -->
            <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
              {{ error }}
            </div>
          </div>

          <!-- Footer actions -->
          <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100 bg-slate-50 gap-3">
            <button
              @click="$emit('close')"
              :disabled="isSubmitting"
              class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-100 rounded-xl transition-colors disabled:opacity-50"
            >
              Cancel
            </button>
            <div class="flex gap-3">
              <button
                @click="submit('rejected')"
                :disabled="isSubmitting"
                class="px-5 py-2 text-sm font-semibold rounded-xl border border-red-200 text-red-600 hover:bg-red-50 transition-colors disabled:opacity-50 flex items-center gap-1.5"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Reject
              </button>
              <button
                @click="submit('approved')"
                :disabled="isSubmitting || isOverLimit"
                class="px-5 py-2 text-sm font-semibold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 transition-colors disabled:opacity-50 flex items-center gap-1.5"
              >
                <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Approve & Apply
              </button>
            </div>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active {
  transition: opacity 0.25s ease;
}
.modal-fade-enter-from, .modal-fade-leave-to {
  opacity: 0;
}
</style>
