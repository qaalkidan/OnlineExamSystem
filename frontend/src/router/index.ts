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
  const isInstructorMutationRoute = 
    lockedInstructorMutationRoutes.includes(to.name as string) ||
    to.path.includes('/instructor/exams/create') ||
    to.path.includes('/instructor/exams/edit/') ||
    to.path.includes('/create-question') ||
    to.path.includes('/edit-question/')

  if (isInstructorMutationRoute && token) {
    try {
      const lockStore = useSemesterLockStore()
      await lockStore.fetchLockStatus()
      if (lockStore.isLocked) {
        lockStore.promptLockedNotice((to.name as string) || 'perform modifications')
        if (to.path.includes('/question-banks/')) {
          next('/instructor/question-banks')
        } else {
          next('/instructor/exams')
        }
        return
      }
    } catch (err) {
      console.warn('Semester lock router guard check error:', err)
    }
  }

  next()
})

export default router
