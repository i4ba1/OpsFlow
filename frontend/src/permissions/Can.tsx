import { useAuth } from '../auth/auth-context';

export function usePermission() {
  const { hasPermission } = useAuth();

  return { hasPermission };
}

/**
 * Render children only when the current user holds the required permission.
 * Route guards + backend policies remain authoritative (PRD §42).
 */
export function Can({
  permission,
  children,
  fallback = null,
}: {
  permission: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}) {
  const { hasPermission } = useAuth();

  return hasPermission(permission) ? children : fallback;
}
