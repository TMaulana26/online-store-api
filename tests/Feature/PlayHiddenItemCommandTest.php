<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;

test('it displays active session information and grid when running without options', function () {
    Cache::put('hidden_item_session', [
        'player' => [4, 1],
        'item' => [2, 5],
    ]);

    $this->artisan('play:hidden-item')
        ->assertExitCode(0)
        ->expectsOutput('Active Game Session:')
        ->expectsOutput("- Player 'X' Position: (Row 4, Col 1)")
        ->expectsOutput("- Hidden Item '$' Position: (Row 2, Col 5)")
        ->expectsOutput('########')
        ->expectsOutput('#......#')
        ->expectsOutput('#.###$.#')
        ->expectsOutput('#...#.##')
        ->expectsOutput('#X#....#')
        ->expectsOutput('########');
});

test('it defaults unspecified movement parameters to zero', function () {
    Cache::put('hidden_item_session', [
        'player' => [4, 1],
        'item' => [2, 5],
    ]);

    // Only specify --A => B, C, D should default to 0
    $this->artisan('play:hidden-item', ['--A' => 3])
        ->assertExitCode(0)
        ->expectsOutput('Player moved to: (Row 1, Col 1)')
        ->expectsOutput('########')
        ->expectsOutput('#X.....#') // player is at (1, 1)
        ->expectsOutput('#.###$.#')
        ->expectsOutput('#...#.##')
        ->expectsOutput('#.#....#')
        ->expectsOutput('########');

    $session = Cache::get('hidden_item_session');
    expect($session['player'])->toBe([1, 1]);
});

test('it updates player position on non-winning valid move with positive values', function () {
    Cache::put('hidden_item_session', [
        'player' => [4, 1],
        'item' => [2, 5],
    ]);

    $this->artisan('play:hidden-item', ['--A' => 3, '--B' => 4, '--C' => 3, '--D' => 2])
        ->assertExitCode(0)
        ->expectsOutput('Player moved to: (Row 4, Col 3)')
        ->expectsOutput('########')
        ->expectsOutput('#......#')
        ->expectsOutput('#.###$.#')
        ->expectsOutput('#...#.##')
        ->expectsOutput('#.#X...#')
        ->expectsOutput('########');

    $session = Cache::get('hidden_item_session');
    expect($session['player'])->toBe([4, 3]);
    expect($session['item'])->toBe([2, 5]);
});

test('it accepts moves with zero steps', function () {
    Cache::put('hidden_item_session', [
        'player' => [4, 1],
        'item' => [2, 5],
    ]);

    $this->artisan('play:hidden-item', ['--A' => 3, '--B' => 4, '--C' => 0, '--D' => 0])
        ->assertExitCode(0)
        ->expectsOutput('Player moved to: (Row 1, Col 5)')
        ->expectsOutput('########')
        ->expectsOutput('#....X.#')
        ->expectsOutput('#.###$.#')
        ->expectsOutput('#...#.##')
        ->expectsOutput('#.#....#')
        ->expectsOutput('########');

    $session = Cache::get('hidden_item_session');
    expect($session['player'])->toBe([1, 5]);
});

test('it blocks moves with negative steps', function () {
    Cache::put('hidden_item_session', [
        'player' => [4, 1],
        'item' => [2, 5],
    ]);

    $this->artisan('play:hidden-item', ['--A' => -1])
        ->assertExitCode(1)
        ->expectsOutput('Steps A, B, C, and D must be non-negative integers.');
});

test('it completes game and clears session when player lands on hidden item coordinate', function () {
    Cache::put('hidden_item_session', [
        'player' => [4, 1],
        'item' => [4, 3],
    ]);

    $this->artisan('play:hidden-item', ['--A' => 3, '--B' => 4, '--C' => 3, '--D' => 2])
        ->assertExitCode(0)
        ->expectsOutput('🎉 Congratulations! You found the hidden item at (Row 4, Col 3)!')
        ->expectsOutput('########')
        ->expectsOutput('#......#')
        ->expectsOutput('#.###..#')
        ->expectsOutput('#...#.##')
        ->expectsOutput('#.#X...#')
        ->expectsOutput('########');

    expect(Cache::has('hidden_item_session'))->toBeFalse();
});

test('it resets the game session and places player back at the start', function () {
    Cache::put('hidden_item_session', [
        'player' => [2, 5],
        'item' => [2, 5],
    ]);

    $this->artisan('play:hidden-item', ['--reset' => true])
        ->assertExitCode(0)
        ->expectsOutput('Game session reset! A new hidden item has been placed.');

    $session = Cache::get('hidden_item_session');
    expect($session['player'])->toBe([4, 1]);
    expect(Cache::has('hidden_item_session'))->toBeTrue();
});

test('it detects blocked path for invalid moves', function () {
    Cache::put('hidden_item_session', [
        'player' => [4, 1],
        'item' => [2, 5],
    ]);

    $this->artisan('play:hidden-item', ['--A' => 1, '--B' => 1, '--C' => 1, '--D' => 1])
        ->assertExitCode(1)
        ->expectsOutput('The path from (Row 4, Col 1) with steps (A=1, B=1, C=1, D=1) is blocked by obstacles!');
});
