import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Badge,
  Box,
  Button,
  Divider,
  List,
  ListItemButton,
  ListItemText,
  Popover,
  Stack,
  Typography,
} from '@mui/material';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import type { AppNotification } from '../../types/features';
import { notificationKeys } from '../../api/query-keys';
import {
  fetchNotifications,
  fetchUnreadCount,
  markAllNotificationsRead,
  markNotificationRead,
} from './notification-api';
import { notificationLabel, notificationLink, notificationMessage } from './notification-utils';

/**
 * Toolbar bell: unread badge plus a popover of the latest notifications. The
 * unread count is refreshed by the RealtimeProvider subscription and by a slow
 * poll as a fallback when the socket is unavailable.
 */
export function NotificationsBell() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [anchorEl, setAnchorEl] = useState<HTMLElement | null>(null);

  const unread = useQuery({
    queryKey: notificationKeys.unreadCount(),
    queryFn: fetchUnreadCount,
    refetchInterval: 60_000,
  });

  const open = anchorEl !== null;

  const list = useQuery({
    queryKey: notificationKeys.list({ page: 1, per_page: 10 }),
    queryFn: () => fetchNotifications({ page: 1, per_page: 10 }),
    enabled: open,
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: notificationKeys.all });
  };

  const markRead = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: invalidate,
  });

  const markAll = useMutation({
    mutationFn: () => markAllNotificationsRead(),
    onSuccess: invalidate,
  });

  const handleOpen = (notification: AppNotification) => {
    if (!notification.read) {
      markRead.mutate(notification.id);
    }

    setAnchorEl(null);
    navigate(notificationLink(notification));
  };

  const count = unread.data ?? 0;

  return (
    <>
      <Badge badgeContent={count} color="error">
        <Button color="inherit" onClick={(event) => setAnchorEl(event.currentTarget)}>
          Notifications
        </Button>
      </Badge>

      <Popover
        open={open}
        anchorEl={anchorEl}
        onClose={() => setAnchorEl(null)}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        transformOrigin={{ vertical: 'top', horizontal: 'right' }}
      >
        <Box sx={{ width: 360 }}>
          <Stack
            direction="row"
            sx={{ alignItems: 'center', justifyContent: 'space-between', px: 2, py: 1 }}
          >
            <Typography variant="subtitle2">Notifications</Typography>
            <Button
              size="small"
              disabled={count === 0 || markAll.isPending}
              onClick={() => markAll.mutate()}
            >
              Mark all read
            </Button>
          </Stack>
          <Divider />

          <List dense sx={{ maxHeight: 400, overflowY: 'auto' }}>
            {(list.data?.data ?? []).map((notification) => (
              <ListItemButton
                key={notification.id}
                onClick={() => handleOpen(notification)}
                sx={{ alignItems: 'flex-start' }}
              >
                <ListItemText
                  primary={notificationMessage(notification)}
                  secondary={`${notificationLabel(notification.type)} · ${new Date(
                    notification.created_at,
                  ).toLocaleString()}`}
                  slotProps={{
                    primary: { sx: { fontWeight: notification.read ? 400 : 700 } },
                  }}
                />
              </ListItemButton>
            ))}
            {list.data != null && list.data.data.length === 0 && (
              <Typography variant="body2" color="text.secondary" sx={{ px: 2, py: 1 }}>
                You are all caught up.
              </Typography>
            )}
          </List>

          <Divider />
          <Box sx={{ p: 1, textAlign: 'center' }}>
            <Button
              size="small"
              onClick={() => {
                setAnchorEl(null);
                navigate('/app/notifications');
              }}
            >
              View all
            </Button>
          </Box>
        </Box>
      </Popover>
    </>
  );
}
