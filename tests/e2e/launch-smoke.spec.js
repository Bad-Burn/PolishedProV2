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

test('pricing offers only Premium and shows the yearly price', async ({ page }) => {
  const response = await page.goto('/pricing?cycle=yearly');

  expect(response.status()).toBe(200);
  await expect(page.locator('.plan__name')).toHaveCount(1);
  await expect(page.locator('.plan__name')).toHaveText('Premium');
  await expect(page.locator('.plan__amount')).toContainText('1,490');
  await expect(page.getByRole('link', { name: 'Yearly' })).toHaveClass(/is-active/);

  await page.getByRole('link', { name: 'Monthly' }).click();
  await expect(page.locator('.plan__amount')).toContainText('149');
  await page.getByRole('link', { name: 'Yearly' }).click();
  await expect(page.locator('.plan__amount')).toContainText('1,490');
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

test('PayPal webhook rejects an unsigned activation event', async ({ request }) => {
  const response = await request.post('/webhooks/paypal', {
    data: {
      id: 'unsigned-test-event',
      event_type: 'BILLING.SUBSCRIPTION.ACTIVATED',
      resource: { id: 'I-UNSIGNEDTEST' },
    },
  });

  expect(response.status()).toBe(401);
});
