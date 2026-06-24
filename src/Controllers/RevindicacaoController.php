<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Models\Reivindicacao;
use Models\ItemPerdido;
use Exception;

class ReivindicacaoController
{
    public function store(Request $request): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'aluno') {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas alunos podem reivindicar itens.']);
            return;
        }

        $dados = $request->getBody();

        if (empty($dados['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'O ID do item é obrigatório.']);
            return;
        }

        $item_id = (int) $dados['item_id'];
        $aluno_id = (int) $usuarioLogado->sub;
        
        $itemModel = new ItemPerdido();
        $reivindicacaoModel = new Reivindicacao();

        try {
            $item = $itemModel->findById($item_id);

            if (!$item) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
                return;
            }

            if (strtolower($item['status']) !== 'disponível' && strtolower($item['status']) !== 'disponivel') {
                http_response_code(400);
                echo json_encode(['error' => 'Este item não está mais disponível para reivindicação.']);
                return;
            }

            $data_reivindicacao = date('Y-m-d H:i:s');
            
            $id_reivindicacao = $reivindicacaoModel->registrarPedido($item_id, $aluno_id, $data_reivindicacao);

            http_response_code(201);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Reivindicação enviada com sucesso e está em análise.',
                'id_reivindicacao' => $id_reivindicacao
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_reivindicacao_ativa') !== false) {
                http_response_code(409);
                echo json_encode([
                    'error' => 'Calma lá! Você já enviou uma reivindicação para este item e ela está em análise.'
                ]);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro no banco de dados ao processar reivindicação.']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    public function index(Request $request): void
    {
        header('Content-Type: application/json');
        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado.']);
            return;
        }

        $query = $request->getQuery();
        
        $page  = isset($query['page']) ? (int) $query['page'] : 1;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 20;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;
        
        $offset = ($page - 1) * $limit;

        $filtros = [];
        if (!empty($query['status_reivindicacao'])) {
            $filtros['status_reivindicacao'] = htmlspecialchars(strip_tags($query['status_reivindicacao']));
        }
        if (!empty($query['aluno_id'])) {
            $filtros['aluno_id'] = (int) $query['aluno_id'];
        }

        $reivindicacaoModel = new Reivindicacao();

        try {
            $total = $reivindicacaoModel->countFiltered($filtros);
            $reivindicacoes = $reivindicacaoModel->findAllWithDetails($limit, $offset, $filtros);

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
                'data' => $reivindicacoes
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao buscar reivindicações.']);
        }
    }

    public function updateStatus(Request $request, int $id): void
    {
        header('Content-Type: application/json');

        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Apenas administradores podem avaliar reivindicações.']);
            return;
        }

        $dados = $request->getBody();

        if (empty($dados['status_reivindicacao'])) {
            http_response_code(400);
            echo json_encode(['error' => 'O novo status é obrigatório (aprovado ou recusado).']);
            return;
        }

        $novo_status = strtolower(htmlspecialchars(strip_tags($dados['status_reivindicacao'])));

        if (!in_array($novo_status, ['aprovado', 'recusado'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status inválido. Use "aprovado" ou "recusado".']);
            return;
        }

        $reivindicacaoModel = new Reivindicacao();

        try {
            $reivindicacao = $reivindicacaoModel->findById($id);

            if (!$reivindicacao) {
                http_response_code(404);
                echo json_encode(['error' => 'Reivindicação não encontrada.']);
                return;
            }

            $item_id = $reivindicacao['item_id'];
            $status_item = ($novo_status === 'aprovado') ? 'devolvido' : 'disponível';
            
            $reivindicacaoModel->processarAvaliacao($id, $novo_status, $item_id, $status_item);

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => "Reivindicação marcada como {$novo_status} e status do item atualizado para {$status_item}."
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a avaliação: ' . $e->getMessage()]);
        }
    }
}