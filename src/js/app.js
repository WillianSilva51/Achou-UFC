const AUTH_KEY = 'achou_ufc_auth';
const API_BASE_URL = window.ACHOU_API_BASE_URL || detectApiBaseUrl();
const SAFE_REDIRECT_PAGES = new Set([
    'admin.html',
    'cadastrar.html',
    'editar_item.html',
    'gestao_entidades.html',
    'gestao_usuarios.html',
    'minhas_reivindicacoes.html',
    'perfil.html',
    'reivindicar.html',
    'vitrine.html'
]);
const HEADER_VARIANTS = {
    public: {
        brandHref: 'auth_hub.html',
        layout: 'menu',
        links: [
            { href: 'vitrine.html', icon: 'bi-grid-3x3-gap', label: 'Vitrine' },
            { href: 'auth_hub.html', icon: 'bi-shield-lock', label: 'Acessar', auth: true, className: 'nav-link-login' }
        ]
    },
    home: {
        brandHref: 'auth_hub.html',
        layout: 'menu',
        links: [
            { href: '#vitrine', icon: 'bi-grid-3x3-gap', label: 'Vitrine' },
            { href: '#como-funciona', icon: 'bi-info-circle', label: 'Como Funciona' },
            { href: 'auth_hub.html', icon: 'bi-shield-lock', label: 'Acessar', auth: true, className: 'nav-link-login' }
        ]
    },
    claim: {
        brandHref: 'auth_hub.html',
        layout: 'actions',
        actions: [
            { href: 'vitrine.html', icon: 'bi-arrow-left', label: 'Voltar à vitrine', className: 'btn btn-sm btn-gold' }
        ],
        authAction: true
    },
    profile: {
        brandHref: 'auth_hub.html',
        brandSuffix: 'Perfil',
        layout: 'actions',
        actions: [
            { href: 'vitrine.html', icon: 'bi-arrow-left', label: 'Voltar', className: 'btn btn-sm btn-outline-light', id: 'perfil-voltar' }
        ],
        authAction: true
    },
    admin: {
        brandHref: 'admin.html',
        brandSuffix: 'Painel',
        containerClass: 'container-fluid',
        layout: 'actions',
        actions: [
            { href: 'vitrine.html', icon: 'bi-box-arrow-up-right', label: 'Vitrine', className: 'btn btn-sm btn-outline-light' }
        ],
        authAction: true
    },
    adminBack: {
        brandHref: 'admin.html',
        brandSuffix: 'Painel',
        containerClass: 'container-fluid',
        layout: 'actions',
        actions: [
            { href: 'admin.html', icon: 'bi-arrow-left', label: 'Painel', className: 'btn btn-sm btn-outline-light' }
        ],
        authAction: true
    }
};

let categorias = [];
let locais = [];

const ICONES_POR_NOME = {
    chave: 'bi-key-fill',
    chaves: 'bi-key-fill',
    garrafa: 'bi-cup-straw',
    garrafas: 'bi-cup-straw',
    documento: 'bi-file-earmark-text-fill',
    documentos: 'bi-file-earmark-text-fill',
    eletronico: 'bi-phone-fill',
    eletronicos: 'bi-phone-fill',
    eletrônico: 'bi-phone-fill',
    eletrônicos: 'bi-phone-fill',
    bolsa: 'bi-bag-fill',
    bolsas: 'bi-bag-fill',
    livro: 'bi-book-fill',
    livros: 'bi-book-fill'
};

function getAuth() {
    try {
        localStorage.removeItem(AUTH_KEY);

        const auth = JSON.parse(sessionStorage.getItem(AUTH_KEY) || 'null');
        if (!auth) return null;

        if (!isValidAuth(auth)) {
            clearAuth();
            return null;
        }

        return auth;
    } catch {
        clearStoredAuth();
        return null;
    }
}

function setAuth(auth) {
    if (!isValidAuth(auth)) {
        throw apiError('Sessão inválida recebida do servidor.', 401);
    }

    clearStoredAuth();
    sessionStorage.setItem(AUTH_KEY, JSON.stringify(auth));
    renderAuthArea();
}

