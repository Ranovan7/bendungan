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
            $vnotch = $this->db->query("SELECT * FROM vnotch WHERE waduk_id={$id}")->fetchAll();
            $piezometer = $this->db->query("SELECT * FROM piezometer WHERE waduk_id={$id}")->fetchAll();

            $month = $prev_date = date('m', strtotime($request->getParam('sampling', date('Y-m-d'))));
            $year = $prev_date = date('Y', strtotime($request->getParam('sampling', date('Y-m-d'))));
            $keamanan = $this->db->query("SELECT * FROM periodik_keamanan
                                            WHERE waduk_id={$id}
                                                AND EXTRACT(MONTH FROM sampling)={$month}
                                                AND EXTRACT(YEAR FROM sampling)={$year}")->fetchAll();

            # make vnotch and piezometer name easier to get
            $vnotch_q = [];
            $piezometer_q = [];
            foreach ($vnotch as $v) {
                $vnotch_q[$v['id']] = $v['nama'];
            }
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
                    $periodik[$tanggal]['vnotch'][$vnotch_q[$k['keamanan_id']]] = [
                        'id' => $k['id'],
                        'tma' => $k['tma'],
                        'debit' => $k['debit']
                    ];
                } else {
                    $periodik[$tanggal]['piezometer'][$piezometer_q[$k['keamanan_id']]] = [
                        'id' => $k['id'],
                        'tma' => $k['tma']
                    ];
                }
            }
            krsort($periodik);
            // dump($periodik);
            return $this->view->render($response, 'keamanan/bendungan.html', [
                'waduk' => $waduk,
                'vnotch' => $vnotch,
                'piezometer' => $piezometer,
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
                $records = $this->db->query("SELECT id, keamanan_id FROM periodik_keamanan
                                                WHERE
                                                    keamanan_type='vnotch'
                                                AND
                                                    waduk_id={$id}
                                                AND
                                                    sampling='{$sampling}'")->fetchAll();

                // get all form values, put into array with id as key
                $values = [];
                foreach ($form as $k => $v) {
                    if ($k == 'sampling') {
                        // sampling not included since it's form name
                        // doesn't contain record id
                        continue;
                    }
                    $f = explode("-", $k);
                    $values[$f[1]][$f[0]] = $v;
                }

                // checking existing record
                $v_insert = '';
                $v_update = '';
                foreach ($values as $i => $v) {
                    // check if form id exist
                    $check = [];
                    foreach ($records as $record) {
                        if ($i == $record['keamanan_id']) {
                            $check['id'] = $record['id'];
                            $check['keamanan_id'] = $record['keamanan_id'];
                            break;
                        }
                    }

                    if ($check) {
                        // updating existing records
                        if ($v_update) {
                            $v_update .= ' ,';
                        }
                        $tma = $v["tma"];
                        $debit = $v["debit"];
                        $v_update .= "({$check['id']}, {$tma}, {$debit})";
                    } else {
                        // insert new record
                        if ($v_insert) {
                            $v_insert .= ' ,';
                        }
                        $tma = $v["tma"];
                        $debit = $v["debit"];
                        $v_insert .= "('{$sampling}' ,{$tma}, {$debit}, 'vnotch', {$i}, {$id})";
                    }
                }

                // use to check values string
                // die($v_update);
                // die($v_insert);

                // insert query
                if (!empty($v_insert)) {
                    $stmt = $this->db->query("INSERT INTO periodik_keamanan
                                                (sampling, tma, debit, keamanan_type, keamanan_id, waduk_id)
                                            VALUES
                                                {$v_insert}");
                }

                // update query
                if (!empty($v_update)) {
                    $stmt = $this->db->query("UPDATE periodik_keamanan AS m
                                                SET tma = c.tma, debit = c.debit
                                                FROM (VALUES {$v_update})
                                                    AS c(id, tma, debit)
                                                WHERE c.id = m.id");
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
                $records = $this->db->query("SELECT id, keamanan_id FROM periodik_keamanan
                                                WHERE
                                                    keamanan_type='piezometer'
                                                AND
                                                    waduk_id={$id}
                                                AND
                                                    sampling='{$sampling}'")->fetchAll();

                // get all form values, put into array with id as key
                $values = [];
                foreach ($form as $k => $v) {
                    if ($k == 'sampling') {
                        continue;
                    }
                    $f = explode("-", $k);
                    $values[$f[1]][$f[0]] = $v;
                }

                // checking existing record
                $v_insert = '';
                $v_update = '';
                foreach ($values as $i => $v) {
                    // check if form id exist
                    $check = [];
                    foreach ($records as $record) {
                        if ($i == $record['keamanan_id']) {
                            $check['id'] = $record['id'];
                            $check['keamanan_id'] = $record['keamanan_id'];
                            break;
                        }
                    }

                    if ($check) {
                        // updating existing records
                        if ($v_update) {
                            $v_update .= ' ,';
                        }
                        $tma = $v["tma"];
                        $v_update .= "({$check['id']}, {$tma})";
                    } else {
                        // insert new record
                        if ($v_insert) {
                            $v_insert .= ' ,';
                        }
                        $tma = $v["tma"];
                        $v_insert .= "('{$sampling}' ,{$tma}, 'piezometer', {$i}, {$id})";
                    }
                }

                // die($v_update);
                // die($v_insert);

                // insert query
                if (v_insert) {
                    $stmt = $this->db->query("INSERT INTO periodik_keamanan
                                                (sampling, tma, keamanan_type, keamanan_id, waduk_id)
                                            VALUES
                                                {$v_insert}");
                }
                // update query
                if (v.update) {
                    $stmt = $this->db->query("UPDATE periodik_keamanan AS m
                                                SET tma = c.tma
                                                FROM (VALUES {$v_update})
                                                    AS c(id, tma)
                                                WHERE c.id = m.id");
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
