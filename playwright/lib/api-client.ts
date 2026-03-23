import { APIRequestContext, expect } from '@playwright/test';

export const BASE = (process.env.API_BASE_URL ?? 'http://127.0.0.1:8000').replace('localhost', '127.0.0.1');
export const V1   = `${BASE}/api/v1`;

// ── Types ─────────────────────────────────────────────────────────────────────

export interface ApiResponse<T = unknown> {
  success: boolean;
  message: string;
  request_id?: string;
  data?: T;
  errors?: Record<string, string[]>;
  meta?: PaginationMeta;
  links?: PaginationLinks;
}

export interface PaginationMeta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  has_more: boolean;
}

export interface PaginationLinks {
  first: string;
  last: string;
  prev: string | null;
  next: string | null;
}

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: string;
  phone_number?: string;
}

export interface AuthResponse {
  success: boolean;
  message: string;
  token: string;
  user: AuthUser;
}

// ── Client ────────────────────────────────────────────────────────────────────

export class ApiClient {
  private token: string | null = null;

  constructor(private request: APIRequestContext) {}

  // ── Auth ──────────────────────────────────────────────────────────────────

  async register(payload: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
    phone_number?: string;
  }): Promise<AuthResponse> {
    const res = await this.request.post(`${V1}/auth/register`, { data: payload });
    const body = await res.json() as AuthResponse;
    if (body.token) this.token = body.token;
    return body;
  }

  async login(email: string, password: string): Promise<AuthResponse> {
    const res = await this.request.post(`${V1}/auth/login`, {
      data: { email, password },
    });
    const body = await res.json() as AuthResponse;
    if (body.token) this.token = body.token;
    return body;
  }

  setToken(token: string): void {
    this.token = token;
  }

  getToken(): string | null {
    return this.token;
  }

  // ── Generic request helpers ───────────────────────────────────────────────

  private authHeaders(): Record<string, string> {
    return this.token ? { Authorization: `Bearer ${this.token}` } : {};
  }

  async get<T = unknown>(path: string, params?: Record<string, string>): Promise<{ status: number; body: ApiResponse<T> }> {
    const url = params
      ? `${V1}${path}?${new URLSearchParams(params).toString()}`
      : `${V1}${path}`;

    const res = await this.request.get(url, { headers: this.authHeaders() });
    return { status: res.status(), body: await res.json() };
  }

  async post<T = unknown>(path: string, data?: unknown, extraHeaders?: Record<string, string>): Promise<{ status: number; body: ApiResponse<T> }> {
    const res = await this.request.post(`${V1}${path}`, {
      data,
      headers: { ...this.authHeaders(), ...extraHeaders },
    });
    return { status: res.status(), body: await res.json() };
  }

  async patch<T = unknown>(path: string, data?: unknown): Promise<{ status: number; body: ApiResponse<T> }> {
    const res = await this.request.patch(`${V1}${path}`, {
      data,
      headers: this.authHeaders(),
    });
    return { status: res.status(), body: await res.json() };
  }

  async put<T = unknown>(path: string, data?: unknown): Promise<{ status: number; body: ApiResponse<T> }> {
    const res = await this.request.put(`${V1}${path}`, {
      data,
      headers: this.authHeaders(),
    });
    return { status: res.status(), body: await res.json() };
  }

  async delete(path: string): Promise<{ status: number; body: ApiResponse }> {
    const res = await this.request.delete(`${V1}${path}`, {
      headers: this.authHeaders(),
    });
    return { status: res.status(), body: await res.json() };
  }
}

// ── Assertion helpers ─────────────────────────────────────────────────────────

export function assertSuccess(body: ApiResponse, expectedStatus = 200): void {
  expect(body.success).toBe(true);
}

export function assertError(body: ApiResponse): void {
  expect(body.success).toBe(false);
}

export function assertPaginated(body: ApiResponse): void {
  expect(body.success).toBe(true);
  expect(body.meta).toBeDefined();
  expect(typeof body.meta!.total).toBe('number');
  expect(typeof body.meta!.per_page).toBe('number');
  expect(typeof body.meta!.current_page).toBe('number');
  expect(body.links).toBeDefined();
}

// ── Seed helpers ──────────────────────────────────────────────────────────────

let _counter = Date.now();
export function uid(): string {
  return (++_counter).toString(36);
}

export function makeEmail(): string {
  return `test_${uid()}@lesgo-test.ph`;
}
