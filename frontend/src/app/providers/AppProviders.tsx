import { QueryClientProvider } from '@tanstack/react-query';
import { ThemeProvider, createTheme } from '@mui/material';
import type { PropsWithChildren } from 'react';

import { queryClient } from '../../api/query-client';

const theme = createTheme({
  palette: {
    primary: {
      main: '#0b3d6e',
    },
    secondary: {
      main: '#0e7490',
    },
  },
});

export function AppProviders({ children }: PropsWithChildren) {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>{children}</ThemeProvider>
    </QueryClientProvider>
  );
}
