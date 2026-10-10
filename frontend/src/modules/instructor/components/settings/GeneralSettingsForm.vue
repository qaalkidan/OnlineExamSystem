<script setup lang="ts">
import { ref } from 'vue'
import { useSettingsStore } from '../../../../store/settingsStore'

const props = withDefaults(
  defineProps<{
    activeSection?: string
  }>(),
  {
    activeSection: 'general'
  }
)

const settingsStore = useSettingsStore()

const isSaving = ref(false)
const saveSuccess = ref(false)

// ─── General Settings Form State ───
const siteInfo = ref({
  institutionName: 'Wollo University',
  systemName: 'Online Examination System',
  institutionEmail: 'info@wu.edu.et',
  systemLanguage: 'English',
  institutionPhone: '+251 11 123 4567',
  timezone: '(UTC+03:00) Addis Ababa',
  dateFormat: 'May 25, 2025 (MMM DD, YYYY)',
  timeFormat: '10:30 AM (12-hour)',
  weekStartsOn: 'Monday',
  academicStartMonth: 'September'
})

// ─── Exam Settings Form State ───
const examSettings = ref({
  defaultDuration: 60,
  passingScore: 60,
  autoSubmitOnTimer: true,
  shuffleQuestions: true,
  shuffleOptions: true,
  allowReview: false,
  proctoringStrictness: 'High',
  lateSubmissionGrace: 5
})

// ─── Security Settings Form State ───
const securitySettings = ref({
  sessionTimeout: 30,
  require2FA: false,
  restrictIPs: true,
  maxLoginAttempts: 5,
  passwordExpiryDays: 90
})

// ─── Notification Settings State ───
const notificationSettings = ref({
  emailExamPublished: true,
  emailResultReady: true,
  emailSystemAlerts: true,
  pushExamApproaching: true,
  pushSubmissions: true
})

// ─── Email Settings State ───
const emailSettings = ref({
  smtpHost: 'smtp.wu.edu.et',
  smtpPort: '587',
  smtpUser: 'notifications@wu.edu.et',
  encryption: 'TLS',
  senderName: 'Wollo University Online Exams'
})

