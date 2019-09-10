<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/user', function() use ($loggedinMiddleware, $adminAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        $lokasi = $this->db->query("SELECT * FROM lokasi")->fetchAll();
        // $user = $this->db->query("SELECT users.*, lokasi.nama AS lokasi_nama, lokasi.jenis AS lokasi_jenis
        //                             FROM users
        //                             LEFT JOIN lokasi ON users.lokasi_id=lokasi.id")->fetchAll();
        $user = $this->db->query("SELECT * FROM users")->fetchAll();
        return $this->view->render($response, 'user/index.html', [
            'lokasi' => $lokasi,
            'users' => $user
        ]);

        return $this->view->render($response, 'user/index.html');
    })->setName('user');

    $this->group('/add', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            return $this->view->render($response, 'user/add.html');
        })->setName('user.add');

        $this->post('[/]', function(Request $request, Response $response, $args) {
            $form = $request->getParams();
            // echo $form['username'];
            // echo $form['password'];
            // echo $form['lokasi'];
            if ($form['lokasi']) {
                $stmt = $this->db->prepare("INSERT INTO users (username, password, role, lokasi_id) VALUES (:username, :password, :role, :lokasi_id)");
                $stmt->execute([
                    ':username' => $form['username'],
                    ':password' => password_hash($form['password'], PASSWORD_DEFAULT),
                    ':lokasi_id' => $form['lokasi'],
                    ':role' => $form['role']
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
                $stmt->execute([
                    ':username' => $form['username'],
                    ':password' => password_hash($form['password'], PASSWORD_DEFAULT),
                    ':role' => $form['role']
                ]);
            }
            return $this->response->withRedirect('/user');
        })->setName('user.add');
    });

    $this->group('/{id}', function() {

        // change password
        $this->get('/password', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            return $this->view->render($response, 'user/password.html', [
                'user_id' => $id,
            ]);
        })->setName('user.password');

        $this->post('/password', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $credentials = $request->getParams();
            $stmt = $this->db->prepare("UPDATE users SET password=:password WHERE id=:id");
            $stmt->execute([
                ':password' => password_hash($credentials['password'], PASSWORD_DEFAULT),
                ':id' => $id
            ]);
            // die("Password {$user['username']} diubah!"); // change to redirect next
            return $this->response->withRedirect('/user');
        })->setName('user.password');

        // delete
        $this->get('/del', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $user = $user = $this->db->query("SELECT * FROM users WHERE id={$id}")->fetch();
            return $this->view->render($response, 'user/delete.html', [
                'user' => $user,
            ]);
        })->setName('user.delete');

        $this->post('/del', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $user = $user = $this->db->query("SELECT * FROM users WHERE id={$id}")->fetch();
            $stmt = $this->db->prepare("DELETE FROM users WHERE id=:id");
            $stmt->execute([
                ':id' => $id
            ]);
            // die("User {$user['username']} dihapus!"); // change to redirect next
            return $this->response->withRedirect('/user');
        })->setName('user.delete');
    });

})->add($adminAuthorizationMiddleware)->add($loggedinMiddleware);
