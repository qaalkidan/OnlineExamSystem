<script setup lang="ts">
import { ref } from 'vue'
import { useProfileStore } from '../../store/profileStore'

const profileStore = useProfileStore()
const fileInput = ref<HTMLInputElement | null>(null)

const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileChange = async (event: Event) => {
  const target = event.target as HTMLInputElement
  if (!target.files || target.files.length === 0) return

  const file = target.files[0]
  
  // Validation: Max 2MB
  if (file.size > 2 * 1024 * 1024) {
    profileStore.showToast('Profile photo size must be less than 2MB', 'error')
    target.value = ''
    return
  }

  // Validation: MIME type
  if (!file.type.match(/^image\/(jpeg|png|jpg|webp|gif)$/i)) {
    profileStore.showToast('Please upload a valid image (JPEG, PNG, WEBP, GIF)', 'error')
    target.value = ''
    return
  }

  try {
    await profileStore.uploadPhoto(file)
  } catch (e) {
    // Error handled in store
  } finally {
    target.value = ''
  }
}

const handleEditProfile = () => {
  profileStore.activeTab = 'personal'
  const formElement = document.getElementById('profile-form-section')
  if (formElement) {
    formElement.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
}

const formatRole = (role?: string) => {
  if (!role) return 'Instructor'
  if (role === 'dept_head') return 'Department Head'
  return role.charAt(0).toUpperCase() + role.slice(1)
}
</script>

<template>
  <div class="bg-white border border-slate-100 rounded-2xl p-4 sm:p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <h2 class="text-[16px] font-bold text-slate-800">Profile Overview</h2>
      <button 
        @click="handleEditProfile"
        type="button"
        class="min-h-[40px] px-4 py-2 text-[12px] font-bold text-[#5138ed] border border-[#5138ed] rounded-xl hover:bg-indigo-50 transition-colors flex items-center gap-2 cursor-pointer shadow-xs"
      >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
        Edit Profile
      </button>
    </div>

    <!-- Hidden file input for photo upload -->
    <input 
      ref="fileInput" 
      type="file" 
      accept="image/png, image/jpeg, image/jpg, image/webp" 
      class="hidden" 
      @change="handleFileChange" 
    />

    <div class="flex flex-col md:flex-row gap-6 md:gap-8">
      
      <!-- Avatar Section -->
      <div class="flex-shrink-0 relative self-start">
        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full overflow-hidden border-4 border-white shadow-md bg-slate-100 flex items-center justify-center">
          <img 
            v-if="profileStore.profile?.profile_picture_url" 
            :src="profileStore.profile.profile_picture_url" 
            :alt="profileStore.profile.name" 
            class="w-full h-full object-cover" 
            @error="(e: any) => e.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(profileStore.profile?.name || 'Instructor') + '&background=5138ed&color=fff'"
          />
          <div 
            v-else 
            class="w-full h-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl sm:text-3xl font-bold uppercase"
          >
            {{ profileStore.profile?.name ? profileStore.profile.name.charAt(0) : 'I' }}
          </div>
        </div>

        <!-- Camera Upload Button -->
        <button 
          @click="triggerFileInput"
          type="button"
          :disabled="profileStore.isUploadingPhoto"
          class="absolute bottom-0 right-0 w-8 h-8 bg-white border border-slate-200 rounded-full flex items-center justify-center text-[#5138ed] hover:bg-slate-50 transition-colors shadow-sm cursor-pointer disabled:opacity-50"
          title="Upload new profile picture"
        >
          <svg v-if="!profileStore.isUploadingPhoto" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
          <svg v-else class="w-4 h-4 animate-spin text-[#5138ed]" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
        </button>
      </div>

      <!-- Basic Info -->
      <div class="flex-1 flex flex-col justify-center">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
          <h1 class="text-xl sm:text-2xl font-bold text-slate-800 capitalize">
            {{ profileStore.profile?.name || 'Instructor' }}
          </h1>
          <span class="px-2.5 py-1 bg-indigo-50 text-[#5138ed] text-[10px] font-bold rounded-lg uppercase tracking-wide">
            {{ formatRole(profileStore.profile?.role) }}
          </span>
        </div>
        
        <div class="space-y-3 mt-4">
          <div class="flex items-center gap-3 text-[13px] text-slate-600">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
            <span class="truncate">{{ profileStore.profile?.email || 'N/A' }}</span>
          </div>
          <div class="flex items-center gap-3 text-[13px] text-slate-600">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
            <span>{{ profileStore.profile?.phone || 'Not provided' }}</span>
          </div>
          <div class="flex items-center gap-3 text-[13px] text-slate-600">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            <span class="truncate">{{ profileStore.profile?.office || profileStore.profile?.location || 'Wollo University Main Campus, Dessie' }}</span>
          </div>
        </div>
      </div>

      <!-- Detail Grid -->
      <div class="flex-1">
        <div class="grid grid-cols-[100px_1fr] gap-y-4 text-[12px]">
          
          <div class="font-medium text-slate-500">Username</div>
          <div class="font-medium text-slate-800 text-right md:text-left truncate">
            {{ profileStore.profile?.username || 'N/A' }}
          </div>
          
          <div class="font-medium text-slate-500">Employee ID</div>
          <div class="font-medium text-slate-800 text-right md:text-left">
            {{ profileStore.profile?.id_no || 'WU-IN-1024' }}
          </div>
          
          <div class="font-medium text-slate-500">Department</div>
          <div class="font-medium text-slate-800 text-right md:text-left capitalize truncate">
            {{ profileStore.profile?.department_name || profileStore.profile?.department?.name || 'Computer Science' }}
          </div>
          
          <div class="font-medium text-slate-500">Role</div>
          <div class="font-medium text-slate-800 text-right md:text-left">
            {{ formatRole(profileStore.profile?.role) }}
          </div>
          
          <div class="font-medium text-slate-500">Member Since</div>
          <div class="font-medium text-slate-800 text-right md:text-left">
            {{ profileStore.profile?.member_since || 'Jan 15, 2023' }}
          </div>
          
          <div class="font-medium text-slate-500">Account Status</div>
          <div class="text-right md:text-left">
            <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 font-bold rounded text-[10px] capitalize">
              {{ profileStore.profile?.status || 'Active' }}
            </span>
          </div>
          
        </div>
      </div>

    </div>
  </div>
</template>
