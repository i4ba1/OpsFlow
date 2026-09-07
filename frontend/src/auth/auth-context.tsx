import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import type { PropsWithChildren } from 'react';

import { normalizeApiError } from '../api/errors';
import type { User } from '../types/api';
import { fetchMe, loginRequest, logoutRequest } from './auth-api';

const TOKEN_KEY = 'opsflow_token';

type AuthContextValue = {
  user: User | null;
  isAuthenticated: boolean;
  isInitializing: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  hasPermission: (permission: string) => boolean;
  can: (permission: string) => boolean;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: PropsWithChildren) {
  const [user, setUser] = useState<User | null>(null);
  // Initializing only matters when a token is present and being validated.
  const [isInitializing, setIsInitializing] = useState(
    () => localStorage.getItem(TOKEN_KEY) !== null,
  );

  useEffect(() => {
    const token = localStorage.getItem(TOKEN_KEY);

    if (!token) {
      return;
    }

    let active = true;

    // Restore session by fetching the current user.
    fetchMe()
      .then((currentUser) => {
        if (active) {
          setUser(currentUser);
        }
      })
      .catch(() => {
        localStorage.removeItem(TOKEN_KEY);
      })
      .finally(() => {
        if (active) {
          setIsInitializing(false);
        }
      });

    return () => {
      active = false;
    };
  }, []);

  const value = useMemo<AuthContextValue>(() => {
    const permissions = user?.permissions ?? [];

    return {
      user,
      isAuthenticated: user !== null,
      isInitializing,
      login: async (email, password) => {
        const { token, user: loggedInUser } = await loginRequest(email, password);
        localStorage.setItem(TOKEN_KEY, token);
        setUser(loggedInUser);
      },
      logout: async () => {
        try {
          await logoutRequest();
        } catch (error) {
          // Ignore network failures on logout; local state must still clear.
          normalizeApiError(error);
        } finally {
          localStorage.removeItem(TOKEN_KEY);
          setUser(null);
        }
      },
      hasPermission: (permission) => permissions.includes(permission),
      can: (permission) => permissions.includes(permission),
    };
  }, [user, isInitializing]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (context === null) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}
