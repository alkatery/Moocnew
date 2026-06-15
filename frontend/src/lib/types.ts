export interface Category {
  id: number;
  name: string;
  slug: string;
  parent_id?: number | null;
}

export interface Course {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  description: string | null;
  status: string;
  pricing_type: 'free' | 'one_time' | 'subscription';
  price_minor: number;
  passing_grade?: number;
  cover_image?: string | null;
  rating?: number | null;
  reviews_count?: number;
  sections?: Section[];
  instructor?: { id: number; name: string };
  category?: Category | null;
}

export interface Section {
  id: number;
  title: string;
  position: number;
  lessons: Lesson[];
}

export interface Lesson {
  id: number;
  title: string;
  type: 'video' | 'article' | 'image' | 'file' | 'live';
  position: number;
  is_free_preview: boolean;
  video_provider: string | null;
  video_status: string;
}

export interface Enrollment {
  id: number;
  course_id: number;
  status: string;
  progress_percent: number;
  course?: Course;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
  locale?: string | null;
  timezone?: string | null;
}

export interface AdminUser {
  id: number;
  name: string;
  email: string;
  roles: string[];
  disabled?: boolean;
  created_at: string | null;
}

export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; total?: number };
}

export interface Order {
  id: number;
  course_id: number;
  status: string;
  total_minor: number;
  currency: string;
  created_at: string | null;
}

export interface ForumThreadSummary {
  id: number;
  title: string;
  posts_count?: number;
}

export interface ForumPost {
  id: number;
  body: string;
  user_id: number;
  created_at: string | null;
}

export interface NotificationItem {
  id: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string | null;
}

export interface Preference {
  type: string;
  channel: string;
  enabled: boolean;
}

export interface CalendarEvent {
  type: string;
  title: string;
  course_id: number;
  at: string;
  at_local: string;
  hijri: string;
}

export interface NewsPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  body: string;
  published_at: string | null;
  created_at: string | null;
  author?: { id?: number; name?: string };
}

export interface PlatformStats {
  courses: number;
  learners: number;
  instructors: number;
  enrollments: number;
}

export interface PathSummary {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  cover_image?: string | null;
  published_at: string | null;
  courses_count: number;
  levels_count: number;
}

export interface PathCourseRef {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  pricing_type: string;
  price_minor: number;
  instructor: string | null;
}

export interface PathLevelItem {
  course: PathCourseRef;
  position: number;
  state: 'completed' | 'unlocked' | 'locked';
}

export interface PathLevel {
  level: number;
  items: PathLevelItem[];
}

export interface PathDetail {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  description: string | null;
  cover_image?: string | null;   // §3.5 — حقل الغلاف المُرجَع من API (مؤكَّد §1.أ)
  published_at: string | null;
  levels: PathLevel[];
  viewer: {
    enrolled: boolean;
    completed: boolean;
    progress: { completed: number; total: number; percent: number } | null;
  };
}

export interface StudyPlanView {
  id: number;
  title: string;
  cadence_days: number;
  target_date: string | null;
  status: 'active' | 'completed';
  completed_at: string | null;
  progress: { completed: number; total: number; percent: number };
  next_course: { id: number; title: string; slug: string } | null;
  items: { course: { id: number; title: string; slug: string }; completed: boolean }[];
}

export interface CertificateView {
  serial: string;
  verification_uuid: string;
  subject_type: 'course' | 'learning_path';
  course_title: string;
  grade: number | null;
  issued_at: string;
}

export interface GradeComponent {
  id?: number;
  type: 'quiz' | 'assignment';
  title: string;
  score: number | null;
  pass_mark: number | null;
  passed: boolean;
}

export interface CourseGrade {
  passing_grade: number;
  overall: number | null;
  passed: boolean;
  components: GradeComponent[];
}

export interface Review {
  id: number;
  rating: number;
  comment: string | null;
  user: string | null;
  created_at: string | null;
}

export interface ReviewSummary {
  average: number;
  count: number;
  distribution: Record<string, number>;
}

export interface MyStats {
  points: number;
  current_streak: number;
  longest_streak: number;
  rank: number;
  badges: string[];
}

export interface LeaderboardRow {
  rank: number;
  name: string;
  points: number;
  current_streak: number;
}

export interface LearnerProfile {
  name: string;
  joined_at: string | null;
  points: number;
  current_streak: number;
  badges: string[];
  certificates: { title: string; type: string; grade: number | null; issued_at: string; verification_uuid: string }[];
}

export interface InstructorProfile {
  name: string;
  bio: string | null;
  social_links: Record<string, string>;
  stats: { courses: number; learners: number; rating: number | null };
  courses: { title: string; slug: string; cover_image: string | null; rating: number | null }[];
}

