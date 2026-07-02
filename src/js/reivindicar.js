const params = new URLSearchParams(location.search);
const itemId = Number.parseInt(params.get('id'), 10);
const wrap = document.getElementById('conteudo');

function createElement(tagName, className = '') {
    const element = document.createElement(tagName);
    if (className) {
        element.className = className;
    }
    return element;
}

function appendText(parent, text) {
    if (!parent) return;

    parent.appendChild(document.createTextNode(text));
}

function renderWarning(message) {
    if (!wrap) return;

    const column = createElement('div', 'col-12');
    const alert = createElement('div', 'alert alert-warning');

    appendText(alert, message);
    alert.appendChild(document.createTextNode(' '));

    const link = document.createElement('a');
    link.href = 'vitrine.html';
    link.textContent = 'Voltar';
    alert.appendChild(link);
    alert.appendChild(document.createTextNode('.'));

    column.appendChild(alert);
    wrap.replaceChildren(column);
}

function buildUserFieldState(user = {}) {
    return {
        nome: user.nome || '',
        email: user.email || '',
        matricula: user.matricula || '',
        nomeReadonly: user.nome ? 'readonly' : '',
        emailReadonly: user.email ? 'readonly' : '',
        matriculaReadonly: user.matricula ? 'readonly' : ''
    };
}

