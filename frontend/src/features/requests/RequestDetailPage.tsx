import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Button,
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
import { useState } from 'react';
import { useParams } from 'react-router-dom';

import { requestKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import { useRequestChannel } from '../../realtime/useRequestChannel';
import { AttachmentsSection } from './AttachmentsSection';
import { CommentsSection } from './CommentsSection';
import { RequestActionsPanel } from './RequestActionsPanel';
import { RequestApprovalDialog } from './RequestApprovalDialog';
import { STATUS_LABEL } from './request-utils';
import { fetchRequest, fetchRequestHistory } from './request-api';

export function RequestDetailPage() {
  const { requestId = '' } = useParams();
  const { hasPermission } = usePermission();
  const [approvalOpen, setApprovalOpen] = useState(false);

  useRequestChannel(requestId || undefined);

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
          {hasPermission('approval.request') && (
            <Button size="small" variant="outlined" onClick={() => setApprovalOpen(true)}>
              Request approval
            </Button>
          )}
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

        <Box sx={{ width: 320 }}>
          <Stack spacing={2}>
            <RequestActionsPanel request={request} />

            <Card variant="outlined">
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
        </Box>
      </Stack>

      <CommentsSection serviceRequestId={request.id} />

      <AttachmentsSection serviceRequestId={request.id} />

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
                        ? `${event.data.from ?? '—'} → ${event.data.to ?? '—'}`
                        : event.type
                    }
                    secondary={`${event.actor?.name ?? 'System'} · ${new Date(event.occurred_at).toLocaleString()}`}
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

      <RequestApprovalDialog
        serviceRequestId={request.id}
        open={approvalOpen}
        onClose={() => setApprovalOpen(false)}
      />
    </Stack>
  );
}
