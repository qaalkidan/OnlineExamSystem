<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useInstructorQbStore } from '../store/instructorQbStore'

const route = useRoute()
const router = useRouter()
const qbStore = useInstructorQbStore()

const bankId = route.params.id as string
const bank = ref<any>(null)
const isLoading = ref(true)
const isPublishing = ref(false)

const draftQuestions = computed(() => qbStore.draftQuestions)

// Calculate total drafts from nested structure
const totalDrafts = computed(() => {
  let count = 0
  if (!Array.isArray(draftQuestions.value)) return 0
  draftQuestions.value.forEach((typeGroup: any) => {
    if (typeGroup.instructionGroups) {
      typeGroup.instructionGroups.forEach((instGroup: any) => {
        if (instGroup.questions) count += instGroup.questions.length
      })
    }
  })
  return count
})

const getQuestionCount = (typeGroup: any) => {
  let count = 0
  if (typeGroup.instructionGroups) {
    typeGroup.instructionGroups.forEach((instGroup: any) => {
      if (instGroup.questions) count += instGroup.questions.length
    })
  }
  return count
}

// Toast State
const toastMessage = ref('')
const showToast = ref(false)

const triggerToast = (msg: string) => {
  toastMessage.value = msg
  showToast.value = true
  setTimeout(() => { showToast.value = false }, 4000)
}

onMounted(async () => {
  try {
    const data = await qbStore.fetchQuestionBank(bankId)
    bank.value = data.bank
    await qbStore.fetchDraftQuestions(bankId)
  } catch (err) {
    console.error("Failed to load draft questions", err)
  } finally {
    isLoading.value = false
  }
})

const handleEdit = (question: any) => {
  router.push({
    path: `/instructor/question-banks/${bankId}/create-question`,
    query: { editDraftId: question.id }
  })
}

const handleDelete = async (questionId: number) => {
  if (!confirm("Are you sure you want to delete this draft question?")) return
  try {
    await qbStore.deleteQuestion(questionId)
    // Re-fetch to update grouped data properly instead of manual nested deletion
    await qbStore.fetchDraftQuestions(bankId)
    triggerToast("Draft question removed successfully.")
  } catch (err) {
    triggerToast("Failed to delete draft question.")
  }
}

const handlePublishAll = async () => {
  if (totalDrafts.value === 0) {
    alert("No draft questions available to publish.")
    return
  }

  if (!confirm(`Are you sure you want to publish ${totalDrafts.value} question(s)? They will be permanently saved to this Question Bank.`)) return

  isPublishing.value = true
  try {
    await qbStore.publishQuestions(bankId)
    triggerToast("All questions published successfully!")
    setTimeout(() => {
      router.push(`/instructor/question-banks/${bankId}`)
    }, 1200)
  } catch (err) {
    triggerToast("Failed to publish questions. Please try again.")
    isPublishing.value = false
  }
}

const getDifficultyClass = (diff: string) => {
  switch (diff?.toLowerCase()) {
    case 'easy': return 'bg-emerald-50 text-emerald-700 border-emerald-200'
    case 'hard': return 'bg-rose-50 text-rose-700 border-rose-200'
    default: return 'bg-amber-50 text-amber-700 border-amber-200'
  }
}

// Letter label for Matching Options
const getLetterLabel = (index: number) => {
  let label = ''
  let num = index
  while (num >= 0) {
    label = String.fromCharCode(65 + (num % 26)) + label
    num = Math.floor(num / 26) - 1
  }
  return label
}
</script>

