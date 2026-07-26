export function BoutiqueMetrics({
  customersWithAccount = 0,
  customersWithoutAccount = 0,
  ordersCount = 0,
  customerAccountsEnabled = true,
}: {
  customersWithAccount?: number;
  customersWithoutAccount?: number;
  ordersCount?: number;
  customerAccountsEnabled?: boolean;
}) {
  const metrics = [
    ...(customerAccountsEnabled ? [{ label: 'Clients avec compte', value: customersWithAccount }] : []),
    { label: 'Clients sans compte', value: customersWithoutAccount },
    { label: 'Commandes', value: ordersCount },
  ];

  return (
    <section className="px-4 py-10 sm:px-6 lg:px-8" aria-label="Statistiques de la boutique">
      <div className={`mx-auto grid max-w-7xl gap-4 ${metrics.length >= 3 ? 'sm:grid-cols-3' : metrics.length === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-1'}`}>
        {metrics.map((metric) => (
          <div key={metric.label} className="rounded-[1.5rem] border border-[color:var(--sf-outline,#DDD6FE)] bg-white p-6">
            <div className="text-3xl font-black text-[color:var(--sf-accent,#111111)]">{metric.value.toLocaleString('fr-FR')}</div>
            <div className="mt-2 text-sm font-bold text-slate-500">{metric.label}</div>
          </div>
        ))}
      </div>
    </section>
  );
}
