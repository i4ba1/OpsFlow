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
