const AUTH_KEY = 'achou_ufc_auth';
const API_BASE_URL = window.ACHOU_API_BASE_URL || detectApiBaseUrl();

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
        return JSON.parse(localStorage.getItem(AUTH_KEY) || 'null');
    } catch {
        localStorage.removeItem(AUTH_KEY);
        return null;
    }
}

function setAuth(auth) {
    localStorage.setItem(AUTH_KEY, JSON.stringify(auth));
    renderAuthArea();
}

function clearAuth() {
    localStorage.removeItem(AUTH_KEY);
    renderAuthArea();
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
    if (!isLoggedIn() || (role && user?.role !== role)) {
        const next = encodeURIComponent(`${location.pathname.split('/').pop() || 'index.html'}${location.search}`);
        location.href = `login.html?next=${next}`;
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
    return user?.role === 'admin' ? 'admin.html' : 'index.html';
}

function redirectIfAuthenticated() {
    if (!isLoggedIn()) return false;

    const next = new URLSearchParams(location.search).get('next');
    location.href = next || authHomeFor();
    return true;
}

function renderAuthArea() {
    const user = authUser();
    const authItems = new Set(
        Array.from(document.querySelectorAll('.nav-auth-item, .nav-link-login'))
            .map((element) => element.closest('.nav-auth-item') || element.closest('.nav-item') || element)
    );

    authItems.forEach((item) => {
        item.classList.add('nav-auth-item');

        if (!user) {
            item.innerHTML = `
                <a href="login.html" class="nav-link nav-link-login">
                    <i class="bi bi-shield-lock"></i>
                    Login
                </a>
            `;
            return;
        }

        item.innerHTML = `
            <div class="nav-user d-flex flex-column flex-lg-row align-items-lg-center gap-2">
                <a href="perfil.html" class="nav-link nav-user-name">
                    <i class="bi bi-person-circle"></i>
                    ${escapeHtml(user.nome || user.email || 'Usuário')}
                </a>
                <button type="button" class="btn btn-sm btn-gold" data-logout>
                    <i class="bi bi-box-arrow-right"></i>
                    Fazer Logout
                </button>
            </div>
        `;
    });
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
        foto_url: item.foto_url || '',
        status: normalizeStatus(item.status)
    };
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

    async excluirItem(id) {
        return apiRequest(`/api/itens/${id}`, { method: 'DELETE' });
    },

    async listarCategorias() {
        const payload = await apiRequest('/api/categorias');
        return setCategorias(payload.data || []);
    },

    async listarLocais() {
        const payload = await apiRequest('/api/locais');
        return setLocais(payload.data || []);
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

const mockApi = AchouApi;

document.addEventListener('DOMContentLoaded', () => {
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
