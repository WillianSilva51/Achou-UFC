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
        $item_id = isset($dados['item_id']) ? (int)$dados['item_id'] : 0;
        $aluno_id = (int)$usuarioLogado->sub;

        if ($item_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'ID do item é obrigatório.']);
            return;
        }

        $pdo = \Core\Database::getConnection();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT status, registrado_por FROM item_perdido WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $item_id]);
            $item = $stmt->fetch();

            if (!$item) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado.']);
                return;
            }

            if (strtolower($item['status']) !== 'disponível' && strtolower($item['status']) !== 'disponivel') {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Este item não está mais disponível para reivindicação.']);
                return;
            }

            if (isset($item['registrado_por']) && $item['registrado_por'] == $aluno_id) {
                $pdo->rollBack();
                http_response_code(409); 
                echo json_encode(['error' => 'Você não pode reivindicar um item que você mesmo registrou no sistema.']);
                return;
            }

            $reivindicacaoModel = new \Models\Reivindicacao();
            $data_reivindicacao = date('Y-m-d H:i:s');
            
            $id_reivindicacao = $reivindicacaoModel->registrarPedido($item_id, $aluno_id, $data_reivindicacao);

            $pdo->commit();

            http_response_code(201);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Reivindicação enviada com sucesso e está em análise.',
                'id_reivindicacao' => $id_reivindicacao
            ]);

        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            if ($e->getCode() == 23505 || strpos($e->getMessage(), 'uq_reivindicacao_ativa') !== false) {
                http_response_code(409);
                echo json_encode(['error' => 'Você já enviou uma reivindicação para este item.']);
                return;
            }
            
            error_log("Erro no banco (Reivindicação): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro no banco de dados ao processar reivindicação.']);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro interno (Reivindicação): " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a reivindicação.']);
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
        
        // 🛡️ LEITURA ÚNICA: Aceita tanto 'status' quanto 'status_reivindicacao' do front-end
        $statusBruto = $dados['status'] ?? $dados['status_reivindicacao'] ?? '';
        $novo_status = strtolower(htmlspecialchars(strip_tags($statusBruto), ENT_QUOTES, 'UTF-8'));

        if (!in_array($novo_status, ['aprovado', 'recusado', 'pendente'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Status inválido. Use aprovado, recusado ou pendente.']);
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
            
            // Chamando a model com os 4 parâmetros exatos que você definiu no seu código
            $reivindicacaoModel->processarAvaliacao($id, $novo_status, $item_id, $status_item);

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => "Reivindicação marcada como {$novo_status} e status do item atualizado para {$status_item}."
            ]);

        } catch (Exception $e) {
            error_log("Erro no updateStatus: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a avaliação.']);
        }
    }
}