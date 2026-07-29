import DOMPurify from 'dompurify';

const ALLOWED_URI_REGEXP = /^(?:(?:https?|mailto|tel):|\/|#)/i;

export function sanitizeCmsHtml(value: string): string {
  return DOMPurify.sanitize(value, {
    ALLOWED_TAGS: [
      'a', 'blockquote', 'br', 'code', 'em', 'h2', 'h3', 'h4', 'li', 'ol', 'p', 'pre', 'strong', 'ul',
    ],
    ALLOWED_ATTR: ['href', 'title', 'rel'],
    ALLOW_DATA_ATTR: false,
    FORBID_ATTR: ['style', 'target'],
    ALLOWED_URI_REGEXP,
  });
}
