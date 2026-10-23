<?php

namespace BackgammonNetwork\Engine;

use BackgammonNetwork\Engine\Dice\Dice;
use DomainException;

/**
 * A single game at a table without stakes. State is a plain array that round trips through JSON.
 * Every function returns a new state.
 *
 * @phpstan-import-type BoardArray from Board
 * @phpstan-import-type Step from Board
 *
 * @phpstan-type TableConfig array{cube: bool, length: int, crawford: bool}
 * @phpstan-type Cube array{value: int, owner: int|null}
 * @phpstan-type ResignOffer array{seat: int, kind: string, phase: string}
 * @phpstan-type GameResult array{winner: int, kind: string, how: string, points: int}
 * @phpstan-type LogDetail array{kind: string, seat?: int, dice?: list<int>, steps?: list<Step>, value?: int}
 * @phpstan-type LogEntry array{kind: string, text: string, seat?: int, dice?: list<int>, steps?: list<Step>, value?: int}
 * @phpstan-type TableState array{
 *     config: TableConfig, names: list<string>, crawfordGame: bool, board: BoardArray,
 *     phase: string, toAct: int|null, dice: list<int>, cube: Cube,
 *     gameNumber: int, passes: int, resign: ResignOffer|null, declined: int|null,
 *     result: GameResult|null, log: list<LogEntry>
 * }
 */
final class Table
{
    private const array MULTIPLIERS = ['single' => 1, 'gammon' => 2, 'backgammon' => 3];

    /**
     * @param  TableConfig  $config
     * @param  list<string>  $names
     * @return TableState
     */
    public static function create(array $config, array $names): array
    {
        return [
            'config' => $config,
            'names' => $names,
            'crawfordGame' => false,
            'board' => Board::start()->toArray(),
            'phase' => 'idle',
            'toAct' => null,
            'dice' => [],
            'cube' => ['value' => 1, 'owner' => null],
            'gameNumber' => 0,
            'passes' => 0,
            'resign' => null,
            'declined' => null,
            'result' => null,
            'log' => [],
        ];
    }

    /**
     * @param  TableState  $state
     */
    public static function live(array $state): bool
    {
        return $state['phase'] !== 'idle' && $state['phase'] !== 'complete';
    }

    /**
     * @param  TableState  $state
     * @return TableState
     */
    public static function start(array $state, Dice $dice): array
    {
        if (self::live($state)) {
            throw new DomainException('A game is already in progress');
        }

        $state = [
            ...$state,
            'board' => Board::start()->toArray(),
            'phase' => 'move',
            'dice' => [],
            'cube' => ['value' => 1, 'owner' => null],
            'gameNumber' => $state['gameNumber'] + 1,
            'passes' => 0,
            'resign' => null,
            'declined' => null,
            'result' => null,
            'log' => [],
        ];

        do {
            $a = $dice->die();
            $b = $dice->die();
            $state = self::log($state, "{$state['names'][0]} rolls {$a}, {$state['names'][1]} rolls {$b}", ['kind' => 'opening', 'dice' => [$a, $b]]);
        } while ($a === $b);

        $state['toAct'] = $a > $b ? 0 : 1;
        $state['dice'] = [$a, $b];

        return self::afterRoll($state, $dice);
    }

    /**
     * @param  TableState  $state
     */
    public static function canDouble(array $state, int $seat): bool
    {
        return $state['config']['cube']
            && ! $state['crawfordGame']
            && $state['toAct'] === $seat
            && $state['phase'] === 'decide'
            && ($state['cube']['owner'] === null || $state['cube']['owner'] === $seat);
    }

    /**
     * @param  TableState  $state
     * @return TableState
     */
    public static function forfeit(array $state, int $seat): array
    {
        if (! self::live($state)) {
            throw new DomainException('No game in progress');
        }

        $state = self::log($state, "{$state['names'][$seat]} forfeits", ['kind' => 'forfeit', 'seat' => $seat]);

        return self::finish($state, Board::other($seat), 'forfeit');
    }

