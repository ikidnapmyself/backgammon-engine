<?php

use BackgammonNetwork\Engine\Dice\RandomDice;
use BackgammonNetwork\Engine\Dice\SequenceDice;

it('rolls fair six sided dice', function () {
    $dice = new RandomDice;

    foreach (range(1, 200) as $_) {
        expect($dice->die())->toBeBetween(1, 6);
    }
});

it('replays a fixed sequence and fails loudly when it runs out', function () {
    $dice = new SequenceDice([3, 1]);

    expect($dice->die())->toBe(3)->and($dice->die())->toBe(1);
    $dice->die();
})->throws(LogicException::class);
