<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Exception;
use Models\ItemPerdido;
use Models\Categoria;
use Models\Local;

class ItemController
{
    private const STATUS_VALIDOS = ['disponivel', 'devolvido', 'arquivado', 'em_analise'];
    private const STATUS_CRIACAO = ['disponivel', 'em_analise'];
    private const FOTO_DOMINIOS_PERMITIDOS = [
        'images.unsplash.com',
        'plus.unsplash.com',
        'raw.githubusercontent.com',
        'githubusercontent.com',
        'i.imgur.com',
        'imgur.com',
        'placehold.co',
        'picsum.photos',
        'localhost',
        '127.0.0.1',
    ];

    public function store(Request $request): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas administradores podem registrar itens.']);
            return;
        }

        $dados = $request->getBody();
        $camposString = ['titulo', 'descricao', 'status', 'data_encontrado', 'foto_url'];
        foreach ($camposString as $campo) {
            if (isset($dados[$campo]) && !is_string($dados[$campo])) {
                http_response_code(400);
                echo json_encode(['error' => "O campo '{$campo}' deve ser uma string."]);
                return;
            }
        }

        $camposInt = ['categoria_id', 'local_id'];
        foreach ($camposInt as $campo) {
            if (isset($dados[$campo]) && !is_numeric($dados[$campo])) {
                http_response_code(400);
                echo json_encode(['error' => "O campo '{$campo}' deve ser numérico."]);
                return;
            }
        }

        if (empty($dados['titulo']) || empty($dados['categoria_id']) || empty($dados['local_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Título, categoria_id e local_id são obrigatórios.']);
            return;
        }

        $titulo = $this->sanitizarTexto($dados['titulo'], 255);
        $descricao = $this->sanitizarTexto($dados['descricao'] ?? '', 5000);

        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 255) {
            http_response_code(400);
            echo json_encode(['error' => 'O título deve ter entre 3 e 255 caracteres.']);
            return;
        }

        if (mb_strlen($descricao) > 5000) {
            http_response_code(400);
            echo json_encode(['error' => 'A descrição não pode ter mais de 5000 caracteres.']);
            return;
        }

        $statusRaw = !empty($dados['status'])
            ? $this->normalizarStatus($dados['status'])
            : 'disponivel';

        if (!in_array($statusRaw, self::STATUS_CRIACAO, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Status inválido. Use: disponivel ou em_analise.']);
            return;
        }

        $categoria_id   = (int) $dados['categoria_id'];
        $local_id       = (int) $dados['local_id'];
        $registrado_por = (int) $usuarioLogado->sub;

        if ($categoria_id <= 0 || $local_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'categoria_id e local_id devem ser números inteiros positivos.']);
            return;
        }
        $categoriaModel = new \Models\Categoria();
        if (!$categoriaModel->findById($categoria_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'categoria_id informado não existe.']);
            return;
        }

        $localModel = new \Models\Local();
        if (!$localModel->findById($local_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'local_id informado não existe.']);
            return;
        }

        $data_encontrado = date('Y-m-d');
        if (isset($dados['data_encontrado']) && trim($dados['data_encontrado']) !== '') {
            $data_raw = trim($dados['data_encontrado']);
            $d        = \DateTime::createFromFormat('!Y-m-d', $data_raw);
            if (!$d || $d->format('Y-m-d') !== $data_raw) {
                http_response_code(400);
                echo json_encode(['error' => 'Formato de data inválido. Use AAAA-MM-DD.']);
                return;
            }
            if ($d > new \DateTime('today')) {
                http_response_code(400);
                echo json_encode(['error' => 'A data em que o item foi encontrado não pode ser no futuro.']);
                return;
            }
            $data_encontrado = $data_raw;
        }

        $foto_url = $this->validarFotoUrl($dados['foto_url'] ?? null);
        if ($foto_url === false) {
            http_response_code(400);
            echo json_encode(['error' => 'A URL da foto é inválida ou o domínio não é permitido.']);
            return;
        }

        $itemModel = new ItemPerdido();

        try {
            if ($itemModel->findActiveDuplicate(
                $titulo,
                $descricao,
                $data_encontrado,
                $local_id,
                $categoria_id,
                $registrado_por
            )) {
                http_response_code(409);
                echo json_encode(['error' => 'Este item já foi cadastrado com os mesmos dados. Revise a lista antes de reenviar.']);
                return;
            }

            $id = $itemModel->create(
                $titulo,
                $descricao,
                $data_encontrado,
                $foto_url,
                $local_id,
                $categoria_id,
                $registrado_por,
                $statusRaw
            );

            http_response_code(201);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Item registrado com sucesso.', 'id' => $id]);
        } catch (Exception $e) {
            error_log('Erro ao criar item: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao salvar o item.']);
        }
    }

    public function index(Request $request): void
    {
        header('Content-Type: application/json');

        AuthMiddleware::handle();

        $query = $request->getQuery();

        $page  = isset($query['page'])  ? (int) $query['page']  : 1;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 20;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;

        $offset = ($page - 1) * $limit;

        $filtros = [];

        if (!empty($query['status'])) {
            $statusFiltro = $this->normalizarStatus($query['status']);
            if (in_array($statusFiltro, self::STATUS_VALIDOS, true)) {
                $filtros['status'] = $statusFiltro;
            }
        }

        if (!empty($query['categoria_id'])) {
            $filtros['categoria_id'] = (int) $query['categoria_id'];
        }

        if (!empty($query['local_id'])) {
            $filtros['local_id'] = (int) $query['local_id'];
        }

        if (!empty($query['busca'])) {
            $filtros['busca'] = $this->sanitizarTexto($query['busca'], 120);
        }

        $itemModel = new ItemPerdido();

        try {
            $total = $itemModel->countFiltered($filtros);
            $itens = $itemModel->findAllWithDetails($limit, $offset, $filtros);

            http_response_code(200);
            echo json_encode([
                'sucesso'           => true,
                'paginacao'         => [
                    'total_registros'   => $total,
                    'pagina_atual'      => $page,
                    'limite_por_pagina' => $limit,
                    'total_paginas'     => (int) ceil($total / $limit),
                ],
                'filtros_aplicados' => $filtros,
                'data'              => $itens,
            ]);
        } catch (Exception $e) {
            error_log('Erro ao listar itens: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao listar itens.']);
        }
    }

    public function show(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        AuthMiddleware::handle();

        $itemModel = new ItemPerdido();

        try {
            $item = $itemModel->findById($id);

            
            if (!$item || trim($item['status']) === 'arquivado') {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
                return;
            }

            http_response_code(200);
            echo json_encode(['sucesso' => true, 'data' => $item]);
        } catch (Exception $e) {
            error_log('Erro ao buscar item: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao buscar detalhes do item.']);
        }
    }

    public function update(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas administradores podem editar itens.']);
            return;
        }

        $dados     = $request->getBody();
        $camposString = ['titulo', 'descricao', 'status', 'data_encontrado', 'foto_url'];
        foreach ($camposString as $campo) {
            if (isset($dados[$campo]) && !is_string($dados[$campo])) {
                http_response_code(400);
                echo json_encode(['error' => "O campo '{$campo}' deve ser uma string."]);
                return;
            }
        }

        $camposInt = ['categoria_id', 'local_id'];
        foreach ($camposInt as $campo) {
            if (isset($dados[$campo]) && !is_numeric($dados[$campo])) {
                http_response_code(400);
                echo json_encode(['error' => "O campo '{$campo}' deve ser numérico."]);
                return;
            }
        }
        
        $itemModel = new ItemPerdido();

        $itemAtual = $itemModel->findById($id);
        if (!$itemAtual || trim($itemAtual['status']) === 'arquivado') {
            http_response_code(404);
            echo json_encode(['error' => 'Item não encontrado.']);
            return;
        }

        if (empty($dados['titulo']) || empty($dados['categoria_id']) || empty($dados['local_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Título, categoria_id e local_id são obrigatórios.']);
            return;
        }

        $titulo = $this->sanitizarTexto($dados['titulo'], 255);
        $descricao = $this->sanitizarTexto($dados['descricao'] ?? '', 5000);

        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 255) {
            http_response_code(400);
            echo json_encode(['error' => 'O título deve ter entre 3 e 255 caracteres.']);
            return;
        }

        if (mb_strlen($descricao) > 5000) {
            http_response_code(400);
            echo json_encode(['error' => 'A descrição não pode ter mais de 5000 caracteres.']);
            return;
        }

        $categoria_id = (int) $dados['categoria_id'];
        $local_id     = (int) $dados['local_id'];

        if ($categoria_id <= 0 || $local_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'categoria_id e local_id devem ser números inteiros positivos.']);
            return;
        }

        $categoriaModel = new Categoria();
        if (!$categoriaModel->findById($categoria_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'categoria_id informado não existe.']);
            return;
        }

        $localModel = new Local();
        if (!$localModel->findById($local_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'local_id informado não existe.']);
            return;
        }

        if (!empty($dados['status'])) {
            $statusRaw = $this->normalizarStatus($dados['status']);
            if (!in_array($statusRaw, self::STATUS_VALIDOS, true)) {
                http_response_code(400);
                echo json_encode(['error' => 'Status do item inválido. Use: ' . implode(', ', self::STATUS_VALIDOS)]);
                return;
            }
            $status = $statusRaw;
        } else {
            $status = $itemAtual['status'];
        }

        if (!empty($dados['data_encontrado'])) {
            $data_raw = trim($dados['data_encontrado']);
            $d        = \DateTime::createFromFormat('!Y-m-d', $data_raw);
            if (!$d || $d->format('Y-m-d') !== $data_raw) {
                http_response_code(400);
                echo json_encode(['error' => 'Formato de data inválido. Use AAAA-MM-DD.']);
                return;
            }
            if ($d > new \DateTime('today')) {
                http_response_code(400);
                echo json_encode(['error' => 'A data em que o item foi encontrado não pode ser no futuro.']);
                return;
            }
            $data_encontrado = $data_raw;
        } else {
            $data_encontrado = $itemAtual['data_encontrado'];
        }

        if (array_key_exists('foto_url', $dados)) {
            if (empty($dados['foto_url'])) {
                $foto_url = null; 
            } else {
                $foto_url = $this->validarFotoUrl($dados['foto_url']);
                if ($foto_url === false) {
                    http_response_code(400);
                    echo json_encode(['error' => 'A URL da foto é inválida ou o domínio não é permitido.']);
                    return;
                }
            }
        } else {
            $foto_url = $itemAtual['foto_url'];
        }

        try {
            if ($itemModel->findActiveDuplicate(
                $titulo,
                $descricao,
                $data_encontrado,
                $local_id,
                $categoria_id,
                (int) $itemAtual['registrado_por'],
                $id
            )) {
                http_response_code(409);
                echo json_encode(['error' => 'Já existe outro item ativo com os mesmos dados.']);
                return;
            }

            $itemModel->update(
                $id,
                $titulo,
                $descricao,
                $data_encontrado,
                $foto_url,
                $local_id,
                $categoria_id,
                $status
            );

            http_response_code(200);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Item atualizado com sucesso.']);
        } catch (Exception $e) {
            error_log('Erro ao atualizar item: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao atualizar item.']);
        }
    }

    public function destroy(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado.']);
            return;
        }

        $itemModel = new ItemPerdido();

        try {
            $itemAtual = $itemModel->findById($id);
            if (!$itemAtual) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
                return;
            }
            
            if (trim($itemAtual['status']) === 'arquivado') {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
                return;
            }
            
            $itemModel->updateStatus($id, 'arquivado');
            http_response_code(200);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Item arquivado com sucesso.']);
        } catch (Exception $e) {
            error_log('Erro ao arquivar item: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao arquivar item.']);
        }
    }

    /**
     * Valida e sanitiza uma URL de foto.
     *
     * @return string|null|false  string = URL válida, null = sem foto, false = URL inválida
     */
    private function sanitizarTexto(mixed $valor, int $limite): string
    {
        if (!is_string($valor)) {
            return '';
        }

        $texto = preg_replace('/\s+/u', ' ', trim(strip_tags($valor))) ?? '';
        $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

        if (mb_strlen($texto) > $limite) {
            return '';
        }

        return $texto;
    }

    private function normalizarStatus(mixed $status): string
    {
        if (!is_string($status)) {
            return '';
        }

        $status = strtolower(trim(strip_tags($status)));
        $status = str_replace('í', 'i', $status);

        return match ($status) {
            'disponível', 'disponivel' => 'disponivel',
            'reivindicado' => 'em_analise',
            'entregue' => 'devolvido',
            default => $status,
        };
    }

    private function validarFotoUrl(?string $rawUrl): string|null|false
    {
        if (empty($rawUrl)) {
            return null;
        }

        $url_limpa = filter_var(trim($rawUrl), FILTER_SANITIZE_URL);

        if (!filter_var($url_limpa, FILTER_VALIDATE_URL)) {
            return false;
        }

        $esquema = strtolower(parse_url($url_limpa, PHP_URL_SCHEME) ?? '');
        if (!in_array($esquema, ['http', 'https'], true)) {
            return false;
        }

        if (strlen($url_limpa) > 2048) {
            return false;
        }

        $host = strtolower(parse_url($url_limpa, PHP_URL_HOST) ?? '');
        $permitido = false;
        foreach (self::FOTO_DOMINIOS_PERMITIDOS as $dominio) {
            if ($host === $dominio || str_ends_with($host, '.' . $dominio)) {
                $permitido = true;
                break;
            }
        }

        if (!$permitido) {
            return false;
        }

        return $url_limpa;
    }
}
