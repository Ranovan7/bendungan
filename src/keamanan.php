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
                $tanggal = date('d M Y', strtotime($k['sampling']));
                if (!$periodik[$tanggal]['id']) {
                $periodik[$tanggal]['id'] = $i;
                }

                if ($k['keamanan_type'] == 'vnotch') {
                    $periodik[$tanggal]['vnotch'][] = [
                        'nama' => $vnotch_q[$k['keamanan_id']],
                        'tma' => $k['tma'],
                        'debit' => $k['debit']
                    ];
                } else {
                    $periodik[$tanggal]['piezometer'][] = [
                        'nama' => $piezometer_q[$k['keamanan_id']],
                        'tma' => $k['tma']
                    ];
                }
            }
            krsort($periodik);
            return $this->view->render($response, 'keamanan/bendungan.html', [
                'waduk' => $waduk,
                'vnotch' => $vnotch,
                'piezometer' => $piezometer,
                'periodik' => $periodik,
                'sampling' => tanggal_format(strtotime($hari)),
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
                    'sampling' => tanggal_format(strtotime($hari)),
                ]);
            })->setName('keamanan.vnotch.add');

            $this->post('/add', function(Request $request, Response $response, $args) {
                $hari = $request->getParam('sampling', date('Y-m-d'));
                $id = $request->getAttribute('id');
                $vnotch = $this->db->query("SELECT * FROM vnotch WHERE waduk_id={$id}")->fetchAll();
                $form = $request->getParams();

                $values = '';
                foreach ($vnotch as $i=>$v) {
                    if ($i > 0) {
                        $values .= ' ,';
                    }
                    $tma = $form["tma-{$v['id']}"];
                    $debit = $form["debit-{$v['id']}"];
                    $values .= "('{$hari}' ,{$tma}, {$debit}, 'vnotch', {$v['id']}, {$id})";
                }

                // echo $values;
                $stmt = $this->db->query("INSERT INTO periodik_keamanan
                                            (sampling, tma, debit, keamanan_type, keamanan_id, waduk_id)
                                        VALUES
                                            {$values}");

                return $this->response->withRedirect($this->router->pathFor('keamanan.bendungan', ['id' => $id], []));
            })->setName('keamanan.vnotch.add');
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
                    'sampling' => tanggal_format(strtotime($hari)),
                ]);
            })->setName('keamanan.piezometer.add');

            $this->post('/add', function(Request $request, Response $response, $args) {
                $hari = $request->getParam('sampling', date('Y-m-d'));
                $id = $request->getAttribute('id');
                $piezometer = $this->db->query("SELECT * FROM piezometer WHERE waduk_id={$id}")->fetchAll();
                $form = $request->getParams();

                $values = '';
                foreach ($piezometer as $i=>$p) {
                    if ($i > 0) {
                        $values .= ' ,';
                    }
                    $tma = $form["tma-{$p['id']}"];
                    $values .= "('{$hari}' ,{$tma}, 'piezometer', {$p['id']}, {$id})";
                }

                // die($values);
                $stmt = $this->db->query("INSERT INTO periodik_keamanan
                                            (sampling, tma, keamanan_type, keamanan_id, waduk_id)
                                        VALUES
                                            {$values}");

                return $this->response->withRedirect($this->router->pathFor('keamanan.bendungan', ['id' => $id], []));
            })->setName('keamanan.piezometer.add');
        });

    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
