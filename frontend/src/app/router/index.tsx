import { Navigate, RouterProvider, createHashRouter } from 'react-router-dom';

import { AuthProvider } from '../../auth/auth-context';
import { AppShell } from '../../components/layout/AppShell';
import { HomePage } from '../../features/dashboard/HomePage';
import { LoginPage } from '../../features/auth/LoginPage';
import { RequireAuth } from '../../permissions/guards';

export const router = createHashRouter([
  {
    path: '/login',
    element: <LoginPage />,
  },
  {
    element: <RequireAuth />,
    children: [
      {
        path: '/app',
        element: <AppShell />,
        children: [
          { index: true, element: <HomePage /> },
          { path: 'customers', element: <HomePage /> },
          { path: 'projects', element: <HomePage /> },
          { path: 'requests', element: <HomePage /> },
          { path: 'approvals', element: <HomePage /> },
          { path: 'invoices', element: <HomePage /> },
        ],
      },
    ],
  },
  { path: '*', element: <Navigate to="/login" replace /> },
]);

export function AppRouter() {
  return (
    <AuthProvider>
      <RouterProvider router={router} />
    </AuthProvider>
  );
}
