import { useQuery } from '@tanstack/react-query';
import {
  Alert,
  Box,
  Card,
  CardContent,
  Chip,
  CircularProgress,
  Container,
  Stack,
  Typography,
} from '@mui/material';

import { api } from '../../api/client';
import { normalizeApiError } from '../../api/errors';

type PingResponse = {
  data: {
    service: string;
    version: string;
    status: string;
  };
  meta: {
    request_id: string;
  };
};

async function fetchPing(): Promise<PingResponse> {
  const response = await api.get<PingResponse>('/ping');
  return response.data;
}

export function HomePage() {
  const { data, isPending, error } = useQuery({
    queryKey: ['ping'],
    queryFn: fetchPing,
  });

  return (
    <Container maxWidth="md" sx={{ py: 8 }}>
      <Stack spacing={3}>
        <Box>
          <Typography variant="h3" component="h1" gutterBottom>
            OpsFlow
          </Typography>
          <Typography variant="subtitle1" color="text.secondary">
            Multi-tenant operations and client service platform
          </Typography>
        </Box>

        <Card variant="outlined">
          <CardContent>
            <Stack spacing={2}>
              <Typography variant="h6" component="h2">
                API status
              </Typography>

              {isPending && <CircularProgress aria-label="Loading API status" />}

              {error != null && <Alert severity="error">{normalizeApiError(error).message}</Alert>}

              {data != null && (
                <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                  <Chip label="Connected" color="success" size="small" />
                  <Typography variant="body2" color="text.secondary">
                    {data.data.service} v{data.data.version}
                  </Typography>
                </Stack>
              )}
            </Stack>
          </CardContent>
        </Card>
      </Stack>
    </Container>
  );
}
