redirectIfAuthenticated();

const formLogin = document.getElementById('form-login');
const erroLogin = document.getElementById('erro');
const submitLogin = formLogin.querySelector('button[type="submit"]');
let loginEmAndamento = false;

formLogin.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (loginEmAndamento) return;

    if (!formLogin.checkValidity()) {
        formLogin.classList.add('was-validated');
        return;
    }

    erroLogin.classList.add('d-none');
    loginEmAndamento = true;
    submitLogin.disabled = true;
    submitLogin.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';

    try {
        const fd = new FormData(formLogin);
        await AchouApi.login({
            email: fd.get('email'),
            senha: fd.get('senha')
        });

        const next = new URLSearchParams(location.search).get('next');
        location.href = safeRedirectTarget(next, authUser()?.role === 'admin' ? 'admin.html' : 'vitrine.html');
    } catch (error) {
        erroLogin.textContent = error.message;
        erroLogin.classList.remove('d-none');
        loginEmAndamento = false;
        submitLogin.disabled = false;
        submitLogin.textContent = 'Entrar';
    }
});
