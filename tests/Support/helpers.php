<?php

use BackgammonNetwork\Engine\Board;
use BackgammonNetwork\Engine\Dice\Dice;
use BackgammonNetwork\Engine\Dice\SequenceDice;
use BackgammonNetwork\Engine\Table;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * @param  array<string, mixed>  $over
 * @return array{cube: bool, length: int, crawford: bool}
 */
function tableConfig(array $over = []): array
{
    return [...['cube' => true, 'length' => 5, 'crawford' => true], ...$over];
}

/**
 * @param  list<int>  $dice
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function openedTable(array $dice, array $config = []): array
{
    return Table::start(Table::create(tableConfig($config), ['Ann', 'Bob']), new SequenceDice($dice));
}

/**
 * @param  array<string, mixed>  $state
 * @return array<string, mixed>
 */
function withBoard(array $state, Board $board): array
{
    return [...$state, 'board' => $board->toArray()];
}

/**
 * @return list<array<string, mixed>>
 */
function legalPlayCases(): array
{
    return json_decode(file_get_contents(__DIR__.'/../fixtures/legal-plays.json'), true, flags: JSON_THROW_ON_ERROR);
}

/**
 * Builds a board from checker counts per pip.
 *
 * @param  array<int, array<int|string, int>>  $layout
 */
function boardOf(array $layout): Board
{
    $points = array_fill(0, 24, 0);
    $bar = [0, 0];
    $off = [0, 0];

    foreach ([0, 1] as $seat) {
        foreach ($layout[$seat] ?? [] as $key => $count) {
            if ($key === 'bar') {
                $bar[$seat] = $count;
            } elseif ($key === 'off') {
                $off[$seat] = $count;
            } else {
                $points[Board::idx($seat, $key)] += $seat === 0 ? $count : -$count;
            }
        }
    }

    return new Board($points, $bar, $off);
}

function seededDice(int $seed): Dice
{
    return new class($seed) implements Dice
    {
        private readonly Randomizer $random;

        public function __construct(int $seed)
        {
            $this->random = new Randomizer(new Mt19937($seed));
        }

        public function die(): int
        {
            return $this->random->getInt(1, 6);
        }
    };
}
