<?php

use BackgammonNetwork\Engine\Dice\SequenceDice;
use BackgammonNetwork\Engine\Table;

function lastCheckerHome(array $loser, int $cube = 1): array
{
    $state = withBoard(openedTable([6, 5]), boardOf([0 => [1 => 1, 'off' => 14], 1 => $loser]));
    $state = [...$state, 'toAct' => 0, 'phase' => 'move', 'dice' => [1, 2], 'cube' => ['value' => $cube, 'owner' => null]];

    return Table::apply($state, 0, ['type' => 'move', 'steps' => Table::legal($state, 0)['plays'][0]], new SequenceDice([]));
}

it('scores a single game when the loser has borne a checker off', function () {
    $done = lastCheckerHome(['off' => 1, 6 => 14]);

    expect($done['result'])->toBe(['winner' => 0, 'kind' => 'single', 'how' => 'played', 'points' => 1])
        ->and($done['phase'])->toBe('complete')
        ->and($done['toAct'])->toBeNull()
        ->and($done['dice'])->toBe([])
        ->and(end($done['log']))->toMatchArray(['kind' => 'win', 'seat' => 0, 'value' => 1, 'text' => 'Ann wins 1 point']);
});

it('scores a gammon when the loser has nothing off and is clear of the winner home board', function () {
    $done = lastCheckerHome([13 => 15]);

    expect($done['result'])->toMatchArray(['kind' => 'gammon', 'points' => 2])
        ->and(end($done['log'])['text'])->toBe('Ann wins 2 points (gammon)');
});

it('scores a backgammon with a checker on the bar', function () {
    expect(lastCheckerHome(['bar' => 1, 13 => 14])['result'])->toMatchArray(['kind' => 'backgammon', 'points' => 3]);
});

it('scores a backgammon with a checker in the winner home board', function () {
    expect(lastCheckerHome([20 => 1, 13 => 14])['result'])->toMatchArray(['kind' => 'backgammon', 'points' => 3]);
});

it('multiplies by the cube value and has no Jacoby rule', function () {
    $done = lastCheckerHome([13 => 15], 4);

    expect($done['result']['points'])->toBe(8)
        ->and(array_keys($done['config']))->toBe(['cube', 'length', 'crawford']);
});

it('ends the game for the other seat on a forfeit', function () {
    $state = withBoard(openedTable([6, 5]), boardOf([0 => [24 => 15], 1 => [13 => 15]]));

    $done = Table::forfeit($state, 1);

    expect($done['result'])->toBe(['winner' => 0, 'kind' => 'gammon', 'how' => 'forfeit', 'points' => 2])
        ->and($done['log'][array_key_last($done['log']) - 1])->toMatchArray(['kind' => 'forfeit', 'seat' => 1]);
});

it('refuses a forfeit when no game is live', function () {
    Table::forfeit(Table::create(tableConfig(), ['Ann', 'Bob']), 0);
})->throws(DomainException::class, 'No game in progress');
