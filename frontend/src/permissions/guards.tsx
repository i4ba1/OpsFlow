import { Navigate, Outlet, useLocation } from 'react-router-dom';

import { useAuth } from '../auth/auth-context';

/**
 * Route-level guard: redirects unauthenticated users to /login, preserving
 * the intended destination.
 */
export function RequireAuth() {
  const { isAuthenticated, isInitializing } = useAuth();
  const location = useLocation();

  if (isInitializing) {
    return null; // Auth provider is restoring the session.
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />;
  }

  return <Outlet />;
}

/**
 * Route-level permission guard: keeps the shell but blocks entry to areas the
 * user cannot access (UX layer only; API policies enforce server-side).
 */
export function RequirePermission({ permission }: { permission: string }) {
  const { hasPermission, isAuthenticated, isInitializing } = useAuth();

  if (isInitializing || !isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (!hasPermission(permission)) {
    return <Navigate to="/app" replace />;
  }

  return <Outlet />;
}