function buildItemMarkup(item, userState) {
    const leftColumn = createElement('div', 'col-md-5');
    const leftCard = createElement('div', 'card item-card');
    const imageWrapper = createElement('div', 'item-img');
    imageWrapper.style.height = '280px';

    const title = createElement('h5');
    title.textContent = item.titulo;

    const description = createElement('p');
    description.className = 'text-muted small';
    description.textContent = item.descricao;

    const list = createElement('ul', 'list-unstyled small mb-0');
    const items = [
        ['bi bi-geo-alt', 'Local:', item.local || nomeLocal(item.local_id)],
        ['bi bi-calendar3', 'Encontrado em:', formatDate(item.data_encontrado)],
        ['bi bi-tag', 'Status:', String(item.status || '').replace('_', ' ')]
    ];

    items.forEach(([iconClass, label, value]) => {
        const listItem = createElement('li');
        const icon = createElement('i', iconClass);
        const strong = document.createElement('strong');
        strong.textContent = `${label} `;

        listItem.appendChild(icon);
        listItem.appendChild(document.createTextNode(' '));
        listItem.appendChild(strong);
        listItem.appendChild(document.createTextNode(String(value)));
        list.appendChild(listItem);
    });

    const badge = createElement('span', 'badge badge-cat');
    badge.textContent = nomeCategoria(item.categoria_id);

    if (item.foto_url) {
        const image = document.createElement('img');
        image.src = item.foto_url;
        image.alt = item.titulo;
        imageWrapper.appendChild(image);
    } else {
        const icon = createElement('i', `bi ${iconeCategoria(item.categoria_id)}`);
        imageWrapper.appendChild(icon);
    }

    imageWrapper.appendChild(badge);

    leftCard.appendChild(imageWrapper);
    leftCard.appendChild(createElement('div', 'card-body'));
    leftCard.lastElementChild.appendChild(title);
    leftCard.lastElementChild.appendChild(description);
    leftCard.lastElementChild.appendChild(list);
    leftColumn.appendChild(leftCard);

    const rightColumn = createElement('div', 'col-md-7');
    const rightCard = createElement('div', 'card item-card');
    const rightBody = createElement('div', 'card-body');

    const heading = createElement('h5', 'mb-1');
    const headingIcon = createElement('i', 'bi bi-hand-index-thumb text-primary');
    heading.appendChild(headingIcon);
    heading.appendChild(document.createTextNode(' Solicitar retirada'));

    const intro = createElement('p', 'text-muted small');
    intro.textContent = 'Preencha seus dados. A recepção entrará em contato para confirmar e liberar o item.';

    const form = document.createElement('form');
    form.id = 'form-reiv';
    form.className = 'needs-validation';
    form.setAttribute('novalidate', '');

    const formRow = createElement('div', 'row g-3');

    const fields = [
        {
            label: 'Nome completo *',
            type: 'text',
            name: 'nome',
            required: true,
            minLength: 3,
            maxLength: 120,
            value: userState.nome,
            readonly: userState.nomeReadonly,
            feedback: 'Informe seu nome (mín. 3 caracteres).'
        },
        {
            label: 'Matrícula *',
            type: 'text',
            name: 'matricula',
            required: true,
            pattern: '[0-9]{6,12}',
            maxLength: 12,
            value: userState.matricula,
            readonly: userState.matriculaReadonly,
            feedback: 'Matrícula deve conter de 6 a 12 dígitos.'
        },
        {
            label: 'E-mail institucional *',
            type: 'email',
            name: 'email',
            required: true,
            maxLength: 120,
            placeholder: 'seuemail@alu.ufc.br',
            value: userState.email,
            readonly: userState.emailReadonly,
            feedback: 'Informe um e-mail válido.'
        }
    ];

    fields.forEach((field) => {
        const wrapper = createElement('div', 'col-md-6');
        if (field.name === 'email') {
            wrapper.className = 'col-md-7';
        }

        const label = document.createElement('label');
        label.className = 'form-label';
        label.textContent = field.label;

        const input = document.createElement('input');
        input.type = field.type;
        input.className = 'form-control';
        input.name = field.name;
        input.required = Boolean(field.required);
        input.maxLength = field.maxLength || 120;
        if (field.minLength) input.minLength = field.minLength;
        if (field.pattern) input.pattern = field.pattern;
        if (field.placeholder) input.placeholder = field.placeholder;
        if (field.value) input.value = field.value;
        if (field.readonly) input.setAttribute('readonly', '');

        const feedback = createElement('div', 'invalid-feedback');
        feedback.textContent = field.feedback;

        wrapper.appendChild(label);
        wrapper.appendChild(input);
        wrapper.appendChild(feedback);
        formRow.appendChild(wrapper);
    });

    const phoneWrapper = createElement('div', 'col-md-5');
    const phoneLabel = document.createElement('label');
    phoneLabel.className = 'form-label';
    phoneLabel.textContent = 'Telefone (WhatsApp)';

    const phoneInput = document.createElement('input');
    phoneInput.type = 'tel';
    phoneInput.className = 'form-control';
    phoneInput.name = 'telefone';
    phoneInput.pattern = '[0-9\\s\\-\\(\\)\\+]{8,20}';
    phoneInput.placeholder = '(88) 9 9999-9999';

    const phoneFeedback = createElement('div', 'invalid-feedback');
    phoneFeedback.textContent = 'Telefone inválido.';

    phoneWrapper.appendChild(phoneLabel);
    phoneWrapper.appendChild(phoneInput);
    phoneWrapper.appendChild(phoneFeedback);
    formRow.appendChild(phoneWrapper);

    const proofWrapper = createElement('div', 'col-12');
    const proofLabel = document.createElement('label');
    proofLabel.className = 'form-label';
    proofLabel.textContent = 'Comprove que o item é seu *';

    const textarea = document.createElement('textarea');
    textarea.className = 'form-control';
    textarea.name = 'prova';
    textarea.rows = 3;
    textarea.required = true;
    textarea.minLength = 10;
    textarea.maxLength = 500;
    textarea.placeholder = 'Descreva detalhes que só o dono saberia (cor, marca, conteúdo, arranhões...)';

    const proofFeedback = createElement('div', 'invalid-feedback');
    proofFeedback.textContent = 'Descreva com pelo menos 10 caracteres.';

    proofWrapper.appendChild(proofLabel);
    proofWrapper.appendChild(textarea);
    proofWrapper.appendChild(proofFeedback);
    formRow.appendChild(proofWrapper);

    const checkWrapper = createElement('div', 'col-12 form-check ms-2');
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'form-check-input';
    checkbox.id = 'ok';
    checkbox.required = true;

    const checkLabel = document.createElement('label');
    checkLabel.className = 'form-check-label small';
    checkLabel.htmlFor = 'ok';
    checkLabel.textContent = 'Declaro que as informações fornecidas são verdadeiras.';

    checkWrapper.appendChild(checkbox);
    checkWrapper.appendChild(checkLabel);
    formRow.appendChild(checkWrapper);

    const actions = createElement('div', 'd-flex gap-2 mt-4');
    const submitButton = document.createElement('button');
    submitButton.type = 'submit';
    submitButton.className = 'btn btn-ufc';
    submitButton.appendChild(createElement('i', 'bi bi-send'));
    submitButton.appendChild(document.createTextNode(' Enviar reivindicação'));

    const cancelLink = document.createElement('a');
    cancelLink.href = 'vitrine.html';
    cancelLink.className = 'btn btn-outline-secondary';
    cancelLink.textContent = 'Cancelar';

    actions.appendChild(submitButton);
    actions.appendChild(cancelLink);

    form.appendChild(formRow);
    form.appendChild(actions);

    const feedback = createElement('div', 'alert alert-success mt-3 d-none');
    feedback.id = 'sucesso';
    feedback.appendChild(createElement('i', 'bi bi-check-circle-fill'));
    feedback.appendChild(document.createTextNode(' Reivindicação registrada! A recepção fará contato em até 2 dias úteis. Protocolo: '));
    const protocol = document.createElement('strong');
    protocol.id = 'protocolo';
    feedback.appendChild(protocol);

    const confirmation = createElement('div', 'modal fade');
    confirmation.id = 'confirmacao-reivindicacao';
    confirmation.tabIndex = -1;
    confirmation.setAttribute('aria-labelledby', 'confirmacao-reivindicacao-titulo');
    confirmation.setAttribute('aria-hidden', 'true');

    const modalDialog = createElement('div', 'modal-dialog modal-dialog-centered');
    const modalContent = createElement('div', 'modal-content');
    const modalHeader = createElement('div', 'modal-header bg-warning-subtle');
    const modalTitle = createElement('h5', 'modal-title text-warning-emphasis');
    modalTitle.id = 'confirmacao-reivindicacao-titulo';
    modalTitle.appendChild(createElement('i', 'bi bi-exclamation-triangle-fill'));
    modalTitle.appendChild(document.createTextNode(' Atenção antes de enviar'));

    const modalCloseButton = document.createElement('button');
    modalCloseButton.type = 'button';
    modalCloseButton.className = 'btn-close';
    modalCloseButton.setAttribute('data-bs-dismiss', 'modal');
    modalCloseButton.setAttribute('aria-label', 'Fechar');

    const modalBody = createElement('div', 'modal-body');
    const confirmationText = createElement('p', 'mb-2');
    confirmationText.textContent = 'Após confirmar a reivindicação, o pedido entrará em análise. Caso seja aprovado, você terá apenas 4 horas para retirar o item na recepção.';

    const confirmationQuestion = createElement('p', 'mb-0 fw-semibold');
    confirmationQuestion.textContent = 'Você tem certeza que deseja fazer isso?';

    const modalFooter = createElement('div', 'modal-footer');

    const confirmButton = document.createElement('button');
    confirmButton.type = 'button';
    confirmButton.className = 'btn btn-ufc';
    confirmButton.id = 'confirmar-reivindicacao';
    confirmButton.appendChild(createElement('i', 'bi bi-check2-circle'));
    confirmButton.appendChild(document.createTextNode(' Sim, enviar reivindicação'));

    const cancelConfirmationButton = document.createElement('button');
    cancelConfirmationButton.type = 'button';
    cancelConfirmationButton.className = 'btn btn-outline-secondary';
    cancelConfirmationButton.id = 'cancelar-confirmacao-reivindicacao';
    cancelConfirmationButton.setAttribute('data-bs-dismiss', 'modal');
    cancelConfirmationButton.textContent = 'Cancelar';

    modalHeader.appendChild(modalTitle);
    modalHeader.appendChild(modalCloseButton);
    modalBody.appendChild(confirmationText);
    modalBody.appendChild(confirmationQuestion);
    modalFooter.appendChild(cancelConfirmationButton);
    modalFooter.appendChild(confirmButton);
    modalContent.appendChild(modalHeader);
    modalContent.appendChild(modalBody);
    modalContent.appendChild(modalFooter);
    modalDialog.appendChild(modalContent);
    confirmation.appendChild(modalDialog);

    rightBody.appendChild(heading);
    rightBody.appendChild(intro);
    rightBody.appendChild(form);
    rightBody.appendChild(feedback);
    document.body.appendChild(confirmation);

    rightCard.appendChild(rightBody);
    rightColumn.appendChild(rightCard);

    return [leftColumn, rightColumn];
}

