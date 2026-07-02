const STATUS_CONFIG = {
    pendente: { cls: 'badge-pendente', label: 'Aguardando análise', icon: 'bi-hourglass-split' },
    aprovado: { cls: 'badge-aprovado', label: 'Aprovado', icon: 'bi-check-circle-fill' },
    recusado: { cls: 'badge-recusado', label: 'Recusado', icon: 'bi-x-circle-fill' }
};

function badgeStatus(status) {
    const s = STATUS_CONFIG[status] || { cls: 'text-bg-secondary', label: status, icon: 'bi-question-circle' };
    const badge = domEl('span', `badge ${s.cls} d-inline-flex align-items-center gap-1`);

    badge.append(domIcon(s.icon), document.createTextNode(` ${s.label}`));
    return badge;
}

function renderReivindicacaoRow(reivindicacao) {
    const status = reivindicacao.status_reivindicacao || 'pendente';
    const row = document.createElement('tr');
    const idCell = domEl('td', 'ps-3 text-muted small');
    const itemCell = document.createElement('td');
    const itemTitle = document.createElement('strong');
    const localCell = domEl('td', 'text-muted small');
    const dateCell = domEl('td', 'text-muted small');
    const statusCell = document.createElement('td');

    if (status === 'aprovado') {
        row.className = 'table-success';
    }

    idCell.textContent = `#${reivindicacao.id}`;
    itemTitle.textContent = reivindicacao.item_titulo || reivindicacao.titulo || `Item #${reivindicacao.item_id}`;
    itemCell.appendChild(itemTitle);
    if (reivindicacao.categoria) {
        const categoria = domEl('div', 'small text-muted');
        categoria.textContent = reivindicacao.categoria;
        itemCell.appendChild(categoria);
    }
    localCell.textContent = reivindicacao.local || reivindicacao.local_nome || '—';
    dateCell.textContent = formatDate(reivindicacao.data_solicitacao);
    statusCell.appendChild(badgeStatus(status));
    row.append(idCell, itemCell, localCell, dateCell, statusCell);

    return row;
}

function renderTabelaReivindicacoes(lista) {
    const tbody = document.getElementById('tbody-reivs');
    document.getElementById('contador-reivs').textContent = lista.length;

    tbody.replaceChildren(...lista.map(renderReivindicacaoRow));
}

async function carregarReivindicacoes() {
    const loading = document.getElementById('loading');
    const blocoErro = document.getElementById('bloco-erro');
    const blocoVazio = document.getElementById('bloco-vazio');
    const blocoTabela = document.getElementById('bloco-tabela');
    const blocoRetirada = document.getElementById('bloco-retirada');

    try {
        const user = authUser();
        if (user?.role !== 'aluno') {
            location.href = authHomeFor(user);
            return;
        }

        const lista = await AchouApi.listarReivindicacoes();

        loading.classList.add('d-none');

        if (!lista.length) {
            blocoVazio.classList.remove('d-none');
            return;
        }

        if (lista.some((reivindicacao) => reivindicacao.status_reivindicacao === 'aprovado')) {
            blocoRetirada.classList.remove('d-none');
        }

        renderTabelaReivindicacoes(lista);
        blocoTabela.classList.remove('d-none');
    } catch (err) {
        loading.classList.add('d-none');
        document.getElementById('texto-erro').textContent = err.message;
        blocoErro.classList.remove('d-none');
    }
}

document.addEventListener('DOMContentLoaded', carregarReivindicacoes);
