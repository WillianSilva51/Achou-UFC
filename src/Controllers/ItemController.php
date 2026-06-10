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

        if (empty($dados['nome']) || empty($dados['id_categoria']) || empty($dados['id_local'])) {
            http_response_code(400);
            echo json_encode(['error' => 'nome, id, é local são obrigatorio']);
            return;
        }
        $titulo = !empty($dados['titulo']) ? htmlspecialchars(strip_tags($dados['titulo'])) : '';
        $descricao = !empty($dados['descricao']) ? htmlspecialchars(strip_tags($dados['descricao'])) : '';
        $categoria_id = (int) $dados['id_categoria'];
        $local_id = (int) $dados['id_local'];
        $status = !empty($dados['status']) ? htmlspecialchars(strip_tags($dados['status'])) : '';

        $registrado_por = (int) $usuarioLogado->sub;

        $ItemPerdidoModel = new ItemPerdido();
        try {
            $id_item = $ItemPerdidoModel->create(
                $titulo,
                $descricao,
                $categoria_id,
                $local_id,
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
            $itens = $itemModel->findAll();
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
}
