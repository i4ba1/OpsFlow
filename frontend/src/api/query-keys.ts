export const customerKeys = {
  all: ['customers'] as const,
  lists: () => [...customerKeys.all, 'list'] as const,
  list: (filters: unknown) => [...customerKeys.lists(), filters] as const,
  details: () => [...customerKeys.all, 'detail'] as const,
  detail: (id: string) => [...customerKeys.details(), id] as const,
  contacts: (id: string) => [...customerKeys.detail(id), 'contacts'] as const,
};

export const projectKeys = {
  all: ['projects'] as const,
  lists: () => [...projectKeys.all, 'list'] as const,
  list: (filters: unknown) => [...projectKeys.lists(), filters] as const,
  details: () => [...projectKeys.all, 'detail'] as const,
  detail: (id: string) => [...projectKeys.details(), id] as const,
};

export const requestKeys = {
  all: ['service-requests'] as const,
  lists: () => [...requestKeys.all, 'list'] as const,
  list: (filters: unknown) => [...requestKeys.lists(), filters] as const,
  details: () => [...requestKeys.all, 'detail'] as const,
  detail: (id: string) => [...requestKeys.details(), id] as const,
  history: (id: string) => [...requestKeys.detail(id), 'history'] as const,
};

export const slaKeys = {
  all: ['sla-policies'] as const,
  lists: () => [...slaKeys.all, 'list'] as const,
  list: (filters: unknown) => [...slaKeys.lists(), filters] as const,
  details: () => [...slaKeys.all, 'detail'] as const,
  detail: (id: string) => [...slaKeys.details(), id] as const,
};

export const calendarKeys = {
  all: ['business-calendars'] as const,
  lists: () => [...calendarKeys.all, 'list'] as const,
};
