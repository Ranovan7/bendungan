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
            // $hari = $request->getParam('sampling', date('Y-m-d'));
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            return $this->view->render($response, 'kegiatan/bendungan.html', [
                'waduk' =>  $waduk,
                'petugas' => $petugas
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
            $date = date("Y-m-d_H-i");  // to make it unique
            $folder = $directory . DIRECTORY_SEPARATOR . "kegiatan";
            $img_dir = $folder . DIRECTORY_SEPARATOR . $date . "-" . $form['filename'];

            // check if file exist, if not create new
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // save image in designated directory
            $file = fopen($img_dir, "wb");
            fwrite($file, $image);
            fclose($file);

            return $response->withJson([
                "status" => "nani",
                "data" => gettype($image),
                "filename" => $form['filename'],
                "keterangan" => $form['keterangan'],
                "waktu" => $form['waktu'],
                "petugas" => $form['petugas']
            ], 200);
        })->setName('kegiatan.add');
    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