    /**
     * @param  TableState  $state
     * @return array{canRoll: bool, canDouble: bool, canTake: bool, canDrop: bool, canResign: bool, canAccept: bool, canDecline: bool, plays: list<list<Step>>}
     */
    public static function legal(array $state, int $seat): array
    {
        $mine = $state['toAct'] === $seat && self::live($state);

        return [
            'canRoll' => $mine && $state['phase'] === 'decide',
            'canDouble' => self::live($state) && self::canDouble($state, $seat),
            'canTake' => $mine && $state['phase'] === 'cube_offered',
            'canDrop' => $mine && $state['phase'] === 'cube_offered',
            'canResign' => $mine && in_array($state['phase'], ['decide', 'move'], true) && $state['declined'] !== $seat,
            'canAccept' => $mine && $state['phase'] === 'resign_offered',
            'canDecline' => $mine && $state['phase'] === 'resign_offered',
            'plays' => $mine && $state['phase'] === 'move'
                ? array_map(fn (Play $play): array => $play->steps, self::plays($state, $seat))
                : [],
        ];
    }

    /**
     * @param  TableState  $state
     * @param  array<string, mixed>  $action
     * @return TableState
     */
    public static function apply(array $state, int $seat, array $action, Dice $dice): array
    {
        if (! self::live($state)) {
            throw new DomainException('No game in progress');
        }

        if ($state['toAct'] !== $seat) {
            throw new DomainException('It\'s not your turn');
        }

        $state['passes'] = 0;
        $name = $state['names'][$seat];

        switch ($action['type'] ?? null) {
            case 'roll':
                if ($state['phase'] !== 'decide') {
                    throw new DomainException('You can\'t roll now');
                }

                return self::rollFor($state, $dice);
            case 'double':
                if (! self::canDouble($state, $seat)) {
                    throw new DomainException('You can\'t double now');
                }

                $value = $state['cube']['value'] * 2;
                $state['phase'] = 'cube_offered';
                $state['toAct'] = Board::other($seat);

                return self::log($state, "{$name} doubles to {$value}", ['kind' => 'double', 'seat' => $seat, 'value' => $value]);
            case 'take':
                if ($state['phase'] !== 'cube_offered') {
                    throw new DomainException('There\'s no double to take');
                }

                $state['cube'] = ['value' => $state['cube']['value'] * 2, 'owner' => $seat];
                $state = self::log($state, "{$name} takes", ['kind' => 'take', 'seat' => $seat, 'value' => $state['cube']['value']]);
                $state['toAct'] = Board::other($seat);

                return self::rollFor($state, $dice);
            case 'drop':
                if ($state['phase'] !== 'cube_offered') {
                    throw new DomainException('There\'s no double to drop');
                }

                $state = self::log($state, "{$name} drops", ['kind' => 'drop', 'seat' => $seat]);

                return self::finish($state, Board::other($seat), 'dropped');
            case 'resign':
                $kind = $action['kind'] ?? null;

                if (! in_array($state['phase'], ['decide', 'move'], true)) {
                    throw new DomainException('You can\'t resign now');
                }

                if ($state['declined'] === $seat) {
                    throw new DomainException('You can offer to resign once per turn');
                }

                if (! is_string($kind) || ! isset(self::MULTIPLIERS[$kind])) {
                    throw new DomainException('Unknown resign kind');
                }

                $state['resign'] = ['seat' => $seat, 'kind' => $kind, 'phase' => $state['phase']];
                $state['phase'] = 'resign_offered';
                $state['toAct'] = Board::other($seat);

                return self::log($state, "{$name} offers to resign ({$kind})", ['kind' => 'resign', 'seat' => $seat, 'value' => self::MULTIPLIERS[$kind]]);
            case 'accept':
                if ($state['resign'] === null) {
                    throw new DomainException('There\'s no resign to accept');
                }

                $kind = $state['resign']['kind'];
                $state = self::log($state, "{$name} accepts", ['kind' => 'accept', 'seat' => $seat]);

                return self::finish($state, $seat, 'resigned', $kind);
            case 'decline':
                if ($state['resign'] === null) {
                    throw new DomainException('There\'s no resign to decline');
                }

                $state = self::log($state, "{$name} declines", ['kind' => 'decline', 'seat' => $seat]);
                $state['phase'] = $state['resign']['phase'];
                $state['toAct'] = $state['resign']['seat'];
                $state['declined'] = $state['resign']['seat'];
                $state['resign'] = null;

                return $state;
            case 'move':
                if ($state['phase'] !== 'move') {
                    throw new DomainException('You need to roll first');
                }

                $steps = self::steps($action['steps'] ?? null);
                $board = self::playMove($state, $seat, $steps);
                $state['board'] = $board->toArray();
                $state = self::log($state, "{$name} plays ".Moves::describe($steps, $seat), [
                    'kind' => 'move',
                    'seat' => $seat,
                    'dice' => Moves::expandDice($state['dice']),
                    'steps' => $steps,
                ]);

                if ($board->off[$seat] === 15) {
                    return self::finish($state, $seat, 'played');
                }

                return self::beginTurn($state, Board::other($seat), $dice);
            default:
                throw new DomainException('Unknown action');
        }
    }

