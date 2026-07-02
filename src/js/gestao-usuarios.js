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
    const badge = domEl('span', `badge ${item.cls}`);
    badge.textContent = item.label;
    return badge;
}

function renderUsuarioRow(usuario) {
    const row = document.createElement('tr');
    const nomeCell = domEl('td', 'ps-3');
    const nome = document.createElement('strong');
    const emailCell = document.createElement('td');
    const roleCell = document.createElement('td');
    const idCell = domEl('td', 'text-end pe-3 text-muted small');

    nome.textContent = usuario.nome;
    nomeCell.appendChild(nome);
    emailCell.textContent = usuario.email;
    roleCell.appendChild(usuarioRoleBadge(usuario.role));
    idCell.textContent = `#${usuario.id}`;
    row.append(nomeCell, emailCell, roleCell, idCell);

    return row;
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
        tbody.replaceChildren();
        return;
    }

    vazio.classList.add('d-none');
    tabela.classList.remove('d-none');
    tbody.replaceChildren(...usuarios.map(renderUsuarioRow));
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
