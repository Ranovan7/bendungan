<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Manage Operasi Bendungan

$app->group('/admin', function() use ($loggedinMiddleware, $petugasAuthorizationMiddleware) {

    $this->get('[/]', function(Request $request, Response $response, $args) {
        // get user yg didapat dari middleware
        // $user = $request->getAttribute('user');

        return $this->view->render($response, 'admin.html');
    })->setName('admin');

    $this->group('/add', function() {

        $this->get('/curahhujan', function(Request $request, Response $response, $args) {
            // get user yg didapat dari middleware
            // $user = $request->getAttribute('user');

            return $this->view->render($response, 'admin/add.html');
        })->setName('admin.add');

    })->add($petugasAuthorizationMiddleware);

})->add($loggedinMiddleware);
