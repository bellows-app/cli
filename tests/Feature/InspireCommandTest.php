<?php

it('inspires artisans', function () {
    $this->artisan('deploy')->assertExitCode(0);
});
