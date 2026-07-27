import type { ReactNode } from 'react';
import { ArrowRight, CheckCircle2, Menu, ShieldCheck, Truck, X } from 'lucide-react';
import { boutiqueLink } from '../../boutiqueRouting';
import { ImageWithFallback } from '../../../../components/ImageWithFallback';
import type { StoreBoutique } from '../StorefrontTheme';

export function TopBar({ boutique }: { boutique: StoreBoutique }) {
  return (
    <div className="bg-slate-950 px-4 py-2 text-xs font-bold text-white">
      <div className="mx-auto flex max-w-7xl justify-between gap-4">
        <span>Livraison suivie · Paiement securise</span>
        <span className="hidden sm:inline">{boutique.email || 'Support boutique'}</span>
      </div>
    </div>
  );
}

export function BrandMark({ boutique, large = false }: { boutique: StoreBoutique; large?: boolean }) {
  const size = large ? 'h-20 w-20 text-2xl' : 'h-11 w-11 text-base';

  return <ImageWithFallback src={boutique.logoUrl} alt={boutique.logoUrl ? boutique.name : 'Hanooti'} className={`${size} rounded-full object-cover`} />;
}

export function navItems() {
  return [
    { label: 'Accueil', href: boutiqueLink('/') },
    { label: 'Catalogue', href: boutiqueLink('/catalogue') },
    { label: 'Promotions', href: boutiqueLink('/promotions') },
    { label: 'Avis', href: boutiqueLink('/avis') },
    { label: 'A propos', href: boutiqueLink('/a-propos') },
    { label: 'Contact', href: boutiqueLink('/contact') },
  ];
}

export function MobileMenu({ onClose }: { onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-50 bg-slate-950/40 backdrop-blur-sm lg:hidden">
      <div className="h-full w-80 max-w-[86vw] bg-white p-6 shadow-2xl">
        <button type="button" onClick={onClose} className="mb-8 cursor-pointer rounded-full border p-2" aria-label="Fermer">
          <X className="h-5 w-5" />
        </button>
        <nav className="grid gap-3">
          {navItems().map((item) => (
            <a key={item.href} href={item.href} className="rounded-2xl px-4 py-3 font-black text-slate-800 hover:bg-[color:var(--sf-surface-muted,#F3E8FF)]">
              {item.label}
            </a>
          ))}
        </nav>
      </div>
    </div>
  );
}

export function Footer({ boutique }: { boutique: StoreBoutique }) {
  return (
    <footer className="mt-16 bg-slate-950 px-4 py-10 text-white sm:px-6 lg:px-8">
      <div className="mx-auto grid max-w-7xl gap-8 md:grid-cols-[1.4fr_1fr_1fr]">
        <div>
          <BrandMark boutique={boutique} />
          <p className="mt-4 max-w-sm text-sm leading-7 text-white/60">
            {boutique.description || 'Boutique propulsee par Hanooti Marketplace.'}
          </p>
        </div>
        <FooterGroup title="Boutique" links={[["Catalogue", "/catalogue"], ["Promotions", "/promotions"], ["Avis", "/avis"]]} />
        <FooterGroup title="Aide" links={[["A propos", "/a-propos"], ["Contact", "/contact"], ["Panier", "/cart"]]} />
      </div>
    </footer>
  );
}

function FooterGroup({ title, links }: { title: string; links: Array<[string, string]> }) {
  return (
    <div>
      <div className="font-black">{title}</div>
      <div className="mt-4 grid gap-2 text-sm text-white/60">
        {links.map(([label, href]) => (
          <a key={href} href={boutiqueLink(href)} className="hover:text-white">
            {label}
          </a>
        ))}
      </div>
    </div>
  );
}

export function PageHero({ title, subtitle }: { title: string; subtitle: string }) {
  return (
    <div className="rounded-[2rem] border border-white/70 bg-white/80 p-8 shadow-xl shadow-purple-950/5 backdrop-blur-xl">
      <div className="text-xs font-black uppercase tracking-[0.22em] text-[color:var(--sf-accent,#7C3AED)]">{title}</div>
      <h1 className="mt-3 text-4xl font-black tracking-[-0.04em] text-slate-950">{title}</h1>
      <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600">{subtitle}</p>
    </div>
  );
}

export function Section({ title, href, children }: { title: string; href: string; children: ReactNode }) {
  return (
    <section className="px-4 py-10 sm:px-6 lg:px-8">
      <div className="mx-auto max-w-7xl">
        <div className="mb-6 flex items-end justify-between gap-4">
          <h2 className="text-3xl font-black tracking-[-0.04em] text-slate-950">{title}</h2>
          <a href={href} className="inline-flex items-center gap-2 text-sm font-black text-[color:var(--sf-accent,#7C3AED)]">
            Tout voir <ArrowRight className="h-4 w-4" />
          </a>
        </div>
        {children}
      </div>
    </section>
  );
}

export function EmptyPanel({ title, text }: { title: string; text: string }) {
  return (
    <div className="rounded-[1.5rem] border border-dashed border-[color:var(--sf-outline,#DDD6FE)] bg-white/70 p-8 text-center">
      <div className="font-black text-slate-950">{title}</div>
      <p className="mt-2 text-sm text-slate-500">{text}</p>
    </div>
  );
}

export function TrustGrid() {
  const items = [
    { Icon: Truck, label: 'Livraison' },
    { Icon: ShieldCheck, label: 'Securise' },
    { Icon: CheckCircle2, label: 'Verifie' },
  ];

  return (
    <div className="grid grid-cols-3 gap-3">
      {items.map(({ Icon, label }) => (
        <div key={label} className="rounded-2xl bg-white p-4 text-center shadow-sm">
          <Icon className="mx-auto h-5 w-5 text-[color:var(--sf-accent,#22C55E)]" />
          <div className="mt-2 text-xs font-black text-slate-700">{label}</div>
        </div>
      ))}
    </div>
  );
}

export function Metric({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-[1.3rem] border border-[color:var(--sf-outline,#DDD6FE)] bg-white p-5">
      <div className="text-2xl font-black text-slate-950">{value}</div>
      <div className="mt-1 text-sm text-slate-500">{label}</div>
    </div>
  );
}

export function ContactLine({ icon, text, href }: { icon: ReactNode; text: string; href?: string }) {
  const content = <span className="flex w-full min-w-0 items-start gap-3 rounded-2xl bg-white px-4 py-3 font-bold text-slate-700 shadow-sm"> <span className="mt-0.5 shrink-0">{icon}</span><span className="min-w-0 break-words">{text}</span></span>;
  return href ? <a href={href} className="block w-full">{content}</a> : content;
}

export function Field({ label, type = 'text' }: { label: string; type?: string }) {
  return (
    <label className="mt-4 block text-sm font-black text-slate-700">
      {label}
      <input type={type} className="mt-2 w-full rounded-2xl border border-[color:var(--sf-outline,#DDD6FE)] px-4 py-3 font-normal outline-none focus:ring-2 focus:ring-[color:var(--sf-accent,#22C55E)]" />
    </label>
  );
}
