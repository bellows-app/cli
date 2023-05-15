<?php

use Illuminate\Support\Str;

it('can launch a site', function () {
    command(base_path('bellows launch'))
        ->fromDir(base_path('../bellows-tester'))
        ->question('Which server would you like to use', 'bellows-testing')
        ->question('App Name', 'Bellows Test')
        ->question('Domain', Str::random() . '.linkleapapp.com')
        ->question('Isolated User', 'bellows_' . strtolower(Str::random(6)), 30)
        ->question('Repository')
        ->question('Repository Branch')
        ->question('Secure site')
        ->confirm('Continue with defaults')
        ->confirm('Update DNS record')
        ->waitForPlugin('Ably')
        ->selectAccount('joe')
        ->deny('Create new app')
        ->question('Which app do you want to use', 'Forge It Test')
        ->question('Which key do you want to use', 'Subscribe only')
        ->waitForPlugin('BugsnagJS')
        ->selectAccount('joe')
        ->deny('Create Bugsnag JS Project')
        ->question('Select a Bugsnag project', 'Forge It Test')
        ->waitForPlugin('BugsnagPHP')
        ->selectAccount('joe')
        ->deny('Create Bugsnag PHP Project')
        ->question('Select a Bugsnag project', 'Forge It Test')
        ->confirm('Enable DigitalOceanDatabase')
        ->selectAccount('joe')
        ->question('Database', 'forge_it_test')
        ->question('Database User', 'forge_it_test')
        ->confirm('User already exists, do you want to continue')
        ->confirm('Database already exists, do you want to continue')
        ->confirm('Do you want to enable Fathom Analytics')
        ->selectAccount('joe')
        ->deny('Create new Fathom Analytics site')
        ->question('Choose a site', 'Forge It Test')
        ->question('Which region is your Mailgun account in', 'US')
        ->selectAccount('joe')
        ->deny('Create a new domain')
        ->question('Which domain do you want to use', 'mail.mg5.joe.codes')
        ->waitForPlugin('Pusher')
        ->selectAccount('joe')
        ->question('Which app do you want to use', 'dm2-development')
        ->confirm('Do you want to set up any queue workers')
        ->question('Connection', 'database')
        ->question('Queue', 'default')
        ->confirm('Defaults look ok')
        ->deny('Do you want to add another queue worker')
        ->confirm('Enable quick deploy')
        ->deny('Do you want to add any security rules')
        ->waitFor('Site', 60)
        ->waitFor('Repository', 60)
        ->waitFor('Environment Variables', 60)
        ->waitFor('The following environment variables are in your local .env file but not in your remote .env file')
        ->deny('Would you like to add any of them')
        ->waitFor('Deploy Script', 60)
        ->waitFor('Daemons', 60)
        ->waitFor('Workers', 60)
        ->waitFor('Scheduled Jobs', 60)
        ->waitFor('Wrapping Up', 60)
        ->waitFor('Summary', 60)
        ->waitFor('Site created successfully', 60)
        ->deny('Open site in Forge')
        ->exec();
})->skip();

