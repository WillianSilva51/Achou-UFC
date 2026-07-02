const filtrosUsuarios = {
    busca: document.getElementById('f-busca-usuario'),
    role: document.getElementById('f-role-usuario'),
    form: document.getElementById('form-filtros-usuarios')
};

function usuarioRoleBadge(role) {
    const config = {
        admin: { cls: 'text-bg-primary', label: 'Administrador' },
        aluno: { cls: 'text-bg-success', label: 'Aluno' }
    };
    const item = config[role] || { cls: 'text-bg-secondary', label: role || 'Sem perfil' };
    return `<span class="badge ${item.cls}">${escapeHtml(item.label)}</span>`;
}

function renderUsuarios(usuarios) {
    const tbody = document.getElementById('tbody-usuarios');
    const tabela = document.getElementById('bloco-tabela-usuarios');
    const vazio = document.getElementById('empty-usuarios');
    const contador = document.getElementById('contador-usuarios');

    contador.textContent = usuarios.length;

    if (!usuarios.length) {
        tabela.classList.add('d-none');
        vazio.classList.remove('d-none');
        tbody.innerHTML = '';
        return;
    }

    vazio.classList.add('d-none');
    tabela.classList.remove('d-none');
    tbody.innerHTML = usuarios.map((usuario) => `
        <tr>
            <td class="ps-3">
                <strong>${escapeHtml(usuario.nome)}</strong>
            </td>
            <td>${escapeHtml(usuario.email)}</td>
            <td>${usuarioRoleBadge(usuario.role)}</td>
            <td class="text-end pe-3 text-muted small">#${escapeHtml(String(usuario.id))}</td>
        </tr>
    `).join('');
}

async function carregarUsuarios() {
    const loading = document.getElementById('loading-usuarios');
    const erro = document.getElementById('erro-usuarios');

    loading.classList.remove('d-none');
    erro.classList.add('d-none');

    try {
        const usuarios = await AchouApi.listarUsuarios({
            busca: filtrosUsuarios.busca.value.trim(),
            role: filtrosUsuarios.role.value
        });
        renderUsuarios(usuarios);
    } catch (err) {
        document.getElementById('bloco-tabela-usuarios').classList.add('d-none');
        document.getElementById('empty-usuarios').classList.add('d-none');
        erro.textContent = err.message;
        erro.classList.remove('d-none');
    } finally {
        loading.classList.add('d-none');
    }
}

filtrosUsuarios.form.addEventListener('submit', (event) => {
    event.preventDefault();
    carregarUsuarios();
});

[filtrosUsuarios.busca, filtrosUsuarios.role].forEach((campo) => {
    campo.addEventListener('input', carregarUsuarios);
    campo.addEventListener('change', carregarUsuarios);
});

document.addEventListener('DOMContentLoaded', carregarUsuarios);
