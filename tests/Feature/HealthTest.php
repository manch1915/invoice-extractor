<?php

declare(strict_types=1);

it('responds on the health endpoint', function () {
    $this->get('/up')->assertOk();
});
