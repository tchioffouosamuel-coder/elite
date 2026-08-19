/// <reference types="vite/client" />

interface Window {
  desktop?: {
    cacheGet: (key: string) => Promise<unknown | null>;
    cachePut: (key: string, value: unknown) => Promise<void>;
    enqueue: (request: {
      method: string;
      url: string;
      data?: unknown;
      headers?: Record<string, string>;
    }) => Promise<void>;
    sync: (options: {
      baseUrl: string;
      token: string;
      schoolId?: number | null;
      locale: string;
    }) => Promise<number>;
  };
}
