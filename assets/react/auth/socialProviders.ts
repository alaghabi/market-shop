export const SOCIAL_PROVIDERS = [
  { id: 'google', label: 'Google', icon: 'google' },
  { id: 'microsoft', label: 'Microsoft', icon: 'microsoft' },
] as const;

export type SocialProvider = (typeof SOCIAL_PROVIDERS)[number]['id'];