// it('can launch a load balanced site', function () {
//     command(base_path('bellows launch'))
//         ->fromDir(base_path('../bellows-tester'))
//         ->question('Which server would you like to use', 'bellows-load-balancer')
//         ->question('App Name', 'Bellows Test')
//         ->question('Domain', Str::random() . '.linkleapapp.com')
//         ->question('Load balancing method', 'Round Robin')
//         ->question('Select servers', 'bellows-testing,bellows-testing-2')
//         ->question('Weight')
//         ->question('Port')
//         ->question('Backup')
//         ->question('Weight')
//         ->question('Port')
//         ->question('Backup')
//         ->question('Isolated User', 'dusky', 30)
//         ->question('Repository')
//         ->question('Repository Branch')
//         ->question('Secure site')
//         // ->question('Select PHP version', '8.2')
//         // ->waitFor('something', 60 * 5)
//         ->confirm('Continue with defaults')
//         ->confirm('Update DNS record')
//         ->waitForPlugin('Ably')
//         ->selectAccount('joe')
//         ->deny('Create new app')
//         ->question('Which app do you want to use', 'Forge It Test')
//         ->question('Which key do you want to use', 'Subscribe only')
//         ->waitForPlugin('BugsnagJS')
//         ->selectAccount('joe')
//         ->deny('Create Bugsnag JS Project')
//         ->question('Select a Bugsnag project', 'Forge It Test')
//         ->waitForPlugin('BugsnagPHP')
//         ->selectAccount('joe')
//         ->deny('Create Bugsnag PHP Project')
//         ->question('Select a Bugsnag project', 'Forge It Test')
//         ->confirm('Enable DigitalOceanDatabase')
//         ->selectAccount('joe')
//         ->question('Database', 'forge_it_test')
//         ->question('Database User', 'forge_it_test')
//         ->confirm('User already exists, do you want to continue')
//         ->confirm('Database already exists, do you want to continue')
//         ->confirm('Do you want to enable Fathom Analytics')
//         ->selectAccount('joe')
//         ->deny('Create new Fathom Analytics site')
//         ->question('Choose a site', 'Forge It Test')
//         ->question('Which region is your Mailgun account in', 'US')
//         ->selectAccount('joe')
//         ->deny('Create a new domain')
//         ->question('Which domain do you want to use', 'mail.mg5.joe.codes')
//         ->waitForPlugin('Pusher')
//         ->selectAccount('joe')
//         ->question('Which app do you want to use', 'dm2-development')
//         ->confirm('Do you want to set up any queue workers')
//         ->question('Connection', 'database')
//         ->question('Queue', 'default')
//         ->confirm('Defaults look ok')
//         ->deny('Do you want to add another queue worker')
//         ->confirm('Enable quick deploy')
//         ->deny('Do you want to add any security rules')
//         ->waitFor('Site', 60)
//         ->waitFor('Repository', 60)
//         ->waitFor('Environment Variables', 60)
//         ->waitFor('The following environment variables are in your local .env file but not in your remote .env file')
//         ->deny('Would you like to add any of them')
//         ->waitFor('Deploy Script', 60)
//         ->waitFor('Daemons', 60)
//         ->waitFor('Workers', 60)
//         ->waitFor('Scheduled Jobs', 60)
//         ->waitFor('Wrapping Up', 60)
//         ->waitFor('Summary', 60)
//         ->waitFor('Site created successfully', 60)
//         ->waitFor('Site', 60)
//         ->waitFor('Repository', 60)
//         ->waitFor('Environment Variables', 60)
//         ->waitFor('The following environment variables are in your local .env file but not in your remote .env file')
//         ->deny('Would you like to add any of them')
//         ->waitFor('Deploy Script', 60)
//         ->waitFor('Daemons', 60)
//         ->waitFor('Workers', 60)
//         ->waitFor('Scheduled Jobs', 60)
//         ->waitFor('Wrapping Up', 60)
//         ->waitFor('Summary', 60)
//         ->waitFor('Site created successfully', 60)
//         ->deny('Open site in Forge')
//         ->exec();
// })->skip();

it('can launch link leap to an existing load balancer', function () {
    command(base_path('bellows launch'))
        ->fromDir(base_path('../link-leap'))
        ->question('Which server would you like to use', 'bellows-load-balancer')
        ->question('App Name', 'Bellows Test')
        ->question('Domain', 'balanced.linkleapapp.com')
        ->confirm('Load balancer already exists, use it')
        ->question('Isolated User', 'dusky', 30)
        ->question('Repository')
        ->question('Repository Branch')
        ->question('Secure site')
        // TODO: Check on this and make sure we're still handling!
        // ->question('Select PHP version', '8.2')
        // ->waitFor('something', 60 * 5)
        ->confirm('Continue with defaults')
        ->waitForPlugin('BugsnagJS')
        ->selectAccount('joe')
        ->deny('Create Bugsnag JS Project')
        ->question('Select a Bugsnag project', 'Forge It Test')
        ->waitForPlugin('BugsnagPHP')
        ->selectAccount('joe')
        ->deny('Create Bugsnag PHP Project')
        ->question('Select a Bugsnag project', 'Forge It Test')
        ->confirm('Enable DigitalOceanDatabase')
        ->selectAccount('joe')
        ->question('Database', 'forge_it_test')
        ->question('Database User', 'forge_it_test')
        ->confirm('User already exists, do you want to continue')
        ->confirm('Database already exists, do you want to continue')
        ->confirm('Do you want to enable Fathom Analytics')
        ->selectAccount('joe')
        ->deny('Create new Fathom Analytics site')
        ->question('Choose a site', 'Forge It Test')
        ->waitForPlugin('Postmark')
        ->selectAccount('joe')
        ->deny('Create new Postmark server')
        ->question('Choose a Postmark server', 'Forge It Test')
        ->deny('Create new Postmark domain')
        ->question('Choose a Postmark sender domain', 'mail.forgeittest.joe.codes')
        ->question('Which Postmark message stream', 'inbound')
        ->question('From email')
        ->waitForPlugin('Pusher')
        ->selectAccount('joe')
        ->question('Which app do you want to use', 'dm2-development')
        ->confirm('Do you want to set up any queue workers')
        ->question('Connection', 'database')
        ->question('Queue', 'default')
        ->confirm('Defaults look ok')
        ->deny('Do you want to add another queue worker')
        ->confirm('Enable quick deploy')
        ->deny('Do you want to add any security rules')
        ->waitFor('Site', 60)
        ->waitFor('Repository', 60)
        ->waitFor('Environment Variables', 60)
        // ->waitFor('The following environment variables are in your local .env file but not in your remote .env file')
        // ->deny('Would you like to add any of them')
        ->waitFor('Deploy Script', 60)
        ->waitFor('Daemons', 60)
        ->waitFor('Workers', 60)
        ->waitFor('Scheduled Jobs', 60)
        ->waitFor('Wrapping Up', 60)
        ->waitFor('Summary', 60)
        ->waitFor('Site created successfully', 60)
        ->waitFor('Site', 60)
        ->waitFor('Repository', 60)
        ->waitFor('Environment Variables', 60)
        // ->waitFor('The following environment variables are in your local .env file but not in your remote .env file')
        // ->deny('Would you like to add any of them')
        ->waitFor('Deploy Script', 60)
        ->waitFor('Daemons', 60)
        ->waitFor('Workers', 60)
        ->waitFor('Scheduled Jobs', 60)
        ->waitFor('Wrapping Up', 60)
        ->waitFor('Summary', 60)
        ->waitFor('Site created successfully', 60)
        ->exec();
})->skip();

