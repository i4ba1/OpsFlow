import { Navigate, RouterProvider, createHashRouter } from 'react-router-dom';

import { AuthProvider } from '../../auth/auth-context';
import { AppShell } from '../../components/layout/AppShell';
import { ApprovalDetailPage } from '../../features/approvals/ApprovalDetailPage';
import { ApprovalsPage } from '../../features/approvals/ApprovalsPage';
import { CustomerDetailPage } from '../../features/customers/CustomerDetailPage';
import { CustomersPage } from '../../features/customers/CustomersPage';
import { DashboardPage } from '../../features/dashboard/DashboardPage';
import { InvoiceDetailPage } from '../../features/invoices/InvoiceDetailPage';
import { InvoicesPage } from '../../features/invoices/InvoicesPage';
import { LoginPage } from '../../features/auth/LoginPage';
import { NotificationsPage } from '../../features/notifications/NotificationsPage';
import { ProjectDetailPage } from '../../features/projects/ProjectDetailPage';
import { ProjectsPage } from '../../features/projects/ProjectsPage';
import { RequestDetailPage } from '../../features/requests/RequestDetailPage';
import { RequestsPage } from '../../features/requests/RequestsPage';
import { RequireAuth, RequirePermission } from '../../permissions/guards';
import { RealtimeProvider } from '../../realtime/RealtimeProvider';
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
          { index: true, element: <DashboardPage /> },
          {
            element: <RequirePermission permission="customer.view" />,
            children: [
              { path: 'customers', element: <CustomersPage /> },
              { path: 'customers/:customerId', element: <CustomerDetailPage /> },
            ],
          },
          {
            element: <RequirePermission permission="project.view" />,
            children: [
              { path: 'projects', element: <ProjectsPage /> },
              { path: 'projects/:projectId', element: <ProjectDetailPage /> },
            ],
          },
          {
            element: <RequirePermission permission="request.view" />,
            children: [
              { path: 'requests', element: <RequestsPage /> },
              { path: 'requests/:requestId', element: <RequestDetailPage /> },
            ],
          },
          {
            element: <RequirePermission permission="approval.view" />,
            children: [
              { path: 'approvals', element: <ApprovalsPage /> },
              { path: 'approvals/:approvalId', element: <ApprovalDetailPage /> },
            ],
          },
          {
            element: <RequirePermission permission="invoice.view" />,
            children: [
              { path: 'invoices', element: <InvoicesPage /> },
              { path: 'invoices/:invoiceId', element: <InvoiceDetailPage /> },
            ],
          },
          { path: 'notifications', element: <NotificationsPage /> },
          {
            element: <RequirePermission permission="sla.view" />,
            children: [{ path: 'settings/sla', element: <SlaPoliciesPage /> }],
          },
        ],
      },
    ],
  },
  { path: '*', element: <Navigate to="/login" replace /> },
]);

export function AppRouter() {
  return (
    <AuthProvider>
      <RealtimeProvider>
        <RouterProvider router={router} />
      </RealtimeProvider>
    </AuthProvider>
  );
}
