<?php

use BackgammonNetwork\Engine\Dice\RandomDice;
use BackgammonNetwork\Engine\Table;

require __DIR__.'/../vendor/autoload.php';

$bot = fn (array $state, int $seat, array $legal): array => $state['phase'] === 'move'
    ? ['type' => 'move', 'steps' => $legal['plays'][0] ?? []]
    : ['type' => 'roll'];

$dice = new RandomDice;
$state = Table::start(Table::create(['cube' => false, 'length' => 1, 'crawford' => false], ['Ann', 'Bob']), $dice);

while (Table::live($state)) {
    $seat = $state['toAct'];
    $state = Table::apply($state, $seat, $bot($state, $seat, Table::legal($state, $seat)), $dice);
}

echo "{$state['names'][$state['result']['winner']]} wins by {$state['result']['kind']}\n";
