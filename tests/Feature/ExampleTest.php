<?php

test('the application redirects visitors to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/clientes');
});
