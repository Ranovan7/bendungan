<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\UploadedFile;

// Manage Operasi Bendungan

$app->group('/kegiatan', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        $user = $request->getAttribute('user');

        // if user is petugas, redirect to their spesific waduk/bendungan
        if ($user['role'] == '2') {
            $waduk_id = $user['waduk_id'];
            return $this->response->withRedirect($this->router->pathFor('kegiatan.bendungan', ['id' => $waduk_id], []));
        }

        return $this->view->render($response, 'kegiatan.html');
    })->setName('kegiatan');

    $this->group('/{id}', function() {

        $petugas = [
            "Tidak Ada",
            "Koordinator",
            "Keamanan",
            "Pemantauan",
            "Operasi",
            "Pemeliharaan",

        ];

        $this->get('[/]', function(Request $request, Response $response, $args) use ($petugas) {
            $hari = $request->getParam('sampling', date('Y-m-d'));
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $kegiatan_raw = $this->db->query("SELECT kegiatan.*, foto.url AS foto_url
                                            FROM kegiatan LEFT JOIN foto ON kegiatan.foto_id=foto.id
                                            WHERE waduk_id={$id}
                                            ORDER BY sampling DESC")->fetchAll();

            $kegiatan = [];
            foreach ($kegiatan_raw as $raw) {
                if (!array_key_exists($raw['sampling'], $kegiatan)) {
                    $kegiatan[$raw['sampling']] = [
                        'koordinator' => [],
                        'keamanan' => [],
                        'pemantauan' => [],
                        'operasi' => [],
                        'pemeliharaan' => []
                    ];
                }

                $kegiatan[$raw['sampling']]['id'] = $raw['id'];
                $kegiatan[$raw['sampling']][strtolower($raw['petugas'])][] = $raw['uraian'];
            }

            return $this->view->render($response, 'kegiatan/bendungan.html', [
                'waduk' =>  $waduk,
                'kegiatan' => $kegiatan,
                'petugas' => $petugas,
                'sampling' => $hari
            ]);
        })->setName('kegiatan.bendungan');

        $this->get('/add', function(Request $request, Response $response, $args) use ($petugas) {
            // $hari = $request->getParam('sampling', date('Y-m-d'));
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            return $this->view->render($response, 'kegiatan/add.html', [
                'waduk' =>  $waduk,
                'petugas' => $petugas
            ]);
        })->setName('kegiatan.add');

        $this->post('/add', function(Request $request, Response $response, $args) use ($petugas) {
            // $hari = $request->getParam('sampling', date('Y-m-d'));
            $id = $request->getAttribute('id');
            $form = $request->getParams();

            // convert base64 to image file
            $data = explode( ',', $form['data'] );
            $image = base64_decode($data[1]);

            // create new directory to save the image
            $directory = $this->get('settings')['upload_directory'];
            $date = date("Y-m-d-H-i");  // to make it unique
            $public_url = "kegiatan" . DIRECTORY_SEPARATOR . $date . "_" . $form['filename'];   // for url in database
            $img_dir = $directory . DIRECTORY_SEPARATOR . $public_url;  // for saving file

            // check if file exist, if not create new
            $folder = $directory . DIRECTORY_SEPARATOR . "kegiatan";
            if (!file_exists($folder)) {
                mkdir($folder, 0775, true);
            }

            // save foto data in database
            $stmt_foto = $this->db->prepare("INSERT INTO foto
                                    (url, keterangan, obj_type)
                                    VALUES
                                    (:url, :keterangan, :obj_type)");
            $stmt_foto->execute([
                ':url' => "uploads" . DIRECTORY_SEPARATOR . $public_url,
                ':keterangan' => $form['keterangan'],
                ':obj_type' => "kegiatan"
            ]);

            // save image in designated directory
            $file = fopen($img_dir, "wb");
            fwrite($file, $image);
            fclose($file);

            $foto_id = $this->db->lastInsertId();

            // save kegiatan data in database
            $stmt = $this->db->prepare("INSERT INTO kegiatan
                                    (sampling, petugas, uraian, foto_id, waduk_id)
                                    VALUES
                                    (:sampling, :petugas, :uraian, :foto_id, :waduk_id)");
            $stmt->execute([
                ':sampling' => $form['sampling'],
                ':petugas' => $form['petugas'],
                ':uraian' => $form['keterangan'],
                ':foto_id' => $foto_id,
                ':waduk_id' => $id
            ]);

            // update obj_id in foto
            $kegiatan_id = $this->db->lastInsertId();
            $stmt_foto = $this->db->prepare("UPDATE foto SET obj_id=:obj_id WHERE id=:id");
            $stmt_foto->execute([
                ':obj_id' => $kegiatan_id,
                ':id' => $foto_id
            ]);

            return $response->withJson([
                "status" => "nani",
                "data" => $foto_id,
                "filename" => $form['filename'],
                "keterangan" => $form['keterangan'],
                "sampling" => $form['sampling'],
                "petugas" => $form['petugas']
            ], 200);
        })->setName('kegiatan.add');
    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
