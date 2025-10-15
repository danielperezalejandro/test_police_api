<?php
require_once __DIR__ . '/../config/database.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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

    public function loginUser($request, $response) {
        $db = new Database();
        $conn = $db->connect();

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json')
                ->write(json_encode(["error" => "Faltan campos obligatorios"]));
        }

        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['password'], $user['password'])) {
                return $response->withStatus(401)->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(["error" => "Credenciales inválidas"]));
            }

            // ✅ Generar token JWT
            $key = "clave_super_secreta"; // 🔒 cámbiala y guárdala segura
            $payload = [
                'iss' => "test_police_api",   // issuer
                'iat' => time(),              // issued at
                'exp' => time() + (60 * 60 * 24 * 7), // expira en 7 días
                'user_id' => $user['id'],
                'email' => $user['email']
            ];

            $jwt = JWT::encode($payload, $key, 'HS256');

            unset($user['password']);

            $response->getBody()->write(json_encode([
                "message" => "Inicio de sesión correcto",
                "token" => $jwt,
                "user" => $user
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function registerWithGoogle($request, $response) {
        $db = new Database();
        $conn = $db->connect();

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data['name']) || empty($data['email'])) {
            $response->getBody()->write(json_encode(["error" => "Faltan campos obligatorios"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            // Verificar si ya existe el usuario
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $response->getBody()->write(json_encode(["message" => "El usuario ya existe con Google"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
            }

            // Crear usuario sin contraseña
            $insert = "INSERT INTO users (name, email, provider, created_at)
                    VALUES (:name, :email, 'google', NOW())";
            $insertStmt = $conn->prepare($insert);
            $insertStmt->bindParam(':name', $data['name']);
            $insertStmt->bindParam(':email', $data['email']);
            $insertStmt->execute();

            $userId = $conn->lastInsertId();

            $response->getBody()->write(json_encode([
                "message" => "Usuario registrado con Google correctamente",
                "user_id" => $userId
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function loginWithGoogle($request, $response) {
        $db = new Database();
        $conn = $db->connect();

        $data = json_decode($request->getBody()->getContents(), true);
        $email = $data['email'] ?? '';

        if (empty($email)) {
            $response->getBody()->write(json_encode(["error" => "Falta el email"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $query = "SELECT * FROM users WHERE email = :email AND provider = 'google'";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $response->getBody()->write(json_encode(["error" => "Usuario no encontrado"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }

            // ✅ Generar token JWT
            $key = "clave_super_secreta";
            $payload = [
                'iss' => "test_police_api",
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24 * 7), // 7 días
                'user_id' => $user['id'],
                'email' => $user['email']
            ];

            $jwt = JWT::encode($payload, $key, 'HS256');
            unset($user['password']);

            $response->getBody()->write(json_encode([
                "message" => "Inicio de sesión con Google correcto",
                "token" => $jwt,
                "user" => $user
            ]));

            return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (PDOException $e) {
            $response->getBody()->write(json_encode(["error" => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }



}