function clearAuth() {
    clearStoredAuth();
    renderAuthArea();
}

function clearStoredAuth() {
    sessionStorage.removeItem(AUTH_KEY);
    localStorage.removeItem(AUTH_KEY);
}

function authToken() {
    return getAuth()?.token || '';
}

function authUser() {
    return getAuth()?.usuario || null;
}

function isLoggedIn() {
    return Boolean(authToken());
}

function requireAuth(role = null) {
    const user = authUser();
    if (!isLoggedIn()) {
        const next = encodeURIComponent(`${location.pathname.split('/').pop() || 'auth_hub.html'}${location.search}`);
        location.href = `login.html?next=${next}`;
        return false;
    }

    if (role && user?.role !== role) {
        location.href = authHomeFor(user);
        return false;
    }

    return true;
}

async function logout() {
    try {
        if (authToken()) {
            await AchouApi.logout();
        }
    } catch {
        // O logout no cliente ainda deve acontecer mesmo que o token tenha expirado.
    } finally {
        clearAuth();
        location.href = 'login.html';
    }
}

function authHomeFor(user = authUser()) {
    return user?.role === 'admin' ? 'admin.html' : 'vitrine.html';
}

function domEl(tagName, className = '', attributes = {}) {
    const element = document.createElement(tagName);

    if (className) {
        element.className = className;
    }

    Object.entries(attributes).forEach(([name, value]) => {
        if (value === undefined || value === null) return;
        element.setAttribute(name, String(value));
    });

    return element;
}

function domIcon(iconClass) {
    return domEl('i', `bi ${iconClass}`);
}

function appendIconText(parent, iconClass, text) {
    parent.append(domIcon(iconClass), document.createTextNode(` ${text}`));
}

async function renderHeaderComponents() {
    const placeholders = Array.from(document.querySelectorAll('[data-header]'));
    if (!placeholders.length) return;

    placeholders.forEach((placeholder, index) => {
        const header = createHeaderElement();
        applyHeaderVariant(header, placeholder, index);
        placeholder.replaceWith(header);
    });
}

function createHeaderElement() {
    const header = domEl('header', 'header');
    const nav = domEl('nav', 'navbar navbar-dark navbar-expand-lg navbar-ufc');
    const container = domEl('div', 'container', { 'data-header-container': '' });
    const brand = domEl('a', 'navbar-brand d-flex align-items-center gap-2', {
        href: 'auth_hub.html',
        'data-header-brand': ''
    });
    const logo = domEl('img', 'brand-logo', {
        src: 'assets/images/Brasao4_vertical_cor_300dpi.png',
        alt: 'Logo da UFC'
    });
    const brandTitle = domEl('span', 'brand-title', { 'data-header-brand-title': '' });
    const accent = domEl('span', 'accent');
    const toggler = domEl('button', 'navbar-toggler', {
        type: 'button',
        'data-bs-toggle': 'collapse',
        'data-bs-target': '#nav',
        'aria-controls': 'nav',
        'aria-label': 'Abrir menu',
        'data-header-toggler': ''
    });
    const togglerIcon = domEl('span', 'navbar-toggler-icon');
    const collapse = domEl('div', 'collapse navbar-collapse', {
        id: 'nav',
        'data-header-collapse': ''
    });
    const menu = domEl('ul', 'navbar-nav ms-auto gap-2', { 'data-header-menu': '' });
    const actions = domEl('div', 'd-none align-items-center gap-2 ms-auto', { 'data-header-actions': '' });

    accent.textContent = 'UFC';
    brandTitle.append('Achou! ', accent);
    brand.append(logo, brandTitle);
    toggler.appendChild(togglerIcon);
    collapse.appendChild(menu);
    container.append(brand, toggler, collapse, actions);
    nav.appendChild(container);
    header.appendChild(nav);

    return header;
}

