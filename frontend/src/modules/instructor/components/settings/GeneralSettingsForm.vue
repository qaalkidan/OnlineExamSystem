<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 lg:p-8 shadow-sm">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-8 pb-6 border-b border-slate-100">
      <div>
        <h2 class="text-lg sm:text-[20px] font-bold text-slate-800">General Settings</h2>
        <p class="text-[12px] text-slate-500 mt-1">Configure general settings for the examination system.</p>
      </div>
      <button class="min-h-[44px] w-full sm:w-auto px-5 py-2.5 bg-[#5138ed] text-white text-[12px] font-bold rounded-xl hover:bg-indigo-600 transition-colors shadow-sm flex items-center justify-center gap-2 flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        Save All Changes
      </button>
    </div>

    <div class="space-y-10">
      
      <!-- Section 1: Site Information -->
      <div>
        <div class="flex items-center gap-3 mb-6">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-800">Site Information</h3>
            <p class="text-[11px] text-slate-500">Manage basic information about your institution and system.</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Institution Name</label>
            <input type="text" value="Wollo University" class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800" />
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">System Name</label>
            <input type="text" value="Online Examination System" class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800" />
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Institution Email</label>
            <input type="email" value="info@wu.edu.et" class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800" />
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">System Language</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800 bg-white cursor-pointer">
                <option>English</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Institution Phone</label>
            <input type="text" value="+251 11 123 4567" class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800" />
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Timezone</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800 bg-white cursor-pointer">
                <option>(UTC+03:00) Addis Ababa</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 2: Date & Time Settings -->
      <div class="pt-8 border-t border-slate-100">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-800">Date & Time Settings</h3>
            <p class="text-[11px] text-slate-500">Configure how dates and times are displayed across the system.</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Date Format</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800 bg-white cursor-pointer">
                <option>May 25, 2025 (MMM DD, YYYY)</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Time Format</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800 bg-white cursor-pointer">
                <option>10:30 AM (12-hour)</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Week Starts On</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800 bg-white cursor-pointer">
                <option>Sunday</option>
                <option>Monday</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Academic Year Start Month</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] focus:ring-1 focus:ring-[#5138ed] text-slate-800 bg-white cursor-pointer">
                <option>September</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Theme Settings -->
      <div class="pt-8 border-t border-slate-100">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path></svg>
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-800">Theme Settings</h3>
            <p class="text-[11px] text-slate-500">Customize the appearance of the system.</p>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
          <div class="space-y-3">
            <label class="block text-[11px] font-bold text-slate-700">Theme Mode</label>
            <div class="flex flex-wrap items-center gap-4 sm:gap-6">
              <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
                <div class="w-4 h-4 rounded-full border-[5px] border-[#5138ed] bg-white"></div>
                <span class="text-[12px] font-bold text-slate-800">Light</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
                <div class="w-4 h-4 rounded-full border border-slate-300 bg-white"></div>
                <span class="text-[12px] font-bold text-slate-800">Dark</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer min-h-[44px]">
                <div class="w-4 h-4 rounded-full border border-slate-300 bg-white"></div>
                <span class="text-[12px] font-bold text-slate-800">System Default</span>
              </label>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Primary Color</label>
            <div class="relative">
              <div class="w-full min-h-[44px] flex items-center justify-between px-4 py-2.5 border border-slate-200 rounded-xl bg-white cursor-pointer">
                <div class="flex items-center gap-3">
                  <div class="w-4 h-4 rounded bg-[#5138ed]"></div>
                  <span class="text-[12px] font-bold text-slate-800">#6366F1</span>
                </div>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 4: Items Per Page -->
      <div class="pt-8 border-t border-slate-100">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-8 h-8 rounded-lg bg-indigo-50 text-[#5138ed] flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
          </div>
          <div>
            <h3 class="text-[14px] font-bold text-slate-800">Items Per Page</h3>
            <p class="text-[11px] text-slate-500">Set default number of items to display in tables.</p>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Students</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white cursor-pointer">
                <option>10</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Exams</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white cursor-pointer">
                <option>10</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Results</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white cursor-pointer">
                <option>10</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
          <div class="space-y-2">
            <label class="block text-[11px] font-bold text-slate-700">Reports</label>
            <div class="relative">
              <select class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-slate-200 rounded-xl focus:outline-none focus:border-[#5138ed] bg-white cursor-pointer">
                <option>10</option>
              </select>
              <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
