<?php

use BackgammonNetwork\Engine\Board;
use BackgammonNetwork\Engine\Dice\Dice;
use BackgammonNetwork\Engine\Moves;
use BackgammonNetwork\Engine\Table;

function checkersOf(Board $board, int $seat): int
{
    $total = $board->bar[$seat] + $board->off[$seat];

    foreach ($board->points as $count) {
        if ($count * Board::sign($seat) > 0) {
            $total += abs($count);
        }
    }

    return $total;
}

/**
 * @return array<string, mixed>
 */
function randomAction(array $state, int $seat, Dice $dice): array
{
    $legal = Table::legal($state, $seat);
    $pick = $dice->die();

    return match ($state['phase']) {
        'move' => ['type' => 'move', 'steps' => $legal['plays'][$pick % count($legal['plays'])]],
        'cube_offered' => ['type' => $pick === 1 ? 'drop' : 'take'],
        'resign_offered' => ['type' => $pick <= 3 ? 'accept' : 'decline'],
        default => $legal['canDouble'] && $pick === 6 && $dice->die() === 6 ? ['type' => 'double'] : ['type' => 'roll'],
    };
}

it('keeps 15 checkers per seat and ends in a result across seeded games', function (int $seed) {
    $dice = seededDice($seed);
    $state = Table::start(Table::create(tableConfig(), ['Ann', 'Bob']), $dice);

    for ($turns = 0; Table::live($state); $turns++) {
        expect($turns)->toBeLessThan(3000);

        $state = Table::apply($state, $state['toAct'], randomAction($state, $state['toAct'], $dice), $dice);
        $board = Board::fromArray($state['board']);

        expect(checkersOf($board, 0))->toBe(15)
            ->and(checkersOf($board, 1))->toBe(15);
    }

    expect($state['result']['points'])->toBeGreaterThanOrEqual(1)
        ->and($state['log'])->not->toBeEmpty();
})->with(range(1, 25));

it('offers only plays that apply and survive a JSON round trip', function (int $seed) {
    $dice = seededDice($seed);
    $state = Table::start(Table::create(tableConfig(['cube' => false]), ['Ann', 'Bob']), $dice);

    while (Table::live($state)) {
        $state = json_decode(json_encode($state), true);
        $seat = $state['toAct'];

        if ($state['phase'] === 'move') {
            foreach (Table::legal($state, $seat)['plays'] as $steps) {
                $board = Moves::applySteps(Board::fromArray($state['board']), $seat, $steps);

                expect(checkersOf($board, $seat))->toBe(15);
            }
        }

        $state = Table::apply($state, $seat, randomAction($state, $seat, $dice), $dice);
    }

    expect($state['phase'])->toBe('complete');
})->with(range(100, 105));
