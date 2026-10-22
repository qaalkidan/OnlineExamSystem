<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../auth/store/authStore'
import { useSettingsStore } from '../../../store/settingsStore'
import { useStudentExamStore } from '../store/studentExamStore'
import { sampleUpcomingExams, sampleRecentResults } from '../data/mockData'
import type { StudentProfile, Announcement } from '../types'

const props = defineProps<{
  profile: StudentProfile
  announcements?: Announcement[]
}>()

const emit = defineEmits<{
  (e: 'open-profile'): void
  (e: 'open-notifications'): void
  (e: 'toggle-sidebar'): void
}>()

const router = useRouter()
const authStore = useAuthStore()
const settingsStore = useSettingsStore()
const examStore = useStudentExamStore()

// Search state
const searchQuery = ref('')
const isSearchOpen = ref(false)
const searchContainerRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const mobileSearchInputRef = ref<HTMLInputElement | null>(null)
const isMobileSearchOpen = ref(false)
const highlightedIndex = ref(-1)

// Profile Dropdown state
const isProfileDropdownOpen = ref(false)
const profileDropdownRef = ref<HTMLElement | null>(null)

// Search result item contract
interface SearchResultItem {
  id: string
  type: 'exam' | 'result' | 'course' | 'quick-link'
  title: string
  subtitle: string
  badgeText?: string
  badgeColor?: string
  iconType: 'exam' | 'result' | 'course' | 'calendar' | 'profile' | 'dashboard'
  route: string
}

// Dynamically resolve academic year from instructor assignment, student profile, or system settings
const displayAcademicYear = computed(() => {
  const raw =
    props.profile?.instructorAcademicYear ||
    props.profile?.academicYear ||
    settingsStore.academicYear ||
    ''

  if (!raw) return 'Academic Year'
  const clean = raw.replace(/\s*academic\s*year\s*/i, '').trim()
  return clean ? `${clean} Academic Year` : 'Academic Year'
})

// Dynamically resolve semester from instructor assignment, course semester, student profile, or system settings
const displaySemester = computed(() => {
  const raw =
    props.profile?.instructorSemester ||
    props.profile?.courseSemester ||
    props.profile?.semester ||
    settingsStore.semester ||
    'First Semester'

  return raw
})

// Compact label for tablet view
const displayTabletYear = computed(() => {
  return displayAcademicYear.value.replace(/\s*academic\s*year\s*/i, '').trim()
})

