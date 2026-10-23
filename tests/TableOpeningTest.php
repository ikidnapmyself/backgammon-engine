<?php

use BackgammonNetwork\Engine\Dice\SequenceDice;
use BackgammonNetwork\Engine\Table;

it('starts idle with the standard board', function () {
    $state = Table::create(tableConfig(), ['Ann', 'Bob']);

    expect($state['phase'])->toBe('idle')
        ->and($state['gameNumber'])->toBe(0)
        ->and($state['board']['points'][23])->toBe(2)
        ->and($state['cube'])->toBe(['value' => 1, 'owner' => null]);
});

it('lets the higher opening die move first with both dice', function () {
    $state = openedTable([3, 5]);

    expect($state['phase'])->toBe('move')
        ->and($state['toAct'])->toBe(1)
        ->and($state['dice'])->toBe([3, 5])
        ->and($state['log'][0]['kind'])->toBe('opening')
        ->and($state['log'][0]['text'])->toBe('Ann rolls 3, Bob rolls 5');
});

it('rerolls tied opening dice', function () {
    $state = openedTable([4, 4, 6, 2]);

    expect($state['toAct'])->toBe(0)->and(count($state['log']))->toBe(2);
});

it('refuses to start while a game is live', function () {
    Table::start(openedTable([3, 5]), new SequenceDice([1, 2]));
})->throws(DomainException::class, 'A game is already in progress');

it('starts the next game on a fresh board after one completes', function () {
    $state = [...openedTable([3, 5]), 'phase' => 'complete'];
    $next = Table::start($state, new SequenceDice([6, 1]));

    expect($next['gameNumber'])->toBe(2)->and($next['log'])->toHaveCount(1);
});
