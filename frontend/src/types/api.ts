export type OrganizationStatus = 'ACTIVE' | 'SUSPENDED' | 'INACTIVE';

export type Organization = {
  id: string;
  name: string;
  slug: string;
  legal_name: string | null;
  country_code: string;
  timezone: string;
  currency: string;
  status: OrganizationStatus;
};

export type Role = {
  id: string;
  name: string;
  slug: string;
  description: string | null;
  is_system: boolean;
  permissions?: string[];
};

export type User = {
  id: string;
  name: string;
  email: string;
  status: 'INVITED' | 'ACTIVE' | 'DISABLED';
  timezone: string;
  organization?: Organization;
  roles?: Role[];
  permissions?: string[];
};
