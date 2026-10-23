<?php

namespace BackgammonNetwork\Engine;

/**
 * Legal move generation. Traversal order matters: callers that keep the first play found for a position
 * rely on a stable order, so the order is part of the contract.
 *
 * @phpstan-import-type Step from Board
 *
 * @phpstan-type Sequence array{steps: list<Step>, board: Board, used: list<int>}
 */
final class Moves
{
    /**
     * @param  list<int>  $dice
     * @return list<int>
     */
    public static function expandDice(array $dice): array
    {
        return $dice[0] === $dice[1] ? [$dice[0], $dice[0], $dice[0], $dice[0]] : [$dice[0], $dice[1]];
    }

    /**
     * @param  list<int>  $dice
     * @return list<Play>
     */
    public static function legalPlays(Board $board, int $seat, array $dice): array
    {
        $seen = [];

        foreach (self::allowed($board, $seat, $dice) as $sequence) {
            $seen[$sequence['board']->key()] ??= new Play($sequence['steps'], $sequence['board']);
        }

        return array_values($seen);
    }

    /**
     * @param  list<Step>  $steps
     */
    public static function applySteps(Board $board, int $seat, array $steps): Board
    {
        foreach ($steps as $step) {
            $board = self::shift($board, $seat, Board::stepFrom($seat, $step), Board::stepTo($seat, $step));
        }

        return $board;
    }

    /**
     * @param  list<Step>  $steps
     */
    public static function describe(array $steps, int $seat = 0): string
    {
        if ($steps === []) {
            return 'nothing';
        }

        $format = fn (int|string $at): string => is_int($at) ? (string) Board::pipOf($seat, $at) : $at;

        return implode(' ', array_map(fn (array $step): string => $format($step['from']).'/'.$format($step['to']), $steps));
    }

    private static function canStep(Board $board, int $seat, int $from, int $die): bool
    {
        if (! $board->own($seat, $from)) {
            return false;
        }

        if ($board->bar[$seat] && $from !== Board::BAR) {
            return false;
        }

        $to = $from - $die;

        if ($to >= 1) {
            return $board->opp($seat, $to) < 2;
        }

        if (! $board->allHome($seat)) {
            return false;
        }

        return $to === 0 || self::highestOwn($board, $seat) === $from;
    }

    private static function highestOwn(Board $board, int $seat): int
    {
        for ($pip = 6; $pip >= 1; $pip--) {
            if ($board->own($seat, $pip)) {
                return $pip;
            }
        }

        return 0;
    }

    private static function shift(Board $board, int $seat, int $from, int $to): Board
    {
        $points = $board->points;
        $bar = $board->bar;
        $off = $board->off;
        $sign = Board::sign($seat);

        if ($from === Board::BAR) {
            $bar[$seat]--;
        } else {
            $points[Board::idx($seat, $from)] -= $sign;
        }

        if ($to === 0) {
            $off[$seat]++;
        } else {
            $index = Board::idx($seat, $to);

            if (max(0, -$sign * $points[$index]) === 1) {
                $points[$index] = 0;
                $bar[Board::other($seat)]++;
            }

            $points[$index] += $sign;
        }

        return new Board(array_values($points), [$bar[0], $bar[1]], [$off[0], $off[1]]);
    }

    /**
     * @return array{board: Board, step: Step}
     */
    private static function doStep(Board $board, int $seat, int $from, int $die): array
    {
        $to = max(0, $from - $die);

        return [
            'board' => self::shift($board, $seat, $from, $to),
            'step' => [
                'from' => $from === Board::BAR ? 'bar' : Board::idx($seat, $from),
                'to' => $to === 0 ? 'off' : Board::idx($seat, $to),
            ],
        ];
    }

    /**
     * @param  list<int>  $dice
     * @return list<Sequence>
     */
    private static function sequences(Board $board, int $seat, array $dice): array
    {
        return self::walk($board, $seat, count($dice) > 2, $dice, [], [], Board::BAR);
    }

    /**
     * @param  list<int>  $left
     * @param  list<Step>  $steps
     * @param  list<int>  $used
     * @return list<Sequence>
     */
    private static function walk(Board $board, int $seat, bool $doubles, array $left, array $steps, array $used, int $top): array
    {
        $sequences = [];
        $moved = false;

        foreach (array_values(array_unique($left)) as $die) {
            $rest = $left;
            array_splice($rest, (int) array_search($die, $rest, true), 1);

            for ($from = $top; $from >= 1; $from--) {
                if (! self::canStep($board, $seat, $from, $die)) {
                    continue;
                }

                $moved = true;
                $next = self::doStep($board, $seat, $from, $die);
                array_push($sequences, ...self::walk($next['board'], $seat, $doubles, $rest, [...$steps, $next['step']], [...$used, $die], $doubles ? $from : Board::BAR));
            }
        }

        if (! $moved) {
            $sequences[] = ['steps' => $steps, 'board' => $board, 'used' => $used];
        }

        return $sequences;
    }

    /**
     * @param  list<int>  $dice
     * @return list<Sequence>
     */
    private static function allowed(Board $board, int $seat, array $dice): array
    {
        $all = self::sequences($board, $seat, $dice);
        $length = fn (array $sequence): int => $sequence['board']->off[$seat] === 15 ? count($dice) : count($sequence['used']);
        $most = array_reduce($all, fn (int $most, array $sequence): int => max($most, $length($sequence)), 0);
        $keep = array_values(array_filter($all, fn (array $sequence): bool => $length($sequence) === $most));

        if ($most === 1 && count($dice) === 2 && $dice[0] !== $dice[1]) {
            $high = max($dice[0], $dice[1]);
            $highFirst = array_values(array_filter($keep, fn (array $sequence): bool => $sequence['used'][0] === $high));

            if ($highFirst !== []) {
                $keep = $highFirst;
            }
        }

        return $keep;
    }
}
