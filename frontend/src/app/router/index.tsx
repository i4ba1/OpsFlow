import { createHashRouter } from 'react-router-dom';

import { HomePage } from '../../features/dashboard/HomePage';

export const router = createHashRouter([
  {
    path: '/',
    element: <HomePage />,
  },
]);
