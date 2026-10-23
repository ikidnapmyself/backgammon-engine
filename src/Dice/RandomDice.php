<?php

namespace BackgammonNetwork\Engine\Dice;

final class RandomDice implements Dice
{
    public function die(): int
    {
        return random_int(1, 6);
    }
}