// Comprehensive global search across exams, results, enrolled courses, and portal pages
const searchResults = computed<SearchResultItem[]>(() => {
  const query = searchQuery.value.trim().toLowerCase()

  // Standard Navigation Links
  const navigationLinks: SearchResultItem[] = [
    {
      id: 'nav-dashboard',
      type: 'quick-link',
      title: 'Dashboard',
      subtitle: 'Overview of courses, exams, and academic stats',
      badgeText: 'Page',
      badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
      iconType: 'dashboard',
      route: '/student'
    },
    {
      id: 'nav-exams',
      type: 'quick-link',
      title: 'My Exams',
      subtitle: 'Examination schedules, active exams, and upcoming sessions',
      badgeText: 'Page',
      badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
      iconType: 'exam',
      route: '/student/exams'
    },
    {
      id: 'nav-results',
      type: 'quick-link',
      title: 'Exam Results',
      subtitle: 'Review performance breakdown, scores, and official grades',
      badgeText: 'Page',
      badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
      iconType: 'result',
      route: '/student/results'
    },
    {
      id: 'nav-calendar',
      type: 'quick-link',
      title: 'Academic Calendar',
      subtitle: 'Key milestone dates, deadlines, and university events',
      badgeText: 'Page',
      badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
      iconType: 'calendar',
      route: '/student/calendar'
    },
    {
      id: 'nav-profile',
      type: 'quick-link',
      title: 'Student Profile',
      subtitle: 'Personal details, department, and academic term information',
      badgeText: 'Page',
      badgeColor: 'bg-purple-50 text-[#4F35F3] border-[#4F35F3]/20',
      iconType: 'profile',
      route: '/student/profile'
    }
  ]

  // If search query is empty, show quick navigation suggestions
  if (!query) {
    return navigationLinks
  }

  const items: SearchResultItem[] = []

  // 1. Navigation items matching query
  navigationLinks.forEach(link => {
    if (link.title.toLowerCase().includes(query) || link.subtitle.toLowerCase().includes(query)) {
      items.push(link)
    }
  })

  // 2. Active & Upcoming Exams
  const allExams = [
    ...(examStore.activeExam ? [examStore.activeExam] : []),
    ...(examStore.upcomingExams.length ? examStore.upcomingExams : sampleUpcomingExams)
  ]

  const seenExamIds = new Set<string | number>()
  allExams.forEach((e: any) => {
    if (seenExamIds.has(e.id)) return
    seenExamIds.add(e.id)

    const code = e.courseCode || ''
    const course = e.courseName || ''
    const title = e.examType || e.examTitle || 'Examination'
    const instructor = e.instructor || ''
    const status = e.status || (e.attemptStatus === 'in_progress' ? 'In Progress' : 'Upcoming')

    if (
      code.toLowerCase().includes(query) ||
      course.toLowerCase().includes(query) ||
      title.toLowerCase().includes(query) ||
      instructor.toLowerCase().includes(query)
    ) {
      const isReady = status === 'Ready' || e.attemptStatus === 'in_progress'
      items.push({
        id: `exam-${e.id}`,
        type: 'exam',
        title: `${code ? code + ' — ' : ''}${title}`,
        subtitle: `${course}${instructor ? ' • ' + instructor : ''}`,
        badgeText: status,
        badgeColor: isReady 
          ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
          : 'bg-blue-50 text-blue-700 border-blue-200',
        iconType: 'exam',
        route: `/student/exams?search=${encodeURIComponent(code || course || title)}`
      })
    }
  })

  // 3. Exam Results
  const allResults = examStore.results.length ? examStore.results : sampleRecentResults
  allResults.forEach((r: any) => {
    const code = r.courseCode || ''
    const course = r.courseName || ''
    const title = r.examTitle || r.examType || 'Examination'
    const grade = r.grade || ''
    const status = r.status || ''

    if (
      code.toLowerCase().includes(query) ||
      course.toLowerCase().includes(query) ||
      title.toLowerCase().includes(query) ||
      grade.toLowerCase().includes(query) ||
      status.toLowerCase().includes(query)
    ) {
      items.push({
        id: `result-${r.id}`,
        type: 'result',
        title: `${code ? code + ' — ' : ''}${course}`,
        subtitle: `${title} • Score: ${r.score}/${r.totalMarks} (${r.percentage}%)`,
        badgeText: r.status || (r.grade ? `Grade ${r.grade}` : 'Result'),
        badgeColor: r.status === 'Passed' 
          ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
          : r.status === 'Failed' 
            ? 'bg-rose-50 text-rose-700 border-rose-200' 
            : 'bg-purple-50 text-purple-700 border-purple-200',
        iconType: 'result',
        route: `/student/results?search=${encodeURIComponent(code || course)}`
      })
    }
  })

  // 4. Distinct Enrolled Courses
  const distinctCourses = new Map<string, { code: string, name: string }>()
  allExams.forEach((e: any) => {
    if (e.courseCode || e.courseName) {
      const key = (e.courseCode || e.courseName).toLowerCase()
      if (!distinctCourses.has(key)) {
        distinctCourses.set(key, { code: e.courseCode || '', name: e.courseName || '' })
      }
    }
  })
  allResults.forEach((r: any) => {
    if (r.courseCode || r.courseName) {
      const key = (r.courseCode || r.courseName).toLowerCase()
      if (!distinctCourses.has(key)) {
        distinctCourses.set(key, { code: r.courseCode || '', name: r.courseName || '' })
      }
    }
  })

  if (props.profile?.department) {
    const key = props.profile.department.toLowerCase()
    if (!distinctCourses.has(key)) {
      distinctCourses.set(key, { code: '', name: props.profile.department })
    }
  }

  distinctCourses.forEach((c) => {
    if (
      c.code.toLowerCase().includes(query) ||
      c.name.toLowerCase().includes(query)
    ) {
      if (!items.some(i => i.title.toLowerCase() === (c.name || c.code).toLowerCase())) {
        items.push({
          id: `course-${c.code || c.name}`,
          type: 'course',
          title: c.name || c.code,
          subtitle: c.code ? `Course Code: ${c.code}` : 'Enrolled Academic Course',
          badgeText: 'Course',
          badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200',
          iconType: 'course',
          route: `/student/exams?search=${encodeURIComponent(c.code || c.name)}`
        })
      }
    }
  })

  return items
})

