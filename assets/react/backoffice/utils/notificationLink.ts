type NotificationTarget = {
  type: string;
  boutiqueId?: string | null;
};

export function notificationLink(
  notification: NotificationTarget,
  isSuperAdmin = false,
): string {
  const type = notification.type.toLowerCase();

  if (isSuperAdmin && type.includes("publication")) return "/admin/boutiques";
  if (
    isSuperAdmin &&
    notification.boutiqueId &&
    (type.includes("boutique") ||
      type.includes("subscription") ||
      type.includes("extension"))
  ) {
    return `/admin/boutiques/${encodeURIComponent(notification.boutiqueId)}`;
  }
  if (type.includes("order") || type.includes("commande"))
    return "/admin/orders";
  if (type.includes("stock") || type.includes("inventory"))
    return "/admin/products";
  if (type.includes("chat") || type.includes("message")) return "/admin/chat";
  if (type.includes("subscription") || type.includes("extension"))
    return "/admin/subscriptions";
  if (
    type.includes("payment") ||
    type.includes("invoice") ||
    type.includes("refund")
  )
    return "/admin/analytics";

  return "/admin/notifications";
}
