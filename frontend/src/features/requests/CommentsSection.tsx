import { zodResolver } from '@hookform/resolvers/zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  Alert,
  Avatar,
  Box,
  Button,
  Card,
  CardContent,
  Chip,
  Divider,
  List,
  ListItem,
  ListItemAvatar,
  Stack,
  TextField,
  Typography,
} from '@mui/material';
import type { SubmitHandler } from 'react-hook-form';
import { useForm } from 'react-hook-form';
import { z } from 'zod';

import type { Comment } from '../../types/features';
import { requestKeys } from '../../api/query-keys';
import { usePermission } from '../../permissions/Can';
import { createComment, fetchComments } from './comment-api';

const commentSchema = z.object({
  body: z.string().trim().min(1).max(10_000),
  visibility: z.enum(['PUBLIC', 'INTERNAL']),
});

type CommentForm = z.infer<typeof commentSchema>;

export function CommentsSection({ serviceRequestId }: { serviceRequestId: string }) {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();
  const canInternal = hasPermission('comment.internal');

  const commentsQuery = useQuery({
    queryKey: [...requestKeys.detail(serviceRequestId), 'comments'],
    queryFn: () => fetchComments(serviceRequestId),
  });

  const {
    register,
    handleSubmit,
    reset,
    watch,
    formState: { errors },
  } = useForm<CommentForm>({
    resolver: zodResolver(commentSchema),
    defaultValues: { body: '', visibility: 'PUBLIC' },
  });

  const visibility = watch('visibility');

  const mutation = useMutation({
    mutationFn: ({ body, vis }: { body: string; vis: 'PUBLIC' | 'INTERNAL' }) =>
      createComment(serviceRequestId, body, vis),
    onSuccess: (comment) => {
      queryClient.setQueryData<Comment[]>(
        [...requestKeys.detail(serviceRequestId), 'comments'],
        (old) => [comment, ...(old ?? [])],
      );
      reset({ body: '', visibility: 'PUBLIC' });
    },
  });

  const onSubmit: SubmitHandler<CommentForm> = ({ body, visibility: vis }) => {
    mutation.mutate({ body, vis });
  };

  const comments = commentsQuery.data ?? [];

  return (
    <Card variant="outlined">
      <CardContent>
        <Typography variant="subtitle2" gutterBottom>
          Conversation
        </Typography>
        <Divider sx={{ mb: 1 }} />

        {mutation.error != null && <Alert severity="error">{mutation.error.message}</Alert>}

        <List dense sx={{ maxHeight: 420, overflowY: 'auto' }}>
          {comments.map((comment) => (
            <ListItem key={comment.id} alignItems="flex-start" disableGutters>
              <ListItemAvatar>
                <Avatar sx={{ width: 28, height: 28, fontSize: 14 }}>
                  {comment.author?.name?.charAt(0) ?? '?'}
                </Avatar>
              </ListItemAvatar>
              <Box>
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <Typography variant="body2" sx={{ fontWeight: 600 }}>
                    {comment.author?.name ?? 'Unknown'}
                  </Typography>
                  <Typography variant="caption" color="text.secondary">
                    {new Date(comment.created_at).toLocaleString()}
                  </Typography>
                  {comment.visibility === 'INTERNAL' && (
                    <Chip label="Internal" size="small" color="secondary" variant="outlined" />
                  )}
                </Stack>
                <Typography variant="body2" sx={{ whiteSpace: 'pre-wrap' }}>
                  {comment.body}
                </Typography>
              </Box>
            </ListItem>
          ))}
          {comments.length === 0 && (
            <Typography color="text.secondary" variant="body2">
              No comments yet. Start the conversation below.
            </Typography>
          )}
        </List>

        <Stack component="form" spacing={1} onSubmit={handleSubmit(onSubmit)} sx={{ mt: 1 }}>
          <Stack direction="row" spacing={1}>
            <Button
              type="button"
              size="small"
              variant={visibility === 'PUBLIC' ? 'contained' : 'outlined'}
              onClick={() => reset({ body: watch('body'), visibility: 'PUBLIC' })}
            >
              Public
            </Button>
            {canInternal && (
              <Button
                type="button"
                size="small"
                variant={visibility === 'INTERNAL' ? 'contained' : 'outlined'}
                color="secondary"
                onClick={() => reset({ body: watch('body'), visibility: 'INTERNAL' })}
              >
                Internal
              </Button>
            )}
          </Stack>
          <TextField
            label="Add a comment"
            multiline
            minRows={2}
            {...register('body')}
            error={errors.body != null}
            helperText={errors.body?.message}
          />
          <input type="hidden" {...register('visibility')} />
          <Box>
            <Button type="submit" variant="contained" disabled={mutation.isPending}>
              {mutation.isPending ? 'Posting…' : 'Post comment'}
            </Button>
          </Box>
        </Stack>
      </CardContent>
    </Card>
  );
}
