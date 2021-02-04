<?php

/*
 * davshell - Simple Shell to interact with (Card)DAV servers
 *
 * Copyright (C) 2020 Michael Stilkerich <ms@mike2k.de>
 *
 * This file is part of davshell.
 *
 * davshell is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * davshell is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with davshell.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace MStilkerich\CardDavClient\Shell;

/**
 * Abstract class for shell commands.
 */
abstract class Command
{
    /** @var array<string, class-string<Command>> Maps command name to their implementing class. */
    protected const COMMAND_MAP = [
        'help' => Command\Help::class,
        'cd' => Command\ChangeCollection::class,

        'ls' => Command\ListCollection::class,
        'cat' => Command\DumpResource::class,
        'time' => Command\TimeCommand::class,


        'acc:discover' => Command\Account\Discover::class,
        'acc:list' => Command\Account\ListAccounts::class,
        'acc:add' => Command\Account\Add::class,

        'ab:list' => Command\Addressbook\ListAddrBooks::class,
        'ab:info' => Command\Addressbook\Info::class,
        'ab:putcard' => Command\Addressbook\CreateCard::class,
        'ab:select' => Command\Addressbook\Select::class,
        'ab:query' => Command\Addressbook\Query::class,
        'ab:sync' => Command\Addressbook\Synchronize::class,
        'ab:clone' => Command\Addressbook\CloneAddrBook::class,
        'ab:sweep' => Command\Addressbook\Sweep::class,
    ];

    /** @var string Short summary of what the command does */
    protected $synopsis;

    /** @var string One-line usage description of command and parameters */
    protected $usage;

    /** @var string More extensive description on functionality and parameters */
    protected $help;

    /** @var int */
    protected $minArgs;

    /** @var int */
    protected $maxArgs;

    protected function usage(): string
    {
        return $this->usage;
    }

    protected function synopsis(): string
    {
        return $this->synopsis;
    }

    protected function help(): string
    {
        return $this->help;
    }

    /**
     * @param list<string> $args Arguments for the function as command line tokens.
     * @return bool Indicates if the command was successful
     */
    abstract public function run(array $args): bool;

    /**
     * @param non-empty-list<string> $tokens
     */
    public static function execCommand(array $tokens): bool
    {
        $ret = false;
        $command = array_shift($tokens);

        if (isset(self::COMMAND_MAP[$command])) {
            $cmdClass = self::COMMAND_MAP[$command];
            $cmd = new $cmdClass();
            $nargs = count($tokens);
            if ($cmd->minArgs <= $nargs && ($cmd->maxArgs < 0 /* no limit */ || $nargs <= $cmd->maxArgs)) {
                if ($cmd->maxArgs > 0 && $nargs < $cmd->maxArgs) {
                    $tokens = array_merge($tokens, array_fill(0, $cmd->maxArgs - $nargs, ""));
                }
                try {
                    $ret = $cmd->run($tokens);
                } catch (\Exception $e) {
                    Shell::$logger->error("Command raised Exception: " . $e);
                }
            } else {
                Shell::$logger->error("Wrong number of arguments to $command.");
                Shell::$logger->info($cmd->usage());
            }
        } else {
            Shell::$logger->error("Unknown command $command. Type \"help\" for a list of available commands");
        }

        return $ret;
    }
}

// vim: ts=4:sw=4:expandtab:fenc=utf8:ff=unix:tw=120
