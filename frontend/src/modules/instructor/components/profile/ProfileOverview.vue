<script setup lang="ts">
import { ref, computed } from 'vue'
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

const displayName = computed(() => {
  return profileStore.profile?.name || 'Fitsum Gashaw'
})

const displayRole = computed(() => {
  const r = profileStore.profile?.role
  if (r === 'dept_head') return 'DEPARTMENT HEAD'
  if (r === 'instructor') return 'INSTRUCTOR'
  return r ? r.toUpperCase() : 'DEPARTMENT HEAD'
})

const displayEmail = computed(() => {
  return profileStore.profile?.email || 'fitshumgashaw@gmail.com'
})

const displayPhone = computed(() => {
  return profileStore.profile?.phone || '+251980426395'
})

const displayOffice = computed(() => {
  return profileStore.profile?.office || profileStore.profile?.location || 'Wollo University Main Campus, Dessie'
})

const displayUsername = computed(() => {
  return profileStore.profile?.username || 'app'
})

const displayEmployeeId = computed(() => {
  return profileStore.profile?.id_no || '2354/8'
})

const displayDepartment = computed(() => {
  const dept = profileStore.profile?.department_name || profileStore.profile?.department?.name || 'Computer Science'
  return dept
})

const displayMemberSince = computed(() => {
  return profileStore.profile?.member_since || 'Oct 04, 2026'
})

const displayStatus = computed(() => {
  return profileStore.profile?.status || 'Active'
})
</script>

<template>
  <div class="bg-white border border-[#E6EBF3] rounded-2xl p-5 sm:p-7 shadow-2xs hover:border-slate-300 transition-all">
    
    <!-- Header with Title & Edit Profile Button -->
    <div class="flex items-center justify-between pb-5 mb-5 border-b border-slate-100">
      <h2 class="text-[15px] sm:text-[16px] font-bold text-[#17243A]">
        Profile Overview
      </h2>
      <button 
        @click="handleEditProfile"
        type="button"
        class="min-h-[40px] px-4 py-2 text-[12px] font-bold text-[#4F35F3] border border-[#4F35F3] bg-white hover:bg-[#EEF0FF] active:bg-indigo-100 rounded-xl transition-all flex items-center gap-2 cursor-pointer shadow-2xs group"
        title="Edit Personal Information"
      >
        <svg class="w-3.5 h-3.5 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
        </svg>
        <span>Edit Profile</span>
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

    <!-- Main Overview Content Grid matching screenshot -->
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 lg:gap-8">
      
      <!-- Left: Avatar + Contact Block -->
      <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 sm:gap-6 flex-1 min-w-0">
        
        <!-- Avatar with Camera Overlay Button -->
        <div class="relative shrink-0">
          <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-full overflow-hidden border-4 border-white shadow-md bg-slate-100 flex items-center justify-center ring-1 ring-slate-100">
            <img 
              v-if="profileStore.profile?.profile_picture_url" 
              :src="profileStore.profile.profile_picture_url" 
              :alt="displayName" 
              class="w-full h-full object-cover" 
              @error="(e: any) => e.target.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(displayName) + '&background=4F35F3&color=fff'"
            />
            <div 
              v-else 
              class="w-full h-full bg-gradient-to-br from-indigo-500 via-[#4F35F3] to-purple-700 flex items-center justify-center text-white text-3xl font-black uppercase tracking-wider"
            >
              {{ displayName.charAt(0) }}
            </div>
          </div>

          <!-- Camera Upload Button -->
          <button 
            @click="triggerFileInput"
            type="button"
            :disabled="profileStore.isUploadingPhoto"
            class="absolute bottom-1 right-1 w-8 h-8 bg-white border border-[#E6EBF3] rounded-full flex items-center justify-center text-[#4F35F3] hover:bg-[#EEF0FF] transition-all shadow-md cursor-pointer disabled:opacity-50 group"
            title="Upload Profile Picture"
          >
            <svg v-if="!profileStore.isUploadingPhoto" class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <svg v-else class="w-4 h-4 animate-spin text-[#4F35F3]" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
          </button>
        </div>

        <!-- Instructor Name, Role Badge & Contact Info -->
        <div class="flex-1 min-w-0 text-center sm:text-left">
          <h1 class="text-2xl sm:text-[26px] font-black text-[#17243A] tracking-tight leading-tight capitalize">
            {{ displayName }}
          </h1>

          <!-- Role Pill Badge matching screenshot -->
          <div class="mt-1.5 mb-3.5">
            <span class="inline-flex items-center px-3 py-1 bg-[#EEF0FF] text-[#4F35F3] border border-indigo-100 text-[10px] font-black rounded-lg uppercase tracking-wider">
              {{ displayRole }}
            </span>
          </div>
          
          <!-- Contact Items matching screenshot -->
          <div class="space-y-2 text-xs sm:text-[13px] text-[#71819B]">
            <div class="flex items-center justify-center sm:justify-start gap-2.5">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
              <span class="truncate font-medium">{{ displayEmail }}</span>
            </div>

            <div class="flex items-center justify-center sm:justify-start gap-2.5">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
              </svg>
              <span class="font-medium">{{ displayPhone }}</span>
            </div>

            <div class="flex items-center justify-center sm:justify-start gap-2.5">
              <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span class="truncate font-medium">{{ displayOffice }}</span>
            </div>
          </div>
        </div>

      </div>

      <!-- Right: Structured Metadata Grid matching screenshot -->
      <div class="w-full lg:w-[260px] xl:w-[280px] pt-4 lg:pt-0 border-t lg:border-t-0 lg:border-l border-slate-100 lg:pl-6 shrink-0">
        <div class="grid grid-cols-[105px_1fr] gap-y-3.5 text-xs">
          
          <div class="font-semibold text-[#71819B] text-[11px]">Username</div>
          <div class="font-bold text-[#17243A] truncate">
            {{ displayUsername }}
          </div>
          
          <div class="font-semibold text-[#71819B] text-[11px]">Employee ID</div>
          <div class="font-bold text-[#17243A]">
            {{ displayEmployeeId }}
          </div>
          
          <div class="font-semibold text-[#71819B] text-[11px]">Department</div>
          <div class="font-bold text-[#17243A] capitalize truncate" :title="displayDepartment">
            {{ displayDepartment }}
          </div>
          
          <div class="font-semibold text-[#71819B] text-[11px]">Role</div>
          <div class="font-bold text-[#17243A]">
            {{ formatRole(profileStore.profile?.role) }}
          </div>
          
          <div class="font-semibold text-[#71819B] text-[11px]">Member Since</div>
          <div class="font-bold text-[#17243A]">
            {{ displayMemberSince }}
          </div>
          
          <div class="font-semibold text-[#71819B] text-[11px]">Account Status</div>
          <div>
            <span class="inline-flex items-center px-2 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-100 font-bold rounded text-[10px] capitalize">
              {{ displayStatus }}
            </span>
          </div>
          
        </div>
      </div>

    </div>
  </div>
</template>
