export const SOCIAL_PROVIDERS = [
  { id: 'google', label: 'Google', icon: 'google' },
  { id: 'microsoft', label: 'Microsoft', icon: 'microsoft' },
  { id: 'facebook', label: 'Facebook', icon: 'facebook' },
] as const;

export type SocialProvider = (typeof SOCIAL_PROVIDERS)[number]['id'];
