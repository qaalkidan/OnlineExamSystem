import { createRouter, createWebHistory } from 'vue-router'
import { routes } from './routes'
import { useSemesterLockStore } from '../modules/instructor/store/semesterLockStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

const lockedInstructorMutationRoutes = [
  'CreateExam',
  'EditExam',
  'CreateQuestion',
  'EditQuestion'
]

router.beforeEach(async (to, _from, next) => {
  const token = localStorage.getItem('auth_token')
  const authUserStr = localStorage.getItem('auth_user')
  let authUser: any = null
  if (authUserStr) {
    try {
      authUser = JSON.parse(authUserStr)
    } catch (_) {}
  }

  // 1. First-time login mandatory password change enforcement
  if (authUser?.must_change_password) {
    if (to.path !== '/change-password' && to.path !== '/login' && to.path !== '/access-locked') {
      next('/change-password')
      return
    }
  } else if (to.path === '/change-password') {
    if (!token) {
      next('/login')
      return
    }
    // If user already changed their password, redirect to their role home
    if (authUser?.role === 'dept_head') {
      next('/dept-head/dashboard')
      return
    } else if (authUser?.role === 'admin') {
      next('/admin/dashboard')
      return
    } else if (authUser?.role === 'student') {
      next('/student')
      return
    } else if (authUser?.role === 'instructor') {
      next('/instructor/dashboard')
      return
    }
  }

  const isInstructorRoute = to.path.startsWith('/instructor')

  if (isInstructorRoute && token) {
    try {
      const lockStore = useSemesterLockStore()
      await lockStore.fetchLockStatus()
      if (lockStore.isLocked) {
        const lockMsg = lockStore.lockMessage || 'Access Locked: The Department Head has closed access to your semester workspace. Please contact your Department Head if you need access restored.'
        localStorage.setItem('semester_locked_notice', lockMsg)
        sessionStorage.setItem('semester_locked_notice', lockMsg)
        if (lockStore.courseTitle) {
          localStorage.setItem('semester_locked_course', lockStore.courseTitle)
          sessionStorage.setItem('semester_locked_course', lockStore.courseTitle)
        }
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
        localStorage.removeItem('instructor_context')
        next('/access-locked')
        return
      }
    } catch (err: any) {
      if (err?.response?.status === 401 || (err?.response?.status === 403 && err?.response?.data?.locked)) {
        const lockMsg = err?.response?.data?.message || 'Access Locked: The Department Head has closed access to your semester workspace. Please contact your Department Head if you need access restored.'
        localStorage.setItem('semester_locked_notice', lockMsg)
        sessionStorage.setItem('semester_locked_notice', lockMsg)
        if (err?.response?.data?.course_title) {
          localStorage.setItem('semester_locked_course', err.response.data.course_title)
          sessionStorage.setItem('semester_locked_course', err.response.data.course_title)
        }
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
        localStorage.removeItem('instructor_context')
        next('/access-locked')
        return
      }
      console.warn('Semester lock router guard check error:', err)
    }
  }

  next()
})

export default router
