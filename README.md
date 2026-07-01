# Achou-UFC

O projeto tem como "cliente" institucional o Setor de Recepção e Vigilância da UFC Quixadá. A comunidade diretamente beneficiada são os alunos, professores e servidores que transitam diariamente pelo campus e necessitam de um meio eficiente para recuperar ou entregar objetos perdidos.

## Docker local

Suba o ambiente com:

```bash
docker compose up -d --build
```

O `docker/init.sql` cria categorias, locais e duas contas para teste:

- Admin: `admin@ufc.br` / `admin12345`
- Aluno: `aluno@alu.ufc.br` / `aluno12345`

O Postgres usa volume persistente. Se o volume já existir, o script de init não roda de novo automaticamente; para reaplicar sem apagar dados, use:

```bash
docker compose exec -T db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f /docker-entrypoint-initdb.d/init.sql'
```

calebe esteve aqui
