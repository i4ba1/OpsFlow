import axios from 'axios';

export type ApiErrorEnvelope = {
  code: string;
  message: string;
  fields?: Record<string, string[]>;
};

export function normalizeApiError(error: unknown): ApiErrorEnvelope {
  if (axios.isAxiosError(error)) {
    const envelope = error.response?.data?.error as Partial<ApiErrorEnvelope> | undefined;

    return {
      code: envelope?.code ?? 'INTERNAL_ERROR',
      message:
        envelope?.message ??
        (error.response
          ? 'The server returned an unexpected response.'
          : 'Unable to reach the server. Check your connection and try again.'),
      fields: envelope?.fields,
    };
  }

  return {
    code: 'INTERNAL_ERROR',
    message: 'An unexpected error occurred.',
  };
}
