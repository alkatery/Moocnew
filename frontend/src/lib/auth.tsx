'use client';

import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { api, getToken, setToken } from './api';
import type { AuthUser } from './types';

const ADMIN_TOKEN_KEY = 'mooc_admin_token';
const IMPERSONATING_KEY = 'mooc_impersonating';

interface AuthState {
  user: AuthUser | null;
  loading: boolean;
  impersonating: string | null;
  login: (email: string, password: string) => Promise<void>;
  register: (payload: RegisterPayload) => Promise<void>;
  adoptSession: (token: string) => Promise<void>;
  refresh: () => Promise<void>;
  logout: () => Promise<void>;
  impersonate: (token: string, name: string) => Promise<void>;
  stopImpersonating: () => Promise<void>;
}

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  consents: string[];
}

const AuthContext = createContext<AuthState | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [impersonating, setImpersonating] = useState<string | null>(null);

  const loadMe = useCallback(async () => {
    try {
      const res = await api<{ user: AuthUser }>('/auth/me');
      setUser(res.user);
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (typeof window !== 'undefined') {
      setImpersonating(window.localStorage.getItem(IMPERSONATING_KEY));
    }
    void loadMe();
  }, [loadMe]);

  const login = useCallback(async (email: string, password: string) => {
    const res = await api<{ token: string; user: AuthUser }>('/auth/login', {
      method: 'POST',
      body: { email, password },
      auth: false,
    });
    setToken(res.token);
    setUser(res.user);
  }, []);

  // Registration no longer logs the user in: the account stays unverified
  // until the email link is followed, so no token is issued here.
  const register = useCallback(async (payload: RegisterPayload) => {
    await api<{ message: string; user: AuthUser }>('/auth/register', {
      method: 'POST',
      body: payload,
      auth: false,
    });
  }, []);

  // Adopt a token handed back by the email-verification endpoint so the user
  // lands already signed in.
  const adoptSession = useCallback(async (token: string) => {
    setToken(token);
    setLoading(true);
    await loadMe();
  }, [loadMe]);

  const logout = useCallback(async () => {
    try {
      await api('/auth/logout', { method: 'POST' });
    } catch {
      // ignore
    }
    window.localStorage.removeItem(ADMIN_TOKEN_KEY);
    window.localStorage.removeItem(IMPERSONATING_KEY);
    setImpersonating(null);
    setToken(null);
    setUser(null);
  }, []);

  // Switch the session to the target user's token, stashing the admin's own
  // token so it can be restored when impersonation ends.
  const impersonate = useCallback(async (token: string, name: string) => {
    const adminToken = getToken();
    if (adminToken) window.localStorage.setItem(ADMIN_TOKEN_KEY, adminToken);
    window.localStorage.setItem(IMPERSONATING_KEY, name);
    setImpersonating(name);
    setToken(token);
    setLoading(true);
    await loadMe();
  }, [loadMe]);

  const stopImpersonating = useCallback(async () => {
    const adminToken = window.localStorage.getItem(ADMIN_TOKEN_KEY);
    window.localStorage.removeItem(ADMIN_TOKEN_KEY);
    window.localStorage.removeItem(IMPERSONATING_KEY);
    setImpersonating(null);
    setToken(adminToken);
    setLoading(true);
    await loadMe();
  }, [loadMe]);

  return (
    <AuthContext.Provider
      value={{ user, loading, impersonating, login, register, adoptSession, refresh: loadMe, logout, impersonate, stopImpersonating }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthState {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used within AuthProvider');
  return ctx;
}
