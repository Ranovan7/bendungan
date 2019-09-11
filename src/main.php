<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Main Route

// home
$app->get('/', function(Request $request, Response $response, $args) {
    return $this->response->withRedirect('/operasi');
    // return $this->view->render($response, 'main/index.html');
});

// Auth User

$app->get('/login', function(Request $request, Response $response, $args) {
    return $this->view->render($response, 'main/login.html');
});
// dummy login flow, bisa di uncomment ke POST
// $app->get('/lg', function(Request $request, Response $response, $args) {
$app->post('/login', function(Request $request, Response $response, $args) {
    $credentials = $request->getParams();
    if (empty($credentials['username']) || empty($credentials['password'])) {
        die("Masukkan username dan password");
    }

    $stmt = $this->db->prepare("SELECT * FROM users WHERE username=:username");
    $stmt->execute([':username' => $credentials['username']]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($credentials['password'], $user['password'])) {
        die("Username / password salah!");
    }

    $this->session->user_id = $user['id'];
    $this->session->user_refresh_time = strtotime("+1hour");

    // die("Welcommmen {$user['username']}!");
    $this->flash->addMessage('messages', 'Berhasil Login');
    return $this->response->withRedirect('/operasi');
});

// generate admin, warning!
$app->get('/gen', function(Request $request, Response $response, $args) {
    $credentials = $request->getParams();
    if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['role'])) {
        die("Masukkan username, password dan role");
    }

    $stmt = $this->db->prepare("SELECT * FROM users WHERE username=:username");
    $stmt->execute([':username' => $credentials['username']]);
    $user = $stmt->fetch();

    // jika belum ada di DB, tambahkan
    if (!$user) {
        $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
        $stmt->execute([
            ':username' => $credentials['username'],
            ':password' => password_hash($credentials['password'], PASSWORD_DEFAULT),
            ':role' => $credentials['role'],
        ]);
        die("Username {$credentials['username']} ditambahkan!");
    } else { // else update password
        $stmt = $this->db->prepare("UPDATE users SET password=:password WHERE id=:id");
        $stmt->execute([
            ':password' => password_hash($credentials['password'], PASSWORD_DEFAULT),
            ':id' => $user['id']
        ]);
        die("Password {$user['username']} diubah!");
    }
});

$app->get('/logout', function(Request $request, Response $response, $args) {
    $this->flash->addMessage('messages', 'Berhasil Logout');
    $this->session->destroy();
    return $this->response->withRedirect('/login');
});

$app->get('/forbidden', function(Request $request, Response $response, $args) {

    return $this->view->render($response, 'errors/403.html');
});
