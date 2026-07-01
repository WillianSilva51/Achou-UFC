const identificadorLabel = document.getElementById('identificador-label');
const identificadorInput = document.getElementById('identificador');
const identificadorIcon = document.getElementById('identificador-icon');
const identificadorFeedback = document.getElementById('identificador-feedback');
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm-password');
const formRegister = document.getElementById('form-register');
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

        await AchouApi.register(payload);
        alertaRegistro.textContent = 'Cadastro realizado com sucesso. Faça login para continuar.';
        alertaRegistro.className = 'alert alert-success small py-2';
        formRegister.reset();
        formRegister.classList.remove('was-validated');
        atualizarIdentificador();
    } catch (error) {
        alertaRegistro.textContent = error.message;
        alertaRegistro.className = 'alert alert-danger small py-2';
    }
});