function applyHeaderVariant(header, placeholder, index) {
    const variantName = placeholder.dataset.header || 'public';
    const variant = HEADER_VARIANTS[variantName] || HEADER_VARIANTS.public;
    const config = {
        ...variant,
        brandSuffix: placeholder.dataset.headerTitle ?? variant.brandSuffix
    };

    const navId = `nav-${index + 1}`;
    const container = header.querySelector('[data-header-container]');
    const brand = header.querySelector('[data-header-brand]');
    const brandTitle = header.querySelector('[data-header-brand-title]');
    const toggler = header.querySelector('[data-header-toggler]');
    const collapse = header.querySelector('[data-header-collapse]');
    const menu = header.querySelector('[data-header-menu]');
    const actions = header.querySelector('[data-header-actions]');

    if (container && config.containerClass) {
        container.className = config.containerClass;
    }

    if (brand) {
        brand.href = config.brandHref || 'auth_hub.html';
    }

    if (brandTitle) {
        const accent = domEl('span', 'accent');
        accent.textContent = 'UFC';
        brandTitle.replaceChildren(
            document.createTextNode('Achou! '),
            accent,
            document.createTextNode(config.brandSuffix ? ` · ${config.brandSuffix}` : '')
        );
    }

    if (collapse) {
        collapse.id = navId;
    }

    if (toggler) {
        toggler.setAttribute('data-bs-target', `#${navId}`);
        toggler.setAttribute('aria-controls', navId);
    }

    if (config.layout === 'actions') {
        collapse?.classList.add('d-none');
        toggler?.classList.add('d-none');
        actions?.classList.remove('d-none');
        actions?.classList.add('d-flex');
        if (actions) {
            const actionElements = (config.actions || []).map(headerActionLink);
            if (config.authAction) {
                actionElements.push(headerAuthAction());
            }
            actions.replaceChildren(...actionElements);
        }
        return;
    }

    actions?.classList.add('d-none');
    if (menu) {
        menu.replaceChildren(...(config.links || HEADER_VARIANTS.public.links).map(headerMenuItem));
    }
}

function headerMenuItem(link) {
    const itemClass = link.auth ? 'nav-item nav-auth-item' : 'nav-item';
    const linkClass = ['nav-link', link.className].filter(Boolean).join(' ');
    const item = domEl('li', itemClass);
    const anchor = domEl('a', linkClass, { href: link.href });

    appendIconText(anchor, link.icon, link.label);
    item.appendChild(anchor);

    return item;
}

function headerActionLink(link) {
    const anchor = domEl('a', link.className || 'btn btn-sm btn-outline-light', { href: link.href });

    if (link.id) {
        anchor.id = link.id;
    }
    appendIconText(anchor, link.icon, link.label);

    return anchor;
}

function headerAuthAction() {
    const wrapper = domEl('div', 'nav-auth-item');
    wrapper.appendChild(createAuthAccessLink());
    return wrapper;
}

function redirectIfAuthenticated() {
    if (!isLoggedIn()) return false;

    const next = new URLSearchParams(location.search).get('next');
    location.href = safeRedirectTarget(next, authHomeFor());
    return true;
}

function renderAuthArea() {
    const user = authUser();
    const currentPage = getCurrentPage();
    const authItems = new Set(
        Array.from(document.querySelectorAll('.nav-auth-item, .nav-link-login'))
            .map((element) => element.closest('.nav-auth-item') || element.closest('.nav-item') || element)
    );

    authItems.forEach((item) => {
        item.classList.add('nav-auth-item');

        if (!user) {
            item.replaceChildren(createAuthAccessLink());
            return;
        }

        const userLinks = [];
        if (user.role === 'admin') {
            userLinks.push(createNavUserLink('admin.html', 'bi-speedometer2', 'Painel'));
        } else if (currentPage !== 'minhas_reivindicacoes.html') {
            userLinks.push(createNavUserLink('minhas_reivindicacoes.html', 'bi-list-check', 'Minhas reivindicações'));
        }

        const wrapper = domEl('div', 'nav-user d-flex flex-column flex-lg-row align-items-lg-center gap-2');
        const profileLink = createNavUserLink('perfil.html', 'bi-person-circle', user.nome || user.email || 'Usuário');
        const logoutButton = domEl('button', 'btn btn-sm btn-gold', { type: 'button', 'data-logout': '' });

        appendIconText(logoutButton, 'bi-box-arrow-right', 'Fazer Logout');
        wrapper.append(...userLinks, profileLink, logoutButton);
        item.replaceChildren(wrapper);
    });
}

