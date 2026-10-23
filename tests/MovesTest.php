<?php

use BackgammonNetwork\Engine\Board;
use BackgammonNetwork\Engine\Moves;
use BackgammonNetwork\Engine\Play;

it('finds the legal plays recorded in the fixture', function (array $case) {
    $plays = Moves::legalPlays(Board::fromArray($case['board']), $case['seat'], $case['dice']);

    expect(array_map(fn (Play $p): array => ['steps' => $p->steps, 'board' => $p->board->toArray()], $plays))
        ->toBe($case['plays']);
})->with(fn (): array => array_map(fn (array $case): array => [$case], legalPlayCases()));

it('enters from the bar before anything else', function () {
    $board = boardOf([0 => ['bar' => 1, 13 => 14]]);

    foreach (Moves::legalPlays($board, 0, [6, 5]) as $play) {
        expect($play->steps[0]['from'])->toBe('bar');
    }
});

it('must use both dice when possible', function () {
    $board = boardOf([0 => ['bar' => 1, 10 => 1, 'off' => 13], 1 => [16 => 2, 7 => 2]]);

    expect(array_map(fn (Play $p): array => $p->steps, Moves::legalPlays($board, 0, [6, 1])))
        ->toBe([[['from' => 'bar', 'to' => 23], ['from' => 9, 'to' => 3]]]);
});

it('plays the larger die when only one can be played', function () {
    $board = boardOf([0 => [13 => 1, 'off' => 14], 1 => [23 => 2]]);

    expect(array_map(fn (Play $p): array => $p->steps, Moves::legalPlays($board, 0, [6, 5])))
        ->toBe([[['from' => 12, 'to' => 6]]]);
});

it('bears off with a higher die only from the highest point', function () {
    $plays = Moves::legalPlays(boardOf([0 => [4 => 1, 1 => 1, 'off' => 13]]), 0, [6, 1]);
    $blocked = Moves::legalPlays(boardOf([0 => [5 => 1, 2 => 1, 'off' => 13]]), 0, [6, 5]);

    expect(array_filter($plays, fn (Play $p): bool => $p->board->off[0] === 15))->not->toBeEmpty()
        ->and(array_map(fn (Play $p): array => $p->steps[0], $blocked))->each->toBe(['from' => 4, 'to' => 'off']);
});

it('expands a roll into the dice to play', function () {
    expect(Moves::expandDice([3, 1]))->toBe([3, 1])
        ->and(Moves::expandDice([4, 4]))->toBe([4, 4, 4, 4]);
});

it('plays four steps on doubles', function () {
    $plays = Moves::legalPlays(Board::start(), 0, [3, 3, 3, 3]);

    expect($plays)->not->toBeEmpty()
        ->and(array_unique(array_map(fn (Play $p): int => count($p->steps), $plays)))->toBe([4]);
});

it('plays fewer than four steps on doubles when blocked', function () {
    $board = boardOf([0 => [24 => 1, 'off' => 14], 1 => [7 => 2]]);
    $plays = Moves::legalPlays($board, 0, [6, 6, 6, 6]);

    expect($plays)->toHaveCount(1)
        ->and($plays[0]->steps)->toBe([]);
});

it('enters every checker from the bar before moving on', function () {
    $board = boardOf([0 => ['bar' => 2, 13 => 13]]);

    foreach (Moves::legalPlays($board, 0, [2, 2, 2, 2]) as $play) {
        expect(array_slice(array_column($play->steps, 'from'), 0, 2))->toBe(['bar', 'bar']);
    }
});

it('returns a single empty play when the bar entry is blocked', function () {
    $board = boardOf([0 => ['bar' => 1, 13 => 14], 1 => [1 => 2, 3 => 2]]);
    $plays = Moves::legalPlays($board, 0, [1, 3]);

    expect($plays)->toHaveCount(1)
        ->and($plays[0]->steps)->toBe([])
        ->and($plays[0]->board->key())->toBe($board->key());
});

it('hits a blot and sends it to the bar', function () {
    $board = boardOf([0 => [13 => 15], 1 => [15 => 1, 24 => 14]]);
    $plays = Moves::legalPlays($board, 0, [3, 1]);
    $hits = array_filter($plays, fn (Play $p): bool => $p->board->bar[1] === 1);

    expect($hits)->not->toBeEmpty();
});

it('never lands on a point held by two opposing checkers', function () {
    $board = boardOf([0 => [13 => 15], 1 => [15 => 2, 24 => 13]]);

    foreach (Moves::legalPlays($board, 0, [3, 4]) as $play) {
        expect($play->board->points[Board::idx(0, 10)])->toBe(-2);
    }
});

it('returns one play per resulting position', function () {
    $plays = Moves::legalPlays(Board::start(), 0, [2, 1]);
    $keys = array_map(fn (Play $p): string => $p->board->key(), $plays);

    expect($keys)->toBe(array_values(array_unique($keys)));
});

it('keeps the move order stable', function () {
    $a = Moves::legalPlays(Board::start(), 0, [6, 5]);
    $b = Moves::legalPlays(Board::start(), 0, [6, 5]);

    expect(array_map(fn (Play $p): array => $p->steps, $a))->toBe(array_map(fn (Play $p): array => $p->steps, $b));
});

it('bears off two checkers with two exact dice', function () {
    $plays = Moves::legalPlays(boardOf([0 => [2 => 1, 1 => 1, 'off' => 13]]), 0, [2, 1]);

    expect(array_filter($plays, fn (Play $p): bool => $p->board->off[0] === 15))->toHaveCount(1);
});

it('describes a play from the seat point of view', function () {
    $steps = [['from' => 'bar', 'to' => 21], ['from' => 5, 'to' => 'off']];

    expect(Moves::describe($steps, 0))->toBe('bar/22 6/off')
        ->and(Moves::describe($steps, 1))->toBe('bar/3 19/off')
        ->and(Moves::describe([]))->toBe('nothing');
});
