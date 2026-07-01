let modalTipo = null;
let modalId = null;
let salvandoEntidade = false;

function mostrarToast(msg, tipo = 'success') {
    const el = document.getElementById('toast-feedback');
    el.className = `toast align-items-center border-0 text-bg-${tipo}`;
    document.getElementById('toast-texto').textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 3000 }).show();
}

function renderLinha(tipo, entidade) {
    const tr = document.createElement('tr');
    tr.className = 'entity-row';
    tr.dataset.id = entidade.id;
    tr.dataset.nome = entidade.nome;
    tr.dataset.tipo = tipo;
    tr.innerHTML = `
        <td class="ps-3 entity-name">${escapeHtml(entidade.nome)}</td>
        <td class="text-end pe-3">
            <button class="btn btn-sm btn-outline-secondary me-1" title="Editar" data-entity-edit>
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" title="Excluir" data-entity-delete>
                <i class="bi bi-trash3"></i>
            </button>
        </td>`;
    return tr;
}

function renderTabela(tipo, lista) {
    const suffix = tipo === 'categoria' ? 'cat' : 'loc';
    const tbl = document.getElementById(`tbl-${suffix}`);
    const tbody = document.getElementById(`tbody-${suffix}`);
    const empty = document.getElementById(`empty-${suffix}`);
    const loading = document.getElementById(`loading-${suffix}`);

    loading.classList.add('d-none');

    if (!lista.length) {
        tbl.classList.add('d-none');
        empty.classList.remove('d-none');
        return;
    }

    empty.classList.add('d-none');
    tbody.innerHTML = '';
    lista.forEach((entidade) => tbody.appendChild(renderLinha(tipo, entidade)));
    tbl.classList.remove('d-none');
}

async function carregarTudo() {
    try {
        const [cats, locs] = await Promise.all([
            AchouApi.listarCategorias(),
            AchouApi.listarLocais()
        ]);
        renderTabela('categoria', cats);
        renderTabela('local', locs);
    } catch (err) {
        mostrarToast(`Erro ao carregar dados: ${err.message}`, 'danger');
    }
}

function abrirModal(tipo, id = null, nome = '') {
    modalTipo = tipo;
    modalId = id;

    const ehEdicao = id !== null;
    const tipoLabel = tipo === 'categoria' ? 'Categoria' : 'Local';

    document.getElementById('modalEntidadeTitulo').textContent =
        ehEdicao ? `Editar ${tipoLabel}` : `Nova ${tipoLabel}`;
    document.getElementById('label-nome-entidade').textContent =
        tipo === 'local' ? 'Nome do local / bloco' : 'Nome da categoria';
    document.getElementById('input-nome-entidade').value = ehEdicao ? nome : '';
    document.getElementById('input-nome-entidade').placeholder =
        tipo === 'local' ? 'Ex: Bloco A, Biblioteca...' : 'Ex: Eletrônicos, Documentos...';

    const erroEl = document.getElementById('erro-modal-entidade');
    erroEl.classList.add('d-none');
    erroEl.textContent = '';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEntidade')).show();

    document.getElementById('modalEntidade').addEventListener('shown.bs.modal', () => {
        document.getElementById('input-nome-entidade').focus();
    }, { once: true });
}

async function salvarEntidade() {
    if (salvandoEntidade) return;

    const input = document.getElementById('input-nome-entidade');
    const erroEl = document.getElementById('erro-modal-entidade');
    const btn = document.getElementById('btn-salvar-entidade');
    const nome = input.value.trim();

    if (!nome) {
        input.classList.add('is-invalid');
        return;
    }

    input.classList.remove('is-invalid');
    erroEl.classList.add('d-none');
    salvandoEntidade = true;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Salvando...';

    try {
        const ehEdicao = modalId !== null;

        if (modalTipo === 'categoria') {
            ehEdicao
                ? await AchouApi.atualizarCategoria(modalId, { nome })
                : await AchouApi.criarCategoria({ nome });
        } else {
            ehEdicao
                ? await AchouApi.atualizarLocal(modalId, { nome_local: nome })
                : await AchouApi.criarLocal({ nome_local: nome });
        }

        bootstrap.Modal.getInstance(document.getElementById('modalEntidade')).hide();
        mostrarToast(ehEdicao ? 'Atualizado com sucesso!' : 'Criado com sucesso!', 'success');
        await carregarTudo();
    } catch (err) {
        erroEl.textContent = err.message;
        erroEl.classList.remove('d-none');
    } finally {
        salvandoEntidade = false;
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save"></i> Salvar';
    }
}

async function confirmarExclusao(tipo, id, nome) {
    const tipoLabel = tipo === 'categoria' ? 'categoria' : 'local';
    if (!confirm(`Excluir ${tipoLabel} "${nome}"?\n\nEsta ação não pode ser desfeita.`)) return;

    try {
        if (tipo === 'categoria') {
            await AchouApi.excluirCategoria(id);
        } else {
            await AchouApi.excluirLocal(id);
        }
        mostrarToast(`${tipo === 'categoria' ? 'Categoria' : 'Local'} excluído(a).`, 'success');
        await carregarTudo();
    } catch (err) {
        if (err.status === 409) {
            document.getElementById('conflito-tipo-label').textContent =
                tipo === 'categoria' ? 'A categoria' : 'O local';
            document.getElementById('conflito-nome-label').textContent = nome;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConflito')).show();
        } else {
            mostrarToast(`Erro ao excluir: ${err.message}`, 'danger');
        }
    }
}

document.addEventListener('click', (event) => {
    const newButton = event.target.closest('[data-entity-new]');
    if (newButton) {
        abrirModal(newButton.dataset.entityNew);
        return;
    }

    const actionButton = event.target.closest('[data-entity-edit], [data-entity-delete]');
    if (!actionButton) return;

    const row = actionButton.closest('.entity-row');
    const id = Number(row.dataset.id);
    const nome = row.dataset.nome;
    const tipo = row.dataset.tipo;

    if (actionButton.matches('[data-entity-edit]')) {
        abrirModal(tipo, id, nome);
        return;
    }

    confirmarExclusao(tipo, id, nome);
});

document.getElementById('btn-salvar-entidade').addEventListener('click', salvarEntidade);

document.getElementById('input-nome-entidade').addEventListener('input', () => {
    document.getElementById('input-nome-entidade').classList.remove('is-invalid');
});

document.getElementById('input-nome-entidade').addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        document.getElementById('btn-salvar-entidade').click();
    }
});

document.addEventListener('DOMContentLoaded', carregarTudo);