function createAuthAccessLink() {
    const link = domEl('a', 'nav-link nav-link-login', { href: 'auth_hub.html' });
    appendIconText(link, 'bi-shield-lock', 'Acessar');
    return link;
}

function createNavUserLink(href, iconClass, label) {
    const link = domEl('a', 'nav-link nav-user-name', { href });
    appendIconText(link, iconClass, label);
    return link;
}

function getCurrentPage() {
    return location.pathname.split('/').pop() || 'vitrine.html';
}

function parseJwtPayload(token) {
    if (typeof token !== 'string' || token.split('.').length !== 3) return null;

    try {
        const payload = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
        const padded = payload.padEnd(payload.length + ((4 - payload.length % 4) % 4), '=');
        return JSON.parse(atob(padded));
    } catch {
        return null;
    }
}

function isValidAuth(auth) {
    if (!auth?.token || !auth?.usuario?.id || !auth?.usuario?.role) return false;
    if (!['admin', 'aluno'].includes(auth.usuario.role)) return false;

    const payload = parseJwtPayload(auth.token);
    if (!payload?.exp || !payload?.sub || !payload?.role) return false;
    if (Number(payload.exp) <= Math.floor(Date.now() / 1000)) return false;
    if (String(payload.sub) !== String(auth.usuario.id)) return false;
    if (payload.role !== auth.usuario.role) return false;

    return true;
}

function safeRedirectTarget(target, fallback = 'auth_hub.html') {
    if (!target) return fallback;

    try {
        const decoded = decodeURIComponent(String(target)).trim();
        if (!decoded || decoded.includes('\\') || decoded.startsWith('//')) return fallback;
        if (/^[a-z][a-z0-9+.-]*:/i.test(decoded)) return fallback;

        const url = new URL(decoded, location.href);
        if (url.origin !== location.origin) return fallback;

        const file = url.pathname.split('/').pop() || 'auth_hub.html';
        if (!SAFE_REDIRECT_PAGES.has(file)) return fallback;

        return `${file}${url.search}${url.hash}`;
    } catch {
        return fallback;
    }
}

async function recaptchaToken() {
    const siteKey = document.querySelector('meta[name="recaptcha-site-key"]')?.content || window.RECAPTCHA_SITE_KEY;
    if (siteKey && window.grecaptcha?.execute) {
        return window.grecaptcha.execute(siteKey, { action: 'submit' });
    }
    return 'local-dev-token';
}

function detectApiBaseUrl() {
    const localFrontendPorts = ['3000', '3001', '5000', '5173', '5500', '5501'];
    const isLocalHost = ['localhost', '127.0.0.1', '::1'].includes(location.hostname);
    const isStaticPreview = location.protocol === 'file:' || (isLocalHost && localFrontendPorts.includes(location.port));

    if (isStaticPreview) {
        return 'http://localhost:8000';
    }

    return '';
}

async function apiRequest(path, options = {}) {
    const headers = new Headers(options.headers || {});
    const body = options.body;

    if (body && !(body instanceof FormData) && !headers.has('Content-Type')) {
        headers.set('Content-Type', 'application/json');
    }

    if (authToken()) {
        headers.set('Authorization', `Bearer ${authToken()}`);
    }

    let response;
    try {
        response = await fetch(`${API_BASE_URL}${path}`, {
            ...options,
            headers,
            body: body && !(body instanceof FormData) ? JSON.stringify(body) : body
        });
    } catch (error) {
        throw apiError(
            'Não foi possível conectar ao backend. Inicie a API em http://localhost:8000 ou abra o sistema pela porta do Docker.',
            0,
            error
        );
    }

    const payload = await parseApiPayload(response);

    if (!response.ok) {
        const error = apiError(errorMessageFromPayload(payload, response.status), response.status, payload);
        if (response.status === 401) {
            clearAuth();
        }
        throw error;
    }

    return payload;
}

async function parseApiPayload(response) {
    const raw = await response.text();

    if (!raw.trim()) {
        return null;
    }

    try {
        return JSON.parse(raw);
    } catch {
        throw apiError(errorMessageFromPayload(raw, response.status), response.status, raw);
    }
}

