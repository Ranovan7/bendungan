<?php

use Slim\Http\Request;
use Slim\Http\Response;

// Curah Hujan

$app->group('/embung', function() {

    $this->get('[/]', function(Request $request, Response $response, $args) {

        return $this->view->render($response, 'embung/index.html');
    })->setName('embung');

    $this->group('/{id}', function() {

        $this->get('[/]', function(Request $request, Response $response, $args) {
            $name = $lokasi_id = $request->getAttribute('id');
            return $this->view->render($response, 'embung/show.html', [
                'name' => $name
            ]);
        })->setName('embung.show');

    });

});
