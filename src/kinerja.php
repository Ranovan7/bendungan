<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Kinerja Bendungan

$app->group('/kinerja', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $komponen = [
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

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        $user = $request->getAttribute('user');

        // if user is petugas, redirect to their spesific waduk/bendungan
        if ($user['role'] == '2') {
            $waduk_id = $user['waduk_id'];
            return $this->response->withRedirect($this->router->pathFor('kinerja.bendungan', ['id' => $waduk_id], []));
        }

        $waduk = $this->db->query("SELECT * FROM waduk")->fetchAll();
        $kerusakan = $this->db->query("SELECT * FROM kerusakan ORDER BY tgl_lapor DESC")->fetchAll();

        $kinerja = [];
        foreach ($kerusakan as $ker) {
            $kinerja[$ker['waduk_id']]['kerusakan'][] = $ker;
        }
        foreach ($waduk as $w) {
            $kinerja[$w['id']]['waduk'] = $w;
        }

        return $this->view->render($response, 'kinerja/index.html', [
            'kinerja' => $kinerja
        ]);
    })->setName('kinerja');

    $this->get('/komponen', function(Request $request, Response $response, $args) use ($komponen) {
        $komponens = [];

        foreach ($komponen as $k) {
            $komponens[] = [
                'value' => $k,
                'text' => $k
            ];
        }
        return $response->withJson($komponens, 200);
    })->setName('kinerja.komponen');

    $this->group('/{id}', function() use ($komponen) {

        $this->get('[/]', function(Request $request, Response $response, $args) use ($komponen) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $kerusakan = $this->db->query("SELECT * FROM kerusakan
                                                WHERE waduk_id={$id}
                                                ORDER BY id DESC")->fetchAll();

            // get photos
            $fotos = $this->db->query("SELECT * FROM foto WHERE obj_type='kerusakan'")->fetchAll();
            $foto = [];
            foreach ($fotos as $f) {
                $foto[$f['obj_id']][] = $f;
            }

            return $this->view->render($response, 'kinerja/bendungan.html', [
                'waduk' => $waduk,
                'kerusakan' => $kerusakan,
                'foto' => $foto
            ]);
        })->setName('kinerja.bendungan');

        $this->get('/lapor', function(Request $request, Response $response, $args) use ($komponen) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            return $this->view->render($response, 'kinerja/lapor.html', [
                'waduk' => $waduk,
                'komponen' => $komponen,
                'tanggal' => date('Y-m-d')
            ]);
        })->setName('kinerja.lapor');

        $this->post('/lapor', function(Request $request, Response $response, $args) use ($komponen) {
            $id = $request->getAttribute('id');
            $form = $request->getParams();

            // save kerusakan data in database
            $stmt = $this->db->prepare("INSERT INTO kerusakan
                                    (tgl_lapor, uraian_kerusakan, kategori, komponen, waduk_id)
                                    VALUES
                                    (:tgl_lapor, :uraian_kerusakan, :kategori, :komponen, :waduk_id)");
            $stmt->execute([
                ':tgl_lapor' => date("Y-m-d"),
                ':uraian_kerusakan' => $form['uraian'],
                ':kategori' => $form['kategori'],
                ':komponen' => $form['komponen'],
                ':waduk_id' => $id
            ]);
            $kerusakan_id = $this->db->lastInsertId();

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
                ':obj_id' => $kerusakan_id
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
                "kategori" => $form['kategori'],
                "tgl_lapor" => $form['sampling'],
                "uraian" => $form['uraian']
            ], 200);
        })->setName('kinerja.lapor');

        $this->post('/update', function(Request $request, Response $response, $args) use ($komponen) {
            $id = $request->getAttribute('id');
            $form = $request->getParams();

            // no setup needed, just straight update data
            $column = $form['name'];
            $stmt = $this->db->prepare("UPDATE kerusakan SET {$column}=:value WHERE id=:id");
            $stmt->execute([
                ':value' => $form['value'],
                ':id' => $form['pk']
            ]);

            return $response->withJson([
                "name" => $form['name'],
                "pk" => $form['pk'],
                "value" => $form['value']
            ], 200);
        })->setName('kinerja.update');

    }); // ->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
