import { Mail, MapPin, ExternalLink } from 'lucide-react';

export function StoreFooter({ name, email, address }: { name: string; email?: string | null; address?: string | null }) {
  return (
    <footer className="mt-16 bg-[color:var(--ds-on-surface)] text-white">
      <div className="mx-auto max-w-7xl px-4 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
        <div>
          <div className="flex items-center gap-2">
            <div className="grid h-9 w-9 place-items-center rounded-lg bg-[color:var(--ds-primary)] font-black text-white">N</div>
            <span className="text-lg font-black">{name}</span>
          </div>
           <p className="mt-3 text-sm text-white/70">Retrouvez toute la sélection de {name} et commandez en ligne.</p>
          <div className="mt-4 flex gap-3">
            <a href="#" className="opacity-80 hover:opacity-100" aria-label="Instagram"><ExternalLink className="h-4 w-4" /></a>
            <a href="#" className="opacity-80 hover:opacity-100" aria-label="Facebook"><ExternalLink className="h-4 w-4" /></a>
            <a href="#" className="opacity-80 hover:opacity-100" aria-label="Twitter"><ExternalLink className="h-4 w-4" /></a>
          </div>
        </div>
        <div>
          <h4 className="mb-3 text-sm font-semibold">Boutique</h4>
          <ul className="space-y-2 text-sm text-white/70">
            <li><a href="#" className="hover:text-white">Catalogue</a></li>
            <li><a href="#" className="hover:text-white">Nouveautés</a></li>
            <li><a href="#" className="hover:text-white">Promotions</a></li>
          </ul>
        </div>
        <div>
          <h4 className="mb-3 text-sm font-semibold">Aide</h4>
          <ul className="space-y-2 text-sm text-white/70">
            <li><a href="#" className="hover:text-white">Contact</a></li>
            <li><a href="#" className="hover:text-white">CGV</a></li>
            <li><a href="#" className="hover:text-white">Livraison & retours</a></li>
          </ul>
        </div>
        <div>
          <h4 className="mb-3 text-sm font-semibold">Newsletter</h4>
           <p className="mb-3 text-sm text-white/70">Recevez nos nouveautés et offres.</p>
          <form className="flex gap-2" onSubmit={(e) => e.preventDefault()}>
            <input
              type="email"
              placeholder="Votre email"
              className="flex-1 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder:text-white/50 outline-none focus:border-white/40"
            />
            <button type="submit" className="rounded-lg bg-[color:var(--ds-primary)] px-4 py-2 text-sm font-bold text-white hover:opacity-90 transition-opacity">OK</button>
          </form>
        </div>
      </div>
      <div className="border-t border-white/10">
        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-white/60">
          <span>© 2026 {name}. Tous droits réservés.</span>
          {address && <span className="flex items-center gap-1"><MapPin className="h-3 w-3" /> {address}</span>}
          {email && <span className="flex items-center gap-1"><Mail className="h-3 w-3" /> {email}</span>}
        </div>
      </div>
    </footer>
  );
}
