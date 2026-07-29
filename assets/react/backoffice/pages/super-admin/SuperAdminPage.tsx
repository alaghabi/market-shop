import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody } from '../../components/Card';
import { PageHeader } from '../../layout/Shell';

const LINKS = [
  {
    slug: 'boutiques', title: 'Boutiques', desc: 'Gérer les boutiques, approuver/rejeter, suspendre/activer, publier/dépublier',
    icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', color: '#6366f1',
  },
  {
    slug: 'statistics', title: 'Statistiques', desc: 'KPIs plateforme, indicateurs par module, top boutiques',
    icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z', color: '#22c55e',
  },
  {
    slug: 'analytics', title: 'Analyse & Monitoring', desc: 'Analytiques, logs, audit, surveillance infrastructure',
    icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', color: '#f43f5e',
  },
  {
    slug: 'boutique-admins', title: 'Admins boutique', desc: 'Gérer les administrateurs des boutiques',
    icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z', color: '#8b5cf6',
  },
  {
    slug: 'modules', title: 'Modules', desc: 'Activation/désactivation des modules globaux et par boutique',
    icon: 'M14.25 6.75a2.25 2.25 0 114.5 0v.75h.75a2.25 2.25 0 010 4.5h-.75v.75a2.25 2.25 0 11-4.5 0V12h-.75a2.25 2.25 0 010-4.5h.75v-.75zM9.75 12a2.25 2.25 0 10-4.5 0v.75H4.5a2.25 2.25 0 100 4.5h.75V18a2.25 2.25 0 104.5 0v-.75h.75a2.25 2.25 0 100-4.5h-.75V12z', color: '#f59e0b',
  },
  {
    slug: 'themes', title: 'Thèmes', desc: 'Gestion des thèmes disponibles sur la plateforme',
    icon: 'M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125A3.75 3.75 0 0021 17.25v-9.5A3.75 3.75 0 0017.25 4H9.75', color: '#ec4899',
  },
  {
    slug: 'platform-settings', title: 'Réseaux plateforme', desc: 'Configurer les réseaux sociaux officiels visibles sur le frontoffice général',
    icon: 'M12 2a10 10 0 100 20 10 10 0 000-20zm6.93 6h-3.08a15.7 15.7 0 00-1.19-3.07A8.03 8.03 0 0118.93 8zM12 4c.83 1.2 1.46 2.54 1.84 4h-3.68C10.54 6.54 11.17 5.2 12 4zM4.26 14a8.02 8.02 0 010-4h3.33a16.4 16.4 0 000 4H4.26zm.81 2h3.08c.27 1.1.67 2.13 1.19 3.07A8.03 8.03 0 015.07 16zM5.07 8a8.03 8.03 0 014.27-3.07A15.7 15.7 0 008.15 8H5.07zM12 20c-.83-1.2-1.46-2.54-1.84-4h3.68c-.38 1.46-1.01 2.8-1.84 4zm2.26-6H9.74a14.7 14.7 0 010-4h4.52a14.7 14.7 0 010 4zm.4 5.07A15.7 15.7 0 0015.85 16h3.08a8.03 8.03 0 01-4.27 3.07zM16.41 14a16.4 16.4 0 000-4h3.33a8.02 8.02 0 010 4h-3.33z', color: '#0ea5e9',
  },
 ];

export function SuperAdminPage({ getAccessToken: _t }: { getAccessToken: () => string | null }) {
  const links = useMemo(() => LINKS, []);
  return (
    <div className="bo-page">
      <PageHeader
        title="Super Admin"
        description="Administration globale de la plateforme Hanooti"
      />
      <div className="bo-page-content">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: 16 }}>
          {links.map((link) => (
            <Link key={link.slug} to={`/admin/${link.slug}`} style={{ textDecoration: 'none' }}>
              <Card>
                <CardBody>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 14 }}>
                    <div style={{
                      width: 48, height: 48, borderRadius: 12,
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      background: `${link.color}15`, flexShrink: 0,
                    }}>
                      <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}
                        style={{ width: 24, height: 24, color: link.color }}>
                        <path strokeLinecap="round" strokeLinejoin="round" d={link.icon} />
                      </svg>
                    </div>
                    <div>
                      <div style={{ fontWeight: 700, fontSize: 15, color: 'var(--bo-text)' }}>{link.title}</div>
                      <div style={{ fontSize: 12, color: 'var(--bo-text-muted)', marginTop: 2 }}>{link.desc}</div>
                    </div>
                  </div>
                </CardBody>
              </Card>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
