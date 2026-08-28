<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('banned users can not authenticate', function () {
    $user = User::factory()->create(['type' => User::TYPE_BANNED]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('blacklisted users can not authenticate', function () {
    $user = User::factory()->create(['type' => User::TYPE_BLACKLISTED]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('banning a user invalidates their previous password', function () {
    $user = User::factory()->create();
    $original = $user->password;

    app(\App\Http\Controllers\HeadcountController::class)->banUser((string) $user->id);

    $user->refresh();
    expect($user->type)->toBe(User::TYPE_BANNED);
    // La contraseña ya no es la anterior y tampoco un valor fijo reutilizable.
    expect($user->password)->not->toBe($original);
    expect(\Illuminate\Support\Facades\Hash::check('lindltaylor7@gmail.com', $user->password))->toBeFalse();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});