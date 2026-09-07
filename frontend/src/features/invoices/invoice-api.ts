import type { Invoice, Paginated } from '../../types/features';
import { api } from '../../api/client';

export type InvoiceFilters = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: Invoice['status'][];
  customer_id?: string;
  sort?: string;
};

export async function fetchInvoices(filters: InvoiceFilters): Promise<Paginated<Invoice>> {
  const response = await api.get<Paginated<Invoice>>('/invoices', { params: filters });
  return response.data;
}

export async function fetchInvoice(id: string): Promise<Invoice> {
  const response = await api.get<{ data: Invoice }>(`/invoices/${id}`);
  return response.data.data;
}

export type InvoiceLinePayload = {
  description: string;
  quantity: number;
  unit_price: number;
};

export type CreateInvoicePayload = {
  customer_id: string;
  project_id?: string | null;
  due_at?: string | null;
  notes?: string | null;
  items: InvoiceLinePayload[];
};

export async function createInvoice(payload: CreateInvoicePayload): Promise<Invoice> {
  const response = await api.post<{ data: Invoice }>('/invoices', payload);
  return response.data.data;
}

export async function issueInvoice(id: string): Promise<Invoice> {
  const response = await api.post<{ data: Invoice }>(`/invoices/${id}/issue`);
  return response.data.data;
}

export async function markInvoicePaid(id: string): Promise<Invoice> {
  const response = await api.post<{ data: Invoice }>(`/invoices/${id}/mark-paid`);
  return response.data.data;
}

export async function voidInvoice(id: string): Promise<Invoice> {
  const response = await api.post<{ data: Invoice }>(`/invoices/${id}/void`);
  return response.data.data;
}
