export interface StudentProfile {
  name: string;
  id: string;
  email: string;
  department: string;
  program: string;
  semester: string;
  academicYear: string;
  avatar: string;
  cgpa: number;
  creditsCompleted: number;
  phone?: string;
  gender?: string;
  section?: string;
  yearLevel?: string;
  office?: string;
  username?: string;
  status?: string;
  rawUser?: any;
}

export interface Question {
  id: number;
  text: string;
  instruction?: string;
  options?: string[];
  pairs?: { left: string, right: string }[];
  columnA?: string;
  columnB?: string;
  type: 'multiple-choice' | 'text' | 'true_false' | 'multiple_true_false' | 'fill_blank' | 'matching' | string;
  correctAnswer?: string;
  selectedAnswer?: string;
  flagged?: boolean;
  marks?: number;
}

export interface ActiveExam {
  id: number;
  courseCode: string;
  courseName: string;
  examTitle: string;
  instructor: string;
  date: string;
  time: string;
  durationMinutes: number;
  totalQuestions: number;
  totalMarks: number;
  settings?: Record<string, any>;
  questions: Question[];
}

export interface UpcomingExam {
  id: number;
  courseCode: string;
  courseName: string;
  instructor: string;
  examType: string;
  scheduledAt?: string | null;   // ISO datetime string (primary)
  scheduledDate: string;          // ISO datetime string (legacy alias)
  startTime: string;
  durationMinutes: number;
  totalQuestions: number;
  totalMarks: number;
  status: 'Soon' | 'Pending' | 'Ready' | 'Upcoming';
  // Attempt tracking
  attemptStatus?: 'in_progress' | 'submitted' | 'graded' | 'published' | null;
  attemptId?: number | null;
  attemptStartedAt?: string | null;
}

export interface RecentResult {
  id: number;
  courseCode: string;
  courseName: string;
  examTitle: string;
  examType?: string;
  score: number;
  totalMarks: number;
  percentage: number;
  grade: 'A+' | 'A' | 'A-' | 'B+' | 'B' | 'C+' | 'C' | 'Pending' | string;
  status: 'Passed' | 'Failed' | 'Pending';
  completedDate: string;
  // Grading breakdown
  autoScore?: number;
  autoTotal?: number;
  pendingTotal?: number;
  hasPending?: boolean;
  typeBreakdown?: Record<string, { earned: number; total: number }>;
  questionsReview: {
    questionText: string;
    type?: string;
    gradingStatus?: 'graded' | 'pending';
    studentAnswer: string;
    correctAnswer: string | null;
    explanation: string;
    isCorrect: boolean | null;
    marks?: number;
    earnedMarks?: number | null;
    correctCount?: number;
    totalCount?: number;
  }[];
}

export interface Announcement {
  id: string;
  title: string;
  content: string;
  date: string;
  category: 'Schedule' | 'Notice' | 'System' | 'Urgent';
  sender: string;
}

export interface CalendarEvent {
  id: string;
  title: string;
  date: string;
  type: 'Exam' | 'Holiday' | 'Deadline' | 'Academic';
  description: string;
}
