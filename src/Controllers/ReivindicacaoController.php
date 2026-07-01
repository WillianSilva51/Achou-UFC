<?php

namespace Controllers;

use Core\Request;
use Middlewares\AuthMiddleware;
use Models\Reivindicacao;
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

        $dados    = $request->getBody();
        $item_id  = isset($dados['item_id']) ? (int) $dados['item_id'] : 0;
        $aluno_id = (int) $usuarioLogado->sub;

        if ($item_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do item é obrigatório e deve ser um número válido.']);
            return;
        }

        try {
            $reivindicacaoModel = new Reivindicacao();
            $id_reivindicacao = $reivindicacaoModel->registrarPedido($item_id, $aluno_id);

            http_response_code(201);
            echo json_encode([
                'sucesso'          => true,
                'mensagem'         => 'Reivindicação enviada com sucesso e está em análise.',
                'id_reivindicacao' => $id_reivindicacao,
            ]);

        } catch (\PDOException $e) {
            if ($e->getCode() == 23505 || str_contains($e->getMessage(), 'uq_reivindicacao_ativa')) {
                http_response_code(409);
                echo json_encode(['error' => 'Você já enviou uma reivindicação pendente para este item.']);
                return;
            }
            error_log('Erro de BD (Reivindicação store): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro no banco de dados ao processar reivindicação.']);

        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);

        } catch (\Exception $e) {
            error_log('Erro interno (Reivindicação store): ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a reivindicação.']);
        }
    }

    public function index(Request $request): void
    {
        header('Content-Type: application/json');
        $usuarioLogado = AuthMiddleware::handle();

        $query = $request->getQuery();

        $page  = isset($query['page'])  ? (int) $query['page']  : 1;
        $limit = isset($query['limit']) ? (int) $query['limit'] : 20;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 20;

        $offset = ($page - 1) * $limit;

        $filtros = [];

        if (!empty($query['status_reivindicacao'])) {
            $statusFiltro = strtolower(htmlspecialchars(strip_tags($query['status_reivindicacao']), ENT_QUOTES, 'UTF-8'));
            if (in_array($statusFiltro, ['pendente', 'aprovado', 'recusado'], true)) {
                $filtros['status_reivindicacao'] = $statusFiltro;
            }
        }

        if (!empty($query['aluno_id'])) {
            $filtros['aluno_id'] = (int) $query['aluno_id'];
        }

        if ($usuarioLogado->role === 'aluno') {
            $filtros['aluno_id'] = (int) $usuarioLogado->sub;
        } elseif ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado.']);
            return;
        }

        $reivindicacaoModel = new Reivindicacao();

        try {
            $total          = $reivindicacaoModel->countFiltered($filtros);
            $reivindicacoes = $reivindicacaoModel->findAllWithDetails($limit, $offset, $filtros);

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
                'data'              => $reivindicacoes,
            ]);
        } catch (Exception $e) {
            error_log('Erro ao listar reivindicações: ' . $e->getMessage());
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

        $statusBruto = $dados['status'] ?? $dados['status_reivindicacao'] ?? '';
        $novo_status = strtolower(htmlspecialchars(strip_tags((string) $statusBruto), ENT_QUOTES, 'UTF-8'));

        
        if (!in_array($novo_status, ['aprovado', 'recusado'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Status inválido. Use: aprovado ou recusado.']);
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

            if ($reivindicacao['status_reivindicacao'] !== 'pendente') {
                http_response_code(409);
                echo json_encode([
                    'error' => sprintf(
                        'Esta reivindicação não pode ser processada pois já está com status "%s".',
                        $reivindicacao['status_reivindicacao']
                    ),
                ]);
                return;
            }

            $item_id    = $reivindicacao['item_id'];
            $status_item = ($novo_status === 'aprovado') ? 'devolvido' : 'disponivel';

            $reivindicacaoModel->processarAvaliacao($id, $novo_status, $item_id, $status_item);

            http_response_code(200);
            echo json_encode([
                'sucesso'  => true,
                'mensagem' => "Reivindicação marcada como {$novo_status} e item atualizado para {$status_item}.",
            ]);

        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            error_log('Erro no updateStatus de reivindicação: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a avaliação.']);
        }
    }
}
