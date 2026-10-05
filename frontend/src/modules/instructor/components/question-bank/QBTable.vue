<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useInstructorQbStore } from '../../store/instructorQbStore'
import { useSemesterLockStore } from '../../store/semesterLockStore'

const qbStore = useInstructorQbStore()
const lockStore = useSemesterLockStore()
const router = useRouter()

const emit = defineEmits(['edit'])

const searchQuery = ref('')
const selectedType = ref('All Types')
const selectedStatus = ref('All Statuses')

// Format ISO date strings
const formatDate = (iso: string | null) => {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'short', day: '2-digit', year: 'numeric'
  })
}

const filteredBanks = computed(() => {
  let result = qbStore.banks

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(b => b.title.toLowerCase().includes(q) || (b.description && b.description.toLowerCase().includes(q)))
  }

  return result
})

const handleView = (bank: any) => {
  router.push(`/instructor/question-banks/${bank.id}`)
}

const handleAddQuestion = (bank: any) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('add questions')
    return
  }
  router.push(`/instructor/question-banks/${bank.id}/create-question`)
}

const handleEdit = (bank: any) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('edit question bank')
    return
  }
  emit('edit', bank)
}

const handleDelete = async (id: number) => {
  if (lockStore.isLocked) {
    lockStore.promptLockedNotice('delete question bank')
    return
  }
  if (confirm('Are you sure you want to delete this question bank? This action cannot be undone.')) {
    await qbStore.deleteQuestionBank(id)
  }
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-xs">
    
    <!-- Filter Bar -->
    <div class="mb-4 sm:mb-6">
      <div class="relative w-full sm:max-w-md">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
          <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        <input 
          v-model="searchQuery"
          type="text" 
          class="block w-full pl-10 pr-3 py-2.5 min-h-[44px] border border-slate-200 rounded-xl text-xs sm:text-[13px] focus:outline-none focus:border-[#5138ed] transition-colors bg-slate-50/50"
          placeholder="Search question bank..."
        >
      </div>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden md:block overflow-x-auto">
      <table class="w-full text-left border-collapse whitespace-nowrap">
        <thead>
          <tr class="border-b border-slate-100 text-[12px] font-bold text-slate-800 tracking-wide">
            <th class="pb-4 pr-4 font-bold text-left w-1/4">Question Bank Name</th>
            <th class="pb-4 px-4 font-bold text-left w-1/3">Description</th>
            <th class="pb-4 px-4 font-bold text-left">Created Date</th>
            <th class="pb-4 pl-4 font-bold text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <!-- Loading state -->
          <tr v-if="qbStore.isLoading">
            <td colspan="4" class="py-8 text-center text-slate-500">
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5 inline text-[#5138ed]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Loading question banks...
            </td>
          </tr>
          
          <!-- Empty State -->
          <tr v-else-if="filteredBanks.length === 0">
             <td colspan="4" class="py-8 text-center text-slate-500 font-medium">No question banks found.</td>
          </tr>

          <!-- Data Rows -->
          <tr v-else v-for="bank in filteredBanks" :key="bank.id" class="border-b border-slate-50 hover:bg-slate-50/50 transition-colors last:border-0 group">
            <td class="py-5 pr-4">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0 text-[#5138ed]">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                </div>
                <span class="text-[13px] font-bold text-slate-800">{{ bank.title }}</span>
              </div>
            </td>
            <td class="py-5 px-4 text-[13px] text-slate-600 max-w-xs truncate">
              {{ bank.description || 'No description available.' }}
            </td>
            <td class="py-5 px-4 text-[13px] text-slate-600 font-medium">
              <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                {{ formatDate((bank as any).created_at || bank.updated_at) }}
              </div>
            </td>
            <td class="py-5 pl-4 text-center">
              <div class="flex items-center justify-center gap-2">
                <template v-if="!lockStore.isLocked">
                  <button @click="handleAddQuestion(bank)" class="w-8 h-8 flex items-center justify-center border border-slate-200 text-[#5138ed] hover:bg-indigo-50 hover:border-indigo-200 rounded-lg transition-colors min-h-[36px] min-w-[36px]" title="Add Question">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                  </button>
                  <button @click="handleEdit(bank)" class="w-8 h-8 flex items-center justify-center border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 hover:border-slate-300 rounded-lg transition-colors min-h-[36px] min-w-[36px]" title="Edit Bank">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                  </button>
                  <button @click="handleView(bank)" class="w-8 h-8 flex items-center justify-center border border-slate-200 text-slate-500 hover:text-slate-800 hover:bg-slate-50 hover:border-slate-300 rounded-lg transition-colors min-h-[36px] min-w-[36px]" title="View Questions">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                  </button>
                  <button @click="handleDelete(bank.id)" class="w-8 h-8 flex items-center justify-center border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 rounded-lg transition-colors min-h-[36px] min-w-[36px]" title="Delete Bank">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                  </button>
                </template>
                <template v-else>
                  <button @click="handleView(bank)" class="px-3 py-1.5 flex items-center gap-1.5 border border-slate-200 text-slate-700 hover:text-[#5138ed] hover:border-indigo-200 rounded-lg transition-colors text-xs font-semibold min-h-[36px]" title="View Questions">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                    <span>View</span>
                  </button>
                  <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">Locked</span>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Mobile Card View -->
    <div class="md:hidden space-y-3">
      <!-- Loading skeleton -->
      <div v-if="qbStore.isLoading" class="space-y-3">
        <div v-for="i in 3" :key="i" class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 animate-pulse space-y-3">
          <div class="h-4 bg-slate-200 rounded w-1/2"></div>
          <div class="h-3 bg-slate-100 rounded w-3/4"></div>
          <div class="h-8 bg-slate-100 rounded"></div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="filteredBanks.length === 0" class="text-center py-8 text-slate-400 text-sm">
        No question banks found.
      </div>

      <!-- Bank Cards -->
      <div 
        v-else 
        v-for="bank in filteredBanks" 
        :key="bank.id"
        class="p-4 rounded-xl border border-slate-100 bg-slate-50/50 hover:bg-slate-50 transition-colors space-y-3"
      >
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0 text-[#5138ed]">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
            </div>
            <h3 class="text-sm font-bold text-slate-800 leading-snug truncate">{{ bank.title }}</h3>
          </div>
          <span v-if="lockStore.isLocked" class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 shrink-0">
            Locked
          </span>
        </div>

        <p class="text-xs text-slate-600 line-clamp-2">
          {{ bank.description || 'No description available.' }}
        </p>

        <div class="flex items-center text-[11px] text-slate-400 gap-1.5 pt-1 border-t border-slate-200/60">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
          <span>Created: {{ formatDate((bank as any).created_at || bank.updated_at) }}</span>
        </div>

        <!-- Mobile Action Buttons -->
        <div class="grid grid-cols-2 gap-2 pt-1">
          <button 
            @click="handleView(bank)"
            class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold text-[#5138ed] bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition-colors min-h-[44px]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            View
          </button>
          
          <template v-if="!lockStore.isLocked">
            <button 
              @click="handleAddQuestion(bank)"
              class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4 text-[#5138ed]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
              Add
            </button>
            <button 
              @click="handleEdit(bank)"
              class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
              Edit
            </button>
            <button 
              @click="handleDelete(bank.id)"
              class="flex items-center justify-center gap-1.5 py-2 px-3 text-xs font-semibold text-rose-600 bg-rose-50 border border-rose-100 rounded-lg hover:bg-rose-100 transition-colors min-h-[44px]"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
              Delete
            </button>
          </template>
        </div>
      </div>
    </div>
    
    <!-- Pagination -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4 sm:mt-6 pt-4 border-t border-slate-100">
      <span class="text-xs sm:text-[13px] text-slate-500 font-medium text-center sm:text-left">
        Showing 1 to {{ Math.min(filteredBanks.length, 5) }} of {{ qbStore.banks.length }} question banks
      </span>
      <div class="flex items-center gap-1">
        <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors min-h-[36px] min-w-[36px]">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </button>
        <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg bg-[#5138ed] text-white font-bold text-xs sm:text-sm shadow-xs min-h-[36px] min-w-[36px]">1</button>
        <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs sm:text-sm transition-colors min-h-[36px] min-w-[36px]">2</button>
        <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs sm:text-sm transition-colors min-h-[36px] min-w-[36px]">3</button>
        <button class="w-9 h-9 sm:w-8 sm:h-8 flex items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 transition-colors min-h-[36px] min-w-[36px]">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </button>
      </div>
    </div>
  </div>
</template>
