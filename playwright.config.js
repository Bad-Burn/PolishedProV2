const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  fullyParallel: true,
  reporter: 'list',
  use: {
    baseURL: 'http://127.0.0.1:8765',
    trace: 'retain-on-failure',
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
  webServer: {
    command: 'php -S 127.0.0.1:8765 -t public bin/router.php',
    url: 'http://127.0.0.1:8765/help',
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
    env: {
      APP_ENV: 'local',
      APP_DEBUG: 'false',
      APP_URL: 'http://127.0.0.1:8765',
    },
  },
});
