<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/rtow', function() use ($loggedinMiddleware, $adminAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        $waduk = $this->db->query("SELECT * FROM waduk")->fetchAll();
        // $user = $this->db->query("SELECT users.*, waduk.nama AS waduk_nama, waduk.jenis AS waduk_jenis
        //                             FROM users
        //                             LEFT JOIN waduk ON users.waduk_id=waduk.id")->fetchAll();
        $user = $this->db->query("SELECT * FROM users")->fetchAll();
        return $this->view->render($response, 'rencana/index.html', [
            'waduk' => $waduk,
            'users' => $user
        ]);
    })->setName('rencana');

    $this->group('/{id}', function() {

        // export, send csv file
        $this->get('/export', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            return $this->view->render($response, 'rencana/import.html', [
                'waduk' => $waduk,
            ]);
        })->setName('rencana.export');

        // import, read csv file and insert it into database
        $this->get('/import', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            return $this->view->render($response, 'rencana/import.html', [
                'waduk' => $waduk,
            ]);
        })->setName('rencana.import');

        $this->post('/import', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $files = $request->getUploadedFiles();
            // $file = $files['upload'];
            $file = $_FILES['upload'];

            if ($file['error'] != UPLOAD_ERR_OK) {
                dump("Error Found");
            }

            $raw = file_get_contents($file['tmp_name']);
            $iterren = explode("\n", $raw);
            $rencana = [];
            $columns = "";
            $values = "";
            $waktu_index = 0;
            foreach ($iterren as $n => $i) {
                if (empty($i)) {
                    break;
                }

                if ($values) {
                    // add comma to subsequent values
                    $values .= ",\n";
                }

                if ($n == 0) {
                    // ignore
                    continue;
                } else if ($n == 1) {
                    // columns
                    $columns = "(" . $i . ",waduk_id)";

                    // get index waktu
                    foreach (explode(",", $i) as $index => $val) {
                        if ($val === 'waktu') {
                            $waktu_index = $index;
                        }
                    }
                } else {
                    $temp = "";
                    foreach (explode(",", $i) as $n =>$val) {
                        if ($temp) {
                            $temp .= ",";
                        }
                        if ($n == $waktu_index) {
                            // timestamp is actually string
                            $temp .= "'{$val}'";
                        } else {
                            if ($val == "None") {
                                $temp .= "NULL";
                            } else {
                                $temp .= "{$val}";
                            }
                        }
                    }
                    $values .= "({$temp},{$id})";
                }
                // $rencana[] = $i;
            }

            // dump($columns);
            // dump($values);
            // dump($rencana);

            // insert multiple row
            $stmt = $this->db->prepare("INSERT INTO rencana
                                            {$columns}
                                        VALUES
                                            {$values}");
            $stmt->execute();

            // $this->flash->addMessage('messages', 'RTOW berhasil ditambahkan');
            return $this->response->withRedirect($this->router->pathFor('rencana'));
        })->setName('rencana.import');

    });

})->add($adminAuthorizationMiddleware)->add($loggedinMiddleware);
