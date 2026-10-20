import { test, expect } from '@playwright/test';

test('beranda dan halaman login berasal dari aplikasi lokal', async ({ page }) => {
  const homeResponse = await page.goto('/');

  expect(homeResponse?.ok()).toBeTruthy();
  await expect(page).toHaveTitle(/Trikasa Energi Tama/);
  await expect(page.getByRole('link', { name: 'Masuk', exact: true })).toHaveAttribute('href', 'login.php');

  const loginResponse = await page.goto('/login.php');

  expect(loginResponse?.ok()).toBeTruthy();
  await expect(page.getByRole('heading', { name: 'Masuk ke akun Anda' })).toBeVisible();
  await expect(page.getByLabel('Email')).toBeVisible();
  await expect(page.getByLabel('Kata sandi')).toBeVisible();
});

test('pengunjung diarahkan ke login dari dashboard', async ({ page }) => {
  await page.goto('/dashboard.php');

  await expect(page).toHaveURL(/\/login\.php$/);
  await expect(page.getByRole('heading', { name: 'Masuk ke akun Anda' })).toBeVisible();
});

test('form login dapat dipakai pada lebar layar ponsel', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/login.php');

  await expect(page.getByLabel('Email')).toBeVisible();
  await expect(page.getByLabel('Kata sandi')).toBeVisible();
  await expect(page.getByRole('button', { name: /Masuk/ })).toBeVisible();
  await expect
    .poll(() => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth))
    .toBe(true);
});
