const formDados = document.getElementById('form-dados');
const formSenha = document.getElementById('form-senha');
const alertaDados = document.getElementById('dados-alerta');
const alertaSenha = document.getElementById('senha-alerta');
const novaSenha = document.getElementById('nova-senha');
const confirmarSenha = document.getElementById('confirmar-senha');

function usuarioAtual() {
    const user = authUser();
    if (!user) {
        requireAuth();
        return null;
    }
    return user;
}

function roleLabel(role) {
    return role === 'admin' ? 'Administrador' : 'Aluno';
}

function identificadorUsuario(user) {
    if (user.role === 'admin') {
        return {
            label: 'SIAPE',
            value: user.siap || 'Nao informado'
        };
    }

    return {
        label: 'Matrícula',
        value: user.matricula || 'Nao informada'
    };
}

function renderPerfil() {
    const user = usuarioAtual();
    if (!user) return;

    const identificador = identificadorUsuario(user);

    document.getElementById('perfil-nome').textContent = user.nome || '-';
    document.getElementById('perfil-email').textContent = user.email || '-';
    document.getElementById('perfil-role').textContent = roleLabel(user.role);
    document.getElementById('perfil-id-label').textContent = identificador.label;
    document.getElementById('perfil-identificador').textContent = identificador.value;
    document.getElementById('form-identificador-label').textContent = identificador.label;

    formDados.elements.nome.value = user.nome || '';
    formDados.elements.email.value = user.email || '';
    formDados.elements.role.value = roleLabel(user.role);
    formDados.elements.identificador.value = identificador.value;

    const perfilVoltar = document.getElementById('perfil-voltar');
    if (perfilVoltar) {
        perfilVoltar.href = authHomeFor(user);
    }
}

function showAlert(element, type, message) {
    element.textContent = message;
    element.className = `alert alert-${type} small py-2 mt-3`;
}

function hideAlert(element) {
    element.className = 'alert d-none small py-2 mt-3';
    element.textContent = '';
}

function validarConfirmacaoSenha() {
    const iguais = novaSenha.value === confirmarSenha.value;
    confirmarSenha.setCustomValidity(iguais ? '' : 'As senhas precisam ser iguais.');
}

formDados.addEventListener('submit', async (event) => {
    event.preventDefault();
    const user = usuarioAtual();
    if (!user) return;

    if (!formDados.checkValidity()) {
        formDados.classList.add('was-validated');
        return;
    }

    hideAlert(alertaDados);

    try {
        const payload = {
            nome: formDados.elements.nome.value.trim(),
            email: formDados.elements.email.value.trim(),
            role: user.role
        };

        const response = await AchouApi.atualizarUsuario(user.id, payload);
        const auth = getAuth();
        const usuarioAtualizado = {
            ...user,
            ...(response.usuario || payload),
            matricula: user.matricula || null,
            siap: user.siap || null
        };

        setAuth({ ...auth, usuario: usuarioAtualizado });
        renderPerfil();
        formDados.classList.remove('was-validated');
        showAlert(alertaDados, 'success', response.mensagem || 'Dados atualizados com sucesso.');
    } catch (error) {
        showAlert(alertaDados, 'danger', error.message);
    }
});

formSenha.addEventListener('submit', async (event) => {
    event.preventDefault();
    const user = usuarioAtual();
    if (!user) return;

    validarConfirmacaoSenha();
    if (!formSenha.checkValidity()) {
        formSenha.classList.add('was-validated');
        return;
    }

    hideAlert(alertaSenha);

    try {
        const response = await AchouApi.atualizarSenha(user.id, {
            senha_atual: formSenha.elements.senha_atual.value,
            nova_senha: formSenha.elements.nova_senha.value
        });

        formSenha.reset();
        formSenha.classList.remove('was-validated');
        showAlert(alertaSenha, 'success', response.mensagem || 'Senha atualizada com sucesso.');
    } catch (error) {
        showAlert(alertaSenha, 'danger', error.message);
    }
});

novaSenha.addEventListener('input', validarConfirmacaoSenha);
confirmarSenha.addEventListener('input', validarConfirmacaoSenha);

renderPerfil();
document.addEventListener('achou:header-ready', renderPerfil);
