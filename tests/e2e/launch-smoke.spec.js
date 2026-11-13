const { test, expect } = require('@playwright/test');

test('home page renders the primary booking entry point', async ({ page }) => {
  const response = await page.goto('/');

  expect(response.status()).toBe(200);
  await expect(page.getByRole('heading', { name: /Book the artist/ })).toBeVisible();
});

test('public help page renders with its stylesheet and security headers', async ({ page, request }) => {
  const response = await page.goto('/help');

  expect(response.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'How PolishedPro works' })).toBeVisible();

  const css = await request.get('/assets/css/app.css');
  expect(css.status()).toBe(200);
  expect(css.headers()['content-type']).toContain('text/css');

  const csp = response.headers()['content-security-policy'];
  expect(csp).toContain("default-src 'self'");
  expect(csp).toContain("frame-ancestors 'none'");
});

test('sign-in form renders a CSRF token', async ({ page }) => {
  const response = await page.goto('/login');

  expect(response.status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible();
  await expect(page.locator('input[name="_token"]')).toHaveAttribute('value', /^[a-f0-9]{64}$/);
});

test('application internals are not served from the public document root', async ({ request }) => {
  for (const path of ['/.env', '/app/Core/Auth.php', '/storage/logs/app.log']) {
    const response = await request.get(path);
    expect(response.status(), `${path} should not be public`).toBeGreaterThanOrEqual(400);
  }
});
