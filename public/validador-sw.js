// Service worker do "App Validador" (PWA da portaria). Propositalmente
// mínimo: só existe pra deixar o app instalável (Chrome/Android exige um SW
// com fetch handler ativo pro prompt de instalação aparecer). Não cacheia a
// página de validação — ela tem o token CSRF preso à sessão; cachear
// serviria um token velho e quebraria o POST de validação com erro 419.
// Só os ícones estáticos ficam em cache, pra abrir mais rápido.
const CACHE_NOME = 'validador-v1';
const ARQUIVOS_ESTATICOS = [
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/icon-maskable-192.png',
    '/pwa/icon-maskable-512.png',
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(caches.open(CACHE_NOME).then((cache) => cache.addAll(ARQUIVOS_ESTATICOS)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((chaves) => Promise.all(chaves.filter((c) => c !== CACHE_NOME).map((c) => caches.delete(c))))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (ARQUIVOS_ESTATICOS.includes(url.pathname)) {
        event.respondWith(caches.match(event.request).then((resposta) => resposta || fetch(event.request)));
        return;
    }

    // Tudo mais (a página, a validação, o manifest): sempre rede, nunca cache.
    event.respondWith(fetch(event.request));
});
