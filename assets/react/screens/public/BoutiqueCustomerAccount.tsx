import { useEffect, useState, type FormEvent } from 'react';
import { ArrowLeft, LogOut, LockKeyhole, Mail, UserRound } from 'lucide-react';
import { boutiqueLink, boutiqueQuery, resolveBoutiqueSlug } from './boutiqueRouting';
import { ImageWithFallback } from '../../components/ImageWithFallback';
import { applyStorefrontTheme, resetStorefrontTheme, type StorefrontThemeData } from '../../theme/storefrontThemeRoot';

type CustomerProfile = {
  id: string;
  email: string;
  firstName?: string | null;
  lastName?: string | null;
  phone?: string | null;
  boutique: { id: string; name: string; slug: string };
};

type CustomerSession = {
  accessToken: string;
  customer: CustomerProfile;
};

type CustomerBoutique = StorefrontThemeData & {
  id: string;
  name: string;
  slug: string;
};

const sessionPrefix = 'hanooti.customer.';

function sessionKey(slug: string): string {
  return `${sessionPrefix}${slug}`;
}

function readSession(slug: string): CustomerSession | null {
  try {
    const value = window.localStorage.getItem(sessionKey(slug));
    if (!value) return null;
    const session = JSON.parse(value) as CustomerSession;
    return session.accessToken && session.customer ? session : null;
  } catch {
    return null;
  }
}

function saveSession(slug: string, session: CustomerSession): void {
  window.localStorage.setItem(sessionKey(slug), JSON.stringify(session));
}

function clearSession(slug: string): void {
  window.localStorage.removeItem(sessionKey(slug));
}

function customerHeaders(slug: string): HeadersInit {
  const session = readSession(slug);

  return session ? { Authorization: `Bearer ${session.accessToken}` } : {};
}

export function BoutiqueAccountLink({ boutiqueSlug }: { boutiqueSlug: string }) {
  const [session, setSession] = useState<CustomerSession | null>(() => readSession(boutiqueSlug));

  useEffect(() => {
    const current = readSession(boutiqueSlug);
    if (!current) {
      setSession(null);
      return;
    }

    fetch(`/api/boutique/auth/me${boutiqueQuery(boutiqueSlug)}`, {
      headers: customerHeaders(boutiqueSlug),
      credentials: 'same-origin',
    })
      .then(async (response) => {
        if (!response.ok) throw new Error('Session client invalide.');
        const payload = await response.json() as { customer: CustomerProfile };
        const nextSession = { ...current, customer: payload.customer };
        saveSession(boutiqueSlug, nextSession);
        setSession(nextSession);
      })
      .catch(() => {
        clearSession(boutiqueSlug);
        setSession(null);
      });
  }, [boutiqueSlug]);

  const href = boutiqueLink(session ? '/client/account' : '/client/login');

  return (
    <a
      href={href}
      className="inline-flex h-11 w-11 cursor-pointer items-center justify-center rounded-full border border-[color:var(--sf-accent,var(--sf-outline,var(--ds-outline-variant)))] text-[color:var(--sf-text-muted,var(--ds-on-surface-variant))] transition-colors hover:bg-[color:var(--sf-surface-muted,var(--ds-surface-container))] hover:text-[color:var(--sf-text,var(--ds-on-surface))] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--sf-accent,var(--ds-primary))]"
      aria-label={session ? 'Ouvrir mon profil client' : 'Se connecter à la boutique'}
      title={session ? 'Mon profil' : 'Connexion client'}
    >
      <UserRound className="h-5 w-5" aria-hidden="true" />
    </a>
  );
}

