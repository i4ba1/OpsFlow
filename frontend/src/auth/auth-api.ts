import type { User } from '../types/api';
import { api } from '../api/client';

type LoginResponse = {
  data: {
    token: string;
    user: User;
  };
};

export async function loginRequest(
  email: string,
  password: string,
): Promise<LoginResponse['data']> {
  const response = await api.post<LoginResponse>('/auth/login', { email, password });
  return response.data.data;
}

export async function fetchMe(): Promise<User> {
  const response = await api.get<{ data: User }>('/me');
  return response.data.data;
}

export async function logoutRequest(): Promise<void> {
  await api.post('/auth/logout');
}
