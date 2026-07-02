let itemId = null;

const STATUS_LABEL = {
    disponivel: { label: 'Disponível', cls: 'bg-success' },
    em_analise: { label: 'Em análise', cls: 'bg-warning text-dark' },
    devolvido: { label: 'Devolvido', cls: 'bg-secondary' },
    arquivado: { label: 'Arquivado', cls: 'bg-dark' }
};

function preencherSelect(selectId, lista, valueKey, labelKey, valorSelecionado) {
    const sel = document.getElementById(selectId);
    sel.replaceChildren(...lista.map((item) => {
        const option = document.createElement('option');
        option.value = String(item[valueKey]);
        option.textContent = item[labelKey];
        option.selected = Number(item[valueKey]) === Number(valorSelecionado);
        return option;
    }));
}

function setSaveButtonContent(button) {
    button.replaceChildren(domIcon('bi-save me-1'), document.createTextNode(' Salvar alterações'));
}

function setSaveButtonLoading(button) {
    const spinner = domEl('span', 'spinner-border spinner-border-sm me-2');
    button.replaceChildren(spinner, document.createTextNode('Salvando...'));
}

document.getElementById('input-foto').addEventListener('input', function () {
    const url = this.value.trim();
    const col = document.getElementById('col-preview');
    const img = document.getElementById('img-preview');

    if (url) {
        img.src = url;
        img.onerror = () => { col.classList.add('d-none'); };
        img.onload = () => { col.classList.remove('d-none'); };
    } else {
        col.classList.add('d-none');
    }
});

async function carregarItem() {
    itemId = new URLSearchParams(location.search).get('id');

    if (!itemId || isNaN(Number(itemId))) {
        document.getElementById('bloco-loading').classList.add('d-none');
        document.getElementById('bloco-erro-id').classList.remove('d-none');
        document.getElementById('texto-erro-id').textContent =
            'URL inválida: nenhum ID de item foi informado. Acesse esta página a partir do botão "Editar" no painel.';
        return;
    }

    try {
        const [item, cats, locs] = await Promise.all([
            AchouApi.buscarItem(Number(itemId)),
            AchouApi.listarCategorias(),
            AchouApi.listarLocais()
        ]);

        preencherSelect('sel-categoria', cats, 'id', 'nome', item.categoria_id);
        preencherSelect('sel-local', locs, 'id', 'nome', item.local_id);

        document.getElementById('input-titulo').value = item.titulo || '';
        document.getElementById('input-descricao').value = item.descricao || '';
        document.getElementById('input-data').value = (item.data_encontrado || '').slice(0, 10);
        document.getElementById('input-foto').value = item.foto_url || '';
        document.getElementById('sel-status').value = item.status || 'disponivel';

        const statusConfig = STATUS_LABEL[item.status] || { label: item.status, cls: 'bg-secondary' };
        const badgeEl = document.getElementById('badge-status-atual');
        badgeEl.textContent = statusConfig.label;
        badgeEl.className = `badge text-white ${statusConfig.cls}`;

        if (item.foto_url) {
            const col = document.getElementById('col-preview');
            const img = document.getElementById('img-preview');
            img.src = item.foto_url;
            img.onload = () => { col.classList.remove('d-none'); };
        }

        document.getElementById('bloco-loading').classList.add('d-none');
        document.getElementById('bloco-form').classList.remove('d-none');
    } catch (err) {
        document.getElementById('bloco-loading').classList.add('d-none');
        document.getElementById('bloco-erro-id').classList.remove('d-none');
        document.getElementById('texto-erro-id').textContent = err.message;
    }
}

document.getElementById('form-editar').addEventListener('submit', async function (event) {
    event.preventDefault();

    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const btn = document.getElementById('btn-salvar');
    const alertaSuc = document.getElementById('alerta-sucesso');
    const alertaErr = document.getElementById('alerta-erro');

    btn.disabled = true;
    setSaveButtonLoading(btn);
    alertaSuc.classList.add('d-none');
    alertaErr.classList.add('d-none');

    const fotoUrl = document.getElementById('input-foto').value.trim();
    if (fotoUrl && !safeImageUrl(fotoUrl)) {
        document.getElementById('texto-erro-submit').textContent = 'Informe uma URL de foto http(s) válida.';
        alertaErr.classList.remove('d-none');
        alertaErr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        btn.disabled = false;
        setSaveButtonContent(btn);
        return;
    }

    const payload = {
        titulo: document.getElementById('input-titulo').value.trim(),
        descricao: document.getElementById('input-descricao').value.trim(),
        categoria_id: Number(document.getElementById('sel-categoria').value),
        local_id: Number(document.getElementById('sel-local').value),
        data_encontrado: document.getElementById('input-data').value,
        status: document.getElementById('sel-status').value,
        foto_url: fotoUrl
    };

    try {
        await AchouApi.atualizarItem(itemId, payload);
        alertaSuc.classList.remove('d-none');
        alertaSuc.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        setTimeout(() => { location.href = 'admin.html'; }, 1500);
    } catch (err) {
        document.getElementById('texto-erro-submit').textContent = err.message;
        alertaErr.classList.remove('d-none');
        alertaErr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        btn.disabled = false;
        setSaveButtonContent(btn);
    }
});

document.addEventListener('DOMContentLoaded', carregarItem);
