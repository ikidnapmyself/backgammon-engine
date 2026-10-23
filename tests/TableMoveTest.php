<?php

use BackgammonNetwork\Engine\Board;
use BackgammonNetwork\Engine\Dice\SequenceDice;
use BackgammonNetwork\Engine\Table;

it('plays a legal move and passes the turn to the other seat', function () {
    $state = openedTable([6, 5, 3, 4]);
    $seat = $state['toAct'];
    $plays = Table::legal($state, $seat)['plays'];

    $next = Table::apply($state, $seat, ['type' => 'move', 'steps' => $plays[0]], new SequenceDice([]));

    expect($next['toAct'])->toBe(1 - $seat)
        ->and($next['phase'])->toBe('decide')
        ->and($next['dice'])->toBe([])
        ->and($next['log'][array_key_last($next['log'])]['kind'])->toBe('move');
});

it('rolls on request after the cube decision', function () {
    $state = openedTable([6, 5]);
    $seat = $state['toAct'];
    $played = Table::apply($state, $seat, ['type' => 'move', 'steps' => Table::legal($state, $seat)['plays'][0]], new SequenceDice([]));

    $rolled = Table::apply($played, 1 - $seat, ['type' => 'roll'], new SequenceDice([2, 1]));

    expect($rolled['phase'])->toBe('move')->and($rolled['dice'])->toBe([2, 1]);
});

it('rolls automatically when the cube is off', function () {
    $state = openedTable([6, 5], ['cube' => false]);
    $seat = $state['toAct'];

    $next = Table::apply($state, $seat, ['type' => 'move', 'steps' => Table::legal($state, $seat)['plays'][0]], new SequenceDice([2, 1]));

    expect($next['phase'])->toBe('move')->and($next['toAct'])->toBe(1 - $seat)->and($next['dice'])->toBe([2, 1]);
});

it('accepts equivalent step orders', function () {
    $state = openedTable([3, 1]);
    $steps = array_reverse(Table::legal($state, 0)['plays'][0]);

    $next = Table::apply($state, 0, ['type' => 'move', 'steps' => $steps], new SequenceDice([]));

    expect($next['phase'])->toBe('decide');
});

it('rejects a play that is not legal', function () {
    $state = openedTable([6, 5]);

    Table::apply($state, $state['toAct'], ['type' => 'move', 'steps' => [['from' => 0, 'to' => 1]]], new SequenceDice([]));
})->throws(DomainException::class, 'That play isn\'t allowed');

it('rejects steps that point off the board', function () {
    $state = openedTable([6, 5]);

    Table::apply($state, $state['toAct'], ['type' => 'move', 'steps' => [['from' => 99, 'to' => 'off']]], new SequenceDice([]));
})->throws(DomainException::class, 'That play isn\'t allowed');

it('rejects a play that leaves dice unused', function () {
    $state = openedTable([3, 1]);
    $steps = [Table::legal($state, 0)['plays'][0][0]];

    Table::apply($state, 0, ['type' => 'move', 'steps' => $steps], new SequenceDice([]));
})->throws(DomainException::class);

it('rejects an action from the seat that is not to act', function () {
    $state = openedTable([6, 5]);

    Table::apply($state, 1 - $state['toAct'], ['type' => 'roll'], new SequenceDice([]));
})->throws(DomainException::class, 'It\'s not your turn');

it('rejects moving before a roll', function () {
    $state = [...openedTable([6, 5]), 'phase' => 'decide'];

    Table::apply($state, $state['toAct'], ['type' => 'move', 'steps' => []], new SequenceDice([]));
})->throws(DomainException::class, 'You need to roll first');

it('rejects rolling in the move phase', function () {
    $state = openedTable([6, 5]);

    Table::apply($state, $state['toAct'], ['type' => 'roll'], new SequenceDice([]));
})->throws(DomainException::class, 'You can\'t roll now');

it('passes automatically when the roll cannot be played', function () {
    $points = array_fill(0, 24, 0);
    foreach (range(18, 23) as $i) {
        $points[$i] = -2;
    }
    $points[12] = 14;
    $points[3] = -3;
    $state = [...openedTable([6, 5]), 'board' => (new Board($points, [1, 0], [0, 0]))->toArray(), 'toAct' => 0, 'phase' => 'decide', 'dice' => []];

    $next = Table::apply($state, 0, ['type' => 'roll'], new SequenceDice([1, 2]));

    expect(array_column($next['log'], 'kind'))->toBe(['opening', 'roll', 'pass'])
        ->and($next['toAct'])->toBe(1)
        ->and($next['phase'])->toBe('decide');
});

it('rejects any action once the game is complete', function () {
    $state = Table::forfeit(openedTable([6, 1, 3, 1]), 0);

    expect(fn () => Table::apply($state, 1, ['type' => 'roll'], new SequenceDice([])))->toThrow(DomainException::class, 'No game in progress');
});

it('rejects an unknown action type', function () {
    $state = openedTable([6, 1, 3, 1]);

    expect(fn () => Table::apply($state, 0, ['type' => 'fly'], new SequenceDice([])))->toThrow(DomainException::class, 'Unknown action')
        ->and(fn () => Table::apply($state, 0, [], new SequenceDice([])))->toThrow(DomainException::class, 'Unknown action');
});

it('rejects malformed steps', function (mixed $steps) {
    $state = openedTable([6, 1, 3, 1]);

    expect(fn () => Table::apply($state, 0, ['type' => 'move', 'steps' => $steps], new SequenceDice([])))->toThrow(DomainException::class);
})->with([
    'not a list' => ['nope'],
    'missing keys' => [[['from' => 5]]],
    'bad index' => [[['from' => 24, 'to' => 20]]],
    'string point' => [[['from' => 'x', 'to' => 3]]],
]);

it('does not mutate the state it was given', function () {
    $state = openedTable([6, 1, 3, 1], ['cube' => false]);
    $before = $state;
    $plays = Table::legal($state, 0)['plays'];

    Table::apply($state, 0, ['type' => 'move', 'steps' => $plays[0]], new SequenceDice([3, 1]));

    expect($state)->toBe($before);
});
