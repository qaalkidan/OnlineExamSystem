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
  const isInstructorRoute = to.path.startsWith('/instructor')

  if (isInstructorRoute && token) {
    try {
      const lockStore = useSemesterLockStore()
      await lockStore.fetchLockStatus()
      if (lockStore.isLocked) {
        const lockMsg = lockStore.lockMessage || 'Access Locked: The Department Head has closed access to your semester workspace. Please contact your Department Head if you need access restored.'
        localStorage.setItem('semester_locked_notice', lockMsg)
        sessionStorage.setItem('semester_locked_notice', lockMsg)
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
        localStorage.removeItem('instructor_context')
        next('/login')
        return
      }
    } catch (err: any) {
      if (err?.response?.status === 401 || (err?.response?.status === 403 && err?.response?.data?.locked)) {
        const lockMsg = err?.response?.data?.message || 'Access Locked: The Department Head has closed access to your semester workspace. Please contact your Department Head if you need access restored.'
        localStorage.setItem('semester_locked_notice', lockMsg)
        sessionStorage.setItem('semester_locked_notice', lockMsg)
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
        localStorage.removeItem('instructor_context')
        next('/login')
        return
      }
      console.warn('Semester lock router guard check error:', err)
    }
  }

  next()
})

export default router
