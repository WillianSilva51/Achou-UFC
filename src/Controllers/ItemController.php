<?php

namespace Controllers;

use Core\Request;
use Core\SupabaseStorage;
use Middlewares\AuthMiddleware;
use Exception;
use Models\ItemPerdido;
use Models\Categoria;
use Models\Local;

class ItemController
{
    private const STATUS_VALIDOS = ['disponivel', 'devolvido', 'arquivado', 'em_analise'];
    private const STATUS_CRIACAO = ['disponivel', 'em_analise'];
    private const FOTO_MAX_BYTES = 5242880;
    private const FOTO_MAX_DIMENSION = 8000;
    private const FOTO_MIMES_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    private const FOTO_DOMINIOS_PERMITIDOS = [
        'images.unsplash.com',
        'plus.unsplash.com',
        'raw.githubusercontent.com',
        'githubusercontent.com',
        'i.imgur.com',
        'imgur.com',
        'placehold.co',
        'picsum.photos',
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

        if (array_key_exists('foto_base64', $dados) && $dados['foto_base64'] !== '' && !is_array($dados['foto_base64']))  {
            http_response_code(400);
            echo json_encode(['error' => "O campo 'foto_base64' deve ser um objeto."]);
            return;
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

        if (!$this->validarTamanhoTexto($dados['titulo'], 255, 'título')
            || !$this->validarTamanhoTexto($dados['descricao'] ?? '', 5000, 'descrição')) {
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

       
        $statusRaw = isset($dados['status']) ? trim((string)$dados['status']) : 'disponivel';

        if (!in_array($statusRaw, self::STATUS_CRIACAO, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Status inválido ou em maiúsculas. Use estritamente: disponivel ou em_analise.']);
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
        if (array_key_exists('data_encontrado', $dados)) {
            $data_raw = trim((string) $dados['data_encontrado']);
            if ($data_raw === '') { http_response_code(400); echo json_encode(['error' => 'Data não pode ser vazia.']); return; }
            $d = \DateTime::createFromFormat('!Y-m-d', $data_raw);
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

        $itemModel = new ItemPerdido();

        try {
            $foto_url = $this->resolverFotoUrl($dados);

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
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            error_log('Erro de storage ao criar item: ' . $e->getMessage());
            http_response_code(502);
            echo json_encode(['error' => $e->getMessage()]);
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

        if (array_key_exists('foto_base64', $dados) && $dados['foto_base64'] !== '' && !is_array($dados['foto_base64'])) {
            http_response_code(400);
            echo json_encode(['error' => "O campo 'foto_base64' deve ser um objeto."]);
            return;
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

        if (!$this->validarTamanhoTexto($dados['titulo'], 255, 'título')
            || !$this->validarTamanhoTexto($dados['descricao'] ?? '', 5000, 'descrição')) {
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

        try {
            if (array_key_exists('foto_base64', $dados) || array_key_exists('foto_url', $dados)) {
                if (array_key_exists('foto_url', $dados) && empty($dados['foto_url']) && !array_key_exists('foto_base64', $dados)) {
                    $foto_url = null;
                } else {
                    $foto_url = $this->resolverFotoUrl($dados);
                }
            } else {
                $foto_url = $itemAtual['foto_url'];
            }

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
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            error_log('Erro de storage ao atualizar item: ' . $e->getMessage());
            http_response_code(502);
            echo json_encode(['error' => $e->getMessage()]);
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

        if (preg_match('/^[oOaCT]:[0-9]+:/', $valor)) return '';

        $texto = preg_replace('/\s+/u', ' ', trim(strip_tags($valor))) ?? '';
        $texto = htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');

        if (mb_strlen($texto) > $limite) {
            return '';
        }

        return $texto;
    }

    private function validarTamanhoTexto(mixed $valor, int $limite, string $nomeCampo): bool
    {
        if (!is_string($valor)) {
            http_response_code(400);
            echo json_encode(['error' => "O campo '{$nomeCampo}' deve ser uma string."]);
            return false;
        }

        if (mb_strlen($valor) > $limite) {
            http_response_code(400);
            echo json_encode(['error' => "O campo '{$nomeCampo}' não pode ter mais de {$limite} caracteres."]);
            return false;
        }

        return true;
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

        $ip = gethostbyname($host);
        if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        return $url_limpa;
    }

    private function resolverFotoUrl(array $dados): ?string
    {
        $temArquivo = array_key_exists('foto_base64', $dados) && is_array($dados['foto_base64']);
        $temUrl = isset($dados['foto_url']) && trim((string) $dados['foto_url']) !== '';

        if ($temArquivo && $temUrl) {
            throw new \InvalidArgumentException('Envie uma foto por arquivo ou por URL, não os dois.');
        }

        if ($temArquivo) {
            return $this->salvarFotoBase64($dados['foto_base64']);
        }

        $fotoUrl = $this->validarFotoUrl($dados['foto_url'] ?? null);
        if ($fotoUrl === false) {
            throw new \InvalidArgumentException('A URL da foto é inválida ou o domínio não é permitido.');
        }

        return $fotoUrl;
    }

    private function salvarFotoBase64(array $foto): string
    {
        $conteudo = $foto['conteudo'] ?? '';
        if (!is_string($conteudo) || trim($conteudo) === '') {
            throw new \InvalidArgumentException('Arquivo de foto inválido.');
        }

        if (preg_match('/^data:(?<mime>[-\w.]+\/[-\w.+]+);base64,(?<data>.+)$/s', $conteudo, $matches)) {
            $conteudo = $matches['data'];
        }

        $conteudo = preg_replace('/\s+/', '', $conteudo) ?? '';
        if ($conteudo === '' || strlen($conteudo) > (int) ceil(self::FOTO_MAX_BYTES * 1.37)) {
            throw new \InvalidArgumentException('A foto deve ter no máximo 5MB.');
        }

        $binario = base64_decode($conteudo, true);
        if ($binario === false) {
            throw new \InvalidArgumentException('Arquivo de foto inválido.');
        }

        if (strlen($binario) > self::FOTO_MAX_BYTES) {
            throw new \InvalidArgumentException('A foto deve ter no máximo 5MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeReal = $finfo->buffer($binario) ?: '';
        if (!array_key_exists($mimeReal, self::FOTO_MIMES_PERMITIDOS)) {
            throw new \InvalidArgumentException('Formato de foto inválido. Use PNG, JPEG ou WebP.');
        }

        $dimensoes = @getimagesizefromstring($binario);
        if ($dimensoes === false || empty($dimensoes['mime']) || $dimensoes['mime'] !== $mimeReal) {
            throw new \InvalidArgumentException('Arquivo de foto inválido.');
        }

        if ($dimensoes[0] < 1 || $dimensoes[1] < 1 || $dimensoes[0] > self::FOTO_MAX_DIMENSION || $dimensoes[1] > self::FOTO_MAX_DIMENSION) {
            throw new \InvalidArgumentException('Dimensões da foto inválidas.');
        }

        $extensao = self::FOTO_MIMES_PERMITIDOS[$mimeReal];
        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;

        return SupabaseStorage::upload($binario, $nomeArquivo, $mimeReal);
    }
}
