# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke.spec.ts >> storefront and authentication >> navigates categories and filters catalogue products
- Location: tests/e2e/smoke.spec.ts:27:7

# Error details

```
Error: expect(locator).toHaveAttribute(expected) failed

Locator: getByRole('link', { name: 'Mode' }).first()
Expected pattern: /categories\/mode/
Received string:  "/categories/demo-hanooti-mode"
Timeout: 5000ms

Call log:
  - Expect "toHaveAttribute" with timeout 5000ms
  - waiting for getByRole('link', { name: 'Mode' }).first()
    14 × locator resolved to <a href="/categories/demo-hanooti-mode" class="text-xs font-black uppercase tracking-[0.18em] text-[color:var(--sf-accent,#7C3AED)]">Mode</a>
       - unexpected value "/categories/demo-hanooti-mode"

```

```yaml
- link "Mode":
  - /url: /categories/demo-hanooti-mode
```

# Test source

```ts
  1  | import { expect, test } from '@playwright/test';
  2  | 
  3  | test.describe('storefront and authentication', () => {
  4  |   test('renders the storefront catalogue', async ({ page }) => {
  5  |     await page.goto('/catalogue');
  6  | 
  7  |     await expect(page.locator('body')).toContainText(/catalogue|produits|boutique/i);
  8  |   });
  9  | 
  10 |   test('renders the cart and checkout entry points', async ({ page }) => {
  11 |     await page.goto('/cart');
  12 |     await expect(page.locator('body')).toContainText(/panier|cart/i);
  13 | 
  14 |     await page.goto('/checkout');
  15 |     await expect(page.locator('body')).toContainText(/commande|checkout|paiement|panier/i);
  16 |   });
  17 | 
  18 |   test('shows an error for invalid credentials', async ({ page }) => {
  19 |     await page.goto('/auth/login');
  20 |     await page.getByLabel('Email professionnel').fill('invalid@example.test');
  21 |     await page.locator('#password').fill('invalid-password');
  22 |     await page.getByRole('button', { name: 'Se connecter' }).click();
  23 | 
  24 |     await expect(page.locator('.lovable-auth__error')).toBeVisible();
  25 |   });
  26 | 
  27 |   test('navigates categories and filters catalogue products', async ({ page }) => {
  28 |     await page.goto('/catalogue');
  29 |     await expect(page.getByRole('heading', { name: 'Catalogue' })).toBeVisible();
  30 |     await expect(page.getByText(/produit\(s\)/)).toBeVisible();
  31 | 
  32 |     const categoryLink = page.getByRole('link', { name: 'Mode' }).first();
> 33 |     await expect(categoryLink).toHaveAttribute('href', /categories\/mode/);
     |                                ^ Error: expect(locator).toHaveAttribute(expected) failed
  34 |     await categoryLink.click();
  35 |     await expect(page.getByRole('heading', { name: 'Mode' })).toBeVisible();
  36 |     await expect(page.getByText('2 produit(s)')).toBeVisible();
  37 | 
  38 |     await page.locator('input[placeholder="Min"]').first().fill('500');
  39 |     await expect(page.getByText('1 produit(s)')).toBeVisible();
  40 |     await expect(page.getByRole('link', { name: 'Sac Medina cuir', exact: true })).toBeVisible();
  41 |   });
  42 | });
  43 | 
  44 | test.describe('backoffice authentication', () => {
  45 |   test.skip(
  46 |     !process.env.E2E_ADMIN_EMAIL || !process.env.E2E_ADMIN_PASSWORD,
  47 |     'Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD for the authenticated journey',
  48 |   );
  49 | 
  50 |   test('admin can open the dashboard', async ({ page }) => {
  51 |     await page.goto('/auth/login');
  52 |     await page.getByLabel('Email professionnel').fill(process.env.E2E_ADMIN_EMAIL!);
  53 |     await page.locator('#password').fill(process.env.E2E_ADMIN_PASSWORD!);
  54 |     const loginResponse = page.waitForResponse((response) => response.url().endsWith('/api/auth/login'));
  55 |     await page.getByRole('button', { name: 'Se connecter' }).click();
  56 |     await expect((await loginResponse).status()).toBe(200);
  57 | 
  58 |     await page.goto('/admin/dashboard');
  59 |     await expect(page).toHaveURL(/\/admin\//);
  60 |     await expect(page.locator('body')).toContainText(/tableau de bord|dashboard/i);
  61 |   });
  62 | });
  63 | 
  64 | test.describe('API catalogue CRUD and boutique isolation', () => {
  65 |   test.skip(
  66 |     !process.env.E2E_ADMIN_EMAIL || !process.env.E2E_ADMIN_PASSWORD,
  67 |     'Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD for authenticated API journeys',
  68 |   );
  69 | 
  70 |   test('admin can create, update and delete a category', async ({ request }) => {
  71 |     const host = new URL(process.env.BASE_URL ?? 'http://demo-hanooti.localhost:8082').host;
  72 |     const login = await request.post('/api/auth/login', {
  73 |       headers: { Host: host },
  74 |       data: { email: process.env.E2E_ADMIN_EMAIL, password: process.env.E2E_ADMIN_PASSWORD },
  75 |     });
  76 |     expect(login.ok()).toBeTruthy();
  77 |     const { accessToken } = await login.json();
  78 |     const headers = { Authorization: `Bearer ${accessToken}`, Host: host };
  79 | 
  80 |     const created = await request.post('/api/categories', {
  81 |       headers,
  82 |       data: { name: `E2E Category ${Date.now()}` },
  83 |     });
  84 |     expect(created.status()).toBe(201);
  85 |     const category = await created.json();
  86 | 
  87 |     try {
  88 |       const updated = await request.patch(`/api/categories/${category.id}`, {
  89 |         headers: { ...headers, 'Content-Type': 'application/merge-patch+json' },
  90 |         data: { name: `E2E Category Updated ${Date.now()}` },
  91 |       });
  92 |       expect(updated.ok()).toBeTruthy();
  93 |     } finally {
  94 |       const deleted = await request.delete(`/api/categories/${category.id}`, { headers });
  95 |       expect([200, 204]).toContain(deleted.status());
  96 |     }
  97 |   });
  98 | });
  99 | 
```