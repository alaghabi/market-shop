import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { applyStorefrontTheme, resetStorefrontTheme, type StorefrontThemeData } from '../theme/storefrontThemeRoot';
import { boutiqueLink, boutiqueQuery, resolveBoutiqueSlug } from '../screens/public/boutiqueRouting';

type PasswordResetPageProps = { customer?: boolean };

function useCustomerTheme(customer: boolean): string {
  const boutiqueSlug = useMemo(
    () => customer ? resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/client\/(?:forgot-password|reset-password)/) : '',
    [customer],
  );

  useEffect(() => {
    if (!customer || !boutiqueSlug) return undefined;

    resetStorefrontTheme();
    fetch(`/api/boutiques/${encodeURIComponent(boutiqueSlug)}`)
      .then((response) => response.ok ? response.json() as Promise<StorefrontThemeData> : null)
      .then((payload) => {
        if (payload) applyStorefrontTheme(payload);
      })
      .catch(() => {});

    return resetStorefrontTheme;
  }, [boutiqueSlug, customer]);

  return boutiqueSlug;
}

export function PasswordResetRequestPage({ customer = false }: PasswordResetPageProps) {
  const boutiqueSlug = useCustomerTheme(customer);
  const [email, setEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const loginHref = customer ? boutiqueLink('/client/login') : '/auth/login';

  async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSubmitting(true);
    setMessage(null);
    setError(null);

    try {
      const response = await fetch(customer
        ? `/api/boutique/auth/password-reset${boutiqueQuery(boutiqueSlug)}`
        : '/api/auth/password-reset/request', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      const payload = await response.json().catch(() => ({})) as { message?: string };
      if (!response.ok) throw new Error(payload.message ?? 'Impossible de traiter la demande.');
      setMessage(payload.message ?? 'Si un compte existe pour cet email, un lien sera envoyé.');
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : 'Impossible de traiter la demande.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <main className={customer ? 'min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] px-4 py-10 text-[color:var(--sf-text,#171717)]' : 'lovable-auth'}>
      <section className={customer ? 'mx-auto max-w-xl rounded-[2rem] border border-[color:var(--sf-outline,#d8d0c4)] bg-[color:var(--sf-surface,#ffffff)] p-8 shadow-xl sm:p-10' : 'lovable-auth__form-panel'}>
        <div className={customer ? '' : 'lovable-auth__form-card'}>
          <p className={customer ? 'text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--sf-accent,#7C3AED)]' : 'lovable-auth__eyebrow'}>Sécurité du compte</p>
          <h1 className={customer ? 'mt-3 text-3xl font-black' : ''}>Mot de passe oublié ?</h1>
          <p className="mt-3 text-sm leading-7 opacity-70">Saisissez votre email. Si un compte existe, vous recevrez un lien de réinitialisation valable une heure.</p>
          <form className="mt-8 grid gap-4" onSubmit={(event) => { void submit(event); }}>
            <label className="text-sm font-semibold">Email<input required type="email" value={email} onChange={(event) => setEmail(event.target.value)} className="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--ds-primary,#3525cd)]" /></label>
            {error && <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
            {message && <p role="status" className="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{message}</p>}
            <button type="submit" disabled={isSubmitting} className={customer ? 'mt-2 inline-flex min-h-12 items-center justify-center rounded-full bg-[color:var(--ds-primary,#3525cd)] px-6 py-3 text-sm font-black text-white transition-opacity hover:opacity-90 disabled:opacity-60' : 'lovable-button lovable-auth__submit'}>{isSubmitting ? 'Envoi...' : 'Envoyer le lien'}</button>
          </form>
          <Link to={loginHref} className="mt-6 block text-center text-sm font-bold underline">Retour à la connexion</Link>
        </div>
      </section>
    </main>
  );
}

export function PasswordResetConfirmPage({ customer = false }: PasswordResetPageProps) {
  const boutiqueSlug = useCustomerTheme(customer);
  const token = useMemo(() => new URLSearchParams(window.location.search).get('token') ?? '', []);
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const loginHref = customer ? boutiqueLink('/client/login') : '/auth/login';

  async function submit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    if (password !== confirmation) {
      setError('Les mots de passe ne correspondent pas.');
      return;
    }
    setIsSubmitting(true);
    setMessage(null);
    setError(null);

    try {
      const response = await fetch(customer
        ? `/api/boutique/auth/password-reset/reset${boutiqueQuery(boutiqueSlug)}`
        : '/api/auth/password-reset/reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, password }),
      });
      const payload = await response.json().catch(() => ({})) as { message?: string };
      if (!response.ok) throw new Error(payload.message ?? 'Le lien est invalide ou expiré.');
      setMessage(payload.message ?? 'Mot de passe mis à jour.');
      setPassword('');
      setConfirmation('');
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : 'Impossible de mettre à jour le mot de passe.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <main className={customer ? 'min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] px-4 py-10 text-[color:var(--sf-text,#171717)]' : 'lovable-auth'}>
      <section className={customer ? 'mx-auto max-w-xl rounded-[2rem] border border-[color:var(--sf-outline,#d8d0c4)] bg-[color:var(--sf-surface,#ffffff)] p-8 shadow-xl sm:p-10' : 'lovable-auth__form-panel'}>
        <div className={customer ? '' : 'lovable-auth__form-card'}>
          <p className={customer ? 'text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--sf-accent,#7C3AED)]' : 'lovable-auth__eyebrow'}>Sécurité du compte</p>
          <h1 className={customer ? 'mt-3 text-3xl font-black' : ''}>Créer un nouveau mot de passe</h1>
          <form className="mt-8 grid gap-4" onSubmit={(event) => { void submit(event); }}>
            <label className="text-sm font-semibold">Nouveau mot de passe<input required minLength={8} type="password" value={password} onChange={(event) => setPassword(event.target.value)} className="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--ds-primary,#3525cd)]" /></label>
            <label className="text-sm font-semibold">Confirmer le mot de passe<input required minLength={8} type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} className="mt-2 w-full rounded-xl border border-black/15 px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--ds-primary,#3525cd)]" /></label>
            {!token && <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">Lien de réinitialisation manquant.</p>}
            {error && <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
            {message && <p role="status" className="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">{message}</p>}
            <button type="submit" disabled={!token || isSubmitting} className={customer ? 'mt-2 inline-flex min-h-12 items-center justify-center rounded-full bg-[color:var(--ds-primary,#3525cd)] px-6 py-3 text-sm font-black text-white transition-opacity hover:opacity-90 disabled:opacity-60' : 'lovable-button lovable-auth__submit'}>{isSubmitting ? 'Mise à jour...' : 'Mettre à jour'}</button>
          </form>
          <Link to={loginHref} className="mt-6 block text-center text-sm font-bold underline">Retour à la connexion</Link>
        </div>
      </section>
    </main>
  );
}
