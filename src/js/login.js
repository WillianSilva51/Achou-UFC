redirectIfAuthenticated();

const formLogin = document.getElementById('form-login');
const formVerificacaoLogin = document.getElementById('form-verificacao-login');
const loginVerificacaoEmail = document.getElementById('login-verificacao-email');
const loginCodigoVerificacao = document.getElementById('login-codigo-verificacao');
const loginReenviarCodigo = document.getElementById('login-reenviar-codigo');
const erroLogin = document.getElementById('erro');
const submitLogin = formLogin.querySelector('button[type="submit"]');
let loginEmAndamento = false;

function setLoginButtonLoading() {
    const spinner = domEl('span', 'spinner-border spinner-border-sm me-2');
    submitLogin.replaceChildren(spinner, document.createTextNode('Entrando...'));
}

formLogin.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (loginEmAndamento) return;

    if (!formLogin.checkValidity()) {
        formLogin.classList.add('was-validated');
        return;
    }

    erroLogin.className = 'alert alert-danger small py-2 d-none';
    loginEmAndamento = true;
    submitLogin.disabled = true;
    setLoginButtonLoading();

    try {
        const fd = new FormData(formLogin);
        const email = fd.get('email');
        await AchouApi.login({
            email,
            senha: fd.get('senha')
        });

        const next = new URLSearchParams(location.search).get('next');
        location.href = safeRedirectTarget(next, authUser()?.role === 'admin' ? 'admin.html' : 'vitrine.html');
    } catch (error) {
        erroLogin.textContent = error.message;
        erroLogin.className = 'alert alert-danger small py-2';
        if (error.payload?.requires_verification && error.payload?.email) {
            loginVerificacaoEmail.value = error.payload.email;
            formVerificacaoLogin.classList.remove('d-none');
            loginCodigoVerificacao.focus();
        }
        loginEmAndamento = false;
        submitLogin.disabled = false;
        submitLogin.textContent = 'Entrar';
    }
});

formVerificacaoLogin.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!formVerificacaoLogin.checkValidity()) {
        formVerificacaoLogin.classList.add('was-validated');
        return;
    }

    erroLogin.classList.add('d-none');

    try {
        await AchouApi.verifyEmail({
            email: loginVerificacaoEmail.value,
            codigo: loginCodigoVerificacao.value
        });

        erroLogin.textContent = 'Conta ativada com sucesso. Faça login para continuar.';
        erroLogin.className = 'alert alert-success small py-2';
        formVerificacaoLogin.classList.add('d-none');
        formVerificacaoLogin.reset();
    } catch (error) {
        erroLogin.textContent = error.message;
        erroLogin.className = 'alert alert-danger small py-2';
    }
});

loginReenviarCodigo.addEventListener('click', async () => {
    if (!loginVerificacaoEmail.value) return;

    loginReenviarCodigo.disabled = true;
    try {
        const response = await AchouApi.resendVerification(loginVerificacaoEmail.value);
        erroLogin.textContent = response.mensagem || 'Novo código enviado para seu email institucional.';
        erroLogin.className = 'alert alert-success small py-2';
    } catch (error) {
        erroLogin.textContent = error.message;
        erroLogin.className = 'alert alert-danger small py-2';
    } finally {
        loginReenviarCodigo.disabled = false;
    }
});
