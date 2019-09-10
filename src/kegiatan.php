<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/kegiatan', function() use ($loggedinMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        // $user = $request->getAttribute('user');

        return $this->view->render($response, 'kegiatan.html');
    })->setName('kegiatan');

})->add($loggedinMiddleware);
