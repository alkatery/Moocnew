import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { api, setToken, ApiError } from '@/lib/api';

describe('api client', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
  });
  afterEach(() => vi.restoreAllMocks());

  it('attaches the bearer token and parses JSON', async () => {
    setToken('tok123');
    const fetchMock = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ ok: true }), { status: 200, headers: { 'content-type': 'application/json' } }),
    );
    vi.stubGlobal('fetch', fetchMock);

    const res = await api<{ ok: boolean }>('/health');
    expect(res.ok).toBe(true);
    const [, opts] = fetchMock.mock.calls[0];
    expect((opts as RequestInit).headers).toMatchObject({ Authorization: 'Bearer tok123' });
  });

  it('throws ApiError with validation errors on 422', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'invalid', errors: { email: ['taken'] } }), { status: 422 }),
    ));

    await expect(api('/x', { method: 'POST', body: {}, auth: false })).rejects.toBeInstanceOf(ApiError);
  });

  it('captures the error code (e.g. email_unverified) from the body', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ message: 'unverified', code: 'email_unverified' }), { status: 403 }),
    ));

    await expect(
      api('/auth/login', { method: 'POST', body: {}, auth: false }),
    ).rejects.toMatchObject({ status: 403, code: 'email_unverified' });
  });
});
