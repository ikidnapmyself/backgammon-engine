<?php

namespace BackgammonNetwork\Engine;

/**
 * @phpstan-import-type Step from Board
 */
final readonly class Play
{
    /**
     * @param  list<Step>  $steps
     */
    public function __construct(public array $steps, public Board $board) {}
}
