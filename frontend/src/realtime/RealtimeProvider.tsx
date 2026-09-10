import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';
import type { PropsWithChildren } from 'react';

import { notificationKeys } from '../api/query-keys';
import { useAuth } from '../auth/auth-context';
import { disconnectEcho, getEcho } from './echo';

/**
 * Owns the Reverb connection lifecycle. While authenticated it subscribes to
 * the current user's private channel and refreshes the notification cache when
 * the server pushes `notification.created` (PRD §34).
 */
export function RealtimeProvider({ children }: PropsWithChildren) {
  const { user, isAuthenticated } = useAuth();
  const queryClient = useQueryClient();

  useEffect(() => {
    if (!isAuthenticated || user === null) {
      disconnectEcho();
      return;
    }

    const echo = getEcho();
    const channelName = `user.${user.id}`;
    const channel = echo.private(channelName);

    channel.listen('.notification.created', () => {
      void queryClient.invalidateQueries({ queryKey: notificationKeys.all });
    });

    return () => {
      channel.stopListening('.notification.created');
      echo.leave(channelName);
    };
  }, [isAuthenticated, user, queryClient]);

  return <>{children}</>;
}
