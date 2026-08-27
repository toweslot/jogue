/**
 * Relay gratuito AmploPay - Cloudflare Workers
 * -------------------------------------------------
 * O seu site chama este Worker, e o Worker chama a AmploPay.
 * Como o IP de saida passa a ser da Cloudflare (reputacao alta),
 * o WAF da AmploPay para de devolver HTTP 403.
 *
 * Como publicar (leva ~3 minutos, plano gratuito):
 *  1. dash.cloudflare.com -> Workers & Pages -> Create -> Worker
 *  2. Nome: amplopay-relay -> Deploy -> Edit code
 *  3. Apague tudo e cole ESTE arquivo -> Deploy
 *  4. Settings -> Variables -> Add variable:
 *        Nome:  RELAY_SECRET
 *        Valor: uma senha longa qualquer (ex.: 40 caracteres aleatorios)
 *     (marque "Encrypt") -> Save and deploy
 *  5. No .env do site coloque:
 *        AMPLOPAY_BASE_URL=https://amplopay-relay.SEU-USUARIO.workers.dev/api/v1
 *        AMPLOPAY_RELAY_SECRET=a mesma senha do passo 4
 */

const UPSTREAM = 'https://app.amplopay.com';

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // healthcheck simples
    if (url.pathname === '/' || url.pathname === '/health') {
      return new Response('amplopay-relay ok', { status: 200 });
    }

    // so aceita chamadas do seu proprio site
    const expected = env.RELAY_SECRET || '';
    if (expected === '' || request.headers.get('x-relay-secret') !== expected) {
      return new Response('forbidden', { status: 401 });
    }

    const target = UPSTREAM + url.pathname + url.search;

    const headers = new Headers();
    headers.set('content-type', request.headers.get('content-type') || 'application/json');
    headers.set('accept', 'application/json');
    for (const h of ['x-public-key', 'x-secret-key', 'authorization']) {
      const v = request.headers.get(h);
      if (v) headers.set(h, v);
    }

    const init = {
      method: request.method,
      headers,
      redirect: 'follow',
    };
    if (!['GET', 'HEAD'].includes(request.method)) {
      init.body = await request.text();
    }

    let upstream;
    try {
      upstream = await fetch(target, init);
    } catch (e) {
      return new Response(JSON.stringify({ message: 'relay_error: ' + e.message }), {
        status: 502,
        headers: { 'content-type': 'application/json' },
      });
    }

    const body = await upstream.text();
    return new Response(body, {
      status: upstream.status,
      headers: {
        'content-type': upstream.headers.get('content-type') || 'application/json',
        'x-relay': 'cloudflare-worker',
      },
    });
  },
};
