import { Navigate, RouterProvider, createHashRouter } from 'react-router-dom';

import { AuthProvider } from '../../auth/auth-context';
import { AppShell } from '../../components/layout/AppShell';
import { ApprovalDetailPage } from '../../features/approvals/ApprovalDetailPage';
import { ApprovalsPage } from '../../features/approvals/ApprovalsPage';
import { CustomersPage } from '../../features/customers/CustomersPage';
import { HomePage } from '../../features/dashboard/HomePage';
import { LoginPage } from '../../features/auth/LoginPage';
import { ProjectsPage } from '../../features/projects/ProjectsPage';
import { RequestDetailPage } from '../../features/requests/RequestDetailPage';
import { RequestsPage } from '../../features/requests/RequestsPage';
import { RequireAuth } from '../../permissions/guards';
import { SlaPoliciesPage } from '../../features/sla/SlaPoliciesPage';

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
          { path: 'customers', element: <CustomersPage /> },
          { path: 'customers/:customerId', element: <HomePage /> },
          { path: 'projects', element: <ProjectsPage /> },
          { path: 'projects/:projectId', element: <HomePage /> },
          { path: 'requests', element: <RequestsPage /> },
          { path: 'requests/:requestId', element: <RequestDetailPage /> },
          { path: 'approvals', element: <ApprovalsPage /> },
          { path: 'approvals/:approvalId', element: <ApprovalDetailPage /> },
          { path: 'invoices', element: <HomePage /> },
          { path: 'settings/sla', element: <SlaPoliciesPage /> },
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
