<?php

use Slim\Http\Request;
use Slim\Http\Response;

// API

$app->group('/bendungan', function() use ($loggedinMiddleware, $adminAuthorizationMiddleware) {

    $this->get('/periodic', function(Request $request, Response $response, $args) {
        $sampling = $request->getParam('sampling', date("Y-m-d"));

        $daily = $this->db->query("SELECT * FROM periodik_daily
                                    WHERE sampling='{$sampling} 00:00:00'")->fetchAll();
        $piezo = $this->db->query("SELECT * FROM periodik_piezo
                                    WHERE sampling='{$sampling} 00:00:00'")->fetchAll();
        $vnotch = $this->db->query("SELECT * FROM periodik_vnotch
                                    WHERE sampling='{$sampling} 00:00:00'")->fetchAll();
        $tma = $this->db->query("SELECT * FROM tma
                                    WHERE sampling BETWEEN '{$sampling} 00:00:00' AND '{$sampling} 23:00:00'")->fetchAll();
        $waduk = $this->db->query("SELECT * FROM waduk")->fetchAll();
        $data = [];
        foreach ($waduk as $w) {
            $data[$w['id']] = [
                'nama' => $w['nama'],
                'sampling' => "{$sampling} 00:00:00",
                'tma6' => NULL,
                'volume6' => NULL,
                'tma12' => NULL,
                'volume12' => NULL,
                'tma18' => NULL,
                'volume18' => NULL,
                'a1' => NULL,
                'a2' => NULL,
                'a3' => NULL,
                'a4' => NULL,
                'a5' => NULL,
                'b1' => NULL,
                'b2' => NULL,
                'b3' => NULL,
                'b4' => NULL,
                'b5' => NULL,
                'c1' => NULL,
                'c2' => NULL,
                'c3' => NULL,
                'c4' => NULL,
                'c5' => NULL,
                'vnotch_q1' => NULL,
                'vnotch_q2' => NULL,
                'vnotch_q3' => NULL,
                'vnotch_tin1' => NULL,
                'vnotch_tin2' => NULL,
                'vnotch_tin3' => NULL,
                'curahhujan' => NULL,
                'inflow_q' => NULL,
                'inflow_v' => NULL,
                'intake_q' => NULL,
                'intake_v' => NULL,
                'spillway_q' => NULL,
                'spillway_v' => NULL,
            ];
        }
        foreach ($tma as $t) {
            $hour = date('H', strtotime($t['sampling']));
            if ($hour == '6'){
                $data[$t['waduk_id']]['tma7'] = $t['manual'];
                $data[$t['waduk_id']]['vol7'] = $t['volume'];
            } else if ($hour == '12') {
                $data[$t['waduk_id']]['tma12'] = $t['manual'];
                $data[$t['waduk_id']]['vol12'] = $t['volume'];
            } else {
                $data[$t['waduk_id']]['tma18'] = $t['manual'];
                $data[$t['waduk_id']]['vol18'] = $t['volume'];
            }
        }
        foreach ($vnotch as $v) {
            $data[$v['waduk_id']]['vnotch_q1'] = $v['vn1_debit'];
            $data[$v['waduk_id']]['vnotch_tin1'] = $v['vn1_tma'];
            $data[$v['waduk_id']]['vnotch_q2'] = $v['vn2_debit'];
            $data[$v['waduk_id']]['vnotch_tin2'] = $v['vn2_tma'];
            $data[$v['waduk_id']]['vnotch_q3'] = $v['vn3_debit'];
            $data[$v['waduk_id']]['vnotch_tin3'] = $v['vn3_tma'];
        }
        foreach ($piezo as $p) {
            $data[$p['waduk_id']]['a1'] = $p['p1a'];
            $data[$p['waduk_id']]['b1'] = $p['p1b'];
            $data[$p['waduk_id']]['c1'] = $p['p1c'];
            $data[$p['waduk_id']]['a2'] = $p['p2a'];
            $data[$p['waduk_id']]['b2'] = $p['p2b'];
            $data[$p['waduk_id']]['c2'] = $p['p2c'];
            $data[$p['waduk_id']]['a3'] = $p['p3a'];
            $data[$p['waduk_id']]['b3'] = $p['p3b'];
            $data[$p['waduk_id']]['c3'] = $p['p3c'];
            $data[$p['waduk_id']]['a4'] = $p['p4a'];
            $data[$p['waduk_id']]['b4'] = $p['p4b'];
            $data[$p['waduk_id']]['c4'] = $p['p4c'];
            $data[$p['waduk_id']]['a5'] = $p['p5a'];
            $data[$p['waduk_id']]['b5'] = $p['p5b'];
            $data[$p['waduk_id']]['c5'] = $p['p5c'];
        }
        foreach ($daily as $d) {
            $data[$d['waduk_id']]['curahhujan'] = $v['curahhujan'];
            $data[$d['waduk_id']]['inflow_q'] = $v['inflow_deb'];
            $data[$d['waduk_id']]['inflow_v'] = $v['inflow_vol'];
            $data[$d['waduk_id']]['intake_q'] = $v['intake_deb'];
            $data[$d['waduk_id']]['intake_v'] = $v['intake_vol'];
            $data[$d['waduk_id']]['spillway_q'] = $v['spillway_deb'];
            $data[$d['waduk_id']]['spillway_v'] = $v['spillway_vol'];
        }

        $result = [];
        foreach ($data as $d) {
            $result[] = $d;
        }

        return $response->withJson($result, 200, JSON_PRETTY_PRINT);
    })->setName('api');

});
