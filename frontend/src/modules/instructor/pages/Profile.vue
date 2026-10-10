<script setup lang="ts">
import { onMounted } from 'vue'
import { useProfileStore } from '../store/profileStore'
import ProfileOverview from '../components/profile/ProfileOverview.vue'
import ProfileTabs from '../components/profile/ProfileTabs.vue'
import ProfileForm from '../components/profile/ProfileForm.vue'
import ProfileStats from '../components/profile/ProfileStats.vue'
import ProfileActivity from '../components/profile/ProfileActivity.vue'
import ProfilePreferences from '../components/profile/ProfilePreferences.vue'

const profileStore = useProfileStore()

onMounted(() => {
  profileStore.fetchProfile()
})
</script>

<template>
  <div class="max-w-[1500px] mx-auto pb-10 relative">
    
    <!-- Floating Toast Notification -->
    <Teleport to="body">
      <Transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="profileStore.toast.show"
          class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-xl border text-xs font-semibold max-w-sm backdrop-blur-md"
          :class="[
            profileStore.toast.type === 'error'
              ? 'bg-rose-50/95 border-rose-200 text-rose-800'
              : profileStore.toast.type === 'info'
              ? 'bg-blue-50/95 border-blue-200 text-blue-800'
              : 'bg-emerald-50/95 border-emerald-200 text-emerald-800'
          ]"
        >
          <svg
            v-if="profileStore.toast.type === 'error'"
            class="w-4 h-4 text-rose-600 shrink-0"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <svg
            v-else
            class="w-4 h-4 text-emerald-600 shrink-0"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <span class="flex-1">{{ profileStore.toast.message }}</span>
          <button
            @click="profileStore.toast.show = false"
            type="button"
            class="text-slate-400 hover:text-slate-600 p-0.5 rounded cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
      </Transition>
    </Teleport>

    <!-- Main Content Container with standard padding -->
    <div class="space-y-6">
      
      <!-- Main Content Grid matching screenshot -->
      <div class="flex flex-col lg:flex-row gap-6">
        
        <!-- Left Column: Profile Overview, Tabs + Form, Statistics -->
        <div class="flex-1 min-w-0 space-y-6">
          <ProfileOverview />
          
          <div class="bg-white border border-[#E6EBF3] rounded-2xl overflow-hidden shadow-2xs hover:border-slate-300 transition-all">
            <ProfileTabs />
            <div class="p-5 sm:p-7">
              <ProfileForm />
            </div>
          </div>
          
          <ProfileStats />
        </div>

        <!-- Right Column: Account Activity, Preferences -->
        <div class="w-full lg:w-[320px] xl:w-[350px] space-y-6 shrink-0">
          <ProfileActivity />
          <ProfilePreferences />
        </div>

      </div>

    </div>
    
  </div>
</template>
