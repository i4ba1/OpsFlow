import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Button,
  Chip,
  Divider,
  LinearProgress,
  List,
  ListItemButton,
  ListItemText,
  Paper,
  Stack,
  Typography,
} from '@mui/material';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { notificationKeys } from '../../api/query-keys';
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from './notification-api';
import { notificationLabel, notificationLink, notificationMessage } from './notification-utils';

const PAGE_SIZE = 20;

export function NotificationsPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [page, setPage] = useState(0);

  const list = useQuery({
    queryKey: notificationKeys.list({ page: page + 1, per_page: PAGE_SIZE }),
    queryFn: () => fetchNotifications({ page: page + 1, per_page: PAGE_SIZE }),
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

  return (
    <Stack spacing={2}>
      <Stack
        direction="row"
        spacing={2}
        sx={{ alignItems: 'center', justifyContent: 'space-between' }}
      >
        <Typography variant="h4" component="h1">
          Notifications
        </Typography>
        <Button
          variant="outlined"
          disabled={list.data == null || markAll.isPending}
          onClick={() => markAll.mutate()}
        >
          Mark all read
        </Button>
      </Stack>

      {list.isPending && <LinearProgress />}
      {list.error != null && <Alert severity="error">{list.error.message}</Alert>}

      <Paper variant="outlined">
        <List>
          {(list.data?.data ?? []).map((notification) => (
            <ListItemButton
              key={notification.id}
              alignItems="flex-start"
              onClick={() => {
                if (!notification.read) {
                  markRead.mutate(notification.id);
                }
                navigate(notificationLink(notification));
              }}
            >
              <ListItemText
                primary={notificationMessage(notification)}
                secondary={`${notificationLabel(notification.type)} · ${new Date(
                  notification.created_at,
                ).toLocaleString()}`}
                slotProps={{ primary: { sx: { fontWeight: notification.read ? 400 : 700 } } }}
              />
              {!notification.read && <Chip size="small" color="primary" label="New" />}
            </ListItemButton>
          ))}
          {list.data != null && list.data.data.length === 0 && (
            <Box sx={{ p: 3, textAlign: 'center' }}>
              <Typography color="text.secondary">You are all caught up.</Typography>
            </Box>
          )}
        </List>
        {list.data != null && (
          <>
            <Divider />
            <Stack direction="row" sx={{ p: 1, justifyContent: 'space-between' }}>
              <Button disabled={page === 0} onClick={() => setPage((p) => Math.max(0, p - 1))}>
                Previous
              </Button>
              <Typography variant="body2" color="text.secondary" sx={{ alignSelf: 'center' }}>
                Page {list.data.meta.current_page} of {list.data.meta.last_page}
              </Typography>
              <Button
                disabled={page + 1 >= list.data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                Next
              </Button>
            </Stack>
          </>
        )}
      </Paper>
    </Stack>
  );
}
