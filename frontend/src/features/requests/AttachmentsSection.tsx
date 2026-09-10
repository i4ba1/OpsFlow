import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Button,
  Card,
  CardContent,
  Divider,
  List,
  ListItem,
  ListItemText,
  Stack,
  Typography,
} from '@mui/material';
import { useRef, useState } from 'react';

import { normalizeApiError } from '../../api/errors';
import { requestKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import {
  deleteAttachment,
  downloadAttachment,
  fetchAttachments,
  uploadAttachment,
} from './comment-api';

function formatBytes(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function AttachmentsSection({ serviceRequestId }: { serviceRequestId: string }) {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();
  const inputRef = useRef<HTMLInputElement>(null);
  const [localError, setLocalError] = useState<string | null>(null);

  const canUpload = hasPermission('attachment.create');
  const canDelete = hasPermission('attachment.delete');

  const attachmentsQuery = useQuery({
    queryKey: [...requestKeys.detail(serviceRequestId), 'attachments'],
    queryFn: () => fetchAttachments(serviceRequestId),
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({
      queryKey: [...requestKeys.detail(serviceRequestId), 'attachments'],
    });
  };

  const upload = useMutation({
    mutationFn: (file: File) => uploadAttachment(serviceRequestId, file),
    onSuccess: invalidate,
    onError: (error: unknown) => setLocalError(normalizeApiError(error).message),
  });

  const remove = useMutation({
    mutationFn: (id: string) => deleteAttachment(id),
    onSuccess: invalidate,
    onError: (error: unknown) => setLocalError(normalizeApiError(error).message),
  });

  const download = useMutation({
    mutationFn: ({ id, name }: { id: string; name: string }) => downloadAttachment(id, name),
    onError: (error: unknown) => setLocalError(normalizeApiError(error).message),
  });

  const attachments = attachmentsQuery.data ?? [];

  return (
    <Card variant="outlined">
      <CardContent>
        <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between' }}>
          <Typography variant="subtitle2" gutterBottom>
            Attachments
          </Typography>
          {canUpload && (
            <>
              <input
                ref={inputRef}
                type="file"
                hidden
                onChange={(event) => {
                  const file = event.target.files?.[0];

                  if (file !== undefined) {
                    setLocalError(null);
                    upload.mutate(file);
                  }

                  event.target.value = '';
                }}
              />
              <Button
                size="small"
                disabled={upload.isPending}
                onClick={() => inputRef.current?.click()}
              >
                Upload
              </Button>
            </>
          )}
        </Stack>
        <Divider sx={{ mb: 1 }} />

        {localError != null && <Alert severity="error">{localError}</Alert>}
        {attachmentsQuery.error != null && (
          <Alert severity="error">{attachmentsQuery.error.message}</Alert>
        )}

        <List dense>
          {attachments.map((attachment) => (
            <ListItem
              key={attachment.id}
              disableGutters
              secondaryAction={
                <Stack direction="row" spacing={1}>
                  <Button
                    size="small"
                    onClick={() =>
                      download.mutate({ id: attachment.id, name: attachment.original_name })
                    }
                  >
                    Download
                  </Button>
                  {canDelete && (
                    <Button
                      size="small"
                      color="error"
                      disabled={remove.isPending}
                      onClick={() => remove.mutate(attachment.id)}
                    >
                      Delete
                    </Button>
                  )}
                </Stack>
              }
            >
              <ListItemText
                primary={attachment.original_name}
                secondary={`${formatBytes(attachment.size_bytes)} · ${new Date(attachment.created_at).toLocaleString()}`}
              />
            </ListItem>
          ))}
          {attachments.length === 0 && (
            <Box sx={{ py: 1 }}>
              <Typography variant="body2" color="text.secondary">
                No attachments yet.
              </Typography>
            </Box>
          )}
        </List>
      </CardContent>
    </Card>
  );
}
