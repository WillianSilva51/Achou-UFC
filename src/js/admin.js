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
    return `<span class="badge ${classes[status] || 'text-bg-light'}">${labels[status] || escapeHtml(status)}</span>`;
}

function reivindicacaoBadge(status) {
    const classes = {
        pendente: 'text-bg-warning',
        aprovado: 'text-bg-success',
        recusado: 'text-bg-secondary'
    };
    return `<span class="badge ${classes[status] || 'text-bg-light'}">${escapeHtml(status)}</span>`;
}

function renderItensAdmin(itens) {
    document.getElementById('stat-total').textContent = itens.length;
    document.getElementById('stat-disp').textContent = itens.filter((item) => item.status === 'disponivel').length;
    document.getElementById('stat-entr').textContent = itens.filter((item) => item.status === 'devolvido').length;

    if (!itens.length) {
        tblItens.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Nenhum item cadastrado.</td></tr>';
        return;
    }

    tblItens.innerHTML = itens.map((item) => `
        <tr>
            <td>${escapeHtml(String(item.id))}</td>
            <td>
                <strong>${escapeHtml(item.titulo)}</strong>
                <div class="small text-muted">${escapeHtml(item.descricao || '')}</div>
            </td>
            <td>${escapeHtml(item.categoria || nomeCategoria(item.categoria_id))}</td>
            <td>${escapeHtml(item.local || nomeLocal(item.local_id))}</td>
            <td>${formatDate(item.data_encontrado)}</td>
            <td>${itemStatusBadge(item.status)}</td>
            <td class="text-end">
                <a class="btn btn-sm btn-outline-primary me-1" href="editar_item.html?id=${encodeURIComponent(String(item.id))}" title="Editar item">
                    <i class="bi bi-pencil"></i>
                </a>
                <button class="btn btn-sm btn-outline-danger" data-delete-item="${escapeHtml(String(item.id))}" title="Arquivar item">
                    <i class="bi bi-archive"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function renderReivindicacoesAdmin(reivindicacoes) {
    document.getElementById('stat-reiv').textContent = reivindicacoes.length;

    if (!reivindicacoes.length) {
        tblReiv.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Nenhuma reivindicação recebida.</td></tr>';
        return;
    }

    tblReiv.innerHTML = reivindicacoes.map((reivindicacao) => {
        const pendente = reivindicacao.status_reivindicacao === 'pendente';
        return `
            <tr>
                <td>${escapeHtml(String(reivindicacao.id))}</td>
                <td>${escapeHtml(reivindicacao.item_titulo || '')}</td>
                <td>
                    <strong>${escapeHtml(reivindicacao.aluno_nome || '')}</strong>
                    <div class="small text-muted">${escapeHtml(reivindicacao.matricula || '')}</div>
                </td>
                <td>${escapeHtml(reivindicacao.aluno_email || '')}</td>
                <td>${formatDate(reivindicacao.data_solicitacao)}</td>
                <td>${reivindicacaoBadge(reivindicacao.status_reivindicacao)}</td>
                <td class="text-end">
                    ${pendente ? `
                        <button class="btn btn-sm btn-outline-success" data-reiv-status="${escapeHtml(String(reivindicacao.id))}:aprovado" title="Aprovar">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" data-reiv-status="${escapeHtml(String(reivindicacao.id))}:recusado" title="Recusar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    ` : ''}
                </td>
            </tr>
        `;
    }).join('');
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
        tblItens.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">${escapeHtml(error.message)}</td></tr>`;
        tblReiv.innerHTML = '';
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
