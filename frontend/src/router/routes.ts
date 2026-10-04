import type { RouteRecordRaw } from 'vue-router'

export const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    name: 'Landing',
    component: () => import('../modules/auth/pages/LandingPage.vue')
  },
  {
    path: '/login',
    name: 'Login',
    component: () => import('../modules/auth/pages/Login.vue')
  },

  // ── Instructor Routes ──
  {
    path: '/instructor',
    component: () => import('../layouts/DashboardLayout.vue'),
    children: [
      {
        path: '',
        redirect: '/instructor/dashboard'
      },
      {
        path: 'dashboard',
        name: 'InstructorDashboard',
        component: () => import('../modules/instructor/pages/Dashboard.vue')
      },
      {
        path: 'question-banks',
        name: 'QuestionBanks',
        component: () => import('../modules/instructor/pages/QuestionBanks.vue')
      },
      {
        path: 'question-banks/:id',
        name: 'QuestionBankDetails',
        component: () => import('../modules/instructor/pages/QuestionBankDetails.vue')
      },
      {
        path: 'question-banks/:id/create-question',
        name: 'CreateQuestion',
        component: () => import('../modules/instructor/pages/CreateQuestion.vue')
      },
      {
        path: 'question-banks/:id/review',
        name: 'ReviewQuestions',
        component: () => import('../modules/instructor/pages/ReviewQuestions.vue')
      },
      {
        path: 'question-banks/:id/filtered',
        name: 'ViewFilteredQuestions',
        component: () => import('../modules/instructor/pages/ViewFilteredQuestions.vue')
      },
      {
        path: 'question-banks/:id/edit-question/:questionId',
        name: 'EditQuestion',
        component: () => import('../modules/instructor/pages/CreateQuestion.vue')
      },
      {
        path: 'question-banks/:id/question/:questionId',
        name: 'QuestionDetails',
        component: () => import('../modules/instructor/pages/QuestionDetails.vue')
      },
      {
        path: 'exams',
        name: 'Exams',
        component: () => import('../modules/instructor/pages/Exams.vue')
      },
      {
        path: 'exams',
        name: 'Exams',
        component: () => import('../modules/instructor/pages/Exams.vue')
      },
      {
        path: 'students',
        name: 'Students',
        component: () => import('../modules/instructor/pages/Students.vue')
      },
      {
        path: 'students/:studentId',
        name: 'StudentProfile',
        component: () => import('../modules/instructor/pages/StudentProfile.vue')
      },
      {
        path: 'students/:studentId/results',
        name: 'StudentExamResults',
        component: () => import('../modules/instructor/pages/StudentExamResults.vue')
      },
      {
        path: 'results',
        name: 'Results',
        component: () => import('../modules/instructor/pages/Results.vue')
      },
      {
        path: 'results/:examId',
        name: 'ExamResultDetail',
        component: () => import('../modules/instructor/pages/ExamResultDetail.vue')
      },
      {
        path: 'results/:examId/student/:studentId',
        name: 'InstructorStudentResultDetail',
        component: () => import('../modules/instructor/pages/StudentResultDetail.vue')
      },
      {
        path: 'reports',
        name: 'Reports',
        component: () => import('../modules/instructor/pages/Reports.vue')
      },
      {
        path: 'semester-submission',
        name: 'SemesterSubmission',
        component: () => import('../modules/instructor/pages/SemesterSubmission.vue')
      },
      {
        path: 'exams/create',
        name: 'CreateExam',
        component: () => import('../modules/instructor/pages/CreateExam.vue')
      },
      {
        path: 'exams/edit/:id',
        name: 'EditExam',
        component: () => import('../modules/instructor/pages/EditExam.vue')
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('../modules/instructor/pages/Profile.vue')
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('../modules/instructor/pages/Settings.vue')
      }
    ]
  },

  // ── Student Routes ──
  {
    path: '/student',
    name: 'StudentDashboard',
    component: () => import('../modules/student/views/Dashboard.vue')
  },
  {
    path: '/student/exams',
    name: 'StudentMyExams',
    component: () => import('../modules/student/views/MyExams.vue')
  },
  {
    path: '/student/exam/take',
    name: 'StudentExamTake',
    component: () => import('../modules/student/views/ExamTake.vue')
  },
  {
    path: '/student/results',
    name: 'StudentResults',
    component: () => import('../modules/student/views/Results.vue')
  },
  {
    path: '/student/results/:attemptId',
    name: 'StudentResultDetail',
    component: () => import('../modules/student/views/StudentResultDetail.vue')
  },
  {
    path: '/student/academic-calendar',
    name: 'StudentAcademicCalendar',
    component: () => import('../modules/student/views/AcademicCalendar.vue')
  },
  {
    path: '/student/profile',
    name: 'StudentProfile',
    component: () => import('../modules/student/views/Profile.vue')
  },

  // ── Super Admin Routes ──
  {
    path: '/admin',
    component: () => import('../layouts/AdminLayout.vue'),
    children: [
      {
        path: '',
        redirect: '/admin/dashboard'
      },
      {
        path: 'dashboard',
        name: 'AdminDashboard',
        component: () => import('../modules/admin/pages/Dashboard.vue')
      },

      {
        path: 'instructors',
        name: 'AdminInstructors',
        component: () => import('../modules/admin/pages/Instructors.vue')
      },
      {
        path: 'students',
        name: 'AdminStudents',
        component: () => import('../modules/admin/pages/Students.vue')
      },
      {
        path: 'courses',
        name: 'AdminCourses',
        component: () => import('../modules/admin/pages/Courses.vue')
      },
      {
        path: 'exams',
        name: 'AdminExams',
        component: () => import('../modules/admin/pages/Exams.vue')
      },
      {
        path: 'question-banks',
        name: 'AdminQuestionBanks',
        component: () => import('../modules/admin/pages/QuestionBanks.vue')
      },
      {
        path: 'reports',
        name: 'AdminReports',
        component: () => import('../modules/admin/pages/Reports.vue')
      },
      {
        path: 'activity-logs',
        name: 'AdminActivityLogs',
        component: () => import('../modules/admin/pages/ActivityLogs.vue')
      },
      {
        path: 'departments',
        name: 'AdminDepartments',
        component: () => import('../modules/admin/pages/Departments.vue')
      },
      {
        path: 'academic-calendar',
        name: 'AdminAcademicCalendar',
        component: () => import('../modules/admin/pages/AcademicCalendar.vue')
      },
      {
        path: 'settings',
        name: 'AdminSettings',
        component: () => import('../modules/admin/pages/Settings.vue')
      }
    ]
  },

  // ── Department Head Routes ──
  {
    path: '/dept-head',
    component: () => import('../layouts/DeptHeadLayout.vue'),
    children: [
      {
        path: '',
        redirect: '/dept-head/dashboard'
      },
      {
        path: 'dashboard',
        name: 'DeptHeadDashboard',
        component: () => import('../modules/department-head/pages/Dashboard.vue')
      },
      {
        path: 'instructors',
        name: 'DeptHeadInstructors',
        component: () => import('../modules/department-head/pages/Instructors.vue')
      },
      {
        path: 'students',
        name: 'DeptHeadStudents',
        component: () => import('../modules/department-head/pages/Students.vue')
      },
      {
        path: 'courses',
        name: 'DeptHeadCourses',
        component: () => import('../modules/department-head/pages/Courses.vue')
      },
      {
        path: 'exams',
        name: 'DeptHeadExams',
        component: () => import('../modules/department-head/pages/Exams.vue')
      },
      {
        path: 'results',
        name: 'DeptHeadResults',
        component: () => import('../modules/department-head/pages/Results.vue')
      },
      {
        path: 'schedule',
        name: 'DeptHeadScheduleExams',
        component: () => import('../modules/department-head/pages/ExamSchedule.vue')
      },
      {
        path: 'reports',
        name: 'DeptHeadReports',
        component: () => import('../modules/department-head/pages/Reports.vue')
      },
      {
        path: 'activity-logs',
        name: 'DeptHeadActivityLogs',
        component: () => import('../modules/department-head/pages/ActivityLogs.vue')
      },
      {
        path: 'semester-submissions',
        name: 'DeptHeadSemesterSubmissions',
        component: () => import('../modules/department-head/pages/SemesterSubmissionDetail.vue')
      },
      {
        path: 'semester-submissions/overview',
        name: 'DeptHeadSemesterSubmissionsOverview',
        component: () => import('../modules/department-head/pages/SemesterSubmissions.vue')
      },
      {
        path: 'semester-submissions/:id',
        name: 'DeptHeadSemesterSubmissionDetail',
        component: () => import('../modules/department-head/pages/SemesterSubmissionDetail.vue')
      },
      {
        path: 'settings',
        name: 'DeptHeadSettings',
        component: () => import('../modules/department-head/pages/Settings.vue')
      }
    ]
  }
]