export interface CourseProgress {
  enrolled: boolean;
  status?: string;
  percent: number;
  completed?: boolean;
  lessons: { lesson_id: number; video_position: number; completed: boolean }[];
}

export type AssistantMode = 'off' | 'rules' | 'claude';

export interface AssistantReply {
  text: string;
  sources: string[];
  suggestions: string[];
}

export interface AssistantTurn {
  role: 'user' | 'assistant';
  content: string;
  meta?: { sources?: string[]; suggestions?: string[] } | null;
}

export interface ActivityLogRow {
  id: number;
  event: string;
  causer: string | null;
  subject_type: string | null;
  subject_id: number | null;
  properties: Record<string, unknown> | null;
  created_at: string | null;
}

export interface SiteContentField {
  key: string;
  group: string;
  type: 'text' | 'textarea' | 'color' | 'image' | 'url';
  label: string;
  value: string | null;
}

export interface ContactMessageItem {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  subject: string;
  message: string;
  status: 'new' | 'handled';
  created_at: string | null;
}

// ---- Studio v2: authoring & assessments ----

export type LessonKind = 'video' | 'article' | 'image' | 'file' | 'live';

export interface LessonAuthoring {
  id: number;
  section_id: number;
  title: string;
  type: LessonKind;
  content: string | null;
  transcript: string | null;
  asset_path: string | null;
  video_provider: string | null;
  video_id: string | null;
  video_status: string;
  position: number;
  is_free_preview: boolean;
}

export interface LessonContent {
  id: number;
  title: string;
  type: LessonKind;
  content: string | null;
  asset_path: string | null;
  transcript: string | null;
}

export type QuestionKind = 'mcq' | 'true_false' | 'short_answer';

export interface QuestionChoice { id: string; text: string }

export interface BankQuestion {
  id: number;
  type: QuestionKind;
  body: string;
  choices: QuestionChoice[] | null;
  correct?: unknown;
  points: number;
}

export interface QuizItem {
  id: number;
  course_id: number;
  section_id: number | null;
  title: string;
  time_limit_minutes: number | null;
  shuffle: boolean;
  max_attempts: number | null;
  pass_mark: number;
  weight: number;
  questions_count?: number;
}

export interface AssignmentItem {
  id: number;
  course_id: number;
  section_id: number | null;
  title: string;
  description: string | null;
  due_at: string | null;
  points: number;
  weight: number;
}

export interface InstructorOption { id: number; name: string; email: string }

// ---- Admin tools ----

export interface ImportSummary {
  created: number;
  skipped: number;
  enrolled: number;
  errors: { line: number; message: string }[];
}

export interface EnrollmentCodeItem {
  id: number;
  course_id: number;
  code: string;
  max_uses: number | null;
  used_count: number;
  expires_at: string | null;
  is_expired: boolean;
  is_exhausted: boolean;
  created_at: string | null;
}

export interface RedeemResult {
  slug: string;
  title: string;
}

// ---- NELC compliance ----

export interface SurveyAnswers {
  overall: number;
  content_quality: number;
  instructor_quality: number;
  platform_quality: number;
  comment?: string | null;
}

export interface SurveyAverages {
  overall: number;
  content_quality: number;
  instructor_quality: number;
  platform_quality: number;
}

export interface SurveyComment {
  comment: string;
  created_at: string | null;
}

export interface SurveyCourseSummary {
  course_id: number;
  title: string;
  slug: string;
  count: number;
  averages: SurveyAverages;
}

export interface SurveyDetailSummary {
  averages: SurveyAverages;
  count: number;
  comments: SurveyComment[];
}

// ---- C1: Gradebook للمعلّم (instructor gradebook) ----

/** عمود واحد في جدول الدرجات: اختبار أو واجب */
export interface GradebookColumn {
  key: string;           // "quiz:3" | "assignment:5"
  type: 'quiz' | 'assignment';
  id: number;
  title: string;
  pass_mark: number | null;
  weight: number;
}

/** خلية درجة طالب في تقييم معيّن */
export interface GradebookCell {
  score: number | null;  // نسبة 0–100 أو null (لم يُرصد/لم يُسلَّم/لم يُصحَّح)
  passed: boolean;
}

/** صف طالب واحد في جدول الدرجات */
export interface GradebookRow {
  user_id: number;
  name: string;
  enrollment_status: 'active' | 'completed';
  overall: number | null;           // الدرجة الكلية الموزونة أو null إن لا تقييمات
  passed: boolean;
  cells: Record<string, GradebookCell>; // مفاتيحها = column.key
}

/** استجابة GET /api/v1/assessment/courses/{slug}/gradebook — حقل data */
export interface InstructorGradebook {
  course: { id: number; title: string; passing_grade: number };
  columns: GradebookColumn[];
  rows: GradebookRow[];
}
