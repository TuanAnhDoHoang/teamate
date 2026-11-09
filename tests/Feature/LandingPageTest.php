<?php

test('landing page can be rendered with rbac content', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
    $response->assertSee('Team project management: the right people, the right access');
    $response->assertSee('Project Storage');
    $response->assertSee('Secure Permissions');
    $response->assertSee('Decentralized Backup');
    $response->assertSee('Development Team');
    $response->assertSee('Review Team');
    $response->assertSee('Client');
    $response->assertSee('Create a team');
    $response->assertSee('Create a project');
    $response->assertSee('Invite members to start working');
});

test('login and register pages render with teamate theme branding', function () {
    $login = $this->get(route('login'));
    $login->assertOk();
    $login->assertSee('Welcome back');
    $login->assertSee('Teamate');

    $register = $this->get(route('register'));
    $register->assertOk();
    $register->assertSee('Create your account');
    $register->assertSee('Teamate');
});
