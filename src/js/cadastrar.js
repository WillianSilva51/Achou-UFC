const selCat = document.getElementById('sel-cat');
const selLoc = document.getElementById('sel-loc');
const dataEncontrado = document.querySelector('input[name=data_encontrado]');
const formItem = document.getElementById('form-item');
const submitItem = formItem.querySelector('button[type="submit"]');
const fotoArquivoInput = formItem.querySelector('input[name="foto_arquivo"]');
const FOTO_MAX_BYTES = 5 * 1024 * 1024;
const FOTO_MIMES_PERMITIDOS = new Set(['image/png', 'image/jpeg', 'image/webp']);
let cadastroEmAndamento = false;

dataEncontrado.value = new Date().toISOString().slice(0, 10);
dataEncontrado.max = dataEncontrado.value;

async function carregarCombos() {
    try {
        const [categorias, locais] = await Promise.all([
            AchouApi.listarCategorias(),
            AchouApi.listarLocais()
        ]);
        preencherSelect(selCat, categorias);
        preencherSelect(selLoc, locais);
    } catch (error) {
        mostrarMensagem('danger', 'bi-exclamation-triangle-fill', error.message);
    }
}

function preencherSelect(select, lista) {
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = 'Selecione...';

    select.replaceChildren(
        defaultOption,
        ...lista.map((item) => {
            const option = document.createElement('option');
            option.value = String(item.id);
            option.textContent = item.nome;
            return option;
        })
    );
}

function mostrarMensagem(tipo, iconClass, mensagem) {
    const ok = document.getElementById('ok-msg');

    ok.className = `alert alert-${tipo} mt-3`;
    ok.replaceChildren(domIcon(iconClass), document.createTextNode(` ${mensagem}`));
    return ok;
}

function setSubmitContent(iconClass, text) {
    submitItem.replaceChildren(domIcon(iconClass), document.createTextNode(` ${text}`));
}

function setSubmitLoading() {
    const spinner = domEl('span', 'spinner-border spinner-border-sm me-2');
    submitItem.replaceChildren(spinner, document.createTextNode('Cadastrando...'));
}

function arquivoParaBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result || ''));
        reader.onerror = () => reject(new Error('Não foi possível ler a foto selecionada.'));
        reader.readAsDataURL(file);
    });
}

async function montarFotoBase64(file) {
    if (!FOTO_MIMES_PERMITIDOS.has(file.type)) {
        throw new Error('Formato de foto inválido. Use PNG, JPEG ou WebP.');
    }

    if (file.size > FOTO_MAX_BYTES) {
        throw new Error('A foto deve ter no máximo 5MB.');
    }

    return {
        nome: file.name,
        tipo: file.type,
        tamanho: file.size,
        conteudo: await arquivoParaBase64(file)
    };
}

formItem.addEventListener('submit', async function (event) {
    event.preventDefault();

    if (cadastroEmAndamento) return;

    if (!this.checkValidity()) {
        this.classList.add('was-validated');
        return;
    }

    const fd = new FormData(this);
    const titulo = String(fd.get('titulo') || '').trim();
    const descricao = String(fd.get('descricao') || '').trim();
    const fotoUrl = String(fd.get('foto_url') || '').trim();
    const fotoArquivo = fotoArquivoInput?.files?.[0] || null;
    const ok = document.getElementById('ok-msg');
    const payload = {
        titulo,
        descricao,
        categoria_id: parseInt(fd.get('categoria_id'), 10),
        local_id: parseInt(fd.get('local_id'), 10),
        data_encontrado: fd.get('data_encontrado'),
        status: 'disponivel'
    };

    if (fotoUrl && fotoArquivo) {
        ok.className = 'alert alert-danger mt-3';
        ok.textContent = 'Use uma URL de foto ou envie um arquivo, não os dois.';
        return;
    }

    if (fotoUrl) {
        if (!safeImageUrl(fotoUrl)) {
            ok.className = 'alert alert-danger mt-3';
            ok.textContent = 'Informe uma URL de foto http(s) válida.';
            return;
        }
        payload.foto_url = fotoUrl;
    }

    cadastroEmAndamento = true;
    submitItem.disabled = true;
    setSubmitLoading();

    try {
        if (fotoArquivo) {
            payload.foto_base64 = await montarFotoBase64(fotoArquivo);
        }

        await AchouApi.criarItem(payload);
        this.reset();
        this.classList.remove('was-validated');
        dataEncontrado.value = new Date().toISOString().slice(0, 10);
        mostrarMensagem('success', 'bi-check-circle-fill', 'Item cadastrado com sucesso!');
        setTimeout(() => ok.classList.add('d-none'), 3000);
    } catch (error) {
        mostrarMensagem('danger', 'bi-exclamation-triangle-fill', error.message);
    } finally {
        cadastroEmAndamento = false;
        submitItem.disabled = false;
        setSubmitContent('bi-save', 'Cadastrar');
    }
});

carregarCombos();
