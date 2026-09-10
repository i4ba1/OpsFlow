import type { Customer, CustomerContact, CustomerFilters, Paginated } from '../../types/features';
import { api } from '../../api/client';

export async function fetchCustomers(filters: CustomerFilters): Promise<Paginated<Customer>> {
  const response = await api.get<Paginated<Customer>>('/customers', { params: filters });
  return response.data;
}

export async function fetchCustomer(id: string): Promise<Customer> {
  const response = await api.get<{ data: Customer }>(`/customers/${id}`);
  return response.data.data;
}

export type CustomerPayload = {
  name: string;
  legal_name?: string | null;
  registration_number?: string | null;
  email?: string | null;
  phone?: string | null;
  billing_email?: string | null;
  address_line_1?: string | null;
  address_line_2?: string | null;
  city?: string | null;
  postal_code?: string | null;
  country_code?: string;
};

export async function createCustomer(payload: CustomerPayload): Promise<Customer> {
  const response = await api.post<{ data: Customer }>('/customers', payload);
  return response.data.data;
}

export async function updateCustomer(id: string, payload: CustomerPayload): Promise<Customer> {
  const response = await api.patch<{ data: Customer }>(`/customers/${id}`, payload);
  return response.data.data;
}

export async function archiveCustomer(id: string): Promise<Customer> {
  const response = await api.delete<{ data: Customer }>(`/customers/${id}`);
  return response.data.data;
}

export async function fetchCustomerContacts(customerId: string): Promise<CustomerContact[]> {
  const response = await api.get<{ data: CustomerContact[] }>(`/customers/${customerId}/contacts`);
  return response.data.data;
}

export type CustomerContactPayload = {
  name: string;
  email: string;
  phone?: string | null;
  job_title?: string | null;
  is_primary?: boolean;
  user_id?: string | null;
};

export async function createCustomerContact(
  customerId: string,
  payload: CustomerContactPayload,
): Promise<CustomerContact> {
  const response = await api.post<{ data: CustomerContact }>(
    `/customers/${customerId}/contacts`,
    payload,
  );
  return response.data.data;
}
