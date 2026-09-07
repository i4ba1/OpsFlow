import {
  AppBar,
  Box,
  Button,
  Drawer,
  List,
  ListItem,
  ListItemButton,
  ListItemText,
  Toolbar,
  Typography,
} from '@mui/material';
import { NavLink, Outlet } from 'react-router-dom';

import { useAuth } from '../../auth/auth-context';

const NAV_ITEMS = [
  { label: 'Dashboard', to: '/app' },
  { label: 'Customers', to: '/app/customers', permission: 'customer.view' },
  { label: 'Projects', to: '/app/projects', permission: 'project.view' },
  { label: 'Requests', to: '/app/requests', permission: 'request.view' },
  { label: 'Approvals', to: '/app/approvals', permission: 'approval.view' },
  { label: 'Invoices', to: '/app/invoices', permission: 'invoice.view' },
];

export function AppShell() {
  const { user, logout } = useAuth();

  return (
    <Box sx={{ display: 'flex', minHeight: '100vh' }}>
      <AppBar position="fixed" sx={{ zIndex: (theme) => theme.zIndex.drawer + 1 }}>
        <Toolbar>
          <Typography variant="h6" sx={{ flexGrow: 1 }}>
            OpsFlow
          </Typography>
          <Typography variant="body2" sx={{ mr: 2 }}>
            {user?.organization?.name ?? ''}
          </Typography>
          <Button color="inherit" onClick={() => void logout()}>
            Sign out
          </Button>
        </Toolbar>
      </AppBar>

      <Drawer
        variant="permanent"
        sx={{
          width: 240,
          flexShrink: 0,
          '& .MuiDrawer-paper': { width: 240, boxSizing: 'border-box', top: 64 },
        }}
      >
        <Toolbar />
        <List>
          {NAV_ITEMS.map((item) => (
            <ListItem key={item.to} disablePadding>
              <ListItemButton
                component={NavLink}
                to={item.to}
                end={item.to === '/app'}
                sx={{ '&.active': { fontWeight: 700 } }}
              >
                <ListItemText primary={item.label} />
              </ListItemButton>
            </ListItem>
          ))}
        </List>
      </Drawer>

      <Box component="main" sx={{ flexGrow: 1, p: 3, mt: 8 }}>
        <Outlet />
      </Box>
    </Box>
  );
}
