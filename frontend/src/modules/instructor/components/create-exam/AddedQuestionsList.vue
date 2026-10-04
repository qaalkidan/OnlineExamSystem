<script setup lang="ts">
import { ref, computed } from 'vue'
import { useCreateExamStore } from '../../store/createExamStore'

const formStore = useCreateExamStore()

const isExpanded = ref(true)
const searchQuery = ref('')

const totalExamMarks = computed(() => {
  return formStore.questions.reduce((sum: number, q: any) => sum + (Number(q.marks) || 1), 0)
})

const filteredQuestions = computed(() => {
  if (!searchQuery.value.trim()) return formStore.questions
  const query = searchQuery.value.toLowerCase()
  return formStore.questions.filter((q: any) => {
    const textMatch = (q.text || '').toLowerCase().includes(query)
    const instMatch = (q.instruction || '').toLowerCase().includes(query)
    const typeMatch = (q.type || '').toLowerCase().includes(query)
    return textMatch || instMatch || typeMatch
  })
})

const removeQuestion = (index: number) => {
  formStore.questions.splice(index, 1)
}

const clearAllQuestions = () => {
  if (confirm(`Are you sure you want to remove all ${formStore.questions.length} questions from this exam draft?`)) {
    formStore.questions = []
  }
}

const getTypeLabel = (type: string) => {
  const t = (type || '').toLowerCase()
  if (t === 'multiple_choice' || t === 'mcq') return 'MCQ'
  if (t === 'true_false') return 'True/False'
  if (t === 'fill_blank') return 'Fill Blank'
  if (t === 'short_answer') return 'Short Answer'
  if (t === 'matching') return 'Matching'
  return type
}
</script>

<template>
  <div v-if="formStore.questions.length > 0" class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
    <!-- Header Bar -->
    <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/70 border-b border-slate-100">
      <div class="flex items-center gap-3">
        <button
          type="button"
          @click="isExpanded = !isExpanded"
          class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-600 flex items-center justify-center hover:bg-slate-50 transition-colors cursor-pointer"
        >
          <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': !isExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div>
          <h4 class="text-[14px] font-bold text-slate-800 flex items-center gap-2">
            Questions in this Exam Draft
            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[#5138ed] text-white">
              {{ formStore.questions.length }}
            </span>
          </h4>
          <p class="text-[11.5px] text-slate-500 mt-0.5">
            Marks: <strong :class="totalExamMarks === Number(formStore.totalMarks) ? 'text-emerald-600' : 'text-amber-600'">{{ totalExamMarks }}</strong> / {{ formStore.totalMarks || 100 }} target marks
            <span v-if="totalExamMarks === Number(formStore.totalMarks)" class="text-emerald-600 font-semibold ml-1">✓ Balanced</span>
            <span v-else class="text-amber-600 font-semibold ml-1">({{ Number(formStore.totalMarks) - totalExamMarks }} marks remaining)</span>
          </p>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <div v-if="isExpanded && formStore.questions.length > 3" class="relative w-48">
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Filter questions..."
            class="w-full pl-7 pr-3 py-1.5 bg-white border border-slate-200 rounded-lg text-[12px] text-slate-700 focus:outline-none focus:border-[#5138ed]"
          />
          <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>

        <button
          type="button"
          @click="clearAllQuestions"
          class="px-3 py-1.5 text-rose-600 hover:bg-rose-50 text-[12px] font-bold rounded-lg border border-transparent hover:border-rose-200 transition-colors cursor-pointer"
        >
          Clear All
        </button>
      </div>
    </div>

    <!-- Questions Content (Collapsible) -->
    <div v-if="isExpanded" class="p-4 space-y-2.5 max-h-[420px] overflow-y-auto">
      <div
        v-for="(q, index) in filteredQuestions"
        :key="index"
        class="p-3.5 bg-white rounded-xl border border-slate-100 hover:border-slate-200 hover:shadow-xs transition-all flex items-start gap-3 group"
      >
        <div class="w-6 h-6 rounded-md bg-slate-100 text-slate-500 font-bold font-mono text-[11px] flex items-center justify-center shrink-0 mt-0.5">
          {{ index + 1 }}
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100">
              {{ getTypeLabel(q.type) }}
            </span>
            <span class="text-[10.5px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">
              {{ q.marks || 1 }} Marks
            </span>
            <span v-if="q.difficulty" class="text-[10.5px] text-slate-400">
              • {{ q.difficulty }}
            </span>
            <span v-if="q.instruction" class="text-[10.5px] text-indigo-500 font-medium truncate max-w-xs">
              • {{ q.instruction }}
            </span>
          </div>

          <p class="text-[13px] font-medium text-slate-800 leading-snug line-clamp-2" v-html="q.text"></p>

          <!-- Options snippet -->
          <div v-if="q.options && q.options.length > 0" class="flex flex-wrap gap-1 mt-1.5">
            <span
              v-for="(opt, optIdx) in q.options.slice(0, 4)"
              :key="optIdx"
              :class="[
                (String.fromCharCode(65 + Number(optIdx)) === q.correct_answer || opt === q.correct_answer)
                  ? 'bg-emerald-50 text-emerald-700 font-semibold'
                  : 'bg-slate-50 text-slate-500',
                'text-[10px] px-1.5 py-0.5 rounded border border-slate-100 truncate max-w-[140px]'
              ]"
            >
              {{ String.fromCharCode(65 + Number(optIdx)) }}: {{ typeof opt === 'string' ? opt : opt.text }}
            </span>
            <span v-if="q.options.length > 4" class="text-[10px] text-slate-400 px-1">+{{ q.options.length - 4 }} more</span>
          </div>
        </div>

        <!-- Remove question button -->
        <button
          type="button"
          @click="removeQuestion(index)"
          class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
          title="Remove Question"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
        </button>
      </div>
    </div>
  </div>
</template>