    /**
     * @return list<Step>
     */
    private static function steps(mixed $raw): array
    {
        $steps = [];

        foreach (is_array($raw) ? $raw : [] as $step) {
            $from = is_array($step) ? ($step['from'] ?? null) : null;
            $to = is_array($step) ? ($step['to'] ?? null) : null;

            if (! self::isPoint($from, 'bar') || ! self::isPoint($to, 'off')) {
                throw new DomainException('That play isn\'t allowed');
            }

            $steps[] = ['from' => $from, 'to' => $to];
        }

        return $steps;
    }

    private static function isPoint(mixed $value, string $edge): bool
    {
        return $value === $edge || (is_int($value) && $value >= 0 && $value <= 23);
    }

    /**
     * @param  TableState  $state
     * @param  list<Step>  $steps
     */
    private static function playMove(array $state, int $seat, array $steps): Board
    {
        $board = Board::fromArray($state['board']);

        $key = Moves::applySteps($board, $seat, $steps)->key();

        foreach (self::plays($state, $seat) as $play) {
            if ($play->board->key() === $key) {
                return $play->board;
            }
        }

        throw new DomainException('That play isn\'t allowed');
    }

    /**
     * @param  TableState  $state
     * @return list<Play>
     */
    private static function plays(array $state, int $seat): array
    {
        return Moves::legalPlays(Board::fromArray($state['board']), $seat, Moves::expandDice($state['dice']));
    }

    /**
     * @param  TableState  $state
     * @return TableState
     */
    private static function finish(array $state, int $winner, string $how, ?string $offered = null): array
    {
        $loser = Board::other($winner);
        $kind = $offered ?? ($how === 'dropped' ? 'single' : Board::fromArray($state['board'])->positionKind($loser));
        $points = $state['cube']['value'] * self::MULTIPLIERS[$kind];

        $state['phase'] = 'complete';
        $state['toAct'] = null;
        $state['dice'] = [];
        $state['resign'] = null;
        $state['result'] = ['winner' => $winner, 'kind' => $kind, 'how' => $how, 'points' => $points];

        return self::log($state, "{$state['names'][$winner]} wins {$points} point".($points === 1 ? '' : 's').($kind === 'single' ? '' : " ({$kind})"), [
            'kind' => 'win',
            'seat' => $winner,
            'value' => $points,
        ]);
    }

    /**
     * @param  TableState  $state
     * @return TableState
     */
    private static function beginTurn(array $state, int $seat, Dice $dice): array
    {
        $state['toAct'] = $seat;
        $state['dice'] = [];
        $state['phase'] = 'decide';
        $state['declined'] = null;

        if (self::canDouble($state, $seat)) {
            return $state;
        }

        return self::rollFor($state, $dice);
    }

    /**
     * @param  TableState  $state
     * @return TableState
     */
    private static function rollFor(array $state, Dice $dice): array
    {
        $state['phase'] = 'move';
        $state['dice'] = [$dice->die(), $dice->die()];
        $state = self::log($state, "{$state['names'][self::actor($state)]} rolls {$state['dice'][0]}-{$state['dice'][1]}", [
            'kind' => 'roll',
            'seat' => $state['toAct'],
            'dice' => Moves::expandDice($state['dice']),
        ]);

        return self::afterRoll($state, $dice);
    }

    /**
     * @param  TableState  $state
     * @return TableState
     */
    private static function afterRoll(array $state, Dice $dice): array
    {
        $seat = self::actor($state);

        if (self::canMove($state, $seat) || $state['passes'] >= 2) {
            return $state;
        }

        $state['passes']++;
        $state = self::log($state, "{$state['names'][$seat]} can't move", ['kind' => 'pass', 'seat' => $seat]);

        return self::beginTurn($state, Board::other($seat), $dice);
    }

    /**
     * @param  TableState  $state
     */
    private static function canMove(array $state, int $seat): bool
    {
        return self::plays($state, $seat)[0]->steps !== [];
    }

    /**
     * @param  TableState  $state
     * @param  LogDetail  $entry
     * @return TableState
     */
    private static function log(array $state, string $text, array $entry): array
    {
        $state['log'][] = ['text' => $text, ...$entry];

        return $state;
    }

    /**
     * @param  TableState  $state
     */
    private static function actor(array $state): int
    {
        return $state['toAct'] ?? throw new DomainException('No game in progress');
    }
}
