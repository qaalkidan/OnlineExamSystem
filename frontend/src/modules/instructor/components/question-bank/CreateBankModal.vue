<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  show: boolean
  initialData?: { id: number; title: string; description: string } | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'submit', payload: { id?: number; title: string; description: string }): Promise<void> | void
}>()

const title = ref('')
const description = ref('')
const isSubmitting = ref(false)
const errorMessage = ref('')

watch(() => props.show, (newVal) => {
  if (newVal) {
    errorMessage.value = ''
    if (props.initialData) {
      title.value = props.initialData.title
      description.value = props.initialData.description || ''
    } else {
      title.value = ''
      description.value = ''
    }
  }
})

const handleClose = () => {
  if (isSubmitting.value) return
  title.value = ''
  description.value = ''
  errorMessage.value = ''
  emit('close')
}

const handleSubmit = async () => {
  if (!title.value.trim() || isSubmitting.value) return
  
  isSubmitting.value = true
  errorMessage.value = ''
  try {
    const payload: { id?: number; title: string; description: string } = {
      title: title.value.trim(),
      description: description.value.trim()
    }
    if (props.initialData?.id) {
      payload.id = props.initialData.id
    }
    await emit('submit', payload)
    handleClose()
  } catch (err: any) {
    console.error('Error submitting question bank:', err)
    errorMessage.value = err?.response?.data?.message || err?.message || 'Failed to save question bank. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div 
    v-if="show" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
    @click.self="handleClose"
    @keydown.esc="handleClose"
  >
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
      
      <!-- Header -->
      <div class="px-6 py-4.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-[#5138ed]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-800">
              {{ props.initialData ? 'Edit Question Bank' : 'Create Question Bank' }}
            </h3>
            <p class="text-[11px] text-slate-400">
              {{ props.initialData ? 'Update bank details' : 'Set up a new question repository' }}
            </p>
          </div>
        </div>
        <button 
          @click="handleClose" 
          class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
          aria-label="Close dialog"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
      
      <!-- Error Feedback -->
      <div v-if="errorMessage" class="mx-6 mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Form Body -->
      <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">
            Bank Title <span class="text-rose-500">*</span>
          </label>
          <input 
            v-model="title"
            type="text" 
            placeholder="e.g. Database Systems Core Concepts" 
            autofocus
            class="w-full min-h-[44px] border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-[13px] text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors"
          >
        </div>
        
        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1.5">
            Description <span class="text-slate-400 font-normal">(Optional)</span>
          </label>
          <textarea 
            v-model="description"
            rows="3"
            placeholder="Brief description of the topics or exam coverage..." 
            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-xs sm:text-[13px] text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors resize-none"
          ></textarea>
        </div>

        <!-- Footer -->
        <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
          <button 
            type="button"
            @click="handleClose" 
            :disabled="isSubmitting"
            class="min-h-[42px] px-5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors disabled:opacity-50"
          >
            Cancel
          </button>
          <button 
            type="submit"
            :disabled="!title.trim() || isSubmitting"
            class="min-h-[42px] px-5 py-2 rounded-xl text-xs font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-colors shadow-xs disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
          >
            <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ props.initialData ? (isSubmitting ? 'Saving...' : 'Save Changes') : (isSubmitting ? 'Creating...' : 'Create Bank') }}
          </button>
        </div>
      </form>
      
    </div>
  </div>
</template>
