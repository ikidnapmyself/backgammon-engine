<?php

namespace BackgammonNetwork\Engine;

/**
 * A backgammon position. Points are indexed from seat 0's 1-point, positive for seat 0's checkers
 * and negative for seat 1's. Pips count from each seat's own side, with the bar at pip 25.
 *
 * @phpstan-type BoardArray array{points: list<int>, bar: array{int, int}, off: array{int, int}}
 * @phpstan-type Step array{from: int|'bar', to: int|'off'}
 */
final readonly class Board
{
    public const int BAR = 25;

    /**
     * @param  list<int>  $points
     * @param  array{int, int}  $bar
     * @param  array{int, int}  $off
     */
    public function __construct(public array $points, public array $bar, public array $off) {}

    public static function start(): self
    {
        $points = array_fill(0, 24, 0);

        foreach ([24 => 2, 13 => 5, 8 => 3, 6 => 5] as $pip => $count) {
            $points[self::idx(0, $pip)] += $count;
            $points[self::idx(1, $pip)] -= $count;
        }

        return new self(array_values($points), [0, 0], [0, 0]);
    }

    /**
     * @param  BoardArray  $board
     */
    public static function fromArray(array $board): self
    {
        return new self($board['points'], $board['bar'], $board['off']);
    }

    /**
     * @return BoardArray
     */
    public function toArray(): array
    {
        return ['points' => $this->points, 'bar' => $this->bar, 'off' => $this->off];
    }

    public static function sign(int $seat): int
    {
        return $seat === 0 ? 1 : -1;
    }

    public static function other(int $seat): int
    {
        return $seat === 0 ? 1 : 0;
    }

    public static function idx(int $seat, int $pip): int
    {
        return $seat === 0 ? $pip - 1 : 24 - $pip;
    }

    public static function pipOf(int $seat, int $index): int
    {
        return $seat === 0 ? $index + 1 : 24 - $index;
    }

    /**
     * @param  Step  $step
     */
    public static function stepFrom(int $seat, array $step): int
    {
        return $step['from'] === 'bar' ? self::BAR : self::pipOf($seat, $step['from']);
    }

    /**
     * @param  Step  $step
     */
    public static function stepTo(int $seat, array $step): int
    {
        return $step['to'] === 'off' ? 0 : self::pipOf($seat, $step['to']);
    }

    public function own(int $seat, int $pip): int
    {
        if ($pip === self::BAR) {
            return $this->bar[$seat];
        }

        return max(0, self::sign($seat) * $this->points[self::idx($seat, $pip)]);
    }

    public function opp(int $seat, int $pip): int
    {
        return max(0, -self::sign($seat) * $this->points[self::idx($seat, $pip)]);
    }

    public function pipCount(int $seat): int
    {
        $sum = $this->bar[$seat] * self::BAR;

        for ($pip = 1; $pip <= 24; $pip++) {
            $sum += $this->own($seat, $pip) * $pip;
        }

        return $sum;
    }

    public function allHome(int $seat): bool
    {
        if ($this->bar[$seat]) {
            return false;
        }

        for ($pip = 7; $pip <= 24; $pip++) {
            if ($this->own($seat, $pip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * How badly the loser loses if the game ended here.
     *
     * @return 'single'|'gammon'|'backgammon'
     */
    public function positionKind(int $loser): string
    {
        if ($this->off[$loser]) {
            return 'single';
        }

        if ($this->bar[$loser]) {
            return 'backgammon';
        }

        for ($pip = 19; $pip <= 24; $pip++) {
            if ($this->own($loser, $pip)) {
                return 'backgammon';
            }
        }

        return 'gammon';
    }

    public function key(): string
    {
        return implode(',', $this->points).'|'.implode(',', $this->bar).'|'.implode(',', $this->off);
    }
}
