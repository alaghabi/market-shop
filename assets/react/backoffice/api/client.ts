type ApiResponse<T> = {
  member?: T[];
  items?: T[];
  'hydra:member'?: T[];
  totalItems?: number;
  'hydra:totalItems'?: number;
} & T;

export class ApiClient {
  private baseUrl: string;

  constructor(
    private getAccessToken: () => string | null,
    private boutiqueId?: string,
  ) {
    this.baseUrl = '/api';
  }

  private buildUrl(path: string): string {
    let url = `${this.baseUrl}${path}`;
    if (this.boutiqueId && !/[?&]boutiqueId=/.test(path)) {
      const separator = path.includes('?') ? '&' : '?';
      url += `${separator}boutiqueId=${encodeURIComponent(this.boutiqueId)}`;
    }
    return url;
  }

  private async request<T>(path: string, options: RequestInit = {}): Promise<T> {
    const token = this.getAccessToken();
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options.headers as Record<string, string> ?? {}),
    };

    const response = await fetch(this.buildUrl(path), {
      ...options,
      headers,
    });

    if (response.status === 401) {
      window.location.assign('/auth/login');
      throw new Error('Session expirée. Veuillez vous reconnecter.');
    }

    if (!response.ok) {
      const error = await response.json().catch(() => ({ detail: response.statusText }));
      throw new Error(error.detail ?? error.message ?? `Erreur ${response.status}`);
    }

    if (response.status === 204) return undefined as T;

    return response.json() as Promise<T>;
  }

  get<T>(path: string): Promise<T> {
    return this.request<T>(path, { cache: 'no-store' });
  }

  post<T>(path: string, body?: unknown): Promise<T> {
    return this.request<T>(path, {
      method: 'POST',
      body: body ? JSON.stringify(body) : undefined,
    });
  }

  patch<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>(path, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/merge-patch+json' },
      body: JSON.stringify(body),
    });
  }

  put<T>(path: string, body: unknown): Promise<T> {
    return this.request<T>(path, {
      method: 'PUT',
      body: JSON.stringify(body),
    });
  }

  delete(path: string): Promise<void> {
    return this.request<void>(path, { method: 'DELETE' });
  }

  async download(path: string): Promise<Blob> {
    const token = this.getAccessToken();
    const response = await fetch(this.buildUrl(path), {
      headers: token ? { Authorization: `Bearer ${token}` } : undefined,
    });

    if (response.status === 401) {
      window.location.assign('/auth/login');
      throw new Error('Session expirée. Veuillez vous reconnecter.');
    }

    if (!response.ok) {
      const error = await response.json().catch(() => ({ detail: response.statusText }));
      throw new Error(error.detail ?? error.message ?? `Erreur ${response.status}`);
    }

    return response.blob();
  }

  getCollection<T>(path: string): Promise<{ member: T[]; totalItems: number }> {
    return this.get<ApiResponse<T>>(path).then((res) => ({
      member: res.member ?? res.items ?? res['hydra:member'] ?? [],
      totalItems: res.totalItems
        ?? res['hydra:totalItems']
        ?? res.member?.length
        ?? res.items?.length
        ?? res['hydra:member']?.length
        ?? 0,
    }));
  }

  async upload(path: string, formData: FormData): Promise<Record<string, unknown>> {
    const token = this.getAccessToken();
    const headers: Record<string, string> = token ? { Authorization: `Bearer ${token}` } : {};

    const response = await fetch(`${this.baseUrl}${path}`, {
      method: 'POST',
      headers,
      body: formData,
    });

    if (!response.ok) {
      const error = await response.json().catch(() => ({ detail: response.statusText }));
      throw new Error(error.detail ?? `Erreur ${response.status}`);
    }

    return response.json();
  }
}