function apiError(message, status, payload = null) {
    const error = new Error(message || 'Não foi possível concluir a operação.');
    error.status = status;
    error.payload = payload;
    return error;
}

function errorMessageFromPayload(payload, status) {
    if (payload && typeof payload === 'object') {
        return payload.error || payload.mensagem || payload.message || 'Não foi possível concluir a operação.';
    }

    const text = String(payload || '').trim();
    const looksLikeHtml = /^<!doctype html/i.test(text)
        || /^<html/i.test(text)
        || /^<br\s*\/?>\s*<b>/i.test(text)
        || text.includes('<body');

    if (looksLikeHtml && status === 404) {
        return 'API não encontrada neste endereço. Abra o sistema em http://localhost:8000 ou mantenha o backend Docker rodando.';
    }

    if (looksLikeHtml) {
        return 'O backend retornou HTML em vez de JSON. Verifique os logs do container achados_api para ver o warning PHP original.';
    }

    return text || 'Não foi possível concluir a operação.';
}

function normalizeItem(item) {
    return {
        ...item,
        id: Number(item.id),
        categoria_id: item.categoria_id != null ? Number(item.categoria_id) : categoriaIdPorNome(item.categoria),
        local_id: item.local_id != null ? Number(item.local_id) : localIdPorNome(item.local),
        categoria: item.categoria || nomeCategoria(item.categoria_id),
        local: item.local || nomeLocal(item.local_id),
        foto_url: safeImageUrl(item.foto_url || ''),
        status: normalizeStatus(item.status)
    };
}

function safeImageUrl(value) {
    const raw = String(value || '').trim();
    if (!raw || raw.startsWith('//') || raw.includes('\\')) return '';

    try {
        const url = new URL(raw, location.origin);
        if (!['http:', 'https:'].includes(url.protocol)) return '';
        if (url.origin === location.origin && /^[a-z][a-z0-9+.-]*:/i.test(raw)) {
            return url.href;
        }
        return raw;
    } catch {
        return '';
    }
}

function normalizeStatus(status) {
    const value = String(status || '').trim().toLowerCase();
    const normalized = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (normalized === 'disponivel') return 'disponivel';
    if (normalized === 'devolvido') return 'devolvido';
    if (normalized === 'em_analise') return 'em_analise';
    if (normalized === 'arquivado') return 'arquivado';
    if (normalized === 'entregue') return 'devolvido';
    if (normalized === 'reivindicado') return 'em_analise';
    return normalized || 'disponivel';
}

function normalizeCategoria(categoria) {
    return {
        ...categoria,
        id: Number(categoria.id),
        nome: categoria.nome || categoria.nome_categoria || 'Sem categoria'
    };
}

function normalizeLocal(local) {
    return {
        ...local,
        id: Number(local.id),
        nome: local.nome || local.nome_local || local.nome_bloco || 'Local nao informado'
    };
}

function setCategorias(lista) {
    categorias = (lista || []).map(normalizeCategoria);
    return categorias;
}

function setLocais(lista) {
    locais = (lista || []).map(normalizeLocal);
    return locais;
}

