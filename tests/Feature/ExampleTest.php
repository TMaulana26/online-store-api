<?php

test('the application returns a redirect to docs', function () {
    $response = $this->get('/');

    $response->assertRedirect('/docs/api#/');
});