it('can launch link leap load balanced', function () {
    command(base_path('bellows launch'))
        ->fromDir(base_path('../link-leap'))
        ->question('Which server would you like to use', 'bellows-load-balancer')
        ->question('App Name', 'Bellows Test')
        ->question('Domain', Str::random() . '.linkleapapp.com')
        ->question('Load balancing method', 'Round Robin')
        ->question('Select servers', 'bellows-testing,bellows-testing-2')
        ->question('Weight')
        ->question('Port')
        ->question('Backup')
        ->question('Weight')
        ->question('Port')
        ->question('Backup')
        ->question('Isolated User', 'dusky', 30)
        ->question('Repository')
        ->question('Repository Branch')
        ->question('Secure site')
        // TODO: Check on this and make sure we're still handling!
        // ->question('Select PHP version', '8.2')
        // ->waitFor('something', 60 * 5)
        ->confirm('Continue with defaults')
        ->confirm('Update DNS record')
        ->waitForPlugin('BugsnagJS')
        ->selectAccount('joe')
        ->deny('Create Bugsnag JS Project')
        ->question('Select a Bugsnag project', 'Forge It Test')
        ->waitForPlugin('BugsnagPHP')
        ->selectAccount('joe')
        ->deny('Create Bugsnag PHP Project')
        ->question('Select a Bugsnag project', 'Forge It Test')
        ->confirm('Enable DigitalOceanDatabase')
        ->selectAccount('joe')
        ->question('Database', 'forge_it_test')
        ->question('Database User', 'forge_it_test')
        ->confirm('User already exists, do you want to continue')
        ->confirm('Database already exists, do you want to continue')
        ->confirm('Do you want to enable Fathom Analytics')
        ->selectAccount('joe')
        ->deny('Create new Fathom Analytics site')
        ->question('Choose a site', 'Forge It Test')
        ->waitForPlugin('Postmark')
        ->selectAccount('joe')
        ->deny('Create new Postmark server')
        ->question('Choose a Postmark server', 'Forge It Test')
        ->deny('Create new Postmark domain')
        ->question('Choose a Postmark sender domain', 'mail.forgeittest.joe.codes')
        ->question('Which Postmark message stream', 'inbound')
        ->question('From email')
        ->waitForPlugin('Pusher')
        ->selectAccount('joe')
        ->question('Which app do you want to use', 'dm2-development')
        ->confirm('Do you want to set up any queue workers')
        ->question('Connection', 'database')
        ->question('Queue', 'default')
        ->confirm('Defaults look ok')
        ->deny('Do you want to add another queue worker')
        ->confirm('Enable quick deploy')
        ->deny('Do you want to add any security rules')
        ->waitFor('Site', 60)
        ->waitFor('Repository', 60)
        ->waitFor('Environment Variables', 60)
        // ->waitFor('The following environment variables are in your local .env file but not in your remote .env file')
        // ->deny('Would you like to add any of them')
        ->waitFor('Deploy Script', 60)
        ->waitFor('Daemons', 60)
        ->waitFor('Workers', 60)
        ->waitFor('Scheduled Jobs', 60)
        ->waitFor('Wrapping Up', 60)
        ->waitFor('Summary', 60)
        ->waitFor('Site created successfully', 60)
        ->waitFor('Site', 60)
        ->waitFor('Repository', 60)
        ->waitFor('Environment Variables', 60)
        // ->waitFor('The following environment variables are in your local .env file but not in your remote .env file')
        // ->deny('Would you like to add any of them')
        ->waitFor('Deploy Script', 60)
        ->waitFor('Daemons', 60)
        ->waitFor('Workers', 60)
        ->waitFor('Scheduled Jobs', 60)
        ->waitFor('Wrapping Up', 60)
        ->waitFor('Summary', 60)
        ->waitFor('Site created successfully', 60)
        ->exec();
})->skip();
