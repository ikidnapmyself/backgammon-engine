<?php

use BackgammonNetwork\Engine\Dice\SequenceDice;
use BackgammonNetwork\Engine\Table;

function crawfordMove(bool $crawfordGame): array
{
    $state = Table::create(tableConfig(), ['Ann', 'Bob']);
    $state['crawfordGame'] = $crawfordGame;
    $state = Table::start($state, new SequenceDice([6, 5]));
    $seat = $state['toAct'];

    return Table::apply($state, $seat, ['type' => 'move', 'steps' => Table::legal($state, $seat)['plays'][0]], new SequenceDice([4, 2]));
}

it('rolls straight away for the next seat in a Crawford game', function () {
    $state = crawfordMove(true);

    expect($state['phase'])->toBe('move')
        ->and($state['dice'])->toBe([4, 2])
        ->and(Table::legal($state, $state['toAct'])['canDouble'])->toBeFalse();
});

it('waits for a roll or double in a normal game', function () {
    $state = crawfordMove(false);

    expect($state['phase'])->toBe('decide')
        ->and(Table::legal($state, $state['toAct'])['canDouble'])->toBeTrue();
});

it('rejects a double in a Crawford game', function () {
    $state = crawfordMove(true);

    Table::apply($state, $state['toAct'], ['type' => 'double'], new SequenceDice([]));
})->throws(DomainException::class, 'You can\'t double now');

it('keeps the Crawford flag when the next game starts', function () {
    $state = Table::create(tableConfig(), ['Ann', 'Bob']);
    $state['crawfordGame'] = true;
    $state = Table::start($state, new SequenceDice([6, 5]));
    $state = Table::apply($state, $state['toAct'], ['type' => 'resign', 'kind' => 'single'], new SequenceDice([]));
    $state = Table::apply($state, $state['toAct'], ['type' => 'accept'], new SequenceDice([]));

    $next = Table::start($state, new SequenceDice([6, 5]));

    expect($next['crawfordGame'])->toBeTrue()
        ->and($next['gameNumber'])->toBe(2);
});
