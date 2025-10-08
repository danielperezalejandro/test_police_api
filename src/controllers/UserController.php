<?php
require_once __DIR__ . '/../config/database.php';

class UserController {

    public function getUsers($request, $response) {
        $db = new Database();
        $conn = $db->connect();

        try {
            $query = "SELECT id, name, email, provider, created_at, password FROM users";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($users));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function createUser($request, $response) {
        $db = new Database();
        $conn = $db->connect();

        $data = json_decode($request->getBody()->getContents(), true);

        // Validar campos obligatorios
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            $response->getBody()->write(json_encode(["error" => "Faltan campos obligatorios"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Encriptar contraseña
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $provider = $data['provider'] ?? 'local';

        try {
            $query = "INSERT INTO users (name, email, password, provider, created_at)
                      VALUES (:name, :email, :password, :provider, NOW())";
            $stmt = $conn->prepare($query);

            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':email', $data['email']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':provider', $provider);

            $stmt->execute();

            $response->getBody()->write(json_encode([
                "message" => "Usuario creado correctamente",
                "user_id" => $conn->lastInsertId()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }
}
