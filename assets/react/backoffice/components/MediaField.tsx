import { useState, useRef, useCallback, useEffect } from 'react';
import { FormField } from './FormField';
import { getStoredAccessToken } from '../../auth/getStoredAccessToken';

type MediaFieldProps = {
  label: string;
  value?: string | null;
  onChange: (url: string) => void;
  hint?: string;
  accept?: string;
  maxSizeMb?: number;
  boutiqueId?: string;
  context?: string;
  boutiqueSlug?: string;
  disabled?: boolean;
};

type UploadState = 'idle' | 'uploading' | 'error';

export function MediaField({
  label,
  value,
  onChange,
  hint,
  accept = 'image/*',
  maxSizeMb = 5,
  boutiqueId,
  context = 'settings',
  disabled = false,
}: MediaFieldProps) {
  const [mode, setMode] = useState<'url' | 'upload'>(value ? 'url' : 'url');
  const [uploadState, setUploadState] = useState<UploadState>('idle');
  const [error, setError] = useState('');
  const [urlInput, setUrlInput] = useState(value ?? '');
  const [preview, setPreview] = useState(value ?? '');
  const fileRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setUrlInput(value ?? '');
    setPreview(value ?? '');
  }, [value]);

  const uploadFile = useCallback(async (file: File) => {
    if (file.size > maxSizeMb * 1024 * 1024) {
      setError(`Fichier trop volumineux (max ${maxSizeMb} Mo)`);
      return;
    }

    setUploadState('uploading');
    setError('');

    try {
      const token = getStoredAccessToken();

      const form = new FormData();
      form.append('file', file);
       form.append('context', context);

      const query = boutiqueId ? `?boutiqueId=${encodeURIComponent(boutiqueId)}` : '';
      const resp = await fetch(`/api/media/upload${query}`, {
        method: 'POST',
        headers: token ? { Authorization: `Bearer ${token}` } : {},
        body: form,
      });

      if (!resp.ok) {
        const err = await resp.json().catch(() => ({ detail: 'Upload failed' }));
        throw new Error(err.detail ?? `Erreur ${resp.status}`);
      }

      const data = await resp.json();
      const url = data.url ?? data.contentUrl;
      if (url) {
        setPreview(url);
        onChange(url);
        setUrlInput(url);
        setUploadState('idle');
      } else {
        throw new Error('No URL returned');
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erreur upload');
      setUploadState('error');
    }
  }, [boutiqueId, context, maxSizeMb, onChange]);

  const handleUrlChange = useCallback((val: string) => {
    setUrlInput(val);
    setError('');
    if (val && (val.startsWith('http://') || val.startsWith('https://') || val.startsWith('/'))) {
      setPreview(val);
      onChange(val);
    }
  }, [onChange]);

  const handleClear = useCallback(() => {
    setUrlInput('');
    setPreview('');
    setError('');
    onChange('');
  }, [onChange]);

  return (
    <FormField label={label} error={error} hint={hint}>
      <div style={{ display: 'flex', gap: 8, marginBottom: 8 }}>
        <button
          type="button"
          className={`bo-btn bo-btn--sm ${mode === 'url' ? 'bo-btn--primary' : 'bo-btn--ghost'}`}
          disabled={disabled}
          onClick={() => setMode('url')}
        >
          URL
        </button>
        <button
          type="button"
          className={`bo-btn bo-btn--sm ${mode === 'upload' ? 'bo-btn--primary' : 'bo-btn--ghost'}`}
          disabled={disabled}
          onClick={() => setMode('upload')}
        >
          Upload
        </button>
        {preview && (
          <button type="button" disabled={disabled} className="bo-btn bo-btn--sm bo-btn--danger" onClick={handleClear}>
            Effacer
          </button>
        )}
      </div>

      {mode === 'url' ? (
        <input
          className="bo-input"
          type="text"
          disabled={disabled}
          value={urlInput}
          onChange={(e) => handleUrlChange(e.target.value)}
          placeholder="https://example.com/image.jpg"
        />
      ) : (
        <div>
          <input
            ref={fileRef}
            type="file"
            accept={accept}
            style={{ display: 'none' }}
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) uploadFile(f);
            }}
          />
          <button
            type="button"
            className="bo-btn"
            disabled={disabled || uploadState === 'uploading'}
            onClick={() => fileRef.current?.click()}
          >
            {uploadState === 'uploading' ? 'Upload en cours...' : 'Choisir un fichier'}
          </button>
          {uploadState === 'uploading' && (
            <span style={{ marginLeft: 8, fontSize: 12, color: 'var(--bo-text-muted)' }}>
              Upload en cours...
            </span>
          )}
        </div>
      )}

      {preview && (
        <div style={{ marginTop: 8, position: 'relative', display: 'inline-block' }}>
          <img
            src={preview}
            alt="preview"
            style={{
              maxWidth: 200,
              maxHeight: 120,
              borderRadius: 6,
              border: '1px solid var(--bo-border)',
              objectFit: 'cover',
            }}
            onError={() => setPreview('')}
          />
        </div>
      )}
    </FormField>
  );
}
