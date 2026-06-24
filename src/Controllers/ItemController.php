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

        $dados = $request->getBody();

        if (empty($dados['titulo']) || empty($dados['categoria_id']) || empty($dados['local_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'nome, id, é local são obrigatorio']);
            return;
        }
        $titulo         = !empty($dados['titulo']) ? htmlspecialchars(strip_tags($dados['titulo'])) : '';
        $descricao      = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao'])) : '';
        $data_encontrado = !empty($dados['data_encontrado']) ? htmlspecialchars(strip_tags($dados['data_encontrado'])) : date('Y-m-d');
        $foto_url       = !empty($dados['foto_url']) ? htmlspecialchars(strip_tags($dados['foto_url'])) : null;
        $categoria_id   = (int) $dados['categoria_id'];
        $local_id       = (int) $dados['local_id'];
        $status         = !empty($dados['status']) ? htmlspecialchars(strip_tags($dados['status'])) : 'disponivel';

        $registrado_por = (int) $usuarioLogado->sub;

        $ItemPerdidoModel = new ItemPerdido();
        try {
            $id_item = $ItemPerdidoModel->create(
                $titulo,
                $descricao,
                $data_encontrado,
                $foto_url,
                $local_id,
                $categoria_id,
                $registrado_por,
                $status,
            );
            http_response_code(201);
            echo json_encode([
                'sucesso'  => true,
                'mensagem' => 'Item registrado com sucesso',
                'id_item'  => $id_item,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno banco de dados' . $e->getMessage()]);
        }
    }

    public function index(Request $request): void
    {
        header('Content-Type application/json');

        $itemModel = new ItemPerdido();

        try {
            $itens = $itemModel->findAllWithDetails();
            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'total' => count($itens),
                'data' => $itens,
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno bolado que não é pra mostrar: ' . $e->getMessage()]);
        }
    }

    public function show(Request $request, int $id): void
    {
        header('Content-Type: application/json');
        
        $itemModel = new ItemPerdido();

        try {
            // Vem de graça lá do BaseModel
            $item = $itemModel->findById($id);

            if (!$item) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'sucesso' => true,
                'data' => $item
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno ao buscar item']);
        }
    }

    public function update(Request $request, int $id): void
    {
        header('Content-Type: application/json');
        $usuarioLogado = AuthMiddleware::handle(); 

        $dados = $request->getBody();

        if (empty($dados['titulo']) || empty($dados['categoria_id']) || empty($dados['local_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'titulo, categoria_id e local_id são obrigatórios']);
            return;
        }

        $titulo          = htmlspecialchars(strip_tags($dados['titulo']));
        $descricao       = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao'])) : '';
        $data_encontrado = !empty($dados['data_encontrado']) ? htmlspecialchars(strip_tags($dados['data_encontrado'])) : date('Y-m-d');
        $foto_url        = !empty($dados['foto_url']) ? htmlspecialchars(strip_tags($dados['foto_url'])) : null;
        $categoria_id    = (int) $dados['categoria_id'];
        $local_id        = (int) $dados['local_id'];
        $status          = !empty($dados['status']) ? htmlspecialchars(strip_tags($dados['status'])) : 'disponível';

        $itemModel = new ItemPerdido();

        try {
            if (!$itemModel->findById($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado para atualização']);
                return;
            }

            $sucesso = $itemModel->update($id, $titulo, $descricao, $data_encontrado, $foto_url, $local_id, $categoria_id, $status);

            if ($sucesso) {
                http_response_code(200);
                echo json_encode(['sucesso' => true, 'mensagem' => 'Item atualizado com sucesso']);
            } else {
                throw new Exception("Nenhuma alteração foi feita ou falha no banco.");
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    // O 'D' do CRUD: Deletar
    public function destroy(Request $request, int $id): void
    {
        header('Content-Type: application/json');
        $usuarioLogado = AuthMiddleware::handle();

        if ($usuarioLogado->role !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            return;
        }

        $itemModel = new ItemPerdido();

        try {
             if (!$itemModel->findById($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado']);
                return;
            }

            $itemModel->delete($id);

            http_response_code(200);
            echo json_encode(['sucesso' => true, 'mensagem' => 'Item deletado com sucesso']);
        } catch (Exception $e) {
             http_response_code(500);
             echo json_encode(['error' => 'Erro ao deletar: ' . $e->getMessage()]);
        }
    }
    
}
