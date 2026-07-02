const STATUS_CONFIG = {
    pendente: { cls: 'badge-pendente', label: 'Aguardando análise', icon: 'bi-hourglass-split' },
    aprovado: { cls: 'badge-aprovado', label: 'Aprovado', icon: 'bi-check-circle-fill' },
    recusado: { cls: 'badge-recusado', label: 'Recusado', icon: 'bi-x-circle-fill' }
};

function badgeStatus(status) {
    const s = STATUS_CONFIG[status] || { cls: 'text-bg-secondary', label: status, icon: 'bi-question-circle' };
    return `<span class="badge ${s.cls} d-inline-flex align-items-center gap-1">
        <i class="bi ${s.icon}"></i> ${s.label}
    </span>`;
}

function renderTabelaReivindicacoes(lista) {
    const tbody = document.getElementById('tbody-reivs');
    document.getElementById('contador-reivs').textContent = lista.length;

    tbody.innerHTML = lista.map((reivindicacao) => {
        const status = reivindicacao.status_reivindicacao || 'pendente';
        return `
            <tr class="${status === 'aprovado' ? 'table-success' : ''}">
                <td class="ps-3 text-muted small">#${escapeHtml(String(reivindicacao.id))}</td>
                <td>
                    <strong>${escapeHtml(reivindicacao.item_titulo || reivindicacao.titulo || `Item #${reivindicacao.item_id}`)}</strong>
                    ${reivindicacao.categoria ? `<div class="small text-muted">${escapeHtml(reivindicacao.categoria)}</div>` : ''}
                </td>
                <td class="text-muted small">${escapeHtml(reivindicacao.local || reivindicacao.local_nome || '—')}</td>
                <td class="text-muted small">${formatDate(reivindicacao.data_solicitacao)}</td>
                <td>${badgeStatus(status)}</td>
            </tr>`;
    }).join('');
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