const AchouApi = {
    async login(dados) {
        const payload = await apiRequest('/api/login', {
            method: 'POST',
            body: {
                email: dados.email,
                senha: dados.senha || dados.password,
                recaptcha_token: dados.recaptcha_token || await recaptchaToken()
            }
        });
        setAuth({ token: payload.token, usuario: payload.usuario });
        return payload;
    },

    async register(dados) {
        return apiRequest('/api/register', {
            method: 'POST',
            body: {
                ...dados,
                recaptcha_token: dados.recaptcha_token || await recaptchaToken()
            }
        });
    },

    async logout() {
        return apiRequest('/api/logout', { method: 'POST' });
    },

    async atualizarUsuario(id, dados) {
        return apiRequest(`/api/usuarios/${id}`, {
            method: 'PUT',
            body: dados
        });
    },

    async atualizarSenha(id, dados) {
        return apiRequest(`/api/usuarios/${id}/senha`, {
            method: 'PUT',
            body: dados
        });
    },

    async listarUsuarios(filtros = {}) {
        const params = new URLSearchParams({ limit: '100', ...limparFiltros(filtros) });
        const payload = await apiRequest(`/api/usuarios?${params.toString()}`);
        return payload.data || [];
    },

    async listarItens(filtros = {}) {
        const params = new URLSearchParams({ limit: '100', ...limparFiltros(filtros) });
        const payload = await apiRequest(`/api/itens?${params.toString()}`);
        return (payload.data || []).map(normalizeItem);
    },

    async buscarItem(id) {
        const payload = await apiRequest(`/api/itens/${id}`);
        return normalizeItem(payload.data);
    },

    async criarItem(dados) {
        return apiRequest('/api/itens', { method: 'POST', body: dados });
    },

    async atualizarItem(id, dados) {
        return apiRequest(`/api/itens/${id}`, { method: 'PUT', body: dados });
    },

    async excluirItem(id) {
        return apiRequest(`/api/itens/${id}`, { method: 'DELETE' });
    },

    async listarCategorias() {
        const payload = await apiRequest('/api/categorias');
        return setCategorias(payload.data || []);
    },

    async criarCategoria(dados) {
        return apiRequest('/api/categorias', { method: 'POST', body: dados });
    },

    async atualizarCategoria(id, dados) {
        return apiRequest(`/api/categorias/${id}`, { method: 'PUT', body: dados });
    },

    async excluirCategoria(id) {
        return apiRequest(`/api/categorias/${id}`, { method: 'DELETE' });
    },

    async listarLocais() {
        const payload = await apiRequest('/api/locais');
        return setLocais(payload.data || []);
    },

    async criarLocal(dados) {
        return apiRequest('/api/locais', { method: 'POST', body: dados });
    },

    async atualizarLocal(id, dados) {
        return apiRequest(`/api/locais/${id}`, { method: 'PUT', body: dados });
    },

    async excluirLocal(id) {
        return apiRequest(`/api/locais/${id}`, { method: 'DELETE' });
    },

    async listarReivindicacoes(filtros = {}) {
        const params = new URLSearchParams({ limit: '100', ...limparFiltros(filtros) });
        const payload = await apiRequest(`/api/reivindicacoes?${params.toString()}`);
        return payload.data || [];
    },

    async criarReivindicacao(dados) {
        return apiRequest('/api/reivindicacoes', { method: 'POST', body: dados });
    },

    async atualizarStatusReivindicacao(id, status) {
        return apiRequest(`/api/reivindicacoes/${id}/status`, {
            method: 'PUT',
            body: { status }
        });
    }
};

document.addEventListener('DOMContentLoaded', async () => {
    await renderHeaderComponents();
    document.dispatchEvent(new CustomEvent('achou:header-ready'));
    renderAuthArea();

    document.addEventListener('click', (event) => {
        const logoutButton = event.target.closest('[data-logout]');
        if (!logoutButton) return;

        event.preventDefault();
        logout();
    });
});

function limparFiltros(filtros) {
    return Object.fromEntries(
        Object.entries(filtros).filter(([, value]) => value !== undefined && value !== null && value !== '')
    );
}

function categoriaIdPorNome(nome) {
    return categorias.find((categoria) => categoria.nome === nome)?.id || null;
}

function localIdPorNome(nome) {
    return locais.find((local) => local.nome === nome)?.id || null;
}

function iconeCategoria(idOuNome) {
    const nome = typeof idOuNome === 'number'
        ? nomeCategoria(idOuNome)
        : String(idOuNome || '');
    const chave = nome.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return ICONES_POR_NOME[chave] || 'bi-box-seam';
}

function nomeCategoria(id) {
    return categorias.find((categoria) => categoria.id === Number(id))?.nome || 'Sem categoria';
}

function nomeLocal(id) {
    return locais.find((local) => local.id === Number(id))?.nome || 'Local nao informado';
}

function formatDate(dateValue) {
    if (!dateValue) return 'Data nao informada';
    const date = String(dateValue).slice(0, 10);
    const [year, month, day] = date.split('-');
    if (!year || !month || !day) return dateValue;
    return `${day}/${month}/${year}`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
