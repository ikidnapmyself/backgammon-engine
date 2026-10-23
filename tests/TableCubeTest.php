<?php

use BackgammonNetwork\Engine\Dice\SequenceDice;
use BackgammonNetwork\Engine\Table;

function afterOneMove(array $dice = [6, 5], array $config = []): array
{
    $state = openedTable($dice, $config);
    $seat = $state['toAct'];

    return Table::apply($state, $seat, ['type' => 'move', 'steps' => Table::legal($state, $seat)['plays'][0]], new SequenceDice([]));
}

it('offers a double and hands the decision to the other seat', function () {
    $state = afterOneMove();
    $doubler = $state['toAct'];

    $offered = Table::apply($state, $doubler, ['type' => 'double'], new SequenceDice([]));

    expect($offered['phase'])->toBe('cube_offered')
        ->and($offered['toAct'])->toBe(1 - $doubler)
        ->and($offered['cube']['value'])->toBe(1)
        ->and(Table::legal($offered, 1 - $doubler))->toMatchArray(['canTake' => true, 'canDrop' => true, 'canDouble' => false, 'canResign' => false])
        ->and(end($offered['log']))->toMatchArray(['kind' => 'double', 'value' => 2]);
});

it('doubles the cube to the taker and rolls for the doubler', function () {
    $state = afterOneMove();
    $doubler = $state['toAct'];
    $offered = Table::apply($state, $doubler, ['type' => 'double'], new SequenceDice([]));

    $taken = Table::apply($offered, 1 - $doubler, ['type' => 'take'], new SequenceDice([4, 2]));

    expect($taken['cube'])->toBe(['value' => 2, 'owner' => 1 - $doubler])
        ->and($taken['toAct'])->toBe($doubler)
        ->and($taken['phase'])->toBe('move')
        ->and($taken['dice'])->toBe([4, 2]);
});

it('ends the game when the double is dropped', function () {
    $state = afterOneMove();
    $doubler = $state['toAct'];
    $offered = Table::apply($state, $doubler, ['type' => 'double'], new SequenceDice([]));

    $dropped = Table::apply($offered, 1 - $doubler, ['type' => 'drop'], new SequenceDice([]));

    expect($dropped['phase'])->toBe('complete')
        ->and($dropped['toAct'])->toBeNull()
        ->and($dropped['result'])->toBe(['winner' => $doubler, 'kind' => 'single', 'how' => 'dropped', 'points' => 1]);
});

it('lets only the cube owner redouble', function () {
    $state = afterOneMove();
    $doubler = $state['toAct'];
    $offered = Table::apply($state, $doubler, ['type' => 'double'], new SequenceDice([]));
    $taken = Table::apply($offered, 1 - $doubler, ['type' => 'take'], new SequenceDice([4, 2]));
    $moved = Table::apply($taken, $doubler, ['type' => 'move', 'steps' => Table::legal($taken, $doubler)['plays'][0]], new SequenceDice([]));

    expect($moved['toAct'])->toBe(1 - $doubler)
        ->and(Table::legal($moved, 1 - $doubler)['canDouble'])->toBeTrue();

    $back = Table::apply($moved, 1 - $doubler, ['type' => 'roll'], new SequenceDice([3, 1]));
    $next = Table::apply($back, 1 - $doubler, ['type' => 'move', 'steps' => Table::legal($back, 1 - $doubler)['plays'][0]], new SequenceDice([2, 1]));

    expect($next['toAct'])->toBe($doubler)->and($next['phase'])->toBe('move');

    Table::apply([...$next, 'phase' => 'decide'], $doubler, ['type' => 'double'], new SequenceDice([]));
})->throws(DomainException::class, 'You can\'t double now');

it('has no doubling when the cube is off', function () {
    $state = openedTable([6, 5], ['cube' => false]);

    expect(Table::legal($state, $state['toAct'])['canDouble'])->toBeFalse();
});

it('rejects take and drop without a double', function (string $type) {
    $state = openedTable([6, 5]);

    Table::apply($state, $state['toAct'], ['type' => $type], new SequenceDice([]));
})->with(['take', 'drop'])->throws(DomainException::class);
