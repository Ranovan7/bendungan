<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Curah Hujan

$app->group('/bendungan', function() {

    $this->get('[/]', function(Request $request, Response $response, $args) {

        return $this->view->render($response, 'bendungan/index.html');
    })->setName('bendungan');

    $this->group('/{id}', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            $name = $lokasi_id = $request->getAttribute('id');
            return $this->view->render($response, 'bendungan/show.html', [
                'name' => $name
            ]);
        })->setName('bendungan.show');

    });

});
