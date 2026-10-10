<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorQbStore, type QuestionBank } from '../../store/instructorQbStore'
import { useSemesterLockStore } from '../../store/semesterLockStore'

const qbStore = useInstructorQbStore()
const lockStore = useSemesterLockStore()
const router = useRouter()

const emit = defineEmits<{
  (e: 'edit', bank: QuestionBank): void
  (e: 'create'): void
}>()

const searchQuery = ref('')
const perPage = ref(10)
const currentPage = ref(1)
const isRefreshing = ref(false)

// Delete confirmation modal state
const showDeleteModal = ref(false)
const bankToDelete = ref<QuestionBank | null>(null)
const isDeleting = ref(false)

// Reset to page 1 on search or perPage change
watch([searchQuery, perPage], () => {
  currentPage.value = 1
})

// Format ISO date strings
const formatDate = (iso: string | null | undefined) => {
  if (!iso) return '—'
  try {
    const d = new Date(iso)
    if (isNaN(d.getTime())) return '—'
    return d.toLocaleDateString('en-US', {
      month: 'short',
      day: '2-digit',
      year: 'numeric'
    })
  } catch {
    return '—'
  }
}

// Filtered Question Banks
const filteredBanks = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) return qbStore.banks

  return qbStore.banks.filter(bank => {
    const titleMatch = bank.title?.toLowerCase().includes(query)
    const descMatch = bank.description?.toLowerCase().includes(query)
    const courseMatch = (bank.course_code || bank.course_name)?.toLowerCase().includes(query)
    return titleMatch || descMatch || courseMatch
  })
})

// Pagination calculations
const totalPages = computed(() => {
  return Math.max(1, Math.ceil(filteredBanks.value.length / perPage.value))
})

const paginatedBanks = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filteredBanks.value.slice(start, start + perPage.value)
})

const paginationStart = computed(() => {
  if (filteredBanks.value.length === 0) return 0
  return (currentPage.value - 1) * perPage.value + 1
})

const paginationEnd = computed(() => {
  return Math.min(filteredBanks.value.length, currentPage.value * perPage.value)
})

const handleRefresh = async () => {
  isRefreshing.value = true
  try {
    await qbStore.fetchQuestionBanks(searchQuery.value)
  } finally {
    isRefreshing.value = false
  }
}

const clearSearch = () => {
  searchQuery.value = ''
}

const goToPage = (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    currentPage.value = page
  }
}

const handleView = (bank: QuestionBank) => {
  router.push(`/instructor/question-banks/${bank.id}`)
}

const handleAddQuestion = (bank: QuestionBank) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('add questions')
    return
  }
  router.push(`/instructor/question-banks/${bank.id}/create-question`)
}

const handleEdit = (bank: QuestionBank) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('edit question bank')
    return
  }
  emit('edit', bank)
}

const confirmDelete = (bank: QuestionBank) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete question bank')
    return
  }
  bankToDelete.value = bank
  showDeleteModal.value = true
}

