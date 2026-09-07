import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Divider,
  List,
  ListItem,
  ListItemText,
  Stack,
  Typography,
} from '@mui/material';
import { useParams } from 'react-router-dom';

import type { ServiceRequestStatus } from '../../types/features';
import { requestKeys } from '../../api/query-keys';
import { fetchRequest, fetchRequestHistory } from './request-api';

const STATUS_LABEL: Record<ServiceRequestStatus, string> = {
  NEW: 'New',
  TRIAGED: 'Triaged',
  IN_PROGRESS: 'In progress',
  WAITING_CUSTOMER: 'Waiting on customer',
  WAITING_INTERNAL: 'Waiting on internal',
  ESCALATED: 'Escalated',
  RESOLVED: 'Resolved',
  CLOSED: 'Closed',
  CANCELLED: 'Cancelled',
};

export function RequestDetailPage() {
  const { requestId = '' } = useParams();

  const requestQuery = useQuery({
    queryKey: requestKeys.detail(requestId),
    queryFn: () => fetchRequest(requestId),
    enabled: requestId !== '',
  });

  const historyQuery = useQuery({
    queryKey: requestKeys.history(requestId),
    queryFn: () => fetchRequestHistory(requestId),
    enabled: requestId !== '',
  });

  if (requestQuery.isPending) {
    return <CircularProgress />;
  }

  if (requestQuery.error != null || requestQuery.data == null) {
    return <Alert severity="error">Unable to load this request.</Alert>;
  }

  const request = requestQuery.data;

  return (
    <Stack spacing={3}>
      <Box>
        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
          <Typography variant="h4" component="h1">
            {request.request_number}
          </Typography>
          <Chip label={request.priority} size="small" color="warning" />
          <Chip label={STATUS_LABEL[request.status]} size="small" />
        </Stack>
        <Typography variant="h6" color="text.secondary">
          {request.title}
        </Typography>
      </Box>

      <Stack direction="row" spacing={3} sx={{ alignItems: 'flex-start' }}>
        <Card variant="outlined" sx={{ flexGrow: 1 }}>
          <CardContent>
            <Typography variant="body1" sx={{ whiteSpace: 'pre-wrap' }}>
              {request.description}
            </Typography>
          </CardContent>
        </Card>

        <Card variant="outlined" sx={{ width: 280 }}>
          <CardContent>
            <Typography variant="subtitle2" gutterBottom>
              Details
            </Typography>
            <Divider sx={{ mb: 1 }} />
            <List dense disablePadding>
              <ListItem disableGutters>
                <ListItemText primary="Customer" secondary={request.customer?.name ?? '—'} />
              </ListItem>
              <ListItem disableGutters>
                <ListItemText primary="Project" secondary={request.project?.name ?? '—'} />
              </ListItem>
              <ListItem disableGutters>
                <ListItemText
                  primary="Assignee"
                  secondary={request.assignee?.name ?? 'Unassigned'}
                />
              </ListItem>
              <ListItem disableGutters>
                <ListItemText
                  primary="Requested by"
                  secondary={request.requested_by?.name ?? '—'}
                />
              </ListItem>
              <ListItem disableGutters>
                <ListItemText
                  primary="Created"
                  secondary={new Date(request.created_at).toLocaleString()}
                />
              </ListItem>
            </List>
          </CardContent>
        </Card>
      </Stack>

      <Card variant="outlined">
        <CardContent>
          <Typography variant="subtitle2" gutterBottom>
            Activity timeline
          </Typography>
          <Divider sx={{ mb: 1 }} />
          {historyQuery.isPending && <CircularProgress size={24} />}
          {historyQuery.data != null && (
            <List dense>
              {historyQuery.data.map((event) => (
                <ListItem key={event.id} disableGutters>
                  <ListItemText
                    primary={
                      event.type === 'STATUS_CHANGED'
                        ? `${event.from_value ?? '—'} → ${event.to_value ?? '—'}`
                        : event.type
                    }
                    secondary={`${event.actor?.name ?? 'System'} · ${new Date(event.created_at).toLocaleString()}`}
                  />
                </ListItem>
              ))}
              {historyQuery.data.length === 0 && (
                <Typography color="text.secondary">No activity recorded yet.</Typography>
              )}
            </List>
          )}
        </CardContent>
      </Card>
    </Stack>
  );
}
