<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/operasi', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        $user = $request->getAttribute('user');

        // if user is petugas, redirect to their spesific waduk/bendungan
        if ($user['role'] == '2') {
            $waduk_id = $user['waduk_id'];
            return $this->response->withRedirect($this->router->pathFor('operasi.bendungan', ['id' => $waduk_id], []));
        }

        return $this->response->withRedirect($this->router->pathFor('waduk.harian'));
    })->setName('operasi');

    $this->group('/{id}', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            $hari = $request->getParam('sampling', date('Y-m-d'));
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

            $month = $prev_date = date('m', strtotime($hari));
            $year = $prev_date = date('Y', strtotime($hari));
            $daily = $this->db->query("SELECT * FROM periodik_daily
                                            WHERE waduk_id={$id}
                                                AND EXTRACT(MONTH FROM sampling)={$month}
                                                AND EXTRACT(YEAR FROM sampling)={$year}")->fetchAll();
            $tma = $this->db->query("SELECT * FROM tma
                                            WHERE waduk_id={$id}
                                                AND EXTRACT(MONTH FROM sampling)={$month}
                                                AND EXTRACT(YEAR FROM sampling)={$year}")->fetchAll();

            // save into periodik
            $periodik = [];
            foreach ($daily as $i => $d) {
                $periodik[date('Y-m-d', strtotime($d['sampling']))] = [
                    'ch' => $d['curahhujan'],
                    'inflow' => [
                        'debit' => $d['inflow_deb'],
                        'volume' => $d['inflow_vol']
                    ],
                    'outflow' => [
                        'debit' => $d['outflow_deb'],
                        'volume' => $d['outflow_vol'],
                        'spill_deb' => $d['spillway_deb'],
                        'spill_vol' => $d['spillway_vol']
                    ],
                    'tma' => [
                        '06' => [],
                        '12' => [],
                        '18' => []
                    ],
                    'id' => $d['id']
                ];
            }
            foreach ($tma as $t) {
                $jam = date('H', strtotime($t['sampling']));
                $tanggal = date('Y-m-d', strtotime($t['sampling']));

                // initiate tma if not exist
                if (!$periodik[$tanggal]['tma']) {
                    $periodik[$tanggal]['tma'] = [
                        '06' => [],
                        '12' => [],
                        '18' => []
                    ];
                }
                $periodik[$tanggal]['tma'][$jam] = [
                    'id' => $t['id'],
                    'tma' => $t['manual'],
                    'volume' => $t['volume']
                ];
            }
            krsort($periodik);
            return $this->view->render($response, 'operasi/bendungan.html', [
                'waduk' => $waduk,
                'periodik' => $periodik,
                'sampling' => $hari
            ]);
        })->setName('operasi.bendungan');

        $this->group('/tma', function() {

            $this->get('/add', function(Request $request, Response $response, $args) {
                $hari = $request->getParam('sampling', date('Y-m-d'));
                $id = $request->getAttribute('id');
                $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

                return $this->view->render($response, 'operasi/tma/add.html', [
                    'waduk' => $waduk,
                    'sampling' => $hari
                ]);
            })->setName('operasi.tma.add');

            $this->post('/add', function(Request $request, Response $response, $args) {
                $hari = date('Y-m-d');
                $id = $request->getAttribute('id');
                $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

                $form = $request->getParams();

                // check if row exist
                $sampling = $form['sampling'] . " " . $form['jam'];
                $tma = $this->db->query("SELECT * FROM tma WHERE sampling='$sampling'")->fetch();

                if ($tma) {
                    // update
                    $stmt = $this->db->prepare("UPDATE tma SET
                                                    manual=:manual,
                                                    volume=:volume
                                                WHERE
                                                    id=:id");
                    $stmt->execute([
                        ':manual' => $form['tma'],
                        ':volume' => $form['vol'],
                        ':id' => $tma['id'],
                    ]);
                } else {
                    // insert
                    $stmt = $this->db->prepare("INSERT INTO tma
                                                (sampling, manual, volume, waduk_id)
                                                VALUES
                                                (:sampling, :manual, :volume, :waduk_id)");
                    $stmt->execute([
                        ':sampling' => $sampling,
                        ':manual' => $form['tma'],
                        ':volume' => $form['vol'],
                        ':waduk_id' => $id,
                    ]);
                }

                // $this->flash->addMessage('messages', 'Periodik Daily berhasil ditambahkan');
                return $this->response->withRedirect($this->router->pathFor('operasi.bendungan', ['id' => $id], []));
            })->setName('operasi.tma.add');

            $this->post('/update', function(Request $request, Response $response, $args) {
                $id = $request->getAttribute('id');

                $form = $request->getParams();
                // dump($form);

                $info = explode("_", $form['name']);
                $column = $info[0];
                $sampling = $info[1];
                $jam = $info[2];

                // check if id exist
                $daily = $this->db->query("SELECT * FROM tma WHERE sampling='{$sampling} {$jam}:00:00'")->fetch();

                if ($daily) {
                    // update tma
                    $stmt = $this->db->prepare("UPDATE tma SET {$column}=:value WHERE id=:id");
                    $stmt->execute([
                        ':value' => $form['value'],
                        ':id' => $form['pk']
                    ]);
                } else {
                    // insert new tma
                    $stmt = $this->db->prepare("INSERT INTO tma
                                                    (sampling, {$column}, waduk_id)
                                                VALUES
                                                    (:sampling, :value, :waduk_id)");
                    $stmt->execute([
                        ':sampling' => $sampling . " " . $jam . ":00:00",
                        ':value' => $form['value'],
                        ':waduk_id' => $id
                    ]);
                }

                return $response->withJson([
                    "name" => $form['name'],
                    "pk" => $form['pk'],
                    "value" => $form['value']
                ], 200);
            })->setName('operasi.tma.update');

        });

        $this->group('/daily', function() {

            $this->get('/add', function(Request $request, Response $response, $args) {
                $hari = $request->getParam('sampling', date('Y-m-d'));
                $id = $request->getAttribute('id');
                $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

                return $this->view->render($response, 'operasi/daily/add.html', [
                    'waduk' => $waduk,
                    'sampling' => $hari
                ]);
            })->setName('operasi.daily.add');

            $this->post('/add', function(Request $request, Response $response, $args) {
                $hari = date('Y-m-d');
                $id = $request->getAttribute('id');
                $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();

                $form = $request->getParams();

                // check if periodik with requested sampling exist
                $sampling = $form['sampling'];
                $daily = $this->db->query("SELECT * FROM periodik_daily WHERE sampling='{$sampling}'")->fetch();
                // dump($daily);
                // die();

                if ($daily) {
                    // update the periodik
                    $stmt = $this->db->prepare("UPDATE periodik_daily SET
                                                    curahhujan=:curahhujan,
                                                    inflow_deb=:inflow_deb,
                                                    inflow_vol=:inflow_vol,
                                                    outflow_deb=:outflow_deb,
                                                    outflow_vol=:outflow_vol,
                                                    spillway_deb=:spillway_deb,
                                                    spillway_vol=:spillway_vol
                                                WHERE id=:id");
                    $stmt->execute([
                        ':curahhujan' => $form['ch'],
                        ':inflow_deb' => $form['debit'],
                        ':inflow_vol' => $form['volume'],
                        ':outflow_deb' => $form['deb-in'],
                        ':outflow_vol' => $form['vol-in'],
                        ':spillway_deb' => $form['deb-spill'],
                        ':spillway_vol' => $form['vol-spill'],
                        ':id' => $daily['id'],
                    ]);
                } else {
                    // insert new periodik
                    $stmt = $this->db->prepare("INSERT INTO periodik_daily
                                            (sampling, curahhujan, inflow_deb, inflow_vol,
                                                outflow_deb, outflow_vol, spillway_deb, spillway_vol,
                                                waduk_id)
                                            VALUES
                                            (:sampling, :curahhujan, :inflow_deb, :inflow_vol,
                                                :outflow_deb, :outflow_vol, :spillway_deb, :spillway_vol,
                                                :waduk_id)");
                    $stmt->execute([
                        ':sampling' => $form['sampling'],
                        ':curahhujan' => $form['ch'],
                        ':inflow_deb' => $form['debit'],
                        ':inflow_vol' => $form['volume'],
                        ':outflow_deb' => $form['deb-in'],
                        ':outflow_vol' => $form['vol-in'],
                        ':spillway_deb' => $form['deb-spill'],
                        ':spillway_vol' => $form['vol-spill'],
                        ':waduk_id' => $id,
                    ]);
                }

                // $this->flash->addMessage('messages', 'Periodik Daily berhasil ditambahkan');
                return $this->response->withRedirect($this->router->pathFor('operasi.bendungan', ['id' => $id], []));
            })->setName('operasi.daily.add');

            $this->post('/update', function(Request $request, Response $response, $args) {
                $id = $request->getAttribute('id');

                $form = $request->getParams();

                $info = explode("_", $form['name']);
                $column = $info[0];
                $sampling = $info[1];

                // check if id exist
                $daily = $this->db->query("SELECT * FROM periodik_daily WHERE sampling='{$sampling}'")->fetch();
                // dump($form);

                if ($daily) {
                    // update periodik
                    $stmt = $this->db->prepare("UPDATE periodik_daily SET {$column}=:value WHERE id=:id");
                    $stmt->execute([
                        ':value' => $form['value'],
                        ':id' => $form['pk']
                    ]);
                } else {
                    // insert new periodik
                    $stmt = $this->db->prepare("INSERT INTO periodik_daily
                                                    (sampling, {$column}, waduk_id)
                                                VALUES
                                                    (:sampling, :value, :waduk_id)");
                    $stmt->execute([
                        ':sampling' => $sampling,
                        ':value' => $form['value'],
                        ':waduk_id' => $id
                    ]);
                }

                return $response->withJson([
                    "name" => $form['name'],
                    "pk" => $form['pk'],
                    "value" => $form['value']
                ], 200);
            })->setName('operasi.daily.update');

        });

    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
