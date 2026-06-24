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
            $status_inicial = 'pendente';

            $id_reivindicacao = $reivindicacaoModel->create($status_inicial, $data_reivindicacao, $item_id, $aluno_id);

            $itemModel->update(
                $item_id, 
                $item['titulo'], 
                $item['descricao'], 
                $item['data_encontrado'], 
                $item['foto_url'], 
                $item['local_id'], 
                $item['categoria_id'], 
                'em_analise'
            );

            http_response_code(201);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Reivindicação enviada com sucesso e está em análise.',
                'id_reivindicacao' => $id_reivindicacao
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar reivindicação.']);
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

        $reivindicacaoModel = new Reivindicacao();

        try {
            $reivindicacoes = $reivindicacaoModel->findAllWithDetails();

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'total' => count($reivindicacoes),
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
        $itemModel = new ItemPerdido();

        try {
            $reivindicacao = $reivindicacaoModel->findById($id);

            if (!$reivindicacao) {
                http_response_code(404);
                echo json_encode(['error' => 'Reivindicação não encontrada.']);
                return;
            }

            $reivindicacaoModel->updateStatus($id, $novo_status);

            $item_id = $reivindicacao['item_id'];
            $item = $itemModel->findById($item_id);

            $status_item = ($novo_status === 'aprovado') ? 'devolvido' : 'disponível';

            $itemModel->update(
                $item_id, 
                $item['titulo'], 
                $item['descricao'], 
                $item['data_encontrado'], 
                $item['foto_url'], 
                $item['local_id'], 
                $item['categoria_id'], 
                $status_item
            );

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'mensagem' => "Reivindicação marcada como {$novo_status} e status do item atualizado para {$status_item}."
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao processar a avaliação.']);
        }
    }
}