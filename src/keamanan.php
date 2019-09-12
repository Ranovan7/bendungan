<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/keamanan', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        // $user = $request->getAttribute('user');

        return $this->view->render($response, 'keamanan.html');
    })->setName('keamanan');

    $this->group('/{id}', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            $id = $request->getAttribute('id');
            $waduk = $this->db->query("SELECT * FROM waduk WHERE id={$id}")->fetch();
            $vnotch = $this->db->query("SELECT * FROM vnotch WHERE waduk_id={$id}")->fetchAll();
            $piezometer = $this->db->query("SELECT * FROM piezometer WHERE waduk_id={$id}")->fetchAll();
            $v_periodik = $this->db->query("SELECT * FROM periodik_keamanan WHERE keamanan_type='vnotch' AND waduk_id={$id}")->fetchAll();
            $p_periodik = $this->db->query("SELECT * FROM periodik_keamanan WHERE keamanan_type='piezometer' AND waduk_id={$id}")->fetchAll();

            return $this->view->render($response, 'keamanan/bendungan.html', [
                'waduk' => $waduk,
                'vnotch' => $vnotch,
                'piezometer' => $piezometer,
                'v_periodik' => $v_periodik,
                'p_periodik' => $p_periodik
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
                    $values .= "({$tma}, {$debit}, 'vnotch', {$v['id']}, {$id})";
                }

                // echo $values;
                $stmt = $this->db->query("INSERT INTO periodik_keamanan
                                            (tma, debit, keamanan_type, keamanan_id, waduk_id)
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
                    $values .= "({$tma}, 'piezometer', {$p['id']}, {$id})";
                }

                // die($values);
                $stmt = $this->db->query("INSERT INTO periodik_keamanan
                                            (tma, keamanan_type, keamanan_id, waduk_id)
                                        VALUES
                                            {$values}");

                return $this->response->withRedirect($this->router->pathFor('keamanan.bendungan', ['id' => $id], []));
            })->setName('keamanan.piezometer.add');
        });

    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
