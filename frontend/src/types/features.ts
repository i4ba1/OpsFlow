export type PaginationMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  request_id: string;
};

export type Paginated<T> = {
  data: T[];
  meta: PaginationMeta;
};

export type CustomerStatus = 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';

export type Customer = {
  id: string;
  customer_code: string;
  name: string;
  legal_name: string | null;
  registration_number: string | null;
  email: string | null;
  phone: string | null;
  billing_email: string | null;
  city: string | null;
  country_code: string;
  status: CustomerStatus;
  contacts_count?: number;
  archived_at: string | null;
  created_at: string;
};

export type CustomerContact = {
  id: string;
  customer_id: string;
  user_id: string | null;
  name: string;
  email: string;
  phone: string | null;
  job_title: string | null;
  is_primary: boolean;
};

export type ProjectStatus = 'DRAFT' | 'ACTIVE' | 'ON_HOLD' | 'COMPLETED' | 'ARCHIVED';

export type Project = {
  id: string;
  project_code: string;
  name: string;
  description: string | null;
  status: ProjectStatus;
  starts_on: string | null;
  ends_on: string | null;
  customer?: { id: string; name: string } | null;
  manager?: { id: string; name: string } | null;
  archived_at: string | null;
  created_at: string;
};

export type CustomerFilters = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: CustomerStatus[];
  sort?: string;
};

export type ProjectFilters = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: ProjectStatus[];
  customer_id?: string;
  sort?: string;
};

export type ServiceRequestStatus =
  | 'NEW'
  | 'TRIAGED'
  | 'IN_PROGRESS'
  | 'WAITING_CUSTOMER'
  | 'WAITING_INTERNAL'
  | 'ESCALATED'
  | 'RESOLVED'
  | 'CLOSED'
  | 'CANCELLED';

export type ServiceRequestPriority = 'CRITICAL' | 'HIGH' | 'MEDIUM' | 'LOW';

export type ServiceRequest = {
  id: string;
  request_number: string;
  title: string;
  description: string;
  status: ServiceRequestStatus;
  priority: ServiceRequestPriority;
  customer: { id: string; name: string } | null;
  project: { id: string; name: string } | null;
  assignee: { id: string; name: string } | null;
  category: { id: string; name: string; color: string | null } | null;
  requested_by: { id: string; name: string };
  created_at: string;
  updated_at: string;
  version: number;
  first_response_due_at: string | null;
  resolution_due_at: string | null;
  resolved_at: string | null;
  closed_at: string | null;
};

export type ServiceRequestEvent = {
  id: string;
  type: 'STATUS_CHANGED' | 'ASSIGNED' | 'PRIORITY_CHANGED' | 'COMMENT' | 'ATTACHMENT';
  actor: { id: string; name: string } | null;
  from_value: string | null;
  to_value: string | null;
  note: string | null;
  created_at: string;
};

export type RequestFilters = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: ServiceRequestStatus[];
  priority?: ServiceRequestPriority[];
  assigned_to?: string;
  customer_id?: string;
  project_id?: string;
  sort?: string;
};

export type Comment = {
  id: string;
  service_request_id: string;
  body: string;
  visibility: 'PUBLIC' | 'INTERNAL';
  author: { id: string; name: string } | null;
  created_at: string;
  updated_at: string;
};

export type Attachment = {
  id: string;
  service_request_id: string | null;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  created_at: string;
};

export type AppNotification = {
  id: string;
  type: string;
  data: Record<string, unknown> | null;
  read_at: string | null;
  read: boolean;
  actor: { id: string; name: string } | null;
  resource: { type: string; id: string } | null;
  created_at: string;
};

export type SlaState = 'HEALTHY' | 'AT_RISK' | 'BREACHED' | 'MET' | null;

export type SlaTarget = {
  id: string;
  priority: ServiceRequestPriority;
  first_response_minutes: number;
  resolution_minutes: number;
  warning_threshold_percent: number;
};

export type SlaPolicy = {
  id: string;
  name: string;
  description: string | null;
  timezone: string;
  business_calendar_id: string | null;
  active: boolean;
  targets: SlaTarget[];
  created_at: string;
};

export type BusinessCalendar = {
  id: string;
  name: string;
  description: string | null;
  timezone: string;
  hours: { id: string; day_of_week: number; opens_at: string; closes_at: string }[];
  holidays: { id: string; name: string; date: string }[];
};
