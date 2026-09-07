import { zodResolver } from '@hookform/resolvers/zod';
import { Alert, Box, Button, Card, CardContent, Stack, TextField, Typography } from '@mui/material';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useLocation, useNavigate } from 'react-router-dom';
import { z } from 'zod';

import { normalizeApiError } from '../../api/errors';
import { useAuth } from '../../auth/auth-context';

const loginSchema = z.object({
  email: z.string().trim().email(),
  password: z.string().min(1),
});

type LoginForm = z.infer<typeof loginSchema>;

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const from = (location.state as { from?: { pathname: string } } | null)?.from?.pathname ?? '/app';

  const onSubmit = handleSubmit(async (values) => {
    setSubmitting(true);
    setError(null);

    try {
      await login(values.email, values.password);
      navigate(from, { replace: true });
    } catch (loginError) {
      setError(normalizeApiError(loginError).message);
    } finally {
      setSubmitting(false);
    }
  });

  return (
    <Box
      sx={{ display: 'flex', minHeight: '100vh', alignItems: 'center', justifyContent: 'center' }}
    >
      <Card variant="outlined" sx={{ width: 400 }}>
        <CardContent>
          <Stack spacing={2} component="form" onSubmit={onSubmit} noValidate>
            <Box>
              <Typography variant="h5" component="h1">
                OpsFlow
              </Typography>
              <Typography variant="body2" color="text.secondary">
                Sign in to your operations workspace
              </Typography>
            </Box>

            {error != null && <Alert severity="error">{error}</Alert>}

            <TextField
              label="Email"
              type="email"
              autoComplete="email"
              fullWidth
              {...register('email')}
              error={errors.email != null}
              helperText={errors.email?.message}
            />

            <TextField
              label="Password"
              type="password"
              autoComplete="current-password"
              fullWidth
              {...register('password')}
              error={errors.password != null}
              helperText={errors.password?.message}
            />

            <Button type="submit" variant="contained" fullWidth disabled={submitting}>
              {submitting ? 'Signing in…' : 'Sign in'}
            </Button>
          </Stack>
        </CardContent>
      </Card>
    </Box>
  );
}