const handleSearchFocus = () => {
  isSearchOpen.value = true
  highlightedIndex.value = -1
}

const clearSearch = () => {
  searchQuery.value = ''
  highlightedIndex.value = -1
  if (searchInputRef.value) {
    searchInputRef.value.focus()
  }
}

const toggleMobileSearch = () => {
  isMobileSearchOpen.value = !isMobileSearchOpen.value
  if (isMobileSearchOpen.value) {
    isSearchOpen.value = true
    nextTick(() => {
      mobileSearchInputRef.value?.focus()
    })
  } else {
    isSearchOpen.value = false
  }
}

const handleSelectResult = (item: SearchResultItem) => {
  searchQuery.value = ''
  isSearchOpen.value = false
  isMobileSearchOpen.value = false
  highlightedIndex.value = -1
  if (item.route === '/student/profile') {
    emit('open-profile')
  }
  router.push(item.route)
}

const handleSearchSubmit = () => {
  if (highlightedIndex.value >= 0 && searchResults.value[highlightedIndex.value]) {
    handleSelectResult(searchResults.value[highlightedIndex.value])
    return
  }

  const query = searchQuery.value.trim()
  if (!query) {
    isSearchOpen.value = false
    return
  }

  if (searchResults.value.length > 0) {
    handleSelectResult(searchResults.value[0])
    return
  }

  isSearchOpen.value = false
  isMobileSearchOpen.value = false
  router.push({
    path: '/student/exams',
    query: { search: query }
  })
}

const handleSearchKeyDown = (e: KeyboardEvent) => {
  if (!isSearchOpen.value) {
    if (e.key === 'ArrowDown' || e.key === 'Enter') {
      isSearchOpen.value = true
    }
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (searchResults.value.length > 0) {
      highlightedIndex.value = (highlightedIndex.value + 1) % searchResults.value.length
    }
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    if (searchResults.value.length > 0) {
      highlightedIndex.value = (highlightedIndex.value - 1 + searchResults.value.length) % searchResults.value.length
    }
  } else if (e.key === 'Enter') {
    e.preventDefault()
    handleSearchSubmit()
  } else if (e.key === 'Escape') {
    isSearchOpen.value = false
    highlightedIndex.value = -1
  }
}

const toggleProfileDropdown = () => {
  isProfileDropdownOpen.value = !isProfileDropdownOpen.value
}

const closeDropdown = () => {
  isProfileDropdownOpen.value = false
}

const navigateTo = (path: string) => {
  closeDropdown()
  if (path === '/student/profile') {
    emit('open-profile')
  }
  router.push(path)
}

const handleLogout = async () => {
  closeDropdown()
  await authStore.logout()
}

const handleClickOutside = (event: MouseEvent) => {
  const target = event.target as Node
  if (profileDropdownRef.value && !profileDropdownRef.value.contains(target)) {
    isProfileDropdownOpen.value = false
  }
  if (searchContainerRef.value && !searchContainerRef.value.contains(target)) {
    isSearchOpen.value = false
  }
}

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape') {
    isProfileDropdownOpen.value = false
    isSearchOpen.value = false
  }
}

