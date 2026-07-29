import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { BrandLogo } from '../components/BrandLogo';

export function EmailVerificationPage() {
  const [params] = useSearchParams();
  const [message, setMessage] = useState('Vérification de votre adresse email...');
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    const token = params.get('token');
    if (!token) {
      setMessage('Le lien de vérification est invalide.');
      return;
    }

    fetch(`/api/auth/verify-email?token=${encodeURIComponent(token)}`)
      .then(async (response) => {
        const payload = await response.json().catch(() => ({})) as { message?: string };
        if (!response.ok) throw new Error(payload.message ?? 'Le lien de vérification est invalide ou expiré.');
        setMessage(payload.message ?? 'Votre adresse email a été vérifiée.');
        setSuccess(true);
      })
      .catch((error: unknown) => {
        setMessage(error instanceof Error ? error.message : 'Le lien de vérification est invalide ou expiré.');
      });
  }, [params]);

  return (
    <main className="lovable-auth">
      <div className="lovable-auth__shell">
        <section className="lovable-auth__form-panel" style={{ gridColumn: '1 / -1' }}>
          <div className="lovable-auth__form-card" style={{ maxWidth: 520, margin: '0 auto', textAlign: 'center' }}>
            <Link className="lovable-brand" to="/"><BrandLogo /></Link>
            <p className="lovable-auth__eyebrow">Sécurité du compte</p>
            <h1>{success ? 'Email vérifié' : 'Vérification email'}</h1>
            <p>{message}</p>
            {success && <Link className="lovable-button lovable-auth__submit" to="/auth/login">Se connecter</Link>}
          </div>
        </section>
      </div>
    </main>
  );
}
