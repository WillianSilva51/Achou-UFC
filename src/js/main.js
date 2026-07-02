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
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = labelPadrao;

    select.replaceChildren(
        defaultOption,
        ...opcoes.map((opcao) => {
            const option = document.createElement('option');
            option.value = String(opcao.id);
            option.textContent = opcao.nome;
            return option;
        })
    );
}

function statusLabel(status) {
    const labels = {
        disponivel: 'Disponivel',
        em_analise: 'Em analise',
        devolvido: 'Devolvido',
        arquivado: 'Arquivado'
    };

    return labels[status] || status;
}

function renderItem(item) {
    const column = domEl('div', 'col-12 col-md-6 col-lg-4');
    const article = domEl('article', 'card item-card');
    const imageWrapper = domEl('div', 'item-img');
    const categoryBadge = domEl('span', 'badge badge-cat');
    const statusBadge = domEl('span', `badge badge-status status-${item.status}`);
    const body = domEl('div', 'card-body d-flex flex-column');
    const title = domEl('h5', 'card-title');
    const description = domEl('p', 'card-text text-muted small');
    const infoList = domEl('ul', 'list-unstyled small mt-auto mb-3');

    if (item.foto_url) {
        const image = document.createElement('img');
        image.src = item.foto_url;
        image.alt = item.titulo;
        imageWrapper.appendChild(image);
    } else {
        const icon = domIcon(iconeCategoria(item.categoria_id));
        icon.setAttribute('aria-hidden', 'true');
        imageWrapper.appendChild(icon);
    }

    categoryBadge.textContent = item.categoria || nomeCategoria(item.categoria_id);
    statusBadge.textContent = statusLabel(item.status);
    imageWrapper.append(categoryBadge, statusBadge);

    title.textContent = item.titulo;
    description.textContent = item.descricao;
    infoList.append(
        renderItemInfo('bi-geo-alt', 'Local:', item.local || nomeLocal(item.local_id)),
        renderItemInfo('bi-calendar3', 'Encontrado em:', formatDate(item.data_encontrado))
    );

    body.append(title, description, infoList, renderItemAction(item));
    article.append(imageWrapper, body);
    column.appendChild(article);

    return column;
}

function renderItemInfo(iconClass, label, value) {
    const item = document.createElement('li');
    const labelElement = document.createElement('strong');

    labelElement.textContent = label;
    item.append(domIcon(iconClass), document.createTextNode(' '), labelElement, document.createTextNode(` ${value}`));

    return item;
}

function renderItemAction(item) {
    if (item.status !== 'disponivel') {
        const button = domEl('button', 'btn btn-outline-secondary w-100', { disabled: '' });
        button.textContent = 'Indisponivel';
        return button;
    }

    const link = domEl('a', 'btn btn-ufc w-100', { href: `reivindicar.html?id=${encodeURIComponent(String(item.id))}` });
    appendIconText(link, 'bi-hand-index-thumb', 'Reivindicar');
    return link;
}

function renderListAlert(message, variant = 'light', link = null) {
    const column = domEl('div', 'col-12');
    const alert = domEl('div', `alert alert-${variant} ${variant === 'light' ? 'border ' : ''}text-center`);

    alert.appendChild(document.createTextNode(message));
    if (link) {
        alert.appendChild(document.createTextNode(' '));
        const anchor = domEl('a', 'alert-link', { href: link.href });
        anchor.textContent = link.label;
        alert.appendChild(anchor);
    }
    column.appendChild(alert);

    return column;
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
        elementos.lista.replaceChildren(renderListAlert('Nenhum item encontrado com os filtros atuais.'));
        return;
    }

    elementos.lista.replaceChildren(...filtrados.map(renderItem));
}

async function init() {
    try {
        const [categorias, locais] = await Promise.all([
            AchouApi.listarCategorias(),
            AchouApi.listarLocais()
        ]);
        itens = await AchouApi.listarItens();
        renderSelect(elementos.categoria, categorias, 'Todas');
        renderSelect(elementos.local, locais, 'Todos');
        renderItens();
    } catch (error) {
        if (error.status === 401) {
            elementos.lista.replaceChildren(renderListAlert(
                'Faça login para visualizar e reivindicar os itens encontrados.',
                'warning',
                { href: 'login.html?next=vitrine.html', label: 'Entrar' }
            ));
            return;
        }

        elementos.lista.replaceChildren(renderListAlert(error.message, 'danger'));
        return;
    }

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
