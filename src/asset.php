<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Asset Bendungan

$app->group('/asset', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        $user = $request->getAttribute('user');

        // if user is petugas, redirect to their spesific waduk/bendungan
        if ($user['role'] == '2') {
            $waduk_id = $user['waduk_id'];
            return $this->response->withRedirect($this->router->pathFor('asset.bendungan', ['id' => $waduk_id], []));
        }

        return $this->view->render($response, 'asset.html');
    })->setName('asset');

    $this->group('/{id}', function() {

        $kategori = [
            "Tubuh Bendungan - Puncak",
            "Tubuh Bendungan - Lereng Hulu",
            "Tubuh Bendungan - Lereng Hilir",
            "Bangunan Pengambilan - Jembatan Hantar",
            "Bangunan Pengambilan - Menara Intake",
            "Bangunan Pengambilan - Pintu Intake",
            "Bangunan Pengambilan - Peralatan Hidromekanikal",
            "Bangunan Pengambilan - Mesin Penggerak",
            "Bangunan Pengeluaran - Tunnel / Terowongan",
            "Bangunan Pengeluaran - Katup",
            "Bangunan Pengeluaran - Mesin Penggerak",
            "Bangunan Pengeluaran - Bangunan Pelindung",
            "Bangunan Pelimpah - Lantai Hulu",
            "Bangunan Pelimpah - Mercu Spillway",
            "Bangunan Pelimpah - Saluran Luncur",
            "Bangunan Pelimpah - Dinding / Sayap",
            "Bangunan Pelimpah - Peredam Energi",
            "Bangunan Pelimpah - Jembatan",
            "Bukit Tumpuan - Tumpuan Kiri Kanan",
            "Bangunan Pelengkap - Bangunan Pelengkap",
            "Bangunan Pelengkap - Akses Jalan",
            "Instrumentasi - Tekanan Air Pori",
            "Instrumentasi - Pergerakan Tanah",
            "Instrumentasi - Tekanan Air Tanah",
            "Instrumentasi - Rembesan",
            "Instrumentasi - Curah Hujan"
        ];

        $this->get('[/]', function(Request $request, Response $response, $args) use ($kategori) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            $asset = $this->db->query("SELECT * FROM asset WHERE waduk_id={$id}")->fetchAll();

            return $this->view->render($response, 'asset/bendungan.html', [
                'waduk' => $waduk,
                'kategori' => $kategori,
                'asset' => $asset
            ]);
        })->setName('asset.bendungan');

        $this->get('/add', function(Request $request, Response $response, $args) use ($kategori) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            return $this->view->render($response, 'asset/add.html', [
                'waduk' => $waduk,
                'kategori' => $kategori,
                'tanggal' => date('Y-m-d')
            ]);
        })->setName('asset.add');

        $this->post('/add', function(Request $request, Response $response, $args) use ($kategori) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            $form = $request->getParams();

            $stmt = $this->db->prepare("INSERT INTO asset
                                    (nama, perolehan, kategori, merk,
                                        model, nilai_perolehan, bmn, waduk_id)
                                    VALUES
                                    (:nama, :perolehan, :kategori, :merk,
                                        :model, :nilai_perolehan, :bmn, :waduk_id)");
            $stmt->execute([
                ':nama' => $form['nama'],
                ':perolehan' => $form['perolehan'],
                ':kategori' => $form['kategori'],
                ':merk' => $form['merk'],
                ':model' => $form['model'],
                ':nilai_perolehan' => $form['nilai'],
                ':bmn' => $form['bmn'],
                ':waduk_id' => $id,
            ]);

            $this->flash->addMessage('messages', 'Asset berhasil ditambahkan');
            return $this->response->withRedirect($this->router->pathFor('asset.bendungan', ['id' => $id], []));
        })->setName('asset.add');

        $this->group('/rusak', function() {

            $this->get('[/]', function(Request $request, Response $response, $args) use ($kategori) {
                $id = $request->getAttribute('id');
                $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

                return $this->view->render($response, 'asset/rusak/index.html', [
                    'waduk' => $waduk
                ]);
            })->setName('asset.rusak');

            $this->group('/{asset_id}', function() {

                $this->get('/add', function(Request $request, Response $response, $args) {
                    $id = $request->getAttribute('id');
                    $asset_id = $request->getAttribute('asset_id');
                    $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

                    return $this->view->render($response, 'asset/rusak/add.html', [
                        'waduk' => $waduk,
                        'asset_id' => $asset_id
                    ]);
                })->setName('asset.rusak.add');

                $this->post('/add', function(Request $request, Response $response, $args) {
                    $id = $request->getAttribute('id');
                    $asset_id = $request->getAttribute('asset_id');
                    $form = $request->getParams();

                    // save kerusakan data in database
                    $stmt = $this->db->prepare("INSERT INTO kerusakan
                                            (tgl_lapor, uraian_kerusakan, kategori, asset_id, waduk_id)
                                            VALUES
                                            (:tgl_lapor, :uraian_kerusakan, :kategori, :asset_id, :waduk_id)");
                    $stmt->execute([
                        ':tgl_lapor' => date("Y-m-d"),
                        ':uraian_kerusakan' => $form['uraian'],
                        ':kategori' => $form['kategori'],
                        ':asset_id' => $asset_id,
                        ':waduk_id' => $id
                    ]);

                    // convert base64 to image file
                    $data = explode( ',', $form['data'] );
                    $image = base64_decode($data[1]);

                    // create new directory to save the image
                    $directory = $this->get('settings')['upload_directory'];
                    $date = date("Y-m-d-H-i");  // to make it unique
                    $public_url = "kerusakan" . DIRECTORY_SEPARATOR . $date . "_" . $form['filename'];   // for url in database
                    $img_dir = $directory . DIRECTORY_SEPARATOR . $public_url;  // for saving file

                    // check if file exist, if not create new
                    $folder = $directory . DIRECTORY_SEPARATOR . "kerusakan";
                    if (!file_exists($folder)) {
                        mkdir($folder, 0775, true);
                    }

                    // save foto data in database
                    $stmt_foto = $this->db->prepare("INSERT INTO foto
                                            (url, keterangan, obj_type, obj_id)
                                            VALUES
                                            (:url, :keterangan, :obj_type, :obj_id)");
                    $stmt_foto->execute([
                        ':url' => "uploads" . DIRECTORY_SEPARATOR . $public_url,
                        ':keterangan' => $form['keterangan'],
                        ':obj_type' => "kerusakan",
                        ':obj_id' => $asset_id
                    ]);

                    // save image in designated directory
                    $file = fopen($img_dir, "wb");
                    fwrite($file, $image);
                    fclose($file);

                    return $response->withJson([
                        "status" => "nani",
                        "data" => $img_url,
                        "filename" => $form['filename'],
                        "keterangan" => $form['keterangan'],
                        "tgl_lapor" => $form['sampling'],
                        "uraian" => $form['uraian']
                    ], 200);
                })->setName('asset.rusak.add');

            });

        });

    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
