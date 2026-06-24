<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Exception;
use Models\ItemPerdido;

class ItemController
{
    public function store(Request $request): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        // Apenas admins podem registrar novos itens achados
        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas administradores podem registrar itens.']);
            return;
        }

        $dados = $request->getBody();

        $titulo          = !empty($dados['titulo']) ? htmlspecialchars(strip_tags($dados['titulo']), ENT_QUOTES, 'UTF-8') : '';
        $descricao       = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao']), ENT_QUOTES, 'UTF-8') : '';
        $data_encontrado = !empty($dados['data_encontrado']) ? htmlspecialchars(strip_tags($dados['data_encontrado']), ENT_QUOTES, 'UTF-8') : date('Y-m-d');
        $foto_url        = !empty($dados['foto_url']) ? htmlspecialchars(strip_tags($dados['foto_url']), ENT_QUOTES, 'UTF-8') : null;
        
        $categoria_id    = isset($dados['categoria_id']) ? (int) $dados['categoria_id'] : 0;
        $local_id        = isset($dados['local_id']) ? (int) $dados['local_id'] : 0;
        
        $status          = !empty($dados['status']) ? htmlspecialchars(strip_tags($dados['status']), ENT_QUOTES, 'UTF-8') : 'disponível';
        $registrado_por  = (int) $usuarioLogado->sub;

        if (empty($titulo) || empty($categoria_id) || empty($local_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Título, categoria e local são obrigatórios.']);
            return;
        }

        $itemModel = new ItemPerdido();

        try {
            $id = $itemModel->create(
                $titulo, 
                $descricao, 
                $data_encontrado, 
                $foto_url, 
                $local_id, 
                $categoria_id, 
                $registrado_por, 
                $status
            );

            http_response_code(201);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Item registrado com sucesso.', 'id' => $id]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao salvar o item.']);
        }
    }

    public function index(Request $request): void
    {
        header('Content-Type: application/json');

        $query = $request->getQuery();
        
        $page  = isset($query['page']) ? (int) $query['page'] : 1;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 20;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;
        
        $offset = ($page - 1) * $limit;

        $filtros = [];
        
        if (!empty($query['status'])) {
            $filtros['status'] = htmlspecialchars(strip_tags($query['status']));
        }
        
        if (!empty($query['categoria_id'])) {
            $filtros['categoria_id'] = (int) $query['categoria_id'];
        }
        
        if (!empty($query['local_id'])) {
            $filtros['local_id'] = (int) $query['local_id'];
        }
        
        if (!empty($query['busca'])) {
            $filtros['busca'] = htmlspecialchars(strip_tags($query['busca']));
        }

        $itemModel = new ItemPerdido();

        try {
            $total = $itemModel->countFiltered($filtros);
            $itens = $itemModel->findAllWithDetails($limit, $offset, $filtros);

            http_response_code(200);
            echo json_encode([
                'sucesso'   => true,
                'paginacao' => [
                    'total_registros'   => $total,
                    'pagina_atual'      => $page,
                    'limite_por_pagina' => $limit,
                    'total_paginas'     => ceil($total / $limit)
                ],
                'filtros_aplicados' => $filtros, 
                'data' => $itens,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao listar itens: ' . $e->getMessage()]);
        }
    }

    public function show(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $itemModel = new ItemPerdido();

        try {
            // Busca o item pelo ID
            $item = $itemModel->findById($id);

            if (!$item) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'data' => $item
            ]);
            
        } catch (Exception $e) {
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

        $dados = $request->getBody();

        $titulo          = !empty($dados['titulo']) ? htmlspecialchars(strip_tags($dados['titulo']), ENT_QUOTES, 'UTF-8') : '';
        $descricao       = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao']), ENT_QUOTES, 'UTF-8') : '';
        $data_encontrado = !empty($dados['data_encontrado']) ? htmlspecialchars(strip_tags($dados['data_encontrado']), ENT_QUOTES, 'UTF-8') : '';
        $foto_url        = !empty($dados['foto_url']) ? htmlspecialchars(strip_tags($dados['foto_url']), ENT_QUOTES, 'UTF-8') : null;
        $categoria_id    = isset($dados['categoria_id']) ? (int) $dados['categoria_id'] : 0;
        $local_id        = isset($dados['local_id']) ? (int) $dados['local_id'] : 0;
        $status          = !empty($dados['status']) ? htmlspecialchars(strip_tags($dados['status']), ENT_QUOTES, 'UTF-8') : '';

        if (empty($titulo) || empty($categoria_id) || empty($local_id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Título, categoria e local são obrigatórios para a atualização.']);
            return;
        }

        $itemModel = new ItemPerdido();

        try {
            if (!$itemModel->findById($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
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
             if (!$itemModel->findById($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
                return;
            }

            $itemModel->updateStatus($id, 'arquivado');

            http_response_code(200);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Item removido do fluxo com sucesso.']);
        } catch (Exception $e) {
             http_response_code(500);
             echo json_encode(['error' => 'Erro ao remover item.']);
        }
    }

    
    
}
