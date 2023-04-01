<?php

it('can run a command line script automatically', function () {
    command('./bellows launch')
        ->waitFor('Which server would you like to use')
        ->type('bellows-testing')
        ->waitFor('App Name')
        ->type('Bellows Test')
        ->waitFor('Domain')
        ->type('dusky.linkleapapp.com')
        ->waitFor('Isolated User', 30)
        ->enter()
        ->waitFor('Repository')
        ->enter()
        ->waitFor('Repository Branch')
        ->enter()
        ->waitFor('Secure site')
        ->enter()
        ->exec();
})->skip();
