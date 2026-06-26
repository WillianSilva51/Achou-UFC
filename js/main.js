const elementos = {
    busca: document.getElementById('f-busca'),
    categoria: document.getElementById('f-categoria'),
    local: document.getElementById('f-local'),
    data: document.getElementById('f-data'),
    limpar: document.getElementById('btn-limpar'),
    total: document.getElementById('total-itens'),
    lista: document.getElementById('lista-itens'),
    form: document.getElementById('form-filtros')
};

let itens = [];

function renderSelect(select, opcoes, labelPadrao) {
    select.innerHTML = [
        `<option value="">${labelPadrao}</option>`,
        ...opcoes.map((opcao) => `<option value="${opcao.id}">${escapeHtml(opcao.nome)}</option>`)
    ].join('');
}

function statusLabel(status) {
    const labels = {
        disponivel: 'Disponivel',
        reivindicado: 'Reivindicado',
        entregue: 'Entregue'
    };

    return labels[status] || status;
}

function renderItem(item) {
    const titulo = escapeHtml(item.titulo);
    const descricao = escapeHtml(item.descricao);
    const status = escapeHtml(item.status);
    const categoria = escapeHtml(nomeCategoria(item.categoria_id));
    const local = escapeHtml(nomeLocal(item.local_id));

    return `
        <div class="col-12 col-md-6 col-lg-4">
            <article class="card item-card">
                <div class="item-img">
                    ${item.foto_url
            ? `<img src="${escapeHtml(item.foto_url)}" alt="${titulo}">`
            : `<i class="bi ${iconeCategoria(item.categoria_id)}" aria-hidden="true"></i>`}
                    <span class="badge badge-cat">${categoria}</span>
                    <span class="badge badge-status status-${status}">${statusLabel(item.status)}</span>
                </div>
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">${titulo}</h5>
                    <p class="card-text text-muted small">${descricao}</p>
                    <ul class="list-unstyled small mt-auto mb-3">
                        <li><i class="bi bi-geo-alt"></i> <strong>Local:</strong> ${local}</li>
                        <li><i class="bi bi-calendar3"></i> <strong>Encontrado em:</strong> ${formatDate(item.data_encontrado)}</li>
                    </ul>
                    ${item.status === 'entregue'
            ? '<button class="btn btn-outline-secondary w-100" disabled>Item entregue</button>'
            : `<a class="btn btn-ufc w-100" href="reivindicar.html?id=${item.id}">
                            <i class="bi bi-hand-index-thumb"></i> Reivindicar
                        </a>`}
                </div>
            </article>
        </div>
    `;
}

function filtrarItens() {
    const termo = elementos.busca.value.trim().toLowerCase();
    const categoriaId = Number(elementos.categoria.value);
    const localId = Number(elementos.local.value);
    const data = elementos.data.value;

    return itens.filter((item) => {
        const texto = `${item.titulo} ${item.descricao}`.toLowerCase();
        const bateBusca = !termo || texto.includes(termo);
        const bateCategoria = !categoriaId || item.categoria_id === categoriaId;
        const bateLocal = !localId || item.local_id === localId;
        const bateData = !data || item.data_encontrado === data;

        return bateBusca && bateCategoria && bateLocal && bateData;
    });
}

function renderItens() {
    const filtrados = filtrarItens();
    elementos.total.textContent = filtrados.length;

    if (!filtrados.length) {
        elementos.lista.innerHTML = `
            <div class="col-12">
                <div class="alert alert-light border text-center">
                    Nenhum item encontrado com os filtros atuais.
                </div>
            </div>
        `;
        return;
    }

    elementos.lista.innerHTML = filtrados.map(renderItem).join('');
}

async function init() {
    const [itensMock, categorias, locais] = await Promise.all([
        mockApi.listarItens(),
        mockApi.listarCategorias(),
        mockApi.listarLocais()
    ]);

    itens = itensMock;
    renderSelect(elementos.categoria, categorias, 'Todas');
    renderSelect(elementos.local, locais, 'Todos');
    renderItens();

    [elementos.busca, elementos.categoria, elementos.local, elementos.data].forEach((campo) => {
        campo.addEventListener('input', renderItens);
        campo.addEventListener('change', renderItens);
    });

    elementos.limpar.addEventListener('click', () => {
        setTimeout(renderItens);
    });

    elementos.form.addEventListener('submit', (event) => {
        event.preventDefault();
        renderItens();
    });
}

init();