const handleSave = async () => {
  isSaving.value = true
  saveSuccess.value = false
  
  try {
    await settingsStore.updateSettings({
      universityName: siteInfo.value.institutionName,
      systemTitle: siteInfo.value.systemName,
      supportEmail: siteInfo.value.institutionEmail,
      timezone: siteInfo.value.timezone,
      language: siteInfo.value.systemLanguage
    })
    saveSuccess.value = true
    setTimeout(() => {
      saveSuccess.value = false
    }, 3000)
  } catch (e) {
    // handled
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-7 lg:p-8 shadow-2xs hover:border-slate-300 transition-all relative">
    
    <!-- Top Save Notification Toast -->
    <div
      v-if="saveSuccess"
      class="absolute top-4 right-4 z-20 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold px-3.5 py-2 rounded-xl flex items-center gap-2 shadow-sm animate-in fade-in"
    >
      <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
      </svg>
      <span>Settings saved successfully!</span>
    </div>

    <!-- ======================================================== -->
    <!-- SECTION 1: GENERAL SETTINGS (Default / Screenshot View)  -->
    <!-- ======================================================== -->
    <div v-if="activeSection === 'general'">
      <!-- Header matching screenshot -->
      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-8 pb-6 border-b border-slate-100">
        <div>
          <h2 class="text-xl sm:text-[22px] font-black text-[#17243A] tracking-tight">General Settings</h2>
          <p class="text-xs text-[#71819B] mt-1 font-medium">Configure general settings for the examination system.</p>
        </div>
        <button
          @click="handleSave"
          :disabled="isSaving"
          class="min-h-[42px] px-5 py-2.5 bg-[#4F35F3] hover:bg-indigo-700 active:bg-indigo-800 text-white text-[12px] font-bold rounded-xl transition-all shadow-xs flex items-center justify-center gap-2 shrink-0 cursor-pointer disabled:opacity-50"
        >
          <svg v-if="isSaving" class="w-4 h-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
          </svg>
          <span>{{ isSaving ? 'Saving Changes...' : 'Save All Changes' }}</span>
        </button>
      </div>

      <div class="space-y-10">
        
        <!-- Section: Site Information matching screenshot -->
        <div>
          <div class="flex items-center gap-3 mb-6">
            <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div>
              <h3 class="text-[14px] font-bold text-[#17243A]">Site Information</h3>
              <p class="text-[11px] text-[#71819B]">Manage basic information about your institution and system.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Institution Name</label>
              <input
                v-model="siteInfo.institutionName"
                type="text"
                class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 shadow-2xs transition-colors"
              />
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">System Name</label>
              <input
                v-model="siteInfo.systemName"
                type="text"
                class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 shadow-2xs transition-colors"
              />
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Institution Email</label>
              <input
                v-model="siteInfo.institutionEmail"
                type="email"
                class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 shadow-2xs transition-colors"
              />
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">System Language</label>
              <div class="relative">
                <select
                  v-model="siteInfo.systemLanguage"
                  class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 bg-white cursor-pointer shadow-2xs transition-colors"
                >
                  <option>English</option>
                  <option>Amharic (አማርኛ)</option>
                  <option>Afaan Oromoo</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Institution Phone</label>
              <input
                v-model="siteInfo.institutionPhone"
                type="text"
                class="w-full min-h-[44px] px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 shadow-2xs transition-colors"
              />
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Timezone</label>
              <div class="relative">
                <select
                  v-model="siteInfo.timezone"
                  class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 bg-white cursor-pointer shadow-2xs transition-colors"
                >
                  <option>(UTC+03:00) Addis Ababa</option>
                  <option>(UTC+00:00) UTC</option>
                  <option>(UTC+01:00) Central European Time</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 2: Date & Time Settings matching screenshot -->
        <div class="pt-8 border-t border-slate-100">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-9 h-9 rounded-xl bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 shadow-2xs">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-[14px] font-bold text-[#17243A]">Date & Time Settings</h3>
              <p class="text-[11px] text-[#71819B]">Configure how dates and times are displayed across the system.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Date Format</label>
              <div class="relative">
                <select
                  v-model="siteInfo.dateFormat"
                  class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 bg-white cursor-pointer shadow-2xs"
                >
                  <option>May 25, 2025 (MMM DD, YYYY)</option>
                  <option>2025-05-25 (YYYY-MM-DD)</option>
                  <option>25/05/2025 (DD/MM/YYYY)</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Time Format</label>
              <div class="relative">
                <select
                  v-model="siteInfo.timeFormat"
                  class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 bg-white cursor-pointer shadow-2xs"
                >
                  <option>10:30 AM (12-hour)</option>
                  <option>10:30 (24-hour)</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Week Starts On</label>
              <div class="relative">
                <select
                  v-model="siteInfo.weekStartsOn"
                  class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 bg-white cursor-pointer shadow-2xs"
                >
                  <option>Sunday</option>
                  <option>Monday</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
            <div class="space-y-1.5">
              <label class="block text-[11px] font-bold text-slate-700">Academic Year Start Month</label>
              <div class="relative">
                <select
                  v-model="siteInfo.academicStartMonth"
                  class="w-full min-h-[44px] appearance-none px-4 py-2.5 text-[12px] border border-[#E6EBF3] rounded-xl focus:outline-none focus:border-[#4F35F3] focus:ring-1 focus:ring-[#4F35F3] text-slate-800 bg-white cursor-pointer shadow-2xs"
                >
                  <option>September</option>
                  <option>October</option>
                  <option>January</option>
                </select>
                <svg class="w-4 h-4 text-slate-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- ======================================================== -->
    <!-- SECTION 2: EXAM SETTINGS                                 -->
    <!-- ======================================================== -->
    <div v-else-if="activeSection === 'exams'" class="space-y-6">
      <div class="flex items-center justify-between pb-5 border-b border-slate-100">
        <div>
          <h2 class="text-xl sm:text-[22px] font-black text-[#17243A]">Examination Policies</h2>
          <p class="text-xs text-[#71819B] mt-1">Configure default thresholds and integrity rules for course assessments.</p>
        </div>
        <button @click="handleSave" class="px-5 py-2.5 bg-[#4F35F3] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs">
          Save Exam Settings
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-1.5">
          <label class="block text-[11px] font-bold text-slate-700">Default Duration (Minutes)</label>
          <input v-model="examSettings.defaultDuration" type="number" class="w-full px-4 py-2.5 text-xs border border-[#E6EBF3] rounded-xl" />
        </div>
        <div class="space-y-1.5">
          <label class="block text-[11px] font-bold text-slate-700">Passing Score Threshold (%)</label>
          <input v-model="examSettings.passingScore" type="number" class="w-full px-4 py-2.5 text-xs border border-[#E6EBF3] rounded-xl" />
        </div>
      </div>

      <div class="space-y-4 pt-4 divide-y divide-slate-100 text-xs">
        <div class="pt-3 flex items-center justify-between">
          <div>
            <div class="font-bold text-[#17243A]">Automatic Submission on Timer Expiry</div>
            <div class="text-[#71819B] text-[11px]">Automatically submits student answers when the exam countdown reaches zero.</div>
          </div>
          <button @click="examSettings.autoSubmitOnTimer = !examSettings.autoSubmitOnTimer" class="w-10 h-5 rounded-full relative transition-colors" :class="examSettings.autoSubmitOnTimer ? 'bg-[#4F35F3]' : 'bg-slate-200'">
            <span class="w-4 h-4 bg-white rounded-full absolute top-0.5 transition-transform" :class="examSettings.autoSubmitOnTimer ? 'right-0.5' : 'left-0.5'"></span>
          </button>
        </div>
        <div class="pt-3 flex items-center justify-between">
          <div>
            <div class="font-bold text-[#17243A]">Shuffle Questions by Default</div>
            <div class="text-[#71819B] text-[11px]">Presents questions in random sequence to each test taker.</div>
          </div>
          <button @click="examSettings.shuffleQuestions = !examSettings.shuffleQuestions" class="w-10 h-5 rounded-full relative transition-colors" :class="examSettings.shuffleQuestions ? 'bg-[#4F35F3]' : 'bg-slate-200'">
            <span class="w-4 h-4 bg-white rounded-full absolute top-0.5 transition-transform" :class="examSettings.shuffleQuestions ? 'right-0.5' : 'left-0.5'"></span>
          </button>
        </div>
        <div class="pt-3 flex items-center justify-between">
          <div>
            <div class="font-bold text-[#17243A]">Shuffle Multiple Choice Options</div>
            <div class="text-[#71819B] text-[11px]">Randomizes choices A, B, C, D across student exams.</div>
          </div>
          <button @click="examSettings.shuffleOptions = !examSettings.shuffleOptions" class="w-10 h-5 rounded-full relative transition-colors" :class="examSettings.shuffleOptions ? 'bg-[#4F35F3]' : 'bg-slate-200'">
            <span class="w-4 h-4 bg-white rounded-full absolute top-0.5 transition-transform" :class="examSettings.shuffleOptions ? 'right-0.5' : 'left-0.5'"></span>
          </button>
        </div>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- SECTION 3: SECURITY SETTINGS                             -->
    <!-- ======================================================== -->
    <div v-else-if="activeSection === 'security'" class="space-y-6">
      <div class="flex items-center justify-between pb-5 border-b border-slate-100">
        <div>
          <h2 class="text-xl sm:text-[22px] font-black text-[#17243A]">Security & Compliance</h2>
          <p class="text-xs text-[#71819B] mt-1">Configure session timeouts, institutional firewall rules, and authentication policies.</p>
        </div>
        <button @click="handleSave" class="px-5 py-2.5 bg-[#4F35F3] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs">
          Save Security Settings
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="space-y-1.5">
          <label class="block text-[11px] font-bold text-slate-700">Session Inactivity Timeout (Minutes)</label>
          <input v-model="securitySettings.sessionTimeout" type="number" class="w-full px-4 py-2.5 text-xs border border-[#E6EBF3] rounded-xl" />
        </div>
        <div class="space-y-1.5">
          <label class="block text-[11px] font-bold text-slate-700">Max Failed Login Attempts</label>
          <input v-model="securitySettings.maxLoginAttempts" type="number" class="w-full px-4 py-2.5 text-xs border border-[#E6EBF3] rounded-xl" />
        </div>
      </div>

      <div class="space-y-4 pt-4 divide-y divide-slate-100 text-xs">
        <div class="pt-3 flex items-center justify-between">
          <div>
            <div class="font-bold text-[#17243A]">Campus IP Range Restriction</div>
            <div class="text-[#71819B] text-[11px]">Enforce proctoring logins only from approved Wollo University campus network subnets.</div>
          </div>
          <button @click="securitySettings.restrictIPs = !securitySettings.restrictIPs" class="w-10 h-5 rounded-full relative transition-colors" :class="securitySettings.restrictIPs ? 'bg-[#4F35F3]' : 'bg-slate-200'">
            <span class="w-4 h-4 bg-white rounded-full absolute top-0.5 transition-transform" :class="securitySettings.restrictIPs ? 'right-0.5' : 'left-0.5'"></span>
          </button>
        </div>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- SECTION 4: NOTIFICATIONS / EMAIL / OTHER PANELS          -->
    <!-- ======================================================== -->
    <div v-else class="space-y-6">
      <div class="flex items-center justify-between pb-5 border-b border-slate-100">
        <div>
          <h2 class="text-xl sm:text-[22px] font-black text-[#17243A] capitalize">{{ activeSection }} Configuration</h2>
          <p class="text-xs text-[#71819B] mt-1">Manage institutional configuration and automated dispatches.</p>
        </div>
        <button @click="handleSave" class="px-5 py-2.5 bg-[#4F35F3] hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-xs">
          Save Changes
        </button>
      </div>

      <div class="p-6 bg-slate-50 rounded-xl border border-slate-100 space-y-3">
        <div class="flex items-center gap-2 text-emerald-700 font-bold text-xs">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Configured & Synchronized with University Cloud Gateway</span>
        </div>
        <p class="text-xs text-slate-600 leading-relaxed">
          The {{ activeSection }} service is active for Wollo University Academic Term 2028 Second Semester. Any modifications here are logged to the university compliance audit log.
        </p>
      </div>
    </div>

  </div>
</template>
