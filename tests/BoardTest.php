<?php

use BackgammonNetwork\Engine\Board;

it('sets up 15 checkers per seat, mirrored, with 167 pips each', function () {
    $board = Board::start();

    expect(array_sum(array_filter($board->points, fn (int $n): bool => $n > 0)))->toBe(15)
        ->and(array_sum(array_filter($board->points, fn (int $n): bool => $n < 0)))->toBe(-15)
        ->and($board->pipCount(0))->toBe(167)
        ->and($board->pipCount(1))->toBe(167)
        ->and($board->own(0, 24))->toBe(2)
        ->and($board->opp(0, 24))->toBe(0)
        ->and($board->own(1, 24))->toBe(2);
});

it('round trips through an array', function () {
    $board = boardOf([0 => ['bar' => 1, 6 => 2, 'off' => 3], 1 => [8 => 1]]);

    expect(Board::fromArray($board->toArray())->key())->toBe($board->key());
});

it('maps pips and indexes back and forth for both seats', function () {
    foreach ([0, 1] as $seat) {
        foreach (range(1, 24) as $pip) {
            expect(Board::pipOf($seat, Board::idx($seat, $pip)))->toBe($pip);
        }
    }
});

it('counts the bar as 25 pips', function () {
    expect(boardOf([0 => ['bar' => 2, 3 => 1]])->pipCount(0))->toBe(53);
});

it('knows when every checker is home', function () {
    expect(boardOf([0 => [6 => 14, 1 => 1]])->allHome(0))->toBeTrue()
        ->and(boardOf([0 => [7 => 1, 6 => 14]])->allHome(0))->toBeFalse()
        ->and(boardOf([0 => ['bar' => 1, 6 => 14]])->allHome(0))->toBeFalse()
        ->and(boardOf([0 => [6 => 10, 'off' => 5]])->allHome(0))->toBeTrue();
});

it('classifies the loser position', function () {
    expect(boardOf([0 => [6 => 14], 1 => [5 => 14, 'off' => 1]])->positionKind(1))->toBe('single')
        ->and(boardOf([1 => [10 => 15]])->positionKind(1))->toBe('gammon')
        ->and(boardOf([1 => [24 => 1, 10 => 14]])->positionKind(1))->toBe('backgammon')
        ->and(boardOf([1 => [19 => 1, 10 => 14]])->positionKind(1))->toBe('backgammon')
        ->and(boardOf([1 => [18 => 1, 10 => 14]])->positionKind(1))->toBe('gammon')
        ->and(boardOf([1 => ['bar' => 1, 10 => 14]])->positionKind(1))->toBe('backgammon');
});
