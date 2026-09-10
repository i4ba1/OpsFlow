import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';

import { requestKeys } from '../api/query-keys';
import { getEcho } from './echo';

/**
 * Subscribe to a service request's private channel and refresh its cached
 * detail/history/comments/attachments whenever the server broadcasts a change
 * (PRD §34). The event payload carries only the resource identity; the
 * database stays the source of truth and the client re-fetches.
 */
export function useRequestChannel(requestId: string | undefined): void {
  const queryClient = useQueryClient();

  useEffect(() => {
    if (requestId === undefined || requestId === '') {
      return;
    }

    const echo = getEcho();
    const channelName = `request.${requestId}`;
    const channel = echo.private(channelName);

    const invalidate = () => {
      void queryClient.invalidateQueries({ queryKey: requestKeys.detail(requestId) });
      void queryClient.invalidateQueries({ queryKey: requestKeys.lists() });
    };

    channel.listen('.request.updated', invalidate);
    channel.listen('.request.assigned', invalidate);
    channel.listen('.comment.created', invalidate);

    return () => {
      channel.stopListening('.request.updated');
      channel.stopListening('.request.assigned');
      channel.stopListening('.comment.created');
      echo.leave(channelName);
    };
  }, [requestId, queryClient]);
}
