export const SOCIAL_PROVIDERS = [
  { id: 'google', label: 'Google', icon: 'google' },
] as const;

export type SocialProvider = (typeof SOCIAL_PROVIDERS)[number]['id'];
