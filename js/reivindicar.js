const params = new URLSearchParams(location.search);
const itemId = parseInt(params.get('id'), 10);
const wrap = document.getElementById('conteudo');

async function initReivindicacao() {
    if (!requireAuth('aluno')) return;

    await Promise.all([
        mockApi.listarCategorias(),
        mockApi.listarLocais()
    ]);

    let item;
    try {
        item = await mockApi.buscarItem(itemId);
    } catch (error) {
        wrap.innerHTML = `<div class="col-12"><div class="alert alert-warning">${escapeHtml(error.message)} <a href="index.html">Voltar</a>.</div></div>`;
        return;
    }

    if (!item) {
        wrap.innerHTML = `<div class="col-12"><div class="alert alert-warning">Item não encontrado. <a href="index.html">Voltar</a>.</div></div>`;
        return;
    }

    const titulo = escapeHtml(item.titulo);
    const descricao = escapeHtml(item.descricao);
    const status = escapeHtml(item.status);
    const user = authUser() || {};
    const nomeUsuario = escapeHtml(user.nome || '');
    const emailUsuario = escapeHtml(user.email || '');
    const matriculaUsuario = escapeHtml(user.matricula || '');
    const nomeReadonly = nomeUsuario ? 'readonly' : '';
    const emailReadonly = emailUsuario ? 'readonly' : '';
    const matriculaReadonly = matriculaUsuario ? 'readonly' : '';

    wrap.innerHTML = `
      <div class="col-md-5">
        <div class="card item-card">
          <div class="item-img" style="height:280px;">
            ${item.foto_url ? `<img src="${escapeHtml(item.foto_url)}" alt="${titulo}">` : `<i class="bi ${iconeCategoria(item.categoria_id)}"></i>`}
            <span class="badge badge-cat">${escapeHtml(nomeCategoria(item.categoria_id))}</span>
          </div>
          <div class="card-body">
            <h5>${titulo}</h5>
            <p class="text-muted small">${descricao}</p>
            <ul class="list-unstyled small mb-0">
              <li><i class="bi bi-geo-alt"></i> <strong>Local:</strong> ${escapeHtml(item.local || nomeLocal(item.local_id))}</li>
              <li><i class="bi bi-calendar3"></i> <strong>Encontrado em:</strong> ${formatDate(item.data_encontrado)}</li>
              <li><i class="bi bi-tag"></i> <strong>Status:</strong> ${status.replace('_', ' ')}</li>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-md-7">
        <div class="card item-card">
          <div class="card-body">
            <h5 class="mb-1"><i class="bi bi-hand-index-thumb text-primary"></i> Solicitar retirada</h5>
            <p class="text-muted small">Preencha seus dados. A recepção entrará em contato para confirmar e liberar o item.</p>

            <form id="form-reiv" class="needs-validation" novalidate>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Nome completo *</label>
                  <input type="text" class="form-control" name="nome" required minlength="3" maxlength="120" value="${nomeUsuario}" ${nomeReadonly}>
                  <div class="invalid-feedback">Informe seu nome (mín. 3 caracteres).</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Matrícula *</label>
                  <input type="text" class="form-control" name="matricula" required pattern="[0-9]{6,12}" maxlength="12" value="${matriculaUsuario}" ${matriculaReadonly}>
                  <div class="invalid-feedback">Matrícula deve conter de 6 a 12 dígitos.</div>
                </div>
                <div class="col-md-7">
                  <label class="form-label">E-mail institucional *</label>
                  <input type="email" class="form-control" name="email" required maxlength="120" placeholder="seuemail@alu.ufc.br" value="${emailUsuario}" ${emailReadonly}>
                  <div class="invalid-feedback">Informe um e-mail válido.</div>
                </div>
                <div class="col-md-5">
                  <label class="form-label">Telefone (WhatsApp)</label>
                  <input type="tel" class="form-control" name="telefone" pattern="[0-9\\s\\-\\(\\)\\+]{8,20}" placeholder="(88) 9 9999-9999">
                  <div class="invalid-feedback">Telefone inválido.</div>
                </div>
                <div class="col-12">
                  <label class="form-label">Comprove que o item é seu *</label>
                  <textarea class="form-control" name="prova" rows="3" required minlength="10" maxlength="500" placeholder="Descreva detalhes que só o dono saberia (cor, marca, conteúdo, arranhões...)"></textarea>
                  <div class="invalid-feedback">Descreva com pelo menos 10 caracteres.</div>
                </div>
                <div class="col-12 form-check ms-2">
                  <input type="checkbox" class="form-check-input" id="ok" required>
                  <label class="form-check-label small" for="ok">Declaro que as informações fornecidas são verdadeiras.</label>
                </div>
              </div>
              <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-ufc"><i class="bi bi-send"></i> Enviar reivindicação</button>
                <a href="index.html" class="btn btn-outline-secondary">Cancelar</a>
              </div>
            </form>

            <div id="sucesso" class="alert alert-success mt-3 d-none">
              <i class="bi bi-check-circle-fill"></i> Reivindicação registrada! A recepção fará contato em até 2 dias úteis. Protocolo: <strong id="protocolo"></strong>
            </div>
          </div>
        </div>
      </div>
    `;

    document.getElementById('form-reiv').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.classList.add('was-validated'); return; }
        let novo;
        try {
            novo = await mockApi.criarReivindicacao({ item_id: item.id });
        } catch (error) {
            const s = document.getElementById('sucesso');
            s.className = 'alert alert-danger mt-3';
            s.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i> ${escapeHtml(error.message)}`;
            return;
        }

        this.classList.add('d-none');
        const s = document.getElementById('sucesso');
        s.className = 'alert alert-success mt-3';
        const protocolo = '#' + String(novo.id_reivindicacao || novo.id || '').padStart(4, '0');
        s.innerHTML = `<i class="bi bi-check-circle-fill"></i> Reivindicação registrada! A recepção fará contato em até 2 dias úteis. Protocolo: <strong>${protocolo}</strong>`;
        s.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

initReivindicacao();
