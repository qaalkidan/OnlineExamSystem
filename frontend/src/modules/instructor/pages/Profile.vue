<script setup lang="ts">
import ProfileOverview from '../components/profile/ProfileOverview.vue'
import ProfileTabs from '../components/profile/ProfileTabs.vue'
import ProfileForm from '../components/profile/ProfileForm.vue'
import ProfileStats from '../components/profile/ProfileStats.vue'
import ProfileActivity from '../components/profile/ProfileActivity.vue'
import ProfilePreferences from '../components/profile/ProfilePreferences.vue'
</script>

<template>
  <div class="max-w-[1500px] mx-auto pb-8">
    
    <div class="px-3.5 sm:px-6 lg:px-8 mt-4 sm:mt-6">
      <!-- Main Content Grid -->
      <div class="flex flex-col lg:flex-row gap-6">
        
        <!-- Left Column -->
        <div class="flex-1 min-w-0 space-y-6">
          <ProfileOverview />
          
          <div class="bg-white border border-slate-100 rounded-2xl overflow-hidden shadow-sm">
            <ProfileTabs />
            <div class="p-4 sm:p-6">
              <ProfileForm />
            </div>
          </div>
          
          <ProfileStats />
        </div>

        <!-- Right Column -->
        <div class="w-full lg:w-[320px] xl:w-[350px] space-y-6">
          <ProfileActivity />
          <ProfilePreferences />
        </div>

      </div>
    </div>
    
  </div>
</template>
