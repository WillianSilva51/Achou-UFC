# 🔍 Achou! UFC - Sistema de Achados e Perdidos

O **Achou! UFC** é uma plataforma digital desenvolvida como Projeto de Extensão para o Setor de Recepção e Vigilância da Universidade Federal do Ceará (UFC) - Campus Quixadá. O objetivo principal deste projeto é oferecer à comunidade acadêmica (alunos, docentes e funcionários) um meio eficiente, rápido e centralizado para gerenciar, recuperar e registrar objetos perdidos no campus.

## 🎯 Principais Funcionalidades

### Para a Comunidade (Alunos/Usuários)

- **Vitrine Digital**: Visualização de todos os itens encontrados no campus
- **Filtros Inteligentes**: Pesquisa de itens por texto, categoria, bloco (local) e data
- **Reivindicação de Itens**: Formulário para solicitar a devolução de um objeto, onde o aluno deve comprovar a posse
- **Autenticação**: Registro e login de alunos utilizando a matrícula institucional

### Para a Administração (Recepção/Vigilância)

- **Painel Administrativo (Dashboard)**: Visão geral com estatísticas de itens totais, disponíveis, reivindicados e devolvidos
- **Gestão de Itens**: Registro de novos itens encontrados (com fotografia, local e categoria) e arquivo de itens devolvidos
- **Análise de Reivindicações**: Validação das solicitações dos alunos, com aprovação ou recusa da devolução

## 🛠️ Tecnologias Utilizadas

O projeto foi construído seguindo uma arquitetura MVC com princípios de desenvolvimento sem frameworks pesados, garantindo alta performance e controle total sobre o código.

### Frontend

- HTML5, CSS3 e JavaScript (Vanilla JS)
- Bootstrap 5.3 (Layout e Componentes)
- Bootstrap Icons

### Backend

- **PHP 8.2** (Arquitetura MVC pura, sem frameworks)
- Autoloading padrão PSR-4 via Composer
- Autenticação via JWT (firebase/php-jwt)
- Sistema de Rate Limiting persistido no banco de dados
- Integração com Google reCAPTCHA

### Banco de Dados & Infraestrutura

- **PostgreSQL 16** (via extensão PDO do PHP)
- **Docker & Docker Compose** (orquestração de contêineres e facilidade de deploy)

## 🚀 Como Executar o Projeto Localmente

Devido à utilização do Docker, a configuração do ambiente de desenvolvimento é simples e não requer a instalação manual do PHP ou do PostgreSQL na sua máquina.

### Pré-requisitos

- Docker instalado
- Docker Compose instalado
- Git

### Passos para a Instalação

#### 1. Clonar o repositório

```bash
git clone https://github.com/seu-usuario/achou-ufc.git
cd achou-ufc
```

#### 2. Configurar as variáveis de ambiente

Crie uma cópia do arquivo `.env.example` e renomeie-a para `.env`:

```bash
cp .env.example .env
```

> **Nota**: O arquivo `.env` já possui valores padrão configurados para funcionar com o Docker localmente.

#### 3. Subir os contêineres com o Docker Compose

```bash
docker-compose up -d --build
```

Este comando irá baixar as imagens do PHP e PostgreSQL, instalar as dependências do Composer e inicializar o banco de dados (criando as tabelas e inserindo os dados iniciais).

#### 4. Acessar a aplicação

Abra o seu navegador e acesse: **[http://localhost:8000](http://localhost:8000)**

## 📂 Estrutura do Projeto

```text
.
├── .env.example          # Modelo de variáveis de ambiente
├── docker-compose.yml    # Orquestração dos contêineres Docker
├── Dockerfile            # Imagem do servidor PHP/Apache
├── composer.json         # Dependências do backend
├── docker/
│   └── init.sql          # Script SQL de criação das tabelas (executado automaticamente)
├── public/
│   ├── index.php         # Ponto de entrada (Front Controller) da API
│   └── .htaccess         # Redirecionamentos do Apache
├── src/
│   ├── Controllers/      # Lógica de negócio e respostas HTTP da API
│   ├── Core/             # Classes base (Database, Router, Request, RateLimiter)
│   ├── Middlewares/      # Filtros de requisição (ex: autenticação JWT)
│   └── Models/           # Acesso ao banco de dados (PostgreSQL)
├── assets/               # Imagens estáticas e logotipos
├── css/                  # Estilos organizados por componentes e seções
├── js/                   # Scripts frontend (consumo da API, interatividade)
└── *.html                # Arquivos de interface do usuário
```

## 👥 Autores

Este projeto foi concebido e desenvolvido pelos alunos de Ciência da Computação:

- Willian Silva
- Calebe Mesquita
- José Nilson
