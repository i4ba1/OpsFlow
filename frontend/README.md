# OpsFlow Frontend

React 19 + TypeScript + Vite 8 single-page application for OpsFlow.

See the [root README](../README.md) for setup (`make setup`), service URLs, and
developer commands. In Docker, this app is served by nginx through
http://localhost:8080.

## Scripts

```bash
npm run dev           # Vite dev server (port 5173)
npm run build         # typecheck + production build
npm run lint          # ESLint
npm run typecheck     # tsc project references, no emit
npm run test          # Vitest (jsdom)
npm run format        # Prettier write
npm run format:check  # Prettier check
```

## Structure

```text
src/
├── api/          # axios client, query client, error normalization
├── app/          # providers, router, config
├── auth/         # (Phase 1) authentication context and guards
├── components/   # shared/common/form/feedback/layout components
├── features/     # feature modules (dashboard, customers, requests, ...)
├── hooks/  lib/  permissions/  realtime/  types/
└── tests/        # Vitest setup; co-located __tests__ suites
```
