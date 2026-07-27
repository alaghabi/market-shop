import { useState } from 'react';
import { ProductCard, type StoreProduct } from './ProductCard';

export function ProductGrid({
  products,
  categories,
}: {
  products: StoreProduct[];
  categories: string[];
}) {
  const [activeCategory, setActiveCategory] = useState<string>('Toutes');
  const filtered = activeCategory === 'Toutes' ? products : products.filter((p) => p.categoryName === activeCategory);

  return (
    <section className="mx-auto max-w-7xl px-4 pt-8">
      <div className="mb-6 flex items-center justify-between">
        <h2 className="text-2xl font-bold text-[color:var(--ds-on-surface)]">Nos produits</h2>
      </div>

      <div className="mb-6 flex gap-2 overflow-x-auto pb-2 scrollbar-none">
        {['Toutes', ...categories].map((cat) => (
          <button
            key={cat}
            onClick={() => setActiveCategory(cat)}
            className={`shrink-0 rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
              activeCategory === cat
                ? 'bg-[color:var(--ds-primary)] text-white'
                : 'bg-[color:var(--ds-surface-container)] text-[color:var(--ds-on-surface-variant)] hover:bg-[color:var(--ds-surface-container)]/80'
            }`}
          >
            {cat}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8">
        {filtered.map((p) => (
          <ProductCard key={p.id} product={p} />
        ))}
      </div>

      {filtered.length === 0 && (
        <p className="py-20 text-center text-sm text-[color:var(--ds-on-surface-variant)]">Aucun produit trouvé.</p>
      )}
    </section>
  );
}
