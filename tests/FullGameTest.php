<?php

use BackgammonNetwork\Engine\Dice\RandomDice;
use BackgammonNetwork\Engine\Table;

it('plays a full game to a result with a first-legal-play bot', function () {
    $dice = new RandomDice;
    $state = Table::start(Table::create(tableConfig(['cube' => false, 'length' => 1, 'crawford' => false]), ['Ann', 'Bob']), $dice);

    for ($turns = 0; Table::live($state); $turns++) {
        expect($turns)->toBeLessThan(5000);

        $seat = $state['toAct'];
        $legal = Table::legal($state, $seat);
        $action = $state['phase'] === 'move'
            ? ['type' => 'move', 'steps' => $legal['plays'][0] ?? []]
            : ['type' => 'roll'];

        $state = Table::apply($state, $seat, $action, $dice);
    }

    expect($state['phase'])->toBe('complete')
        ->and($state['result']['winner'])->toBeIn([0, 1]);
});
