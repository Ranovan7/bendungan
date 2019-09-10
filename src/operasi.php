<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/operasi', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        // $user = $request->getAttribute('user');

        return $this->view->render($response, 'admin.html');
    })->setName('operasi');

    $this->group('/add', function() {

        $this->get('/curahhujan', function(Request $request, Response $response, $args) {
            // get user yg didapat dari middleware
            // $user = $request->getAttribute('user');

            return $this->view->render($response, 'operasi/add.html');
        })->setName('operasi.add');

    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
