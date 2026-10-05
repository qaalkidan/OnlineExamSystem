<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  show: boolean
  initialData?: { id: number; title: string; description: string } | null
}>()

const emit = defineEmits(['close', 'submit'])

const title = ref('')
const description = ref('')
const isSubmitting = ref(false)

watch(() => props.show, (newVal) => {
  if (newVal) {
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
  title.value = ''
  description.value = ''
  emit('close')
}

const handleSubmit = async () => {
  if (!title.value.trim()) return
  
  isSubmitting.value = true
  try {
    const payload: any = { title: title.value, description: description.value }
    if (props.initialData?.id) {
      payload.id = props.initialData.id
    }
    await emit('submit', payload)
    handleClose()
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
      
      <!-- Header -->
      <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50">
        <h3 class="text-[15px] font-bold text-slate-800">{{ props.initialData ? 'Edit Question Bank' : 'Create Question Bank' }}</h3>
        <button @click="handleClose" class="text-slate-400 hover:text-slate-600 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
      </div>
      
      <!-- Form Body -->
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-[13px] font-bold text-slate-700 mb-2">Bank Title <span class="text-rose-500">*</span></label>
          <input 
            v-model="title"
            type="text" 
            placeholder="e.g. Database Systems Core Concepts" 
            class="w-full min-h-[44px] border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed]"
          >
        </div>
        
        <div>
          <label class="block text-[13px] font-bold text-slate-700 mb-2">Description</label>
          <textarea 
            v-model="description"
            rows="3"
            placeholder="Brief description of the topics covered..." 
            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-[13px] text-slate-700 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] resize-none"
          ></textarea>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50">
        <button 
          @click="handleClose" 
          class="min-h-[44px] flex-1 sm:flex-none px-5 py-2 rounded-xl text-[13px] font-bold text-slate-600 hover:bg-slate-200 transition-colors flex items-center justify-center"
        >
          Cancel
        </button>
        <button 
          @click="handleSubmit" 
          :disabled="!title.trim() || isSubmitting"
          class="min-h-[44px] flex-1 sm:flex-none px-5 py-2 rounded-xl text-[13px] font-bold text-white bg-[#5138ed] hover:bg-indigo-600 transition-colors shadow-sm disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2"
        >
          <svg v-if="isSubmitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ props.initialData ? (isSubmitting ? 'Saving...' : 'Save Changes') : (isSubmitting ? 'Creating...' : 'Create Bank') }}
        </button>
      </div>
      
    </div>
  </div>
</template>