function setFeedbackMessage(message, variant, protocol = '') {
    const feedback = document.getElementById('sucesso');
    if (!feedback) return;

    feedback.className = `alert alert-${variant} mt-3`;
    feedback.classList.remove('d-none');
    feedback.replaceChildren();

    const icon = createElement('i', variant === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-triangle-fill');
    feedback.appendChild(icon);
    feedback.appendChild(document.createTextNode(' '));

    if (variant === 'success') {
        feedback.appendChild(document.createTextNode('Reivindicação registrada! A recepção fará contato em até 2 dias úteis. Protocolo: '));
        const strong = document.createElement('strong');
        strong.textContent = protocol;
        feedback.appendChild(strong);
    } else {
        feedback.appendChild(document.createTextNode(message));
    }
}

function bindReivindicacaoForm(item) {
    const form = document.getElementById('form-reiv');
    if (!form) return;

    const confirmation = document.getElementById('confirmacao-reivindicacao');
    const confirmButton = document.getElementById('confirmar-reivindicacao');
    const cancelConfirmationButton = document.getElementById('cancelar-confirmacao-reivindicacao');
    const submitButton = form.querySelector('button[type="submit"]');
    const confirmationModal = window.bootstrap?.Modal && confirmation
        ? new bootstrap.Modal(confirmation)
        : null;
    let isSubmitting = false;

    async function submitReivindicacao() {
        if (isSubmitting) return;

        isSubmitting = true;
        if (submitButton) submitButton.disabled = true;
        if (confirmButton) {
            confirmButton.disabled = true;
            const spinner = createElement('span', 'spinner-border spinner-border-sm');
            spinner.setAttribute('aria-hidden', 'true');
            confirmButton.replaceChildren(spinner, document.createTextNode(' Enviando...'));
        }

        let novo;
        try {
            novo = await AchouApi.criarReivindicacao({ item_id: item.id });
        } catch (error) {
            isSubmitting = false;
            if (submitButton) submitButton.disabled = false;
            if (confirmButton) {
                confirmButton.disabled = false;
                confirmButton.replaceChildren(
                    createElement('i', 'bi bi-check2-circle'),
                    document.createTextNode(' Sim, enviar reivindicação')
                );
            }
            setFeedbackMessage(error.message, 'danger');
            return;
        }

        form.classList.add('d-none');
        confirmationModal?.hide();
        const protocolo = '#' + String(novo.id_reivindicacao || novo.id || '').padStart(4, '0');
        setFeedbackMessage('', 'success', protocolo);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    form.addEventListener('submit', async function handleSubmit(event) {
        event.preventDefault();

        if (!this.checkValidity()) {
            this.classList.add('was-validated');
            return;
        }

        document.getElementById('sucesso')?.classList.add('d-none');
        if (confirmationModal) {
            confirmationModal.show();
        }
    });

    confirmButton?.addEventListener('click', submitReivindicacao);

    cancelConfirmationButton?.addEventListener('click', () => {
        confirmationModal?.hide();
    });
}

async function initReivindicacao() {
    if (!requireAuth('aluno')) return;

    await Promise.all([
        AchouApi.listarCategorias(),
        AchouApi.listarLocais()
    ]);

    let item;
    try {
        item = await AchouApi.buscarItem(itemId);
    } catch (error) {
        renderWarning(error.message);
        return;
    }

    if (!item) {
        renderWarning('Item não encontrado.');
        return;
    }

    const userState = buildUserFieldState(authUser() || {});
    const renderedContent = buildItemMarkup(item, userState);
    wrap.replaceChildren(...renderedContent);
    bindReivindicacaoForm(item);
}

initReivindicacao();