const executeDelete = async () => {
  if (!bankToDelete.value) return
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete question bank')
    showDeleteModal.value = false
    return
  }

  isDeleting.value = true
  try {
    await qbStore.deleteQuestionBank(bankToDelete.value.id)
    showDeleteModal.value = false
    bankToDelete.value = null
    // Adjust page if last item on page was deleted
    if (paginatedBanks.value.length === 0 && currentPage.value > 1) {
      currentPage.value--
    }
  } catch (err) {
    console.error('Failed to delete question bank:', err)
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
    
    <!-- Toolbar -->
    <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 bg-slate-50/50">
      
      <!-- Search Input -->
      <div class="relative flex-1 max-w-lg">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input 
          v-model="searchQuery"
          type="text" 
          class="block w-full pl-10 pr-9 py-2.5 min-h-[44px] bg-white border border-slate-200 rounded-xl text-xs sm:text-[13px] text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] transition-colors"
          placeholder="Search question banks by name, course, or description..."
        >
        <button 
          v-if="searchQuery"
          @click="clearSearch"
          class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
          title="Clear search"
          aria-label="Clear search"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Controls: Result Count & Refresh & PerPage -->
      <div class="flex items-center justify-between sm:justify-end gap-2.5 shrink-0">
        <!-- Results Badge -->
        <span class="text-xs text-slate-500 font-medium">
          <span class="font-bold text-slate-800">{{ filteredBanks.length }}</span> {{ filteredBanks.length === 1 ? 'bank' : 'banks' }}
        </span>

        <!-- Page Size Selector -->
        <div class="flex items-center gap-1.5 pl-2 border-l border-slate-200">
          <span class="text-xs text-slate-400 hidden xl:inline">Show</span>
          <select 
            v-model="perPage" 
            class="text-xs bg-white border border-slate-200 text-slate-700 rounded-lg px-2.5 py-1.5 min-h-[36px] focus:outline-none focus:border-[#5138ed]"
            aria-label="Select items per page"
          >
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
          </select>
        </div>

        <!-- Refresh Button -->
        <button 
          @click="handleRefresh"
          :disabled="isRefreshing || qbStore.isLoading"
          class="p-2 border border-slate-200 text-slate-600 hover:text-[#5138ed] hover:border-indigo-200 hover:bg-indigo-50/50 rounded-xl transition-colors min-h-[38px] min-w-[38px] flex items-center justify-center disabled:opacity-50"
          title="Refresh question banks"
          aria-label="Refresh question banks"
        >
          <svg 
            class="w-4 h-4" 
            :class="{ 'animate-spin text-[#5138ed]': isRefreshing || qbStore.isLoading }"
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>

    </div>

    <!-- Desktop Table View -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider bg-slate-50/30">
            <th class="py-3.5 pl-6 pr-4 w-[28%]">Question Bank</th>
            <th class="py-3.5 px-4 w-[24%]">Description</th>
            <th class="py-3.5 px-4 w-[14%]">Course</th>
            <th class="py-3.5 px-4 w-[12%] text-center">Questions</th>
            <th class="py-3.5 px-4 w-[12%]">Created Date</th>
            <th class="py-3.5 pl-4 pr-6 text-right w-[10%]">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100/80">
          
          <!-- Loading State -->
          <template v-if="qbStore.isLoading">
            <tr v-for="i in 3" :key="'skeleton-' + i" class="animate-pulse">
              <td class="py-4 pl-6 pr-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-slate-100 shrink-0"></div>
                  <div class="space-y-1.5 flex-1">
                    <div class="h-4 bg-slate-100 rounded w-3/4"></div>
                    <div class="h-3 bg-slate-100 rounded w-1/3"></div>
                  </div>
                </div>
              </td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-100 rounded w-4/5"></div></td>
              <td class="py-4 px-4"><div class="h-5 bg-slate-100 rounded-md w-20"></div></td>
              <td class="py-4 px-4 text-center"><div class="h-5 bg-slate-100 rounded-full w-12 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-100 rounded w-24"></div></td>
              <td class="py-4 pl-4 pr-6 text-right"><div class="h-8 bg-slate-100 rounded-lg w-16 ml-auto"></div></td>
            </tr>
          </template>

          <!-- Search Empty State -->
          <tr v-else-if="filteredBanks.length === 0 && searchQuery">
            <td colspan="6" class="py-12 text-center">
              <div class="max-w-sm mx-auto flex flex-col items-center">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mb-3">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <h4 class="text-sm font-bold text-slate-800 mb-1">No question banks match your search</h4>
                <p class="text-xs text-slate-500 mb-4 text-center">
                  We couldn't find any question bank matching "{{ searchQuery }}". Try adjusting your search keywords.
                </p>
                <button 
                  @click="clearSearch"
                  class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors min-h-[38px]"
                >
                  Clear Search Filter
                </button>
              </div>
            </td>
          </tr>

          <!-- Empty State (Zero Banks) -->
          <tr v-else-if="qbStore.banks.length === 0">
            <td colspan="6" class="py-14 text-center">
              <div class="max-w-md mx-auto flex flex-col items-center">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-[#5138ed] flex items-center justify-center mb-3.5 shadow-2xs">
                  <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                  </svg>
                </div>
                <h4 class="text-base font-bold text-slate-900 mb-1">No Question Banks Yet</h4>
                <p class="text-xs text-slate-500 mb-4 text-center max-w-sm">
                  You haven't created any question banks for your assigned courses yet. Create your first question bank to start assembling questions.
                </p>
                <button 
                  v-if="!lockStore.isLocked"
                  @click="$emit('create')"
                  class="px-5 py-2.5 bg-[#5138ed] hover:bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-xs transition-colors min-h-[40px] flex items-center gap-2"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Create Question Bank
                </button>
                <span v-else class="text-xs text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200 font-semibold">
                  Semester is locked (Read-Only)
                </span>
              </div>
            </td>
          </tr>

          <!-- Data Rows -->
          <tr 
            v-else 
            v-for="bank in paginatedBanks" 
            :key="bank.id" 
            class="hover:bg-slate-50/70 transition-colors group"
          >
            <!-- Bank Title & Icon -->
            <td class="py-4 pl-6 pr-4">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50/80 border border-indigo-100/70 flex items-center justify-center shrink-0 text-[#5138ed] shadow-2xs group-hover:scale-105 transition-transform">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                  </svg>
                </div>
                <div class="min-w-0 flex-1">
                  <button 
                    @click="handleView(bank)"
                    class="text-[13px] font-bold text-slate-800 hover:text-[#5138ed] transition-colors truncate block text-left w-full cursor-pointer"
                    :title="bank.title"
                  >
                    {{ bank.title }}
                  </button>
                  <span class="text-[11px] text-slate-400 block truncate">
                    ID: #{{ bank.id }}
                  </span>
                </div>
              </div>
            </td>

            <!-- Description -->
            <td class="py-4 px-4">
              <p 
                class="text-[13px] max-w-xs truncate"
                :class="bank.description ? 'text-slate-600' : 'text-slate-400 italic'"
                :title="bank.description || 'No description available.'"
              >
                {{ bank.description || 'No description available.' }}
              </p>
            </td>

            <!-- Course -->
            <td class="py-4 px-4">
              <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700 border border-slate-200/60 max-w-[130px] truncate" :title="bank.course_name || bank.course_code">
                {{ bank.course_code || bank.course_name || 'GENERAL' }}
              </span>
            </td>

            <!-- Number of Questions -->
            <td class="py-4 px-4 text-center">
              <span 
                class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold"
                :class="bank.total_questions > 0 ? 'bg-indigo-50 text-[#5138ed] border border-indigo-100' : 'bg-slate-100 text-slate-500'"
              >
                {{ bank.total_questions }} {{ bank.total_questions === 1 ? 'question' : 'questions' }}
              </span>
            </td>

            <!-- Created Date -->
            <td class="py-4 px-4 text-[13px] text-slate-600 font-medium">
              <div class="flex items-center gap-1.5 whitespace-nowrap">
                <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{{ formatDate(bank.created_at || bank.updated_at) }}</span>
              </div>
            </td>

            <!-- Actions -->
            <td class="py-4 pl-4 pr-6 text-right">
              <div class="flex items-center justify-end gap-1.5">
                
                <!-- Unlocked Mode Actions -->
                <template v-if="!lockStore.isLocked">
                  <!-- View Questions -->
                  <button 
                    @click="handleView(bank)" 
                    class="w-8 h-8 flex items-center justify-center border border-slate-200 text-slate-600 hover:text-[#5138ed] hover:border-indigo-200 hover:bg-indigo-50/50 rounded-lg transition-colors min-h-[34px] min-w-[34px]" 
                    title="View Questions"
                    aria-label="View Questions"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>

                  <!-- Add Question -->
                  <button 
                    @click="handleAddQuestion(bank)" 
                    class="w-8 h-8 flex items-center justify-center border border-slate-200 text-[#5138ed] hover:bg-indigo-50 hover:border-indigo-200 rounded-lg transition-colors min-h-[34px] min-w-[34px]" 
                    title="Add Question to Bank"
                    aria-label="Add Question"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                  </button>

                  <!-- Edit Bank Title/Desc -->
                  <button 
                    @click="handleEdit(bank)" 
                    class="w-8 h-8 flex items-center justify-center border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 hover:border-slate-300 rounded-lg transition-colors min-h-[34px] min-w-[34px]" 
                    title="Edit Bank Details"
                    aria-label="Edit Bank Details"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                  </button>

                  <!-- Delete Bank -->
                  <button 
                    @click="confirmDelete(bank)" 
                    class="w-8 h-8 flex items-center justify-center border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 rounded-lg transition-colors min-h-[34px] min-w-[34px]" 
                    title="Delete Bank"
                    aria-label="Delete Bank"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </template>

                <!-- Semester Locked Mode Actions -->
                <template v-else>
                  <button 
                    @click="handleView(bank)" 
                    class="px-3 py-1.5 flex items-center gap-1.5 border border-slate-200 bg-white hover:bg-indigo-50/50 hover:border-indigo-200 text-slate-700 hover:text-[#5138ed] rounded-lg transition-colors text-xs font-bold min-h-[34px] cursor-pointer"
                    title="View Questions (Read-Only)"
                  >
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>View</span>
                  </button>
                  <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded border border-emerald-200/70 inline-flex items-center gap-1">
                    <svg class="w-3 h-3 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Locked
                  </span>
                </template>

              </div>
            </td>
          </tr>

        </tbody>
      </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden p-4 space-y-3">
      <!-- Loading Skeleton -->
      <template v-if="qbStore.isLoading">
        <div v-for="i in 3" :key="'mob-skel-' + i" class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 animate-pulse space-y-3">
          <div class="h-4 bg-slate-200 rounded w-1/2"></div>
          <div class="h-3 bg-slate-100 rounded w-3/4"></div>
          <div class="h-8 bg-slate-100 rounded"></div>
        </div>
      </template>

      <!-- Search Empty Mobile -->
      <div v-else-if="filteredBanks.length === 0 && searchQuery" class="text-center py-8">
        <p class="text-sm font-bold text-slate-800">No question banks match your search</p>
        <p class="text-xs text-slate-500 mt-1 mb-3">Adjust your keywords or clear the filter.</p>
        <button @click="clearSearch" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold">
          Clear Search
        </button>
      </div>

      <!-- Empty Mobile -->
      <div v-else-if="qbStore.banks.length === 0" class="text-center py-8">
        <p class="text-sm font-bold text-slate-800">No question banks found</p>
        <p class="text-xs text-slate-500 mt-1 mb-3">Create your first question bank to start building exams.</p>
        <button 
          v-if="!lockStore.isLocked"
          @click="$emit('create')"
          class="px-4 py-2 bg-[#5138ed] text-white rounded-xl text-xs font-bold"
        >
          Create Bank
        </button>
      </div>

      <!-- Mobile Data Cards -->
      <div 
        v-else 
        v-for="bank in paginatedBanks" 
        :key="'mob-' + bank.id"
        class="p-4 rounded-xl border border-slate-200/80 bg-white hover:border-slate-300 transition-colors space-y-3 shadow-2xs"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center shrink-0 text-[#5138ed]">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
              </svg>
            </div>
            <div class="min-w-0">
              <h3 
                @click="handleView(bank)"
                class="text-sm font-bold text-slate-900 leading-snug truncate cursor-pointer hover:text-[#5138ed]"
              >
                {{ bank.title }}
              </h3>
              <span class="text-[10px] text-slate-400">ID: #{{ bank.id }}</span>
            </div>
          </div>
          <span v-if="lockStore.isLocked" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 shrink-0 flex items-center gap-1">
            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Locked
          </span>
          <span v-else class="text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded shrink-0">
            {{ bank.course_code || 'GENERAL' }}
          </span>
        </div>

        <p class="text-xs text-slate-600 line-clamp-2" :class="{ 'italic text-slate-400': !bank.description }">
          {{ bank.description || 'No description available.' }}
        </p>

        <!-- Meta info -->
        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-2 border-t border-slate-100">
          <span class="font-medium">
            {{ bank.total_questions }} {{ bank.total_questions === 1 ? 'question' : 'questions' }}
          </span>
          <span>Created: {{ formatDate(bank.created_at || bank.updated_at) }}</span>
        </div>

        <!-- Mobile Actions -->
        <div class="grid grid-cols-2 gap-2 pt-1">
          <button 
            @click="handleView(bank)"
            class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-[#5138ed] bg-indigo-50 border border-indigo-100 rounded-xl hover:bg-indigo-100 transition-colors min-h-[44px]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View
          </button>
          
          <template v-if="!lockStore.isLocked">
            <button 
              @click="handleAddQuestion(bank)"
              class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              Add Qs
            </button>
            <button 
              @click="handleEdit(bank)"
              class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              Edit
            </button>
            <button 
              @click="confirmDelete(bank)"
              class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-bold text-rose-600 bg-rose-50 border border-rose-100 rounded-xl hover:bg-rose-100 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              Delete
            </button>
          </template>
        </div>
      </div>
    </div>
    
    <!-- Real Dynamic Pagination -->
    <div 
      v-if="filteredBanks.length > 0"
      class="p-4 sm:px-6 sm:py-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-slate-50/40"
    >
      <span class="text-xs sm:text-[13px] text-slate-500 font-medium text-center sm:text-left">
        Showing <span class="font-bold text-slate-800">{{ paginationStart }}</span> to <span class="font-bold text-slate-800">{{ paginationEnd }}</span> of <span class="font-bold text-slate-800">{{ filteredBanks.length }}</span> question banks
      </span>
      
      <div class="flex items-center gap-1.5">
        <!-- Previous Page Button -->
        <button 
          @click="goToPage(currentPage - 1)"
          :disabled="currentPage === 1"
          class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300 disabled:opacity-40 disabled:pointer-events-none transition-colors min-h-[36px] min-w-[36px]"
          title="Previous Page"
          aria-label="Previous Page"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <!-- Page Numbers -->
        <template v-for="page in totalPages" :key="'page-' + page">
          <button 
            @click="goToPage(page)"
            class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-xl font-bold text-xs sm:text-sm transition-colors min-h-[36px] min-w-[36px]"
            :class="currentPage === page ? 'bg-[#5138ed] text-white shadow-xs' : 'border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300'"
            :aria-label="'Page ' + page"
            :aria-current="currentPage === page ? 'page' : undefined"
          >
            {{ page }}
          </button>
        </template>

        <!-- Next Page Button -->
        <button 
          @click="goToPage(currentPage + 1)"
          :disabled="currentPage === totalPages"
          class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 hover:bg-white hover:border-slate-300 disabled:opacity-40 disabled:pointer-events-none transition-colors min-h-[36px] min-w-[36px]"
          title="Next Page"
          aria-label="Next Page"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Clean Delete Confirmation Modal -->
    <div 
      v-if="showDeleteModal && bankToDelete" 
      class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
    >
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-150">
        <div class="p-6">
          <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </div>
          <h3 class="text-base font-bold text-slate-900 mb-1">Delete Question Bank?</h3>
          <p class="text-xs sm:text-[13px] text-slate-600 mb-2">
            Are you sure you want to delete <strong class="text-slate-800 font-semibold">"{{ bankToDelete.title }}"</strong>?
          </p>
          <p class="text-xs text-rose-600 bg-rose-50 p-3 rounded-xl border border-rose-100 mb-4">
            This action cannot be undone and will permanently remove this question bank and any draft questions inside it.
          </p>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button 
              @click="showDeleteModal = false"
              :disabled="isDeleting"
              class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors min-h-[42px]"
            >
              Cancel
            </button>
            <button 
              @click="executeDelete"
              :disabled="isDeleting"
              class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 transition-colors shadow-xs disabled:opacity-50 min-h-[42px] flex items-center gap-2"
            >
              <svg v-if="isDeleting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ isDeleting ? 'Deleting...' : 'Delete Bank' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>
