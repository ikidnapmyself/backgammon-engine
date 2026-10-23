<?php

namespace BackgammonNetwork\Engine\Dice;

use LogicException;

final class SequenceDice implements Dice
{
    private int $next = 0;

    /**
     * @param  list<int>  $values
     */
    public function __construct(private readonly array $values) {}

    public function die(): int
    {
        return $this->values[$this->next++] ?? throw new LogicException('The dice sequence ran out.');
    }
}
