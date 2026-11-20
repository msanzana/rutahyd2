const { test, expect } = require('@playwright/test');

// Estas pruebas son de solo lectura / autenticación rechazada, sin modificar
// clientes, productos, ventas o datos de InfinityFree.
test('la API identifica RutaH&D2 V2 en español', async ({ request }) => {
  const respuesta = await request.get('/api/estado', {
    headers: { Accept: 'application/json' },
  });
  expect(respuesta.status()).toBe(200);
  expect(await respuesta.json()).toMatchObject({
    servicio: 'RutaH&D2 API',
    estado: 'disponible',
    version: 'v2-es',
  });
});

test('el navegador Chromium puede abrir la respuesta de salud', async ({ page }) => {
  const respuesta = await page.goto('/api/estado');
  expect(respuesta && respuesta.status()).toBe(200);
  await expect(page.locator('body')).toContainText('disponible');
});

test('el perfil protegido exige credenciales', async ({ request }) => {
  const respuesta = await request.get('/api/v1/perfil', {
    headers: { Accept: 'application/json' },
  });
  expect(respuesta.status()).toBe(401);
});

test('no se puede enumerar el catálogo sin autenticarse', async ({ request }) => {
  const respuesta = await request.get('/api/v1/recursos/productos', {
    headers: { Accept: 'application/json' },
  });
  expect(respuesta.status()).toBe(401);
});

test('no se aceptan tokens falsos para consultar ventas', async ({ request }) => {
  const respuesta = await request.get('/api/v1/recursos/ventas', {
    headers: { Accept: 'application/json', Authorization: 'Bearer prueba-no-valida' },
  });
  expect(respuesta.status()).toBe(401);
});

test('archivos privados no se entregan desde el servidor Laravel', async ({ request }) => {
  for (const ruta of [
    '/.env',
    '/vendor/autoload.php',
    '/app/Models/Usuario.php',
    '/composer.json',
  ]) {
    const respuesta = await request.get(ruta, { failOnStatusCode: false });
    expect([401, 403, 404]).toContain(respuesta.status());
  }
});
