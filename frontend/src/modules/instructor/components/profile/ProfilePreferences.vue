<script setup lang="ts">
import { computed } from 'vue'
import { useProfileStore } from '../../store/profileStore'

const profileStore = useProfileStore()

const currentPrefs = computed(() => {
  return profileStore.profile?.preferences || {
    language: 'English',
    timezone: '(UTC+03:00) Addis Ababa',
    date_format: 'MMM DD, YYYY',
    dark_mode: false
  }
})

const handleLanguageChange = (e: Event) => {
  const value = (e.target as HTMLSelectElement).value
  profileStore.updatePreferences({ language: value })
}

const handleTimezoneChange = (e: Event) => {
  const value = (e.target as HTMLSelectElement).value
  profileStore.updatePreferences({ timezone: value })
}

const handleDateFormatChange = (e: Event) => {
  const value = (e.target as HTMLSelectElement).value
  profileStore.updatePreferences({ date_format: value })
}

const toggleDarkMode = () => {
  const nextVal = !currentPrefs.value.dark_mode
  profileStore.updatePreferences({ dark_mode: nextVal })
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-sm">
    <h2 class="text-[14px] font-bold text-slate-800 mb-6">Preferences</h2>
    
    <div class="space-y-6">
      
      <!-- Language -->
      <div class="flex items-start gap-4">
        <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
        </div>
        <div class="flex-1 space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Language</label>
          <div class="relative">
            <select 
              :value="currentPrefs.language"
              @change="handleLanguageChange"
              class="w-full appearance-none px-3 py-2 text-[12px] border border-slate-200 rounded-lg focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] bg-white text-slate-800 cursor-pointer"
            >
              <option value="English">English</option>
              <option value="Amharic">Amharic (አማርኛ)</option>
              <option value="Oromo">Afaan Oromoo</option>
            </select>
            <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
      </div>

      <!-- Time Zone -->
      <div class="flex items-start gap-4">
        <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
        <div class="flex-1 space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Time Zone</label>
          <div class="relative">
            <select 
              :value="currentPrefs.timezone"
              @change="handleTimezoneChange"
              class="w-full appearance-none px-3 py-2 text-[12px] border border-slate-200 rounded-lg focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] bg-white text-slate-800 cursor-pointer"
            >
              <option value="(UTC+03:00) Addis Ababa">(UTC+03:00) Addis Ababa</option>
              <option value="(UTC+00:00) UTC">(UTC+00:00) UTC</option>
              <option value="(UTC+01:00) Central European Time">(UTC+01:00) Central European Time</option>
              <option value="(UTC-05:00) Eastern Time">(UTC-05:00) Eastern Time</option>
            </select>
            <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
      </div>

      <!-- Date Format -->
      <div class="flex items-start gap-4">
        <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        </div>
        <div class="flex-1 space-y-2">
          <label class="block text-[11px] font-bold text-slate-700">Date Format</label>
          <div class="relative">
            <select 
              :value="currentPrefs.date_format"
              @change="handleDateFormatChange"
              class="w-full appearance-none px-3 py-2 text-[12px] border border-slate-200 rounded-lg focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] bg-white text-slate-800 cursor-pointer"
            >
              <option value="MMM DD, YYYY">May 25, 2025 (MMM DD, YYYY)</option>
              <option value="YYYY-MM-DD">2025-05-25 (YYYY-MM-DD)</option>
              <option value="DD/MM/YYYY">25/05/2025 (DD/MM/YYYY)</option>
            </select>
            <svg class="w-3.5 h-3.5 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
          </div>
        </div>
      </div>

      <!-- Enable Dark Mode -->
      <div class="flex items-start gap-4">
        <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 shrink-0 mt-0.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
        </div>
        <div class="flex-1 flex items-center justify-between mt-1.5">
          <div>
            <h3 class="text-[12px] font-bold text-slate-800">Enable Dark Mode</h3>
            <p class="text-[10px] text-slate-500 mt-0.5">Switch between light and dark theme</p>
          </div>
          <!-- Toggle Switch -->
          <button 
            @click="toggleDarkMode"
            type="button"
            class="relative w-9 h-5 rounded-full transition-colors focus:outline-none cursor-pointer"
            :class="currentPrefs.dark_mode ? 'bg-[#5138ed]' : 'bg-slate-200'"
          >
            <span 
              class="inline-block w-3.5 h-3.5 transform bg-white rounded-full transition-transform mt-0.5"
              :class="currentPrefs.dark_mode ? 'translate-x-4' : 'translate-x-1'"
            ></span>
          </button>
        </div>
      </div>

    </div>
  </div>
</template>