export function BoutiqueCustomerAuthPage({ mode }: { mode: 'login' | 'register' }) {
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/client\/(?:login|register)/);
  const [boutique, setBoutique] = useState<CustomerBoutique | null>(null);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [phone, setPhone] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (!boutiqueSlug) return;
    window.__boutiqueSlug__ = boutiqueSlug;
    resetStorefrontTheme();
    fetch(`/api/boutiques/${boutiqueSlug}`)
      .then((response) => response.ok ? response.json() : null)
      .then((payload: CustomerBoutique | null) => {
        if (!payload) return;
        applyStorefrontTheme(payload);
        setBoutique(payload);
      })
      .catch(() => {});

    return resetStorefrontTheme;
  }, [boutiqueSlug]);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);
    setIsSubmitting(true);

    try {
      const payload = mode === 'login'
        ? { email, password }
        : { email, password, firstName, lastName, phone: phone || null };
      const response = await fetch(`/api/boutique/auth/${mode}${boutiqueQuery(boutiqueSlug)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      const result = await response.json().catch(() => ({})) as { accessToken?: string; customer?: CustomerProfile; message?: string };
      if (!response.ok || !result.accessToken || !result.customer) {
        throw new Error(result.message ?? 'Authentification client impossible.');
      }

      saveSession(boutiqueSlug, { accessToken: result.accessToken, customer: result.customer });
      window.location.assign(boutiqueLink('/client/account'));
    } catch (submitError) {
      setError(submitError instanceof Error ? submitError.message : 'Authentification client impossible.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const isRegister = mode === 'register';
  const alternatePath = isRegister ? '/client/login' : '/client/register';

  return (
    <main className="min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] px-4 py-8 text-[color:var(--sf-text,#171717)] sm:px-6 lg:px-8">
      <div className="mx-auto max-w-6xl">
        <a href={boutiqueLink('/')} className="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-[color:var(--sf-text-muted,#6b6560)] transition-colors hover:text-[color:var(--sf-text,#171717)]">
          <ArrowLeft className="h-4 w-4" aria-hidden="true" /> Retour à la boutique
        </a>
        <div className="mx-auto mt-10 grid max-w-4xl overflow-hidden rounded-[2rem] border border-[color:var(--sf-outline,#d8d0c4)] bg-[color:var(--sf-surface,#ffffff)] shadow-2xl lg:grid-cols-[.9fr_1.1fr]">
          <div className="bg-[color:var(--sf-accent,#111111)] p-8 text-white sm:p-10">
            <ImageWithFallback src={boutique?.logoUrl} alt={boutique?.logoUrl ? boutique.name : 'Hanooti'} className="h-14 w-14 rounded-full object-cover" />
            <p className="mt-10 text-xs font-bold uppercase tracking-[0.24em] text-white/60">Espace client</p>
            <h1 className="mt-3 text-4xl font-black tracking-tight">{boutique?.name || 'Votre boutique'}</h1>
            <p className="mt-4 text-sm leading-7 text-white/70">Retrouvez vos commandes, vos informations et une expérience personnalisée dans votre boutique.</p>
          </div>
          <div className="p-8 sm:p-10">
            <p className="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--sf-accent,#7C3AED)]">{isRegister ? 'Créer un compte client' : 'Connexion client'}</p>
            <h2 className="mt-3 text-3xl font-black">{isRegister ? 'Bienvenue parmi nous' : 'Ravi de vous revoir'}</h2>
            <form className="mt-8 grid gap-4" onSubmit={submit}>
              {isRegister && <div className="grid gap-4 sm:grid-cols-2"><label className="text-sm font-semibold">Prénom<input required value={firstName} onChange={(event) => setFirstName(event.target.value)} className="mt-2 w-full rounded-xl border border-[color:var(--sf-outline,#d8d0c4)] px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--sf-accent,#22C55E)]" /></label><label className="text-sm font-semibold">Nom<input required value={lastName} onChange={(event) => setLastName(event.target.value)} className="mt-2 w-full rounded-xl border border-[color:var(--sf-outline,#d8d0c4)] px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--sf-accent,#22C55E)]" /></label></div>}
              <label className="text-sm font-semibold"><span className="inline-flex items-center gap-2"> <Mail className="h-4 w-4" aria-hidden="true" /> Email</span><input required type="email" value={email} onChange={(event) => setEmail(event.target.value)} className="mt-2 w-full rounded-xl border border-[color:var(--sf-outline,#d8d0c4)] px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--sf-accent,#22C55E)]" /></label>
              {isRegister && <label className="text-sm font-semibold">Téléphone<input value={phone} onChange={(event) => setPhone(event.target.value)} className="mt-2 w-full rounded-xl border border-[color:var(--sf-outline,#d8d0c4)] px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--sf-accent,#22C55E)]" /></label>}
              <label className="text-sm font-semibold"><span className="inline-flex items-center gap-2"><LockKeyhole className="h-4 w-4" aria-hidden="true" /> Mot de passe</span><input required minLength={8} type="password" value={password} onChange={(event) => setPassword(event.target.value)} className="mt-2 w-full rounded-xl border border-[color:var(--sf-outline,#d8d0c4)] px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--sf-accent,#22C55E)]" /></label>
              {error && <p role="alert" className="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{error}</p>}
              <button type="submit" disabled={isSubmitting} className="mt-2 inline-flex min-h-12 cursor-pointer items-center justify-center rounded-full bg-[color:var(--sf-accent,#111111)] px-6 py-3 text-sm font-black text-white transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60">{isSubmitting ? 'Chargement...' : isRegister ? 'Créer mon compte' : 'Se connecter'}</button>
            </form>
            <p className="mt-6 text-center text-sm text-[color:var(--sf-text-muted,#6b6560)]">{isRegister ? 'Vous avez déjà un compte ?' : 'Nouveau dans cette boutique ?'} <a href={boutiqueLink(alternatePath)} className="font-bold text-[color:var(--sf-accent,#7C3AED)] hover:underline">{isRegister ? 'Se connecter' : 'Créer un compte'}</a></p>
          </div>
        </div>
      </div>
    </main>
  );
}

export function BoutiqueCustomerAccountPage() {
  const boutiqueSlug = resolveBoutiqueSlug(/^\/boutiques\/([^/]+)\/client\/account/);
  const [session, setSession] = useState<CustomerSession | null>(() => readSession(boutiqueSlug));

  useEffect(() => {
    if (!boutiqueSlug) return;
    window.__boutiqueSlug__ = boutiqueSlug;
    resetStorefrontTheme();
    fetch(`/api/boutiques/${boutiqueSlug}`)
      .then((response) => response.ok ? response.json() : null)
      .then((payload: CustomerBoutique | null) => {
        if (payload) applyStorefrontTheme(payload);
      })
      .catch(() => {});

    return resetStorefrontTheme;
  }, [boutiqueSlug]);

  useEffect(() => {
    if (!boutiqueSlug) return;
    window.__boutiqueSlug__ = boutiqueSlug;
    const current = readSession(boutiqueSlug);
    if (!current) {
      window.location.replace(boutiqueLink('/client/login'));
      return;
    }

    fetch(`/api/boutique/auth/me${boutiqueQuery(boutiqueSlug)}`, {
      headers: customerHeaders(boutiqueSlug),
      credentials: 'same-origin',
    })
      .then(async (response) => {
        if (!response.ok) throw new Error('Session expirée.');
        const payload = await response.json() as { customer: CustomerProfile };
        const next = { ...current, customer: payload.customer };
        saveSession(boutiqueSlug, next);
        setSession(next);
      })
      .catch(() => {
        clearSession(boutiqueSlug);
        setSession(null);
      });
  }, [boutiqueSlug]);

  if (!session) {
    return <main className="flex min-h-screen items-center justify-center bg-[color:var(--sf-bg,#f6f2eb)]">Redirection vers la connexion...</main>;
  }

  const customer = session.customer;
  const displayName = [customer.firstName, customer.lastName].filter(Boolean).join(' ') || customer.email;

  return (
    <main className="min-h-screen bg-[color:var(--sf-bg,#f6f2eb)] px-4 py-8 text-[color:var(--sf-text,#171717)] sm:px-6 lg:px-8">
      <div className="mx-auto max-w-4xl">
        <div className="flex items-center justify-between gap-4"><a href={boutiqueLink('/')} className="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-[color:var(--sf-text-muted,#6b6560)] hover:text-[color:var(--sf-text,#171717)]"><ArrowLeft className="h-4 w-4" aria-hidden="true" /> Boutique</a><button type="button" onClick={() => { clearSession(boutiqueSlug); window.location.assign(boutiqueLink('/')); }} className="inline-flex cursor-pointer items-center gap-2 rounded-full border border-[color:var(--sf-outline,#d8d0c4)] px-4 py-2 text-sm font-bold hover:bg-[color:var(--sf-surface,#ffffff)]"><LogOut className="h-4 w-4" aria-hidden="true" /> Déconnexion</button></div>
        <section className="mt-10 rounded-[2rem] border border-[color:var(--sf-outline,#d8d0c4)] bg-[color:var(--sf-surface,#ffffff)] p-8 shadow-xl sm:p-10"><p className="text-xs font-bold uppercase tracking-[0.2em] text-[color:var(--sf-accent,#7C3AED)]">Mon profil</p><h1 className="mt-3 text-4xl font-black">Bonjour {displayName}</h1><div className="mt-8 grid gap-4 sm:grid-cols-2"><div className="rounded-2xl bg-[color:var(--sf-surface-muted,#ece5d9)] p-5"><div className="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--sf-text-muted,#6b6560)]">Email</div><div className="mt-2 font-bold">{customer.email}</div></div><div className="rounded-2xl bg-[color:var(--sf-surface-muted,#ece5d9)] p-5"><div className="text-xs font-bold uppercase tracking-[0.15em] text-[color:var(--sf-text-muted,#6b6560)]">Téléphone</div><div className="mt-2 font-bold">{customer.phone || 'Non renseigné'}</div></div></div></section>
      </div>
    </main>
  );
}
