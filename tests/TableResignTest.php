<?php

use BackgammonNetwork\Engine\Dice\SequenceDice;
use BackgammonNetwork\Engine\Table;

it('offers a resign and hands the decision to the other seat', function () {
    $state = openedTable([6, 5]);
    $seat = $state['toAct'];

    $offered = Table::apply($state, $seat, ['type' => 'resign', 'kind' => 'gammon'], new SequenceDice([]));

    expect($offered['phase'])->toBe('resign_offered')
        ->and($offered['toAct'])->toBe(1 - $seat)
        ->and($offered['resign'])->toBe(['seat' => $seat, 'kind' => 'gammon', 'phase' => 'move'])
        ->and(end($offered['log']))->toMatchArray(['kind' => 'resign', 'seat' => $seat, 'value' => 2])
        ->and(Table::legal($offered, 1 - $seat))->toMatchArray(['canAccept' => true, 'canDecline' => true, 'canResign' => false]);
});

it('ends the game at the offered kind when accepted', function () {
    $state = openedTable([6, 5]);
    $seat = $state['toAct'];
    $offered = Table::apply($state, $seat, ['type' => 'resign', 'kind' => 'backgammon'], new SequenceDice([]));

    $done = Table::apply($offered, 1 - $seat, ['type' => 'accept'], new SequenceDice([]));

    expect($done['result'])->toBe(['winner' => 1 - $seat, 'kind' => 'backgammon', 'how' => 'resigned', 'points' => 3])
        ->and($done['resign'])->toBeNull();
});

it('restores the phase and blocks a second offer in the same turn when declined', function () {
    $state = openedTable([6, 5]);
    $seat = $state['toAct'];
    $offered = Table::apply($state, $seat, ['type' => 'resign', 'kind' => 'single'], new SequenceDice([]));

    $declined = Table::apply($offered, 1 - $seat, ['type' => 'decline'], new SequenceDice([]));

    expect($declined['phase'])->toBe('move')
        ->and($declined['toAct'])->toBe($seat)
        ->and($declined['declined'])->toBe($seat)
        ->and($declined['resign'])->toBeNull()
        ->and(Table::legal($declined, $seat)['canResign'])->toBeFalse();

    Table::apply($declined, $seat, ['type' => 'resign', 'kind' => 'single'], new SequenceDice([]));
})->throws(DomainException::class, 'You can offer to resign once per turn');

it('rejects an unknown resign kind', function () {
    $state = openedTable([6, 5]);

    Table::apply($state, $state['toAct'], ['type' => 'resign', 'kind' => 'huge'], new SequenceDice([]));
})->throws(DomainException::class, 'Unknown resign kind');

it('rejects accept and decline without an offer', function (string $type) {
    $state = openedTable([6, 5]);

    Table::apply($state, $state['toAct'], ['type' => $type], new SequenceDice([]));
})->with(['accept', 'decline'])->throws(DomainException::class);

it('can resign before rolling', function () {
    $state = [...openedTable([6, 5]), 'phase' => 'decide'];

    $offered = Table::apply($state, $state['toAct'], ['type' => 'resign', 'kind' => 'single'], new SequenceDice([]));

    expect($offered['resign']['phase'])->toBe('decide');
});
