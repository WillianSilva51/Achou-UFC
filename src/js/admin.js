const tblItens = document.getElementById('tbl-itens');
const tblReiv = document.getElementById('tbl-reiv');

function itemStatusBadge(status) {
    const classes = {
        disponivel: 'text-bg-success',
        em_analise: 'text-bg-warning',
        devolvido: 'text-bg-secondary',
        arquivado: 'text-bg-dark'
    };
    const labels = {
        disponivel: 'Disponivel',
        em_analise: 'Em analise',
        devolvido: 'Devolvido',
        arquivado: 'Arquivado'
    };
    const badge = domEl('span', `badge ${classes[status] || 'text-bg-light'}`);
    badge.textContent = labels[status] || status;
    return badge;
}

function reivindicacaoBadge(status) {
    const classes = {
        pendente: 'text-bg-warning',
        aprovado: 'text-bg-success',
        recusado: 'text-bg-secondary'
    };
    const badge = domEl('span', `badge ${classes[status] || 'text-bg-light'}`);
    badge.textContent = status;
    return badge;
}

function renderEmptyRow(message, colspan = 7, className = 'text-center text-muted py-4') {
    const row = document.createElement('tr');
    const cell = domEl('td', className, { colspan });

    cell.textContent = message;
    row.appendChild(cell);
    return row;
}

function appendTextCell(row, text, className = '') {
    const cell = domEl('td', className);
    cell.textContent = text;
    row.appendChild(cell);
    return cell;
}

function renderItemRow(item) {
    const row = document.createElement('tr');
    const titleCell = document.createElement('td');
    const title = document.createElement('strong');
    const description = domEl('div', 'small text-muted');
    const statusCell = document.createElement('td');
    const actionsCell = domEl('td', 'text-end');
    const editLink = domEl('a', 'btn btn-sm btn-outline-primary me-1', {
        href: `editar_item.html?id=${encodeURIComponent(String(item.id))}`,
        title: 'Editar item'
    });
    const archiveButton = domEl('button', 'btn btn-sm btn-outline-danger', {
        'data-delete-item': String(item.id),
        title: 'Arquivar item'
    });

    appendTextCell(row, String(item.id));
    title.textContent = item.titulo;
    description.textContent = item.descricao || '';
    titleCell.append(title, description);
    row.appendChild(titleCell);
    appendTextCell(row, item.categoria || nomeCategoria(item.categoria_id));
    appendTextCell(row, item.local || nomeLocal(item.local_id));
    appendTextCell(row, formatDate(item.data_encontrado));
    statusCell.appendChild(itemStatusBadge(item.status));
    row.appendChild(statusCell);
    editLink.appendChild(domIcon('bi-pencil'));
    archiveButton.appendChild(domIcon('bi-archive'));
    actionsCell.append(editLink, archiveButton);
    row.appendChild(actionsCell);

    return row;
}

function renderReivindicacaoRow(reivindicacao) {
    const row = document.createElement('tr');
    const alunoCell = document.createElement('td');
    const alunoNome = document.createElement('strong');
    const matricula = domEl('div', 'small text-muted');
    const statusCell = document.createElement('td');
    const actionsCell = domEl('td', 'text-end');

    appendTextCell(row, String(reivindicacao.id));
    appendTextCell(row, reivindicacao.item_titulo || '');
    alunoNome.textContent = reivindicacao.aluno_nome || '';
    matricula.textContent = reivindicacao.matricula || '';
    alunoCell.append(alunoNome, matricula);
    row.appendChild(alunoCell);
    appendTextCell(row, reivindicacao.aluno_email || '');
    appendTextCell(row, formatDate(reivindicacao.data_solicitacao));
    statusCell.appendChild(reivindicacaoBadge(reivindicacao.status_reivindicacao));
    row.appendChild(statusCell);

    if (reivindicacao.status_reivindicacao === 'pendente') {
        actionsCell.append(
            renderStatusButton(reivindicacao.id, 'aprovado', 'btn btn-sm btn-outline-success', 'Aprovar', 'bi-check-lg'),
            renderStatusButton(reivindicacao.id, 'recusado', 'btn btn-sm btn-outline-danger', 'Recusar', 'bi-x-lg')
        );
    }

    row.appendChild(actionsCell);
    return row;
}

function renderStatusButton(id, status, className, title, iconClass) {
    const button = domEl('button', className, {
        'data-reiv-status': `${id}:${status}`,
        title
    });
    button.appendChild(domIcon(iconClass));
    return button;
}

function renderItensAdmin(itens) {
    document.getElementById('stat-total').textContent = itens.length;
    document.getElementById('stat-disp').textContent = itens.filter((item) => item.status === 'disponivel').length;
    document.getElementById('stat-entr').textContent = itens.filter((item) => item.status === 'devolvido').length;

    if (!itens.length) {
        tblItens.replaceChildren(renderEmptyRow('Nenhum item cadastrado.'));
        return;
    }

    tblItens.replaceChildren(...itens.map(renderItemRow));
}

function renderReivindicacoesAdmin(reivindicacoes) {
    document.getElementById('stat-reiv').textContent = reivindicacoes.length;

    if (!reivindicacoes.length) {
        tblReiv.replaceChildren(renderEmptyRow('Nenhuma reivindicação recebida.'));
        return;
    }

    tblReiv.replaceChildren(...reivindicacoes.map(renderReivindicacaoRow));
}

async function carregarPainel() {
    try {
        await Promise.all([AchouApi.listarCategorias(), AchouApi.listarLocais()]);
        const [itens, reivindicacoes] = await Promise.all([
            AchouApi.listarItens(),
            AchouApi.listarReivindicacoes()
        ]);
        renderItensAdmin(itens);
        renderReivindicacoesAdmin(reivindicacoes);
    } catch (error) {
        tblItens.replaceChildren(renderEmptyRow(error.message, 7, 'text-center text-danger py-4'));
        tblReiv.replaceChildren();
    }
}

document.addEventListener('click', async (event) => {
    const deleteButton = event.target.closest('[data-delete-item]');
    if (deleteButton) {
        if (!confirm('Arquivar este item?')) return;
        await AchouApi.excluirItem(deleteButton.dataset.deleteItem);
        carregarPainel();
        return;
    }

    const statusButton = event.target.closest('[data-reiv-status]');
    if (statusButton) {
        const [id, status] = statusButton.dataset.reivStatus.split(':');
        await AchouApi.atualizarStatusReivindicacao(id, status);
        carregarPainel();
    }
});

carregarPainel();
