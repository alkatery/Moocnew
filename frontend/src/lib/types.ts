export interface Course {
  id: number;
  title: string;
  slug: string;
  summary: string | null;
  description: string | null;
  status: string;
  pricing_type: 'free' | 'one_time' | 'subscription';
  price_minor: number;
  sections?: Section[];
  instructor?: { id: number; name: string };
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
  type: 'video' | 'article' | 'file' | 'live';
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
}

export interface Paginated<T> {
  data: T[];
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
