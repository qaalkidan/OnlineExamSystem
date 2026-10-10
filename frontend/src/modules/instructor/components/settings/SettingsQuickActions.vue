<script setup lang="ts">
import { ref } from 'vue'

const emit = defineEmits<{
  (e: 'selectSection', section: string): void
}>()

const showToast = ref(false)
const toastMessage = ref('')
const showHealthModal = ref(false)

const triggerActionToast = (msg: string) => {
  toastMessage.value = msg
  showToast.value = true
  setTimeout(() => {
    showToast.value = false
  }, 3500)
}

const handleClearCache = () => {
  triggerActionToast('System cache purged successfully. Application memory optimized.')
}

const handleBackupNow = () => {
  triggerActionToast('Database backup initiated. Snapshot saved to encrypted cloud storage.')
}
</script>

<template>
  <div class="space-y-6 relative">
    
    <!-- Floating Feedback Toast -->
    <div
      v-if="showToast"
      class="fixed bottom-5 right-5 z-50 bg-[#17243A] text-white text-xs px-4 py-3 rounded-xl shadow-xl flex items-center gap-2.5 animate-in fade-in"
    >
      <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
      <span class="font-medium">{{ toastMessage }}</span>
    </div>

    <!-- Quick Actions Card matching screenshot -->
    <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-6 shadow-2xs hover:border-slate-300 transition-all">
      <h2 class="text-[14px] font-bold text-[#17243A] mb-5">Quick Actions</h2>
      
      <div class="space-y-2.5">
        
        <!-- Clear System Cache -->
        <button
          @click="handleClearCache"
          type="button"
          class="w-full flex items-start gap-3 p-3 rounded-xl hover:bg-[#EEF0FF]/60 transition-all border border-transparent hover:border-indigo-100 group text-left cursor-pointer"
        >
          <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-[12px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors">Clear System Cache</h3>
            <p class="text-[10px] text-[#71819B] mt-0.5">Improve system performance</p>
          </div>
        </button>

        <!-- Backup Now -->
        <button
          @click="handleBackupNow"
          type="button"
          class="w-full flex items-start gap-3 p-3 rounded-xl hover:bg-[#EEF0FF]/60 transition-all border border-transparent hover:border-indigo-100 group text-left cursor-pointer"
        >
          <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
            </svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-[12px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors">Backup Now</h3>
            <p class="text-[10px] text-[#71819B] mt-0.5">Create a system backup</p>
          </div>
        </button>

        <!-- View Audit Logs -->
        <button
          @click="emit('selectSection', 'audit')"
          type="button"
          class="w-full flex items-start gap-3 p-3 rounded-xl hover:bg-[#EEF0FF]/60 transition-all border border-transparent hover:border-indigo-100 group text-left cursor-pointer"
        >
          <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-[12px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors">View Audit Logs</h3>
            <p class="text-[10px] text-[#71819B] mt-0.5">Check system activity logs</p>
          </div>
        </button>

        <!-- System Health -->
        <button
          @click="showHealthModal = true"
          type="button"
          class="w-full flex items-start gap-3 p-3 rounded-xl hover:bg-[#EEF0FF]/60 transition-all border border-transparent hover:border-indigo-100 group text-left cursor-pointer"
        >
          <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs group-hover:scale-105 transition-transform">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-[12px] font-bold text-[#17243A] group-hover:text-[#4F35F3] transition-colors">System Health</h3>
            <p class="text-[10px] text-[#71819B] mt-0.5">Check system health status</p>
          </div>
        </button>

      </div>
    </div>


    <!-- System Health Modal -->
    <div
      v-if="showHealthModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in"
    >
      <div class="bg-white border border-[#E6EBF3] rounded-2xl w-full max-w-md shadow-2xl p-6 relative">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
          <h3 class="text-base font-bold text-[#17243A]">System Diagnostics & Health</h3>
          <button @click="showHealthModal = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="space-y-3.5 my-5 text-xs">
          <div class="flex justify-between p-2.5 bg-slate-50 rounded-xl">
            <span class="text-slate-500">Database Engine:</span>
            <span class="font-bold text-slate-800">PostgreSQL / MySQL (Healthy)</span>
          </div>
          <div class="flex justify-between p-2.5 bg-slate-50 rounded-xl">
            <span class="text-slate-500">API Response Latency:</span>
            <span class="font-bold text-emerald-600">42 ms (Optimal)</span>
          </div>
          <div class="flex justify-between p-2.5 bg-slate-50 rounded-xl">
            <span class="text-slate-500">Proctoring Queue:</span>
            <span class="font-bold text-slate-800">Idle (0 backlog)</span>
          </div>
          <div class="flex justify-between p-2.5 bg-slate-50 rounded-xl">
            <span class="text-slate-500">TLS Encryption:</span>
            <span class="font-bold text-emerald-600">Enabled (TLS 1.3 Valid)</span>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 flex justify-end">
          <button
            @click="showHealthModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
