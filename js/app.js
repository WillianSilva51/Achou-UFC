const STORAGE_KEYS = {
    itens: 'achou_ufc_itens',
    reivindicacoes: 'achou_ufc_reivindicacoes'
};

const categorias = [
    { id: 1, nome: 'Chaves', icone: 'bi-key-fill' },
    { id: 2, nome: 'Garrafas', icone: 'bi-cup-straw' },
    { id: 3, nome: 'Documentos', icone: 'bi-file-earmark-text-fill' },
    { id: 4, nome: 'Eletronicos', icone: 'bi-phone-fill' },
    { id: 5, nome: 'Bolsas', icone: 'bi-bag-fill' },
    { id: 6, nome: 'Livros', icone: 'bi-book-fill' },
    { id: 7, nome: 'Outros', icone: 'bi-box-seam' }
];

const locais = [
    { id: 1, nome: 'Bloco 1' },
    { id: 2, nome: 'Bloco 2' },
    { id: 3, nome: 'Biblioteca' },
    { id: 4, nome: 'Restaurante Universitario' },
    { id: 5, nome: 'Quadra' },
    { id: 6, nome: 'Recepcao' }
];

const itensMock = [
    {
        id: 1,
        titulo: 'Chave com chaveiro azul',
        descricao: 'Molho com duas chaves e chaveiro azul da UFC.',
        categoria_id: 1,
        local_id: 1,
        data_encontrado: '2026-06-21',
        status: 'disponivel',
        foto_url: ''
    },
    {
        id: 2,
        titulo: 'Garrafa preta',
        descricao: 'Garrafa termica preta, sem marca aparente.',
        categoria_id: 2,
        local_id: 4,
        data_encontrado: '2026-06-18',
        status: 'disponivel',
        foto_url: ''
    },
    {
        id: 3,
        titulo: 'Carteira estudantil',
        descricao: 'Documento estudantil encontrado perto da biblioteca.',
        categoria_id: 3,
        local_id: 3,
        data_encontrado: '2026-06-16',
        status: 'reivindicado',
        foto_url: ''
    },
    {
        id: 4,
        titulo: 'Fone de ouvido',
        descricao: 'Fone com estojo branco encontrado em sala de aula.',
        categoria_id: 4,
        local_id: 2,
        data_encontrado: '2026-06-12',
        status: 'disponivel',
        foto_url: ''
    },
    {
        id: 5,
        titulo: 'Livro de Calculo',
        descricao: 'Livro com anotacoes nas primeiras paginas.',
        categoria_id: 6,
        local_id: 6,
        data_encontrado: '2026-06-09',
        status: 'entregue',
        foto_url: ''
    }
];

const reivindicacoesMock = [
    {
        id: 1,
        item_id: 3,
        aluno_nome: 'Ana Beatriz Lima',
        matricula: '472913',
        aluno_email: 'ana@alu.ufc.br',
        telefone: '(88) 99999-0000',
        prova: 'Documento com meu nome e curso.',
        data_solicitacao: '2026-06-17',
        status_reivindicacao: 'Pendente'
    }
];

const ICONES_CAT = categorias.reduce((acc, categoria) => {
    acc[categoria.id] = categoria.icone;
    return acc;
}, {});

function readStorage(key, fallback) {
    const raw = localStorage.getItem(key);
    if (!raw) {
        localStorage.setItem(key, JSON.stringify(fallback));
        return structuredClone(fallback);
    }

    try {
        return JSON.parse(raw);
    } catch {
        localStorage.setItem(key, JSON.stringify(fallback));
        return structuredClone(fallback);
    }
}

function writeStorage(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
}

// Troque os metodos abaixo por chamadas fetch quando a API real estiver pronta.
const mockApi = {
    async listarItens() {
        return getItens();
    },

    async listarCategorias() {
        return getCategorias();
    },

    async listarLocais() {
        return getLocais();
    },

    async listarReivindicacoes() {
        return getReivindicacoes();
    },

    async criarReivindicacao(dados) {
        const reivindicacoes = getReivindicacoes();
        const nova = {
            id: nextId(reivindicacoes),
            data_solicitacao: new Date().toISOString().slice(0, 10),
            status_reivindicacao: 'Pendente',
            ...dados
        };

        reivindicacoes.push(nova);
        saveReivindicacoes(reivindicacoes);

        const itens = getItens();
        const item = itens.find((it) => it.id === nova.item_id);
        if (item && item.status === 'disponivel') {
            item.status = 'reivindicado';
            saveItens(itens);
        }

        return nova;
    }
};

function getCategorias() {
    return structuredClone(categorias);
}

function getLocais() {
    return structuredClone(locais);
}

function getItens() {
    return readStorage(STORAGE_KEYS.itens, itensMock);
}

function saveItens(itens) {
    writeStorage(STORAGE_KEYS.itens, itens);
}

function getReivindicacoes() {
    return readStorage(STORAGE_KEYS.reivindicacoes, reivindicacoesMock);
}

function saveReivindicacoes(reivindicacoes) {
    writeStorage(STORAGE_KEYS.reivindicacoes, reivindicacoes);
}

function nextId(lista) {
    return lista.length ? Math.max(...lista.map((item) => Number(item.id) || 0)) + 1 : 1;
}

function iconeCategoria(id) {
    return ICONES_CAT[id] || 'bi-box-seam';
}

function nomeCategoria(id) {
    return categorias.find((categoria) => categoria.id === Number(id))?.nome || 'Sem categoria';
}

function nomeLocal(id) {
    return locais.find((local) => local.id === Number(id))?.nome || 'Local nao informado';
}

function formatDate(dateValue) {
    if (!dateValue) return 'Data nao informada';
    const [year, month, day] = dateValue.split('-');
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