<template>
  <div class="min-h-screen bg-[#f8fafc] p-3.5 sm:p-6 lg:p-10 font-sans pb-28">

    <!-- Toast Notification -->
    <transition enter-active-class="transition duration-300 ease-out" enter-from-class="transform translate-y-2 opacity-0" enter-to-class="transform translate-y-0 opacity-100" leave-active-class="transition duration-200 ease-in" leave-from-class="transform translate-y-0 opacity-100" leave-to-class="transform translate-y-2 opacity-0">
      <div v-if="showToast" class="fixed top-4 right-4 z-50 flex items-center gap-3 px-4 py-3 bg-slate-900 text-white text-xs font-semibold rounded-xl shadow-2xl border border-slate-800 max-w-[calc(100vw-2rem)]">
        <svg class="w-4 h-4 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        <span>{{ toastMessage }}</span>
      </div>
    </transition>

    <!-- Header & Breadcrumbs -->
    <div class="max-w-5xl mx-auto mb-8">
      <div class="flex items-center flex-wrap gap-1.5 text-xs font-semibold text-slate-400 mb-3">
        <router-link to="/instructor/question-banks" class="hover:text-slate-600 transition-colors whitespace-nowrap">Question Banks</router-link>
        <span>/</span>
        <router-link :to="`/instructor/question-banks/${bankId}`" class="hover:text-slate-600 transition-colors truncate max-w-[120px] sm:max-w-[200px]">{{ bank?.title || 'Question Bank' }}</router-link>
        <span>/</span>
        <span class="text-[#5138ed] whitespace-nowrap">Review Questions</span>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Review Draft Questions</h1>
            <span class="px-2.5 py-1 bg-amber-50 border border-amber-200 text-amber-700 text-xs font-bold rounded-md uppercase tracking-wider">
              {{ totalDrafts }} Drafts Pending
            </span>
          </div>
          <p class="text-xs text-slate-500 mt-1">Review all grouped questions created in this session before publishing.</p>
        </div>

        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
          <router-link :to="`/instructor/question-banks/${bankId}/create-question`" class="min-h-[44px] px-4 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-bold rounded-xl hover:bg-slate-50 transition-colors flex items-center justify-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add More Questions
          </router-link>
          
          <button 
            @click="handlePublishAll" 
            :disabled="isPublishing || totalDrafts === 0" 
            class="min-h-[44px] px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-600 transition-colors shadow-lg shadow-indigo-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
          >
            <svg v-if="!isPublishing" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <svg v-else class="animate-spin w-4 h-4 text-white shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <span>{{ isPublishing ? 'Publishing...' : 'Publish Questions' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Questions Container -->
    <div class="max-w-5xl mx-auto">
      <div v-if="isLoading" class="p-12 text-center bg-white rounded-2xl border border-slate-100">
        <svg class="animate-spin w-8 h-8 text-[#5138ed] mx-auto mb-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
        <p class="text-xs text-slate-500 font-medium">Loading draft questions...</p>
      </div>

      <div v-else-if="totalDrafts === 0" class="p-16 text-center bg-white rounded-2xl border border-slate-100 shadow-sm">
        <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
        </div>
        <h3 class="text-base font-bold text-slate-800 mb-1">No Draft Questions Found</h3>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mb-6">You don't have any pending draft questions for this bank. Create questions first using the form.</p>
        <router-link :to="`/instructor/question-banks/${bankId}/create-question`" class="px-5 py-2.5 bg-[#5138ed] text-white text-xs font-bold rounded-xl hover:bg-indigo-600 transition-colors">
          Create Question
        </router-link>
      </div>

      <div v-else class="space-y-12">
        <!-- Iterate over Question Types -->
        <div v-for="(typeGroup, typeIdx) in draftQuestions" :key="typeGroup.questionType">
          
          <!-- TYPE HEADER -->
          <div class="mb-6 border-b-2 border-slate-200 pb-3 flex items-center justify-between">
            <h2 class="text-lg font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
              <span class="w-2 h-6 bg-[#5138ed] rounded-full inline-block"></span>
              {{ typeGroup.questionType }} ({{ getQuestionCount(typeGroup) }} Questions)
            </h2>
          </div>

          <!-- Iterate over Instructions within Type -->
          <div class="space-y-10 pl-0 sm:pl-6 border-l-[3px] border-slate-100 ml-1">
            <div v-for="(instGroup, instIdx) in typeGroup.instructionGroups" :key="instIdx">
              
              <!-- INSTRUCTION HEADER -->
              <div class="mb-5 relative">
                <div class="absolute -left-[30px] top-1/2 -translate-y-1/2 w-3 h-3 bg-white border-[3px] border-indigo-400 rounded-full"></div>
                <div v-if="instGroup.instruction">
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Instructor Instruction:</span>
                  <h3 class="text-[14px] font-bold text-slate-800 italic">"{{ instGroup.instruction }}"</h3>
                </div>
                <div v-else>
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Instructor Instruction:</span>
                  <h3 class="text-[13px] font-bold text-slate-400 italic">No specific instruction provided.</h3>
                </div>
              </div>

              <!-- Questions for this Instruction -->
              <div class="space-y-4 pl-4">
                <div 
                  v-for="(q, qIdx) in instGroup.questions" 
                  :key="q.id" 
                  class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all relative group"
                >
                  <!-- Question Metadata Bar -->
                  <div class="flex flex-wrap items-center justify-between gap-3 mb-5 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                      <!-- Question Number -->
                      <span class="w-8 h-8 rounded-full bg-indigo-50 text-[#5138ed] flex items-center justify-center text-[13px] font-black border border-indigo-100">
                        {{ Number(qIdx) + 1 }}
                      </span>
                      <span :class="getDifficultyClass(q.difficulty)" class="px-2.5 py-1 text-[10px] uppercase font-black rounded-md border tracking-widest">
                        {{ q.difficulty || 'Medium' }}
                      </span>
                      <span class="px-2.5 py-1 bg-slate-50 border border-slate-200 text-slate-600 text-[10px] font-black uppercase rounded-md tracking-widest">
                        {{ q.marks || 1 }} Mark{{ (q.marks || 1) > 1 ? 's' : '' }}
                      </span>
                      <span v-if="q.chapter" class="px-2.5 py-1 bg-slate-100 text-slate-500 text-[10px] font-bold uppercase rounded-md tracking-widest">
                        {{ q.chapter }}
                      </span>
                    </div>

                    <div class="flex items-center gap-2">
                      <button @click="handleEdit(q)" class="p-1.5 text-slate-400 hover:text-[#5138ed] hover:bg-indigo-50 rounded-lg transition-colors" title="Edit">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                      </button>
                      <button @click="handleDelete(q.id)" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                      </button>
                    </div>
                  </div>

                  <!-- QUESTION CONTENT RENDERERS -->
                  <div class="text-sm text-slate-800">
                    
                    <!-- 1. MULTIPLE CHOICE / TRUE-FALSE -->
                    <template v-if="q.type === 'multiple_choice' || q.type === 'true_false' || q.type === 'Multiple Choice (MCQ)' || q.type === 'True / False'">
                      <div class="font-medium mb-4 text-[15px]" v-html="q.text"></div>
                      <div v-if="q.image_url" class="mb-4">
                        <img :src="q.image_url" alt="Question Image" class="max-h-48 rounded-xl border border-slate-200 object-cover" />
                      </div>
                      
                      <!-- Options -->
                      <div v-if="q.options && q.options.length" class="space-y-2 mb-5">
                        <div 
                          v-for="(opt, oIdx) in q.options" 
                          :key="oIdx"
                          class="flex items-start gap-3 p-2 rounded-lg"
                        >
                          <span class="w-6 h-6 shrink-0 rounded-md bg-slate-100 text-slate-600 text-[11px] font-bold flex items-center justify-center border border-slate-200 mt-0.5">
                            {{ opt.label || getLetterLabel(Number(oIdx)) }}
                          </span>
                          <span class="text-[14px] leading-relaxed pt-0.5">{{ opt.text || opt }}</span>
                        </div>
                      </div>
                      
                      <!-- Correct Answer Box -->
                      <div class="bg-emerald-50/50 border border-emerald-100 p-3 rounded-xl flex items-center gap-2 text-[12px]">
                        <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px]">Correct Answer:</span>
                        <span class="font-bold text-slate-800">{{ q.correct_answer }}</span>
                      </div>
                    </template>

                    <!-- 2. SHORT ANSWER / FILL IN THE BLANK -->
                    <template v-else-if="q.type === 'short_answer' || q.type === 'fill_in_the_blank' || q.type === 'Short Answer' || q.type === 'Fill in the Blank'">
                      <div class="font-medium mb-4 text-[15px]" v-html="q.text"></div>
                      <div v-if="q.image_url" class="mb-4">
                        <img :src="q.image_url" alt="Question Image" class="max-h-48 rounded-xl border border-slate-200 object-cover" />
                      </div>

                      <div class="bg-slate-50 border border-slate-200 border-dashed p-3 rounded-xl mb-4 text-slate-400 text-xs text-center italic">
                        [ Student's Answer Field ]
                      </div>

                      <div class="bg-emerald-50/50 border border-emerald-100 p-3 rounded-xl flex items-center gap-2 text-[12px]">
                        <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px]">Correct Answer:</span>
                        <span class="font-bold text-slate-800">{{ q.correct_answer || (q.question_data && q.question_data.answers ? q.question_data.answers.map((a: any)=>a.text).join(' OR ') : '') }}</span>
                      </div>
                    </template>

                    <!-- 3. ESSAY -->
                    <template v-else-if="q.type === 'essay' || q.type === 'Essay'">
                      <div class="font-medium mb-4 text-[15px]" v-html="q.text"></div>
                      <div v-if="q.image_url" class="mb-4">
                        <img :src="q.image_url" alt="Question Image" class="max-h-48 rounded-xl border border-slate-200 object-cover" />
                      </div>

                      <div class="bg-indigo-50/30 border border-indigo-100 p-4 rounded-xl text-[12px]">
                        <span class="font-bold text-[#5138ed] uppercase tracking-wider text-[10px] block mb-2">Model Answer / Grading Rubric:</span>
                        <div class="text-slate-700 leading-relaxed text-[13px]" v-html="q.explanation || 'No model answer provided.'"></div>
                      </div>
                    </template>

                    <!-- 4. MATCHING -->
                    <template v-else-if="q.type === 'matching' || q.type === 'Matching'">
                      <div v-if="q.text" class="font-medium mb-4 text-[15px]" v-html="q.text"></div>

                      <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                        <!-- Column A -->
                        <div>
                          <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-3 pb-2 border-b border-slate-100">Column A</h4>
                          <div class="space-y-3">
                            <div v-for="(colA, cIdx) in (q.question_data?.column_a || [])" :key="cIdx" class="flex gap-3">
                              <span class="font-bold text-slate-400 text-[13px] w-4 shrink-0 text-right">{{ Number(cIdx) + 1 }}.</span>
                              <div class="text-[13px] bg-slate-50 border border-slate-200 rounded-lg p-2.5 w-full flex-1 leading-relaxed" v-html="colA"></div>
                            </div>
                          </div>
                        </div>

                        <!-- Column B -->
                        <div>
                          <h4 class="text-[11px] font-black text-slate-400 uppercase tracking-wider mb-3 pb-2 border-b border-slate-100">Column B</h4>
                          <div class="space-y-3">
                            <div v-for="(colB, cIdx) in (q.question_data?.column_b || [])" :key="cIdx" class="flex gap-3 items-center">
                              <span class="w-6 h-6 rounded-md bg-indigo-50 text-[#5138ed] text-[11px] font-bold flex items-center justify-center border border-indigo-100 shrink-0">
                                {{ colB.label || getLetterLabel(Number(cIdx)) }}
                              </span>
                              <div class="text-[13px] flex-1 text-slate-700 leading-relaxed">{{ colB.text || colB }}</div>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Correct Matches -->
                      <div class="bg-emerald-50/50 border border-emerald-100 p-4 rounded-xl text-[12px]">
                        <span class="font-bold text-emerald-700 uppercase tracking-wider text-[10px] block mb-3">Correct Matches:</span>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                          <div v-for="(ans, key) in (q.question_data?.correct_answers || {})" :key="key" class="flex items-center gap-2 font-bold text-slate-800 bg-white border border-emerald-100 px-3 py-1.5 rounded-lg w-fit">
                            <span>{{ key }}</span>
                            <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            <span>{{ ans }}</span>
                          </div>
                        </div>
                      </div>
                    </template>

                  </div>

                </div>
              </div>

            </div>
          </div>
          
        </div>
      </div>
      
    </div>
  </div>
</template>