onMounted(() => {
  settingsStore.fetchSettings()
  examStore.fetchExams(true).catch(() => {})
  examStore.fetchResults().catch(() => {})
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  document.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <header class="sticky top-0 z-40 w-full bg-white border-b border-[#E6EBF3] shadow-2xs backdrop-blur-md">
    <div class="mx-auto flex h-[68px] sm:h-[72px] w-full items-center justify-between px-3.5 sm:px-6 lg:px-8 max-w-[1600px]">
      
      <!-- Left: Hamburger & Brand -->
      <div class="flex items-center gap-2.5 sm:gap-4 lg:gap-5 min-w-0">
        <!-- Hamburger Menu -->
        <button 
          @click="emit('toggle-sidebar')"
          class="flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-xl border border-[#E6EBF3] text-[#71819B] hover:text-[#17243A] hover:bg-[#F6F8FC] transition-all focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 shrink-0 cursor-pointer shadow-2xs"
          aria-label="Toggle navigation menu"
          title="Toggle Menu"
        >
          <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
          </svg>
        </button>

        <!-- Brand/Logo Section -->
        <div class="flex items-center gap-2.5 sm:gap-3 min-w-0">
          <img src="../../../assets/images/logo.png" alt="Wollo University Logo" class="w-8 h-8 sm:w-9 sm:h-9 object-contain rounded-full shadow-2xs shrink-0" />
          <div class="min-w-0">
            <div class="font-serif text-xs sm:text-[13px] font-black tracking-wider text-[#17243A] uppercase leading-tight truncate">Wollo University</div>
            <div class="text-[9px] sm:text-[10px] font-semibold text-[#71819B] tracking-wide hidden xs:block truncate">Online Examination System</div>
          </div>
        </div>
      </div>

      <!-- Center: Search Bar with Live Suggestions Dropdown (Desktop & Tablet) -->
      <div ref="searchContainerRef" class="hidden md:flex flex-1 max-w-xs lg:max-w-md mx-3 lg:mx-8 relative">
        <div class="relative w-full">
          <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#71819B]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
          
          <input 
            ref="searchInputRef"
            type="text" 
            v-model="searchQuery"
            @focus="handleSearchFocus"
            @keydown="handleSearchKeyDown"
            placeholder="Search courses, exams, results..." 
            class="block w-full pl-10 pr-9 py-2 rounded-xl bg-[#F6F8FC] border border-[#E6EBF3] text-[#17243A] placeholder-[#71819B] text-xs sm:text-[13px] focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] focus:bg-white transition-all shadow-2xs"
            aria-label="Search courses, exams, and results"
            autocomplete="off"
          />

          <!-- Clear Search Button -->
          <button 
            v-if="searchQuery"
            type="button"
            @click="clearSearch"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#71819B] hover:text-[#17243A] cursor-pointer"
            aria-label="Clear search"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Live Search Dropdown -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="transform scale-95 opacity-0 -translate-y-1"
          enter-to-class="transform scale-100 opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="transform scale-100 opacity-100 translate-y-0"
          leave-to-class="transform scale-95 opacity-0 -translate-y-1"
        >
          <div
            v-if="isSearchOpen"
            class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl border border-[#E6EBF3] shadow-xl py-2 z-50 max-h-[420px] overflow-y-auto"
          >
            <!-- Dropdown Section Header -->
            <div class="px-3.5 py-1.5 text-[11px] font-bold text-[#71819B] uppercase tracking-wider flex items-center justify-between border-b border-[#F0F4FA] mb-1">
              <span>{{ searchQuery.trim() ? `Results for "${searchQuery.trim()}"` : 'Quick Navigation & Suggestions' }}</span>
              <span v-if="searchResults.length" class="text-[10px] font-normal text-[#71819B]">{{ searchResults.length }} items</span>
            </div>

            <!-- Empty State -->
            <div v-if="searchResults.length === 0" class="py-8 text-center px-4">
              <div class="w-10 h-10 rounded-full bg-[#F6F8FC] text-[#71819B] flex items-center justify-center mx-auto mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
              </div>
              <p class="text-xs font-semibold text-[#17243A]">No matches found</p>
              <p class="text-[11px] text-[#71819B] mt-0.5">Try searching with a course code (e.g. CS201) or exam title</p>
            </div>

            <!-- List of Results -->
            <div v-else class="px-1.5 space-y-0.5">
              <button
                v-for="(item, index) in searchResults"
                :key="item.id"
                type="button"
                @click="handleSelectResult(item)"
                @mouseenter="highlightedIndex = index"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-left transition-colors cursor-pointer group"
                :class="highlightedIndex === index ? 'bg-[#EEF0FF] text-[#4F35F3]' : 'hover:bg-[#F6F8FC] text-[#17243A]'"
              >
                <!-- Icon by Type -->
                <div 
                  class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-transform group-hover:scale-105"
                  :class="highlightedIndex === index ? 'bg-[#4F35F3] text-white' : 'bg-[#EEF0FF] text-[#4F35F3]'"
                >
                  <!-- Exam Icon -->
                  <svg v-if="item.iconType === 'exam'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                  </svg>
                  <!-- Result Icon -->
                  <svg v-else-if="item.iconType === 'result'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                  </svg>
                  <!-- Course Icon -->
                  <svg v-else-if="item.iconType === 'course'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                  </svg>
                  <!-- Calendar Icon -->
                  <svg v-else-if="item.iconType === 'calendar'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  <!-- Profile Icon -->
                  <svg v-else-if="item.iconType === 'profile'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                  <!-- Dashboard / Default Icon -->
                  <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                  </svg>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-bold truncate" :class="highlightedIndex === index ? 'text-[#4F35F3]' : 'text-[#17243A]'">
                      {{ item.title }}
                    </span>
                    <span 
                      v-if="item.badgeText"
                      class="px-1.5 py-0.5 rounded text-[10px] font-semibold border shrink-0"
                      :class="item.badgeColor || 'bg-slate-50 text-slate-700 border-slate-200'"
                    >
                      {{ item.badgeText }}
                    </span>
                  </div>
                  <p class="text-[11px] text-[#71819B] truncate mt-0.5">{{ item.subtitle }}</p>
                </div>

                <!-- Action Chevron -->
                <svg class="w-3.5 h-3.5 text-[#71819B] group-hover:text-[#4F35F3] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            </div>

            <!-- Footer Keyboard Shortcuts Hint -->
            <div class="mt-2 pt-2 border-t border-[#F0F4FA] px-3.5 flex items-center justify-between text-[10px] text-[#71819B]">
              <span class="flex items-center gap-1.5">
                <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[9px]">↑↓</kbd> Navigate
                <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[9px]">↵</kbd> Select
                <kbd class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono text-[9px]">Esc</kbd> Close
              </span>
              <button 
                v-if="searchQuery.trim()" 
                type="button" 
                @click="handleSearchSubmit" 
                class="text-[#4F35F3] hover:underline font-bold cursor-pointer"
              >
                View in Exams →
              </button>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Right: Academic Badges, Notifications & Profile -->
      <div class="flex items-center gap-2 sm:gap-3 shrink-0">
        
        <!-- Mobile Search Toggle Button -->
        <button
          @click="toggleMobileSearch"
          class="flex md:hidden h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-xl border border-[#E6EBF3] text-[#71819B] hover:text-[#17243A] hover:bg-[#F6F8FC] transition-colors focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 cursor-pointer shadow-2xs"
          aria-label="Search"
          title="Search"
        >
          <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
          </svg>
        </button>

        <!-- Desktop Academic Year & Semester Badge -->
        <div class="hidden xl:flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#EEF0FF]/80 text-[#17243A] text-xs font-bold border border-[#4F35F3]/20 shadow-2xs">
          <span class="w-2 h-2 rounded-full bg-[#4F35F3] shrink-0"></span>
          <span>{{ displayAcademicYear }}</span>
          <span class="text-slate-300">•</span>
          <span class="text-[#4F35F3] font-black">{{ displaySemester }}</span>
        </div>

        <!-- Tablet Compact Academic Badge -->
        <div class="hidden md:flex xl:hidden items-center px-3 py-1.5 rounded-full bg-[#EEF0FF]/80 text-[#17243A] text-[11px] font-bold border border-[#4F35F3]/20">
          <span>{{ displayTabletYear }} • {{ displaySemester }}</span>
        </div>

        <!-- Notification Bell Icon Button -->
        <button
          @click="emit('open-notifications')"
          class="relative flex h-10 w-10 sm:h-9 sm:w-9 items-center justify-center rounded-xl border border-[#E6EBF3] text-[#71819B] hover:text-[#17243A] hover:bg-[#F6F8FC] transition-colors focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 cursor-pointer shadow-2xs"
          aria-label="View notifications"
          title="Notifications"
        >
          <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span v-if="(announcements || []).length > 0" class="absolute top-2 right-2 flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#4F35F3] opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-[#4F35F3]"></span>
          </span>
        </button>

        <!-- Student Profile Container with Dropdown -->
        <div class="relative" ref="profileDropdownRef">
          <button
            @click.stop="toggleProfileDropdown"
            class="flex items-center gap-2 sm:gap-2.5 text-left focus:outline-none group rounded-2xl p-1 sm:px-2.5 sm:py-1.5 hover:bg-[#F6F8FC] transition-all border border-transparent hover:border-[#E6EBF3] min-h-[44px] cursor-pointer"
            :class="{ 'bg-[#F6F8FC] border-[#E6EBF3] shadow-2xs': isProfileDropdownOpen }"
            aria-label="Student profile menu"
          >
            <img
              :src="profile.avatar"
              :alt="profile.name"
              class="h-8 w-8 sm:h-9 sm:w-9 rounded-full object-cover ring-2 ring-transparent group-hover:ring-[#4F35F3]/30 transition-all shadow-2xs shrink-0"
              referrerpolicy="no-referrer"
            />
            <div class="hidden lg:block max-w-[140px] truncate">
              <p class="text-[12px] font-bold text-[#17243A] leading-tight truncate">{{ profile.name }}</p>
              <p class="text-[10px] text-[#71819B] font-medium leading-tight mt-0.5 truncate">{{ profile.department }}</p>
            </div>
            <svg
              class="hidden lg:block h-3.5 w-3.5 text-[#71819B] group-hover:text-[#17243A] transition-transform duration-200 shrink-0"
              :class="{ 'rotate-180 text-[#4F35F3]': isProfileDropdownOpen }"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
            </svg>
          </button>

          <!-- Modern Student Profile Dropdown Menu -->
          <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="transform scale-95 opacity-0 -translate-y-1"
            enter-to-class="transform scale-100 opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="transform scale-100 opacity-100 translate-y-0"
            leave-to-class="transform scale-95 opacity-0 -translate-y-1"
          >
            <div
              v-if="isProfileDropdownOpen"
              class="absolute right-0 top-full mt-2 w-72 max-w-[calc(100vw-24px)] bg-white rounded-2xl border border-[#E6EBF3] shadow-xl py-2 z-50 select-none overflow-hidden"
            >
              <!-- User Info Header -->
              <div class="px-4 py-3 bg-gradient-to-br from-[#EEF0FF]/70 via-slate-50/40 to-white border-b border-[#E6EBF3] flex items-center gap-3">
                <img
                  :src="profile.avatar"
                  :alt="profile.name"
                  class="h-11 w-11 rounded-full object-cover ring-2 ring-[#4F35F3]/30 shadow-xs shrink-0"
                  referrerpolicy="no-referrer"
                />
                <div class="min-w-0 flex-1">
                  <p class="text-[13px] font-bold text-[#17243A] truncate">{{ profile.name }}</p>
                  <p class="text-[11px] text-[#71819B] truncate">{{ profile.email || profile.id }}</p>
                  <div class="mt-1 flex items-center gap-1.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-[#EEF0FF] text-[#4F35F3] border border-[#4F35F3]/20 truncate max-w-[140px]">
                      {{ profile.department }}
                    </span>
                    <span class="inline-flex items-center gap-1 text-[10px] text-[#71819B] font-medium">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Student
                    </span>
                  </div>
                </div>
              </div>

              <!-- Quick Action Links -->
              <div class="p-1.5 space-y-0.5">
                <!-- My Profile -->
                <button
                  type="button"
                  @click="navigateTo('/student/profile')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">My Profile</div>
                    <div class="text-[10px] text-[#71819B] font-normal">View & edit academic details</div>
                  </div>
                </button>

                <!-- My Exams -->
                <button
                  type="button"
                  @click="navigateTo('/student/exams')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">My Exams</div>
                    <div class="text-[10px] text-[#71819B] font-normal">Active & scheduled examinations</div>
                  </div>
                </button>

                <!-- Exam Results -->
                <button
                  type="button"
                  @click="navigateTo('/student/results')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">Exam Results</div>
                    <div class="text-[10px] text-[#71819B] font-normal">Scores, performance & transcripts</div>
                  </div>
                </button>

                <!-- Exam Schedule -->
                <button
                  type="button"
                  @click="navigateTo('/student/schedule')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">Exam Schedule</div>
                    <div class="text-[10px] text-[#71819B] font-normal">Department exam timetable</div>
                  </div>
                </button>

                <!-- Academic Calendar -->
                <button
                  type="button"
                  @click="navigateTo('/student/academic-calendar')"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-slate-700 hover:bg-[#EEF0FF] hover:text-[#4F35F3] transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-[#17243A] group-hover:text-[#4F35F3]">Academic Calendar</div>
                    <div class="text-[10px] text-[#71819B] font-normal">Semester events & milestones</div>
                  </div>
                </button>
              </div>

              <!-- Divider -->
              <div class="h-px bg-[#E6EBF3] my-1 mx-2"></div>

              <!-- Logout Option -->
              <div class="p-1.5 pt-0.5">
                <button
                  type="button"
                  @click="handleLogout"
                  class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-left text-xs font-semibold text-rose-600 hover:bg-rose-50 transition-colors group cursor-pointer"
                >
                  <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 group-hover:bg-rose-100 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                  </div>
                  <div class="flex-1">
                    <div class="font-bold text-rose-600">Sign Out</div>
                    <div class="text-[10px] text-rose-400 font-normal">Sign out of student portal</div>
                  </div>
                </button>
              </div>
            </div>
          </Transition>
        </div>

      </div>
    </div>

    <!-- Mobile Search Drawer (Screen width < md) -->
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="transform -translate-y-2 opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform -translate-y-2 opacity-0"
    >
      <div v-if="isMobileSearchOpen" class="md:hidden border-t border-[#E6EBF3] bg-white px-3.5 py-3 shadow-md">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-[#71819B]">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
          </div>
          <input 
            ref="mobileSearchInputRef"
            type="text" 
            v-model="searchQuery"
            @keydown="handleSearchKeyDown"
            placeholder="Search courses, exams, results..." 
            class="block w-full pl-9 pr-9 py-2 rounded-xl bg-[#F6F8FC] border border-[#E6EBF3] text-[#17243A] placeholder-[#71819B] text-xs focus:outline-none focus:ring-2 focus:ring-[#4F35F3]/20 focus:border-[#4F35F3] focus:bg-white"
            autocomplete="off"
          />
          <button 
            v-if="searchQuery"
            type="button"
            @click="clearSearch"
            class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#71819B] hover:text-[#17243A] cursor-pointer"
            aria-label="Clear search"
          >
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Mobile Results List -->
        <div v-if="searchResults.length > 0" class="mt-2.5 max-h-64 overflow-y-auto space-y-1 divide-y divide-[#F0F4FA]">
          <button
            v-for="item in searchResults"
            :key="'mobile-' + item.id"
            type="button"
            @click="handleSelectResult(item)"
            class="w-full flex items-center gap-2.5 pt-1.5 pb-1 px-2 rounded-lg text-left hover:bg-[#EEF0FF] transition-colors cursor-pointer group"
          >
            <div class="w-7 h-7 rounded-lg bg-[#EEF0FF] text-[#4F35F3] flex items-center justify-center shrink-0">
              <svg v-if="item.iconType === 'exam'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
              <svg v-else-if="item.iconType === 'result'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
              </svg>
              <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs font-bold text-[#17243A] truncate group-hover:text-[#4F35F3]">{{ item.title }}</p>
              <p class="text-[10px] text-[#71819B] truncate">{{ item.subtitle }}</p>
            </div>
            <span v-if="item.badgeText" class="text-[9px] font-bold px-1.5 py-0.5 rounded border shrink-0" :class="item.badgeColor">
              {{ item.badgeText }}
            </span>
          </button>
        </div>

        <div v-else-if="searchQuery.trim()" class="py-4 text-center">
          <p class="text-xs text-[#71819B]">No results found for "{{ searchQuery }}"</p>
        </div>
      </div>
    </Transition>
  </header>
</template>
