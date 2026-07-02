const identificadorLabel = document.getElementById('identificador-label');
const identificadorInput = document.getElementById('identificador');
const identificadorIcon = document.getElementById('identificador-icon');
const identificadorFeedback = document.getElementById('identificador-feedback');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm-password');
const formRegister = document.getElementById('form-register');
const formVerificacao = document.getElementById('form-verificacao');
const verificacaoEmail = document.getElementById('verificacao-email');
const codigoVerificacao = document.getElementById('codigo-verificacao');
const reenviarCodigo = document.getElementById('reenviar-codigo');
const alertaRegistro = document.getElementById('registro-alerta');

function atualizarIdentificador() {
    identificadorLabel.textContent = 'Matrícula';
    identificadorInput.name = 'matricula';
    identificadorInput.placeholder = 'Digite sua matrícula';
    identificadorFeedback.textContent = 'Informe sua matrícula.';
    identificadorIcon.className = 'bi bi-credit-card-2-front';
    identificadorInput.value = '';
}

function validarConfirmacaoSenha() {
    const senhasIguais = confirmPasswordInput.value === passwordInput.value;
    confirmPasswordInput.setCustomValidity(senhasIguais ? '' : 'As senhas precisam ser iguais.');
}

passwordInput.addEventListener('input', validarConfirmacaoSenha);
confirmPasswordInput.addEventListener('input', validarConfirmacaoSenha);

formRegister.addEventListener('submit', async (event) => {
    event.preventDefault();
    validarConfirmacaoSenha();

    if (!formRegister.checkValidity()) {
        formRegister.classList.add('was-validated');
        return;
    }

    alertaRegistro.className = 'alert d-none small py-2';

    try {
        const fd = new FormData(formRegister);
        const payload = {
            nome: fd.get('nome'),
            email: fd.get('email'),
            senha: fd.get('senha'),
            role: 'aluno',
            matricula: fd.get('matricula')
        };

        const response = await AchouApi.register(payload);
        const email = response.email || payload.email;
        verificacaoEmail.value = email;
        formVerificacao.classList.remove('d-none');
        codigoVerificacao.focus();
        alertaRegistro.textContent = 'Cadastro criado. Enviamos um código para seu email institucional. Informe o código para ativar a conta.';
        alertaRegistro.className = 'alert alert-success small py-2';
        formRegister.reset();
        formRegister.classList.remove('was-validated');
        atualizarIdentificador();
    } catch (error) {
        alertaRegistro.textContent = error.message;
        alertaRegistro.className = 'alert alert-danger small py-2';
    }
});

formVerificacao.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!formVerificacao.checkValidity()) {
        formVerificacao.classList.add('was-validated');
        return;
    }

    alertaRegistro.className = 'alert d-none small py-2';

    try {
        await AchouApi.verifyEmail({
            email: verificacaoEmail.value,
            codigo: codigoVerificacao.value
        });

        alertaRegistro.textContent = 'Conta ativada com sucesso. Você já pode fazer login.';
        alertaRegistro.className = 'alert alert-success small py-2';
        formVerificacao.classList.add('d-none');
        formVerificacao.reset();
    } catch (error) {
        alertaRegistro.textContent = error.message;
        alertaRegistro.className = 'alert alert-danger small py-2';
    }
});

reenviarCodigo.addEventListener('click', async () => {
    if (!verificacaoEmail.value) return;

    reenviarCodigo.disabled = true;
    try {
        const response = await AchouApi.resendVerification(verificacaoEmail.value);
        alertaRegistro.textContent = response.mensagem || 'Novo código enviado para seu email institucional.';
        alertaRegistro.className = 'alert alert-success small py-2';
    } catch (error) {
        alertaRegistro.textContent = error.message;
        alertaRegistro.className = 'alert alert-danger small py-2';
    } finally {
        reenviarCodigo.disabled = false;
    }
});
