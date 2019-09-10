<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/lokasi', function() use ($loggedinMiddleware, $adminAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        $lokasi = $this->db->query("SELECT * FROM lokasi")->fetchAll();

        return $this->view->render($response, 'lokasi/index.html', [
            'lokasi' => $lokasi
        ]);

        return $this->view->render($response, 'lokasi/index.html');
    })->setName('lokasi');

    $this->group('/add', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            return $this->view->render($response, 'lokasi/add.html');
        })->setName('lokasi.add');

        $this->post('[/]', function(Request $request, Response $response, $args) {
            $form = $request->getParams();
            // echo $form['username'];
            // echo $form['password'];
            // echo $form['lokasi'];
            $stmt = $this->db->prepare("INSERT INTO lokasi (nama, kab, lbi, volume) VALUES (:nama, :kab, :lbi, :volume)");
            $stmt->execute([
                ':nama' => $form['nama'],
                ':kab' => $form['kab'],
                ':lbi' => $form['lbi'],
                ':volume' => $form['volume']
            ]);

            return $this->response->withRedirect('/lokasi');
        })->setName('lokasi.add');
    });

    $this->group('/{id}', function() {

        // change password
        $this->get('/detail', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $lokasi = $this->db->query("SELECT * FROM lokasi WHERE id={$id}")->fetch();

            return $this->view->render($response, 'lokasi/detail.html', [
                'lokasi' => $lokasi
            ]);
        })->setName('lokasi.detail');

        $this->post('/detail', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $form = $request->getParams();

            $stmt = $this->db->prepare("UPDATE lokasi SET nama=:nama, kab=:kab, lbi=:lbi, volume=:volume WHERE id=:id");
            $stmt->execute([
                ':nama' => $form['nama'],
                ':kab' => $form['kab'],
                ':lbi' => $form['lbi'],
                ':volume' => $form['volume'],
                ':id' => $id
            ]);
            // die("Password {$user['username']} diubah!"); // change to redirect next
            return $this->response->withRedirect('/lokasi');
        })->setName('lokasi.detail');

        // delete
        $this->get('/del', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $lokasi = $user = $this->db->query("SELECT * FROM lokasi WHERE id={$id}")->fetch();
            return $this->view->render($response, 'lokasi/delete.html', [
                'lokasi' => $lokasi,
            ]);
        })->setName('lokasi.delete');

        $this->post('/del', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $user = $user = $this->db->query("SELECT * FROM lokasi WHERE id={$id}")->fetch();
            $stmt = $this->db->prepare("DELETE FROM lokasi WHERE id=:id");
            $stmt->execute([
                ':id' => $id
            ]);
            // die("User {$user['username']} dihapus!"); // change to redirect next
            return $this->response->withRedirect('/lokasi');
        })->setName('lokasi.delete');
    });

})->add($adminAuthorizationMiddleware)->add($loggedinMiddleware);
