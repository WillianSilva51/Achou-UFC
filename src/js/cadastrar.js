const selCat = document.getElementById('sel-cat');
const selLoc = document.getElementById('sel-loc');
const dataEncontrado = document.querySelector('input[name=data_encontrado]');
const formItem = document.getElementById('form-item');
const submitItem = formItem.querySelector('button[type="submit"]');
let cadastroEmAndamento = false;

dataEncontrado.value = new Date().toISOString().slice(0, 10);
dataEncontrado.max = dataEncontrado.value;

async function carregarCombos() {
    try {
        const [categorias, locais] = await Promise.all([
            AchouApi.listarCategorias(),
            AchouApi.listarLocais()
        ]);
        selCat.innerHTML = '<option value="">Selecione...</option>' + categorias.map((c) => `<option value="${escapeHtml(String(c.id))}">${escapeHtml(c.nome)}</option>`).join('');
        selLoc.innerHTML = '<option value="">Selecione...</option>' + locais.map((l) => `<option value="${escapeHtml(String(l.id))}">${escapeHtml(l.nome)}</option>`).join('');
    } catch (error) {
        document.getElementById('ok-msg').className = 'alert alert-danger mt-3';
        document.getElementById('ok-msg').innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${escapeHtml(error.message)}`;
    }
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
    const ok = document.getElementById('ok-msg');
    const payload = {
        titulo,
        descricao,
        categoria_id: parseInt(fd.get('categoria_id'), 10),
        local_id: parseInt(fd.get('local_id'), 10),
        data_encontrado: fd.get('data_encontrado'),
        status: 'disponivel'
    };

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
    submitItem.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cadastrando...';

    try {
        await AchouApi.criarItem(payload);
        this.reset();
        this.classList.remove('was-validated');
        dataEncontrado.value = new Date().toISOString().slice(0, 10);
        ok.className = 'alert alert-success mt-3';
        ok.innerHTML = '<i class="bi bi-check-circle-fill"></i> Item cadastrado com sucesso!';
        setTimeout(() => ok.classList.add('d-none'), 3000);
    } catch (error) {
        ok.className = 'alert alert-danger mt-3';
        ok.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${escapeHtml(error.message)}`;
    } finally {
        cadastroEmAndamento = false;
        submitItem.disabled = false;
        submitItem.innerHTML = '<i class="bi bi-save"></i> Cadastrar';
    }
});

carregarCombos();
