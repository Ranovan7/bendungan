<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/keamanan', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        $user = $request->getAttribute('user');

        // if user is petugas, redirect to their spesific waduk/bendungan
        if ($user['role'] == '2') {
            $waduk_id = $user['waduk_id'];
            return $this->response->withRedirect($this->router->pathFor('keamanan.bendungan', ['id' => $waduk_id], []));
        }

        return $this->view->render($response, 'keamanan.html');
    })->setName('keamanan');

    $this->group('/{id}', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            $hari = $request->getParam('sampling', date('Y-m-d'));
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            $month = intval(date('m', strtotime($hari)));
            $year = intval(date('Y', strtotime($hari)));
            $keamanan = $this->db->query("SELECT * FROM periodik_keamanan
                                            WHERE waduk_id={$id}
                                                AND EXTRACT(MONTH FROM sampling)={$month}
                                                AND EXTRACT(YEAR FROM sampling)={$year}")->fetchAll();

            # make vnotch and piezometer name easier to get
            $piezometer_q = [];
            foreach ($piezometer as $p) {
                $piezometer_q[$p['id']] = $p['nama'];
            }

            # prepare preview data
            $periodik = [];
            foreach ($keamanan as $i => $k) {
                $tanggal = $k['sampling'];
                if (!$periodik[$tanggal]['id']) {
                    $periodik[$tanggal]['id'] = $k['id'];
                }

                if ($k['keamanan_type'] == 'vnotch') {

                } else {
                    $periodik[$tanggal]['piezometer'][$piezometer_q[$k['keamanan_id']]] = [
                        'id' => $k['id'],
                        'tma' => $k['tma']
                    ];
                }
            }
            krsort($periodik);
            // dump($periodik);

            $vnotch = $this->db->query("SELECT * FROM periodik_vnotch
                                        WHERE waduk_id={$id}
                                            AND EXTRACT(MONTH FROM sampling)={$month}
                                            AND EXTRACT(YEAR FROM sampling)={$year}
                                        ORDER BY SAMPLING DESC")->fetchAll();
            $piezo = $this->db->query("SELECT * FROM periodik_piezo
                                        WHERE waduk_id={$id}
                                            AND EXTRACT(MONTH FROM sampling)={$month}
                                            AND EXTRACT(YEAR FROM sampling)={$year}
                                        ORDER BY SAMPLING DESC")->fetchAll();
            // dump($vnotch);

            return $this->view->render($response, 'keamanan/bendungan.html', [
                'waduk' => $waduk,
                'vnotch' => $vnotch,
                'piezo' => $piezo,
                'periodik' => $periodik,
                'sampling' => $hari,
            ]);
        })->setName('keamanan.bendungan');

        $this->group('/vnotch', function() {

            $this->get('/add', function(Request $request, Response $response, $args) {
                $hari = $request->getParam('sampling', date('Y-m-d'));
                $id = $request->getAttribute('id');
                $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
                $vnotch = $this->db->query("SELECT * FROM vnotch WHERE waduk_id={$id}")->fetchAll();

                return $this->view->render($response, 'keamanan/vnotch/add.html', [
                    'waduk' => $waduk,
                    'vnotch' => $vnotch,
                    'sampling' => $hari,
                ]);
            })->setName('keamanan.vnotch.add');

            $this->post('/add', function(Request $request, Response $response, $args) {
                $id = $request->getAttribute('id');
                $form = $request->getParams();

                // check if record in submitted sampling/time already existed
                // if exists, insert/update record accordingly
                $sampling = $form['sampling'];
                $record = $this->db->query("SELECT id FROM periodik_vnotch
                                                WHERE
                                                    waduk_id={$id}
                                                AND
                                                    sampling='{$sampling} 00:00:00'")->fetch();

                if (empty($record)) {
                    $stmt = $this->db->prepare("INSERT INTO periodik_vnotch (
                                        sampling,
                                        waduk_id,
                                        vn1_tma,
                                        vn1_debit,
                                        vn2_tma,
                                        vn2_debit,
                                        vn3_tma,
                                        vn3_debit
                                    ) VALUES (
                                        :sampling,
                                        :waduk_id,
                                        :vn1_tma,
                                        :vn1_debit,
                                        :vn2_tma,
                                        :vn2_debit,
                                        :vn3_tma,
                                        :vn3_debit
                                    )");
                    $stmt->execute([
                        ":sampling" => $form['sampling'] . " 00:00:00",
                        ":waduk_id" => $id,
                        ":vn1_tma" => intval($form['tma-vn1']),
                        ":vn1_debit" => intval($form['debit-vn1']),
                        ":vn2_tma" => intval($form['tma-vn2']),
                        ":vn2_debit" => intval($form['debit-vn2']),
                        ":vn3_tma" => intval($form['tma-vn3']),
                        ":vn3_debit" => intval($form['debit-vn3'])
                    ]);
                } else {
                    $stmt = $this->db->prepare("UPDATE periodik_vnotch SET
                                        vn1_tma=:vn1_tma,
                                        vn1_debit=:vn1_debit,
                                        vn2_tma=:vn2_tma,
                                        vn2_debit=:vn2_debit,
                                        vn3_tma=:vn3_tma,
                                        vn3_debit=:vn3_debit
                                     WHERE waduk_id=:waduk_id AND sampling=:sampling");
                    $stmt->execute([
                        ":sampling" => $form['sampling'] . " 00:00:00",
                        ":waduk_id" => $id,
                        ":vn1_tma" => intval($form['tma-vn1']),
                        ":vn1_debit" => intval($form['debit-vn1']),
                        ":vn2_tma" => intval($form['tma-vn2']),
                        ":vn2_debit" => intval($form['debit-vn2']),
                        ":vn3_tma" => intval($form['tma-vn3']),
                        ":vn3_debit" => intval($form['debit-vn3'])
                    ]);
                }

                return $this->response->withRedirect($this->router->pathFor('keamanan.bendungan', ['id' => $id], []));
            })->setName('keamanan.vnotch.add');

            $this->post('/update', function(Request $request, Response $response, $args) {
                $id = $request->getAttribute('id');

                $form = $request->getParams();

                $info = explode("_", $form['name']);
                $column = $info[0];
                $sampling = $info[1];
                $keamanan_id = $info[2];

                if ($form['pk']) {
                    // update keamanan
                    $stmt = $this->db->prepare("UPDATE periodik_keamanan SET {$column}=:value WHERE id=:id");
                    $stmt->execute([
                        ':value' => $form['value'],
                        ':id' => $form['pk']
                    ]);
                } else {
                    // insert new keamanan
                    $stmt = $this->db->prepare("INSERT INTO periodik_keamanan
                                                    (sampling, {$column}, keamanan_type, keamanan_id, waduk_id)
                                                VALUES
                                                    (:sampling, :value, 'vnotch', :keamanan_id, :waduk_id)");
                    $stmt->execute([
                        ':sampling' => $sampling,
                        ':value' => $form['value'],
                        ':keamanan_id' => $keamanan_id,
                        ':waduk_id' => $id
                    ]);
                }

                return $response->withJson([
                    "name" => $form['name'],
                    "pk" => $form['pk'],
                    "value" => $form['value']
                ], 200);
            })->setName('keamanan.vnotch.update');

        });

        $this->group('/piezometer', function() {

            $this->get('/add', function(Request $request, Response $response, $args) {
                $hari = $request->getParam('sampling', date('Y-m-d'));
                $id = $request->getAttribute('id');
                $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
                $piezometer = $this->db->query("SELECT * FROM piezometer WHERE waduk_id={$id}")->fetchAll();

                return $this->view->render($response, 'keamanan/piezometer/add.html', [
                    'waduk' => $waduk,
                    'piezometer' => $piezometer,
                    'sampling' => $hari,
                ]);
            })->setName('keamanan.piezometer.add');

            $this->post('/add', function(Request $request, Response $response, $args) {
                $id = $request->getAttribute('id');
                $form = $request->getParams();

                // check if record in submitted sampling/time already existed
                // if exists, insert/update record accordingly
                $sampling = $form['sampling'];
                $record = $this->db->query("SELECT id FROM periodik_piezo
                                                WHERE waduk_id={$id}
                                                    AND sampling='{$sampling}'")->fetch();

                if (empty($record)) {
                    $stmt = $this->db->prepare("INSERT INTO periodik_piezo (
                                        sampling,
                                        waduk_id,
                                        p1a, p1b, p1c,
                                        p2a, p2b, p2c,
                                        p3a, p3b, p3c,
                                        p4a, p4b, p4c,
                                        p5a, p5b, p5c
                                    ) VALUES (
                                        :sampling,
                                        :waduk_id,
                                        :p1a, :p1b, :p1c,
                                        :p2a, :p2b, :p2c,
                                        :p3a, :p3b, :p3c,
                                        :p4a, :p4b, :p4c,
                                        :p5a, :p5b, :p5c
                                    )");
                    $stmt->execute([
                        ":sampling" => $form['sampling'] . " 00:00:00",
                        ":waduk_id" => $id,
                        ":p1a" => intval($form['p1a']),
                        ":p1b" => intval($form['p1b']),
                        ":p1c" => intval($form['p1c']),
                        ":p2a" => intval($form['p2a']),
                        ":p2b" => intval($form['p2b']),
                        ":p2c" => intval($form['p2c']),
                        ":p3a" => intval($form['p3a']),
                        ":p3b" => intval($form['p3b']),
                        ":p3c" => intval($form['p3c']),
                        ":p4a" => intval($form['p4a']),
                        ":p4b" => intval($form['p4b']),
                        ":p4c" => intval($form['p4c']),
                        ":p5a" => intval($form['p5a']),
                        ":p5b" => intval($form['p5b']),
                        ":p5c" => intval($form['p5c'])
                    ]);
                } else {
                    $stmt = $this->db->prepare("UPDATE periodik_piezo SET
                                            p1a=:p1a, p1b=:p1b, p1c=:p1c,
                                            p2a=:p2a, p2b=:p2b, p2c=:p2c,
                                            p3a=:p3a, p3b=:p3b, p3c=:p3c,
                                            p4a=:p4a, p4b=:p4b, p4c=:p4c,
                                            p5a=:p5a, p5b=:p5b, p5c=:p5c
                                         WHERE waduk_id=:waduk_id AND sampling=:sampling");
                    $stmt->execute([
                        ":sampling" => $form['sampling'] . " 00:00:00",
                        ":waduk_id" => $id,
                        ":p1a" => intval($form['p1a']),
                        ":p1b" => intval($form['p1b']),
                        ":p1c" => intval($form['p1c']),
                        ":p2a" => intval($form['p2a']),
                        ":p2b" => intval($form['p2b']),
                        ":p2c" => intval($form['p2c']),
                        ":p3a" => intval($form['p3a']),
                        ":p3b" => intval($form['p3b']),
                        ":p3c" => intval($form['p3c']),
                        ":p4a" => intval($form['p4a']),
                        ":p4b" => intval($form['p4b']),
                        ":p4c" => intval($form['p4c']),
                        ":p5a" => intval($form['p5a']),
                        ":p5b" => intval($form['p5b']),
                        ":p5c" => intval($form['p5c'])
                    ]);
                }

                return $this->response->withRedirect($this->router->pathFor('keamanan.bendungan', ['id' => $id], []));
            })->setName('keamanan.piezometer.add');

            $this->post('/update', function(Request $request, Response $response, $args) {
                $id = $request->getAttribute('id');

                $form = $request->getParams();

                $info = explode("_", $form['name']);
                $column = $info[0];
                $sampling = $info[1];
                $keamanan_id = $info[2];

                if ($form['pk']) {
                    // update keamanan
                    $stmt = $this->db->prepare("UPDATE periodik_keamanan SET {$column}=:value WHERE id=:id");
                    $stmt->execute([
                        ':value' => $form['value'],
                        ':id' => $form['pk']
                    ]);
                } else {
                    // insert new keamanan
                    $stmt = $this->db->prepare("INSERT INTO periodik_keamanan
                                                    (sampling, {$column}, keamanan_type, keamanan_id, waduk_id)
                                                VALUES
                                                    (:sampling, :value, 'piezometer', :keamanan_id, :waduk_id)");
                    $stmt->execute([
                        ':sampling' => $sampling,
                        ':value' => $form['value'],
                        ':keamanan_id' => $keamanan_id,
                        ':waduk_id' => $id
                    ]);
                }

                return $response->withJson([
                    "name" => $form['name'],
                    "pk" => $form['pk'],
                    "value" => $form['value']
                ], 200);
            })->setName('keamanan.piezometer.update');
        });

    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
