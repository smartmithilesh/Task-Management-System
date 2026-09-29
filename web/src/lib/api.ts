export type User = {
  id: string;
  name: string;
  email: string;
  email_verified_at: string | null;
  organization_id: string | null;
  department_id: string | null;
  phone: string | null;
  employee_number: string | null;
  status: 'active' | 'inactive';
  timezone: string;
  language: string;
};

export type UserInput = {
  name: string;
  email: string;
  phone?: string;
  employee_number?: string;
  department_id?: string;
  timezone?: string;
  language?: string;
};

export type OrganizationInput = {
  name: string;
  website?: string;
  timezone?: string;
  language?: string;
};

type ApiEnvelope<T> = {
  data: T;
  message?: string;
};

type LaravelError = {
  message?: string;
  errors?: Record<string, string[]>;
};

async function responseError(response: Response): Promise<Error> {
  let payload: LaravelError = {};

  try {
    payload = await response.json();
  } catch {
    // The server can return an HTML error page if Laravel is misconfigured.
  }

  const firstFieldError = Object.values(payload.errors ?? {}).flat()[0];

  if (response.status === 401) {
    return new Error('Your session has expired. Sign in again to continue.');
  }

  if (response.status === 403) {
    return new Error(payload.message ?? 'You do not have permission to do that.');
  }

  return new Error(firstFieldError ?? payload.message ?? 'The request could not be completed.');
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`/api/v1${path}`, {
    ...init,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
      ...init?.headers,
    },
  });

  if (!response.ok) {
    throw await responseError(response);
  }

  return response.json() as Promise<T>;
}

async function csrfToken(): Promise<string> {
  const envelope = await request<ApiEnvelope<{ csrf_token: string }>>('/auth/csrf-token');

  return envelope.data.csrf_token;
}

export async function currentUser(): Promise<User> {
  const envelope = await request<ApiEnvelope<User>>('/auth/user');

  return envelope.data;
}

export async function signIn(email: string, password: string): Promise<void> {
  const token = await csrfToken();

  await request('/auth/login', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': token },
    body: JSON.stringify({ email, password }),
  });
}

export async function signOut(): Promise<void> {
  const token = await csrfToken();

  await request('/auth/logout', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': token },
  });
}

export async function requestPasswordReset(email: string): Promise<void> {
  await apiRequest('/auth/password/forgot', { method: 'POST', body: JSON.stringify({ email }) });
}

export async function resetPassword(input: { token: string; email: string; password: string; password_confirmation: string }): Promise<void> {
  await apiRequest('/auth/password/reset', { method: 'POST', body: JSON.stringify(input) });
}

export async function updateProfile(input: Pick<UserInput, 'name' | 'phone' | 'timezone' | 'language'>): Promise<User> {
  const token = await csrfToken();
  const envelope = await request<ApiEnvelope<User>>('/auth/user', {
    method: 'PATCH',
    headers: { 'X-CSRF-TOKEN': token },
    body: JSON.stringify(input),
  });

  return envelope.data;
}

export async function listUsers(search = ''): Promise<{ data: User[]; meta: { current_page: number; last_page: number; total: number } }> {
  const query = search ? `?search=${encodeURIComponent(search)}` : '';

  return request(`/users${query}`);
}

export async function createUser(input: UserInput & { password: string; password_confirmation: string }): Promise<User> {
  const token = await csrfToken();
  const envelope = await request<ApiEnvelope<User>>('/users', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': token },
    body: JSON.stringify(input),
  });

  return envelope.data;
}

export async function createOrganization(input: OrganizationInput): Promise<void> {
  const token = await csrfToken();
  await request('/organization', {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': token },
    body: JSON.stringify(input),
  });
}

export async function apiRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const method = (init?.method ?? 'GET').toUpperCase();
  const headers = new Headers(init?.headers);
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) headers.set('X-CSRF-TOKEN', await csrfToken());
  return request<T>(path, { ...init, headers });
}

export async function uploadTaskAttachment(taskId: string, file: File): Promise<void> {
  const token = await csrfToken();
  const formData = new FormData();
  formData.append('file', file);
  const response = await fetch(`/api/v1/tasks/${taskId}/attachments`, {
    method: 'POST',
    credentials: 'include',
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
    body: formData,
  });
  if (!response.ok) throw await responseError(response);
}
